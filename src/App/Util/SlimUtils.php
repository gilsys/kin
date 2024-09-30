<?php

declare(strict_types=1);

namespace App\Util;

class SlimUtils {

    static $lastBenchmark;

    public static function getContainer() {
        return $GLOBALS["app"]->getContainer();
    }

    public static function getLogger() {
        return self::getContainer()->get("logger");
    }

    public static function benchmark() {
        $lastBenchmark = round(microtime(true) * 1000);
        $diff = empty(self::$lastBenchmark) ? 0 : ($lastBenchmark - self::$lastBenchmark);
        self::$lastBenchmark = $lastBenchmark;        
        return $diff;
    }

}


