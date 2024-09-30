<?php

declare(strict_types=1);

namespace App\Util;

class JsonManager {

    private $folder;
    
    public function __construct($folder) {
        $this->folder = $folder;
    }

    public function readJSON($file) {
        $targetFile = $this->folder . $file;        
        if (file_exists($targetFile)) {
            $content = file_get_contents($this->folder . $file);
            return json_decode($content, true);
        }
        return null;
    }

}

