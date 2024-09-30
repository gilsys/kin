<?php

declare(strict_types=1);

namespace App\Util;

use App\Dao\ParamDAO;

class ParamsLoader {

    private $params;

    public function __construct($pdo) {
        $paramDAO = new ParamDAO($pdo);
        $this->params = $paramDAO->getAllAssoc('value');
    }

    public function getParam($paramKey) {
        return $this->params[$paramKey];
    }

    public function getAll() {
        return $this->params;
    }

}


