<?php
/**
 * Chatbot Builder System - File Manager
 * Handles file uploads, validation, and storage
 */

namespace Chatbot;

class FileManager {
    private Database $db;
    private string $uploadPath;
    private int $maxFileSize;
    private array $allowedTypes;
    
    public function __construct(Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->uploadPath = Config::UPLOAD_PATH;
        $this->maxFileSize = Config::MAX_FILE_SIZE;
        $this->allowedTypes = array_merge(
            Config::ALLOWED_IMAGE_TYPES,
            Config::ALLOWED_DOC_TYPES
        );
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload file
     */
    public function upload(array $file, int $botId, ?int $conversationId = null, array $options = []): array {
        // Validate upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Invalid file upload');
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size of ' . $this->formatBytes($this->maxFileSize));
        }
        
        // Validate file type
        $mimeType = $this->getMimeType($file['tmp_name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!$this->isAllowedType($mimeType, $extension)) {
            throw new \Exception('File type not allowed. Allowed types: ' . implode(', ', $this->getAllowedExtensions()));
        }
        
        // Generate unique filename
        $uniqueName = $this->generateUniqueName($extension);
        $subDir = date('Y/m');
        $targetDir = $this->uploadPath . $subDir . '/';
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetPath = $targetDir . $uniqueName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Failed to save uploaded file');
        }
        
        // Generate thumbnail for images
        $thumbnailPath = null;
        if ($this->isImage($mimeType)) {
            $thumbnailPath = $this->generateThumbnail($targetPath, $subDir . '/' . $uniqueName);
        }
        
        // Save to database
        $fileData = [
            'bot_id' => $botId,
            'conversation_id' => $conversationId,
            'file_name' => $uniqueName,
            'original_name' => $file['name'],
            'file_path' => $subDir . '/' . $uniqueName,
            'file_type' => $this->getFileCategory($mimeType),
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'uploaded_by_type' => $options['uploaded_by_type'] ?? 'user',
            'uploaded_by_id' => $options['uploaded_by_id'] ?? null,
            'thumbnail_path' => $thumbnailPath,
            'is_public' => $options['is_public'] ?? false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $fileId = $this->db->insert('files', $fileData);
        
        return $this->db->fetchOne("SELECT * FROM files WHERE id = ?", [$fileId]);
    }
    
    /**
     * Upload from URL
     */
    public function uploadFromUrl(string $url, int $botId, ?int $conversationId = null): array {
        // Download file
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$content) {
            unlink($tempFile);
            throw new \Exception('Failed to download file from URL');
        }
        
        file_put_contents($tempFile, $content);
        
        // Get file info
        $pathInfo = pathinfo($url);
        $fileName = $pathInfo['basename'] ?? 'download';
        $extension = strtolower($pathInfo['extension'] ?? 'bin');
        
        // Create file array
        $file = [
            'tmp_name' => $tempFile,
            'name' => $fileName,
            'size' => filesize($tempFile),
            'type' => mime_content_type($tempFile) ?: 'application/octet-stream'
        ];
        
        try {
            $result = $this->upload($file, $botId, $conversationId);
            unlink($tempFile);
            return $result;
        } catch (\Exception $e) {
            unlink($tempFile);
            throw $e;
        }
    }
    
    /**
     * Get file by ID
     */
    public function getFile(int $fileId): ?array {
        return $this->db->fetchOne("SELECT * FROM files WHERE id = ?", [$fileId]);
    }
    
    /**
     * Get file URL
     */
    public function getFileUrl(array $file): string {
        return Config::getUploadUrl($file['file_path']);
    }
    
    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl(array $file): ?string {
        if ($file['thumbnail_path']) {
            return Config::getUploadUrl($file['thumbnail_path']);
        }
        return null;
    }
    
    /**
     * Delete file
     */
    public function deleteFile(int $fileId): bool {
        $file = $this->getFile($fileId);
        
        if (!$file) {
            return false;
        }
        
        // Delete physical files
        $filePath = $this->uploadPath . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        if ($file['thumbnail_path']) {
            $thumbPath = $this->uploadPath . $file['thumbnail_path'];
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        
        // Delete from database
        return $this->db->delete('files', 'id = ?', [$fileId]) > 0;
    }
    
    /**
     * Get files for conversation
     */
    public function getConversationFiles(int $conversationId): array {
        $sql = "SELECT * FROM files WHERE conversation_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$conversationId]);
    }
    
    /**
     * Increment download count
     */
    public function incrementDownload(int $fileId): void {
        $this->db->query(
            "UPDATE files SET download_count = download_count + 1 WHERE id = ?",
            [$fileId]
        );
    }
    
    /**
     * Validate file type
     */
    private function isAllowedType(string $mimeType, string $extension): bool {
        // Check MIME type
        if (in_array($mimeType, $this->allowedTypes)) {
            return true;
        }
        
        // Check extension as fallback
        $allowedExtensions = $this->getAllowedExtensions();
        return in_array($extension, $allowedExtensions);
    }
    
    /**
     * Get allowed file extensions
     */
    private function getAllowedExtensions(): array {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt'];
    }
    
    /**
     * Get MIME type
     */
    private function getMimeType(string $filePath): string {
        $mimeType = mime_content_type($filePath);
        return $mimeType ?: 'application/octet-stream';
    }
    
    /**
     * Check if file is image
     */
    private function isImage(string $mimeType): bool {
        return in_array($mimeType, Config::ALLOWED_IMAGE_TYPES);
    }
    
    /**
     * Get file category
     */
    private function getFileCategory(string $mimeType): string {
        if ($this->isImage($mimeType)) {
            return 'image';
        }
        if (in_array($mimeType, Config::ALLOWED_DOC_TYPES)) {
            return 'document';
        }
        return 'file';
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueName(string $extension): string {
        return uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
    
    /**
     * Generate thumbnail for image
     */
    private function generateThumbnail(string $sourcePath, string $relativePath): ?string {
        try {
            $thumbDir = $this->uploadPath . 'thumbs/' . dirname($relativePath) . '/';
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }
            
            $thumbName = 'thumb_' . basename($relativePath);
            $thumbPath = $thumbDir . $thumbName;
            
            // Get image info
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return null;
            }
            
            [$width, $height, $type] = $imageInfo;
            
            // Calculate thumbnail dimensions
            $maxSize = 200;
            $ratio = min($maxSize / $width, $maxSize / $height);
            $newWidth = (int) ($width * $ratio);
            $newHeight = (int) ($height * $ratio);
            
            // Create image from source
            $source = match($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
                IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
                IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
                IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
                default => null
            };
            
            if (!$source) {
                return null;
            }
            
            // Create thumbnail
            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
            }
            
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            // Save thumbnail
            match($type) {
                IMAGETYPE_JPEG => imagejpeg($thumb, $thumbPath, 85),
                IMAGETYPE_PNG => imagepng($thumb, $thumbPath, 8),
                IMAGETYPE_GIF => imagegif($thumb, $thumbPath),
                IMAGETYPE_WEBP => imagewebp($thumb, $thumbPath, 85),
                default => null
            };
            
            imagedestroy($source);
            imagedestroy($thumb);
            
            return 'thumbs/' . dirname($relativePath) . '/' . $thumbName;
            
        } catch (\Exception $e) {
            error_log('Thumbnail generation failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format bytes to human readable
     */
    public function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get file icon based on type
     */
    public function getFileIcon(string $fileType): string {
        return match($fileType) {
            'image' => 'image',
            'pdf' => 'file-text',
            'document' => 'file',
            default => 'paperclip'
        };
    }
    
    /**
     * Clean old files
     */
    public function cleanOldFiles(int $days = 30): int {
        $sql = "SELECT * FROM files WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $oldFiles = $this->db->fetchAll($sql, [$days]);
        
        $deleted = 0;
        foreach ($oldFiles as $file) {
            if ($this->deleteFile($file['id'])) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
