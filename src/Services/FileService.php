<?php

namespace Shahnewaz\RedprintNg\Services;

class FileService
{
    public function ensureDirectoryExists(string $path): void
    {
        if (!file_exists($path)) {
            if (!mkdir($path, 0777, true) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }
    }

    public function createFile(string $path, string $content): bool
    {
        try {
            $this->ensureDirectoryExists(dirname($path));
            
            // Debug: Log the content before saving
            error_log("Writing to file: " . $path);
            error_log("Content: " . $content);
            
            $result = file_put_contents($path, $content);
            
            if ($result === false) {
                error_log("Failed to write file: " . $path);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Error creating file: " . $e->getMessage());
            return false;
        }
    }
} 