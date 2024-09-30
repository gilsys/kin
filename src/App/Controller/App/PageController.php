<?php

declare(strict_types=1);

namespace App\Controller\App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PageController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    /**
     * Página de listado de páginas
     */
    public function page(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return $this->get('renderer')->render($response, "page/" . $args['page'] . ".phtml");
    }

}
