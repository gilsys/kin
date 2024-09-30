<?php

declare(strict_types=1);

use App\Middleware\AccessControlMiddleware;
use App\Middleware\AuthCookieMiddleware;
use App\Middleware\ConsoleMiddleware;
use App\Middleware\PrivateCommonMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Middleware\Session;
use Slim\Psr7\Response;

return function (App $app) {

    // Global middlewares
    // ATENCIÓN, se ejecutan en orden inverso al que se incluyen.
    $app->add(new ConsoleMiddleware($app->getContainer()));
    $app->add(new AccessControlMiddleware());
    $app->add(new AuthCookieMiddleware($app->getContainer()));
    $app->add(new PrivateCommonMiddleware($app->getContainer()));
    $app->add(new Session(['name' => 'user_session', 'autorefresh' => true, 'lifetime' => '8 hours']));
    
    // CSRF
    $app->getContainer()->set('csrf', function () use ($app) {
        $csrfGuard = new Guard($app->getResponseFactory());
        $csrfGuard->setPersistentTokenMode(true);
        $csrfGuard->setFailureHandler(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
            session_destroy();
            $response = new Response();
            return $response->withStatus(302)->withHeader('Location', '/');
        });
        return $csrfGuard;
    });
    
    
};
