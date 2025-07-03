<?php

declare(strict_types=1);

namespace App\Constant;

class Folders {

    public static function getRoot(){
        return dirname(dirname(dirname(__DIR__)));
    }
    
    public static function getPublic() {
        return self::getRoot() . DIRECTORY_SEPARATOR. 'public';
    }
    
    public static function getPublicUpload() {
        return self::getRoot() . DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR .  'upload';
    }

    public static function getFonts() {
        return self::getPublic() . DIRECTORY_SEPARATOR .  'app' . DIRECTORY_SEPARATOR .  'fonts';
    }
    
    public static function getPrivateMedia() {
        return self::getRoot() . DIRECTORY_SEPARATOR. 'var' . DIRECTORY_SEPARATOR .  'media';
    }
    
    public static function getProjectJs() {
        return self::getRoot() . DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR .  'js'. DIRECTORY_SEPARATOR .  'project';
    }
    
    public static function getPublicImg() {
        return self::getRoot() . DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR .  'app' . DIRECTORY_SEPARATOR .  'img';
    }

}


