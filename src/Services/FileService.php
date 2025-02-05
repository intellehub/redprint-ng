<?php

namespace Shahnewaz\RedprintNg\Services;

class FileService
{
    private FormatterService $formatterService;

    public function __construct()
    {
        $this->formatterService = new FormatterService();
    }

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
            // Format content based on file extension
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $formattedContent = match($extension) {
                'vue' => $this->formatterService->formatVueContent($content),
                'php' => $this->formatterService->formatPhpContent($content),
                default => $content
            };

            return $this->writeFile($path, $formattedContent);
        } catch (\Exception $e) {
            // Log error but don't throw to maintain backward compatibility
            error_log("Failed to create file: " . $e->getMessage());
            return false;
        }
    }

    public function copyFile(string $path, string $content): bool
    {
        try {
            return $this->writeFile($path, $content);
        } catch (\Exception $e) {
            error_log("Failed to copy file: " . $e->getMessage());
            return false;
        }
    }

    private function writeFile(string $path, string $content): bool
    {
        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        return file_put_contents($path, $content) !== false;
    }
} 