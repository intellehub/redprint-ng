<?php

namespace Shahnewaz\RedprintNg\Services;

use Peast\Peast;
use Peast\Renderer;
use Peast\Formatter\PrettyPrint;

/**
 * JS code formatter for <script> sections of Vue files.
 * Uses the Peast parser to build an AST, then prints it with controlled indentation.
 * 
 * This code focuses on indentation, method-chaining, and basic node types.
 */
class PrettierService
{
    /**
     * Format the content of a <script> block from a Vue SFC.
     */
    public function formatScript(string $content): string
    {
        $ast = Peast::latest($content, [
            'comments' => true,
            'sourceType' => Peast::SOURCE_TYPE_MODULE,
        ])->parse();
        $renderer = new Renderer;
        //Associate the formatter
        $renderer->setFormatter(new PrettyPrint);
        //Render the AST
        return $renderer->render($ast); 
    }

} 