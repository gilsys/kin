<?php

declare(strict_types=1);

namespace App\Constant;

class StaticListTable
{
    const Language = 'LN';
    const BookletLayout = 'BL';
    const RecipeLayout = 'RL';
    const Color = 'CL';

    public static function getEntity($key)
    {
        $mapping = [
            self::Language => 'language',
            self::BookletLayout => 'booklet_layout',
            self::RecipeLayout => 'recipe_layout',
            self::Color => 'color'
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
