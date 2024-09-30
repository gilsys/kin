<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class AuthCookieMiddleware {

    private $container;

    public function __construct($container) {
        $this->container = $container;
    }

    /**
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $authService = new AuthService($this->container->get('pdo'), $this->container->get('session'));
        $authService->checkRememberCookie();

        return $handler->handle($request);
    }

}
