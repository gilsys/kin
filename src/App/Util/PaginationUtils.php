<?php

declare(strict_types=1);

namespace App\Util;

class PaginationUtils {

    public static function paginate(&$result, $itemsPage, $page = 1) {
        $result = (array)$result;
        $total = count($result);
        if ($total > 0) {
            $result = array_slice($result, ($page - 1) * $itemsPage, $itemsPage);
        }
        return $total;
    }    

}


