<?php

declare(strict_types=1);

namespace App\Constant\App;

class Color {

    const Success = 'success';
    const Error = 'error';
    const Danger = 'danger';
    const Dark = 'dark';
    const Info = 'info';
    const Secondary = 'secondary';
    const Primary = 'primary';

    static function getAll() {
        $oClass = new ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

}


