<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Dao\CityDAO;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LocationController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    /*
    public function postalCode(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $cityDAO = new CityDAO($this->get('pdo'));
        $cities = $cityDAO->searchByPostalCode($args['postalcode']);
        return ResponseUtils::withJson($response, $cities);
    }
    */

}
