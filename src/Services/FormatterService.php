<?php

namespace Shahnewaz\RedprintNg\Services;

use Navindex\HtmlFormatter\Formatter as HTMLFormatter;

class FormatterService
{
    private HTMLFormatter $htmlFormatter;
    private PrettierService $prettier;

    public function __construct()
    {
        $this->packagePath = dirname(__DIR__, 2);
        $this->htmlFormatter = new HTMLFormatter();
        
        // Configure HTML Formatter
        $config = $this->htmlFormatter->getConfig();
        $config->set('tab', '    '); // 4 spaces for indentation
        
        // Add Vue-specific inline elements
        $config->append('inline.tag', [
            'el-button', 
            'el-checkbox', 
            'router-link',
            'el-link'
        ]);
        
        // Add Vue-specific self-closing elements
        $config->append('self-closing.tag', [
            'input-group',
            'form-error',
            'el-input',
            'el-option'
        ]);
        
        $this->htmlFormatter->setConfig($config);
        $this->prettier = new PrettierService();
    }

    private function formatScriptContent(string $content): string
    {
        return $this->prettier->formatScript($content);
    }

    public function formatVueContent(string $content): string
    {
        $sections = $this->splitSections($content);
        
        // Format template section using HTML Formatter
        if (isset($sections['template'])) {
            $formattedTemplate = $this->htmlFormatter->beautify($sections['template']);
            $sections['template'] = trim($formattedTemplate);
        }
        
        // Format script section using our custom formatter
        if (isset($sections['script'])) {
            $scriptContent = preg_replace('/<script>\s*(.*?)\s*<\/script>/s', '$1', $sections['script']);
            $formattedScript = $this->formatScriptContent($scriptContent);
            $sections['script'] = "<script>\n" . $formattedScript . "\n</script>";
        }
        
        return $this->mergeSections($sections);
    }

    private function splitSections(string $content): array
    {
        $sections = [];
        
        // Extract template section
        if (preg_match('/<template>(.*?)<\/template>/s', $content, $matches)) {
            $sections['template'] = $matches[1];
            $content = str_replace($matches[0], '[[TEMPLATE_PLACEHOLDER]]', $content);
        }
        
        // Extract script section
        if (preg_match('/<script>(.*?)<\/script>/s', $content, $matches)) {
            $sections['script'] = $matches[0];
            $content = str_replace($matches[0], '[[SCRIPT_PLACEHOLDER]]', $content);
        }
        
        // Extract style section
        if (preg_match('/<style.*?>(.*?)<\/style>/s', $content, $matches)) {
            $sections['style'] = $matches[0];
            $content = str_replace($matches[0], '[[STYLE_PLACEHOLDER]]', $content);
        }
        
        $sections['remaining'] = $content;
        return $sections;
    }

    private function mergeSections(array $sections): string
    {
        $content = $sections['remaining'];
        
        if (isset($sections['template'])) {
            $content = str_replace(
                '[[TEMPLATE_PLACEHOLDER]]', 
                "<template>\n{$sections['template']}\n</template>", 
                $content
            );
        }
        
        if (isset($sections['script'])) {
            $content = str_replace('[[SCRIPT_PLACEHOLDER]]', $sections['script'], $content);
        }
        
        if (isset($sections['style'])) {
            $content = str_replace('[[STYLE_PLACEHOLDER]]', $sections['style'], $content);
        }
        
        return trim($content) . "\n";
    }

    public function formatPhpContent(string $content): string
    {
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
            return $content;
        }
    }
} 