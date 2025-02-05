<?php

namespace Shahnewaz\RedprintNg\Services;

use Symfony\Component\Process\Process;
use NodejsPhpFallback\NodejsPhpFallback;

class FormatterService
{
    private string $packagePath;

    public function __construct()
    {
        $this->packagePath = dirname(__DIR__, 2); // Get package root path
    }

    public function formatVueContent(string $content): string
    {
        $lines = explode("\n", $content);
        $formattedLines = [];
        $indentLevel = 0;
        $inScript = false;
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Skip empty lines
            if (empty($trimmedLine)) {
                $formattedLines[] = '';
                continue;
            }
            
            // Handle script tag indentation separately
            if (str_contains($trimmedLine, '<script')) {
                $inScript = true;
            } elseif (str_contains($trimmedLine, '</script>')) {
                $inScript = false;
            }
            
            // Decrease indent for closing tags
            if (preg_match('/<\/\w+>$/', $trimmedLine) || str_starts_with($trimmedLine, '}')) {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            // Add line with proper indentation
            $indent = str_repeat('    ', $indentLevel);
            $formattedLines[] = $indent . $trimmedLine;
            
            // Increase indent for opening tags
            if (preg_match('/<[^\/][^>]*>$/', $trimmedLine) || str_ends_with($trimmedLine, '{')) {
                $indentLevel++;
            }
            
            // Special handling for self-closing tags
            if (preg_match('/\/>$/', $trimmedLine)) {
                $indentLevel = max(0, $indentLevel - 1);
            }
        }
        
        return implode("\n", $formattedLines);
    }

    public function formatPhpContent(string $content): string
    {
        // Use existing PHP-CS-Fixer for PHP files
        try {
            $config = new \PhpCsFixer\Config();
            $config->setRules([
                'indentation_type' => true,
                'no_extra_blank_lines' => true,
                'array_indentation' => true,
                'method_chaining_indentation' => true
            ]);
            
            return $content; // Temporary return until we implement the formatting
        } catch (\Exception $e) {
            return $content; // Return original content if formatting fails
        }
    }
} 