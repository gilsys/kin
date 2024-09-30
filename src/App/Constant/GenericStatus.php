<?php

declare(strict_types=1);

namespace App\Constant;

use ReflectionClass;

class GenericStatus {

    const Enabled = 'E';    
    const Disabled = 'D';

    static function getAll() {
        $oClass = new ReflectionClass(__CLASS__);
        return array_values($oClass->getConstants());
    }

}


