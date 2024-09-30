<?php

declare(strict_types=1);

namespace App\Service;

class StaticListService {

    private $i18n;

    public function __construct($i18n = null) {
        $this->i18n = $i18n;
    }

    public function getForSelect($entity) {
        $translations = $this->i18n->getTranslationsStartingWith(['table.' . $entity . '.']);
        uksort($translations, function ($a, $b) {
            $explodedA = explode('.', $a);
            $explodedB = explode('.', $b);

            if (count($explodedA) != count($explodedB) && count($explodedB) < 3) {
                return $a > $b ? 1 : -1;
            }

            $strToCompareA = $explodedA[2];
            $strToCompareB = $explodedB[2];

            if (is_numeric($strToCompareA) && is_numeric($strToCompareA)) {
                return intval($strToCompareA) > intval($strToCompareB) ? 1 : -1;
            }
            return $strToCompareA > $strToCompareB ? 1 : -1;
        });

        $result = [];
        foreach ($translations as $key => $translation) {
            $explodedArray = explode('.', $key);
            $result[] = ['name' => $translation, 'id' => array_pop($explodedArray)];
        }

        return $result;
    }

}
