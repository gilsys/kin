<?php

declare(strict_types=1);

namespace App\Util;

class RequestUtils {

    public static function getParam($request, $param, $method='ANY') {
        if (($method == 'ANY' || $method == 'POST') && isset($request->getParsedBody()[$param])) {
            return $request->getParsedBody()[$param];
        } else if (($method == 'ANY' || $method == 'GET') && isset($request->getQueryParams()[$param])) {
            return $request->getQueryParams()[$param];
        }
        return false;
    }

}


