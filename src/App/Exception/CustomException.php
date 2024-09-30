<?php

declare(strict_types=1);

namespace App\Exception;
use Exception;

class CustomException extends Exception {

    public function __construct($message = 'Custom exception', $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}
