<?php

declare(strict_types=1);

namespace App\Constant;

class StaticListTable
{

    const Area = 'AR';
    const Language = 'LN';

    public static function getEntity($key)
    {
        $mapping = [
            self::Area => 'area',
            self::Language => 'language',
        ];

        if (empty($mapping[$key])) {
            throw new \Exception(__('app.error.invalid_parameters'));
        }

        return $mapping[$key];
    }

    public static function getTable($key)
    {
        return 'st_' . self::getEntity($key);
    }
}
