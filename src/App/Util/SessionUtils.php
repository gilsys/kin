<?php

declare(strict_types=1);

namespace App\Util;

class SessionUtils {

    
    public static function saveSessionData($data) {
        $token = CommonUtils::generateRandString(20);
        $_SESSION['redirect_token_' . $token] = $data;
        return $token;
    }

    public static function getSessionData($token) {
        return !empty($token) && !empty($_SESSION['redirect_token_' . $token]) ? $_SESSION['redirect_token_' . $token] : [];
    }

    public static function deleteSessionData($token) {
        unset($_SESSION['redirect_token_' . $token]);
    }

    

}


