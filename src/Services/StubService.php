<?php

namespace Shahnewaz\RedprintNg\Services;

class StubService
{
    private string $stubsPath;

    public function __construct()
    {
        // Set stubs path relative to package root
        $this->stubsPath = dirname(__DIR__, 2) . '/src/stubs';
    }

    public function getStub(string $path): string
    {
        $stubPath = "{$this->stubsPath}/{$path}";
        
        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub file not found at: {$stubPath}");
        }

        return file_get_contents($stubPath);
    }

    public function processStub(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace($key, $value, $content);
        }
        
        // For debugging
        # DEBUG: echo "Processing replacements: " . print_r($replacements, true) . "\n";
        
        return $content;
    }
} 