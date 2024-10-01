<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Controller\App\BaseController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $response->withStatus(302)->withHeader('Location', '/app/users');
    }

}
