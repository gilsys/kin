<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    const MENU = MenuSection::MenuDashboard;

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        return $response->withStatus(302)->withHeader('Location','/app/clients');
        /*
        $data = $this->preparePage(__('app.common.dashboard'), 'dashboard/dashboard');
        return $this->get('renderer')->render($response, "main.phtml", $data);
        */
    }

}
