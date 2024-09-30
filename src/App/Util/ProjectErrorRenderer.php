<?php

declare(strict_types=1);

namespace App\Util;

use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

class ProjectErrorRenderer implements ErrorRendererInterface {

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string {
        global $app;
        $data = ['exception' => $exception, 'displayErrorDetails' => $displayErrorDetails];

        if ($exception->getCode() == 404) {
            http_response_code(404);
        }

        // Show custom error if we are working on FrontOffice
        if (preg_match("/\/app/", $_SERVER['REQUEST_URI'])) {
            return $app->getContainer()->get('renderer')->fetch("error.phtml", $data);
        }
        return $app->getContainer()->get('renderer')->fetch("error.phtml", $data);
    }

}
