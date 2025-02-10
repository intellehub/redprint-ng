<?php

namespace Shahnewaz\RedprintNg\Services;

class FormatterService
{

    public function __construct()
    {
        $this->packagePath = dirname(__DIR__, 2); // Get package root path
    }

    public function formatVueContent(string $content): string
    {
        $lines = explode("\n", $content);
        $formattedLines = [];
        $indentLevel = 0;
        $skipNextEmptyLine = false; // To handle unnecessary blank lines after <script> and <template>

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Skip unnecessary blank lines after <script> and <template>
            if ($skipNextEmptyLine && empty($trimmedLine)) {
                $skipNextEmptyLine = false;
                continue;
            }

            // Skip empty lines
            if (empty($trimmedLine)) {
                $formattedLines[] = '';
                continue;
            }

            // Handle script tag indentation separately
            if (str_contains($trimmedLine, '<script')) {
                $inScript = true;
                $formattedLines[] = $trimmedLine; // No indentation for the <script> tag
                $skipNextEmptyLine = true; // Skip the next blank line after <script>
                continue;
            }

            if (str_contains($trimmedLine, '</script>')) {
                $inScript = false;
                $formattedLines[] = $trimmedLine; // No indentation for the </script> tag
                continue;
            }

            // Handle template tag indentation
            if (str_contains($trimmedLine, '<template')) {
                $inTemplate = true;
                $formattedLines[] = $trimmedLine; // No indentation for the <template> tag
                $skipNextEmptyLine = true; // Skip the next blank line after <template>
                continue;
            }

            if (str_contains($trimmedLine, '</template>')) {
                $inTemplate = false;
                $formattedLines[] = $trimmedLine; // No indentation for the </template> tag
                continue;
            }

            // Decrease indent for closing tags, braces, or brackets
            if (preg_match('/<\/\w+>$/', $trimmedLine) || str_starts_with($trimmedLine, '}') || str_starts_with($trimmedLine, ']')) {
                $indentLevel = max(0, $indentLevel - 1);
            }

            // Add line with proper indentation
            $indent = str_repeat('    ', $indentLevel); // 4 spaces per indent level
            $formattedLines[] = $indent . $trimmedLine;

            // Increase indent for opening tags, braces, or brackets
            if (preg_match('/<[^\/][^>]*>$/', $trimmedLine) || str_ends_with($trimmedLine, '{') || str_ends_with($trimmedLine, '[')) {
                $indentLevel++;
            }

            // Special handling for self-closing tags
            if (preg_match('/\/>$/', $trimmedLine)) {
                $indentLevel = max(0, $indentLevel - 1);
            }

            // Handle multi-line attributes for custom elements
            if (preg_match('/<\w+[^>]*$/', $trimmedLine) && !str_contains($trimmedLine, '>')) {
                // If the line starts a tag but doesn't close it, increase the indent for the next line
                $indentLevel++;
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