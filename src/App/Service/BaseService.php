<?php

declare(strict_types=1);

namespace App\Service;

class BaseService {

    public $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function get($attribute) {
        return $this->$attribute;
    }

}


