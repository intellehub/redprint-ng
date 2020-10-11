<?php
namespace Shahnewaz\RedprintNg\Exceptions;

class BuildProcessException extends \Exception {
    public function __construct ($message = null, $code = 422) {
        $message = $message ?: 'Builder encountered an error. Please try again later.';
        throw new \Exception($message, $code);
    }
}