<?php

declare(strict_types=1);

namespace App\Constant;

use ReflectionClass;

class ProductStatus {

    const Enabled = 'E';    
    const Deleted = 'Z';

    static function getAll() {
        $oClass = new ReflectionClass(__CLASS__);
        return array_values($oClass->getConstants());
    }

}


