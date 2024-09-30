<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\AuthException;
use App\Service\AuthService;
use App\Util\CommonUtils;
use App\Util\SlimUtils;
use Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class ProfileMiddleware {

    private $profiles;

    public function __construct($profiles = null) {
        $this->profiles = $profiles;
    }

    /**
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $session = $_SESSION;

        try {
            if (empty($session['user'])) {
                throw new AuthException();
            }
            $user = $session['user'];

            if (!empty($this->profiles) && !in_array($user['user_profile_id'], $this->profiles)) {
                throw new AuthException();
            }
        } catch (Exception $e) {
            SlimUtils::getLogger()->addDebug('Session destroy');

            $authService = new AuthService();
            $authService->logout();

            $response = new Response();

            if (!empty($request->getUri()) && CommonUtils::startsWith($request->getUri()->getPath(), '/app')) {
                return $response->withStatus(302)->withHeader('Location', '/app');
            }
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        return $handler->handle($request);
    }

}
