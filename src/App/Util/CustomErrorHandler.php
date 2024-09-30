<?php

declare(strict_types=1);

namespace App\Util;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;
use Exception;

final class CustomErrorHandler extends \Slim\Handlers\Error {

    protected $logger;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, Exception $exception) {
        // Log the message
        $this->get('logger')->critical($exception->getMessage());
        return $response->withStatus(500)->withHeader('Content-Type', 'text/html;charset=utf-8')->write($exception->getMessage());
    }

}
