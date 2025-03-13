<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constant\UserProfile;
use App\Constant\UserStatus;
use App\Dao\MarketDAO;
use App\Dao\UserDAO;
use App\Exception\WPAutoLoginException;
use App\Service\AuthService;
use Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class WPAutoLoginMiddleware {

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
        try {
            $headers = $request->getHeader('X-Requested-With');
            $isAjax = !empty($headers) && strtolower($headers[0]) === 'xmlhttprequest';

            if ($isAjax) {
                return $handler->handle($request);
            }

            $cookies = $request->getCookieParams();
            $wpSessionCookie = $this->container->get('params')->getParam('WP_AUTOLOGIN_SESSION_COOKIE');
            $authService = new AuthService($this->container->get('pdo'), $this->container->get('session'), $this->container->get('params'), $this->container->get('flash'));

            if (empty($cookies[$wpSessionCookie])) {
                if (!empty($this->container->get('session')['wp_last_session_id'])) {
                    $authService->logout();

                    $response = new Response();
                    return $response->withStatus(302)->withHeader('Location', '/');
                }
                return $handler->handle($request);
            }

            $sessionId = $cookies[$wpSessionCookie];

            if ((time() - intval($this->container->get('session')['wp_last_request'])) < $this->container->get('params')->getParam('WP_AUTOLOGIN_WAIT')) {
                return $handler->handle($request);
            }

            $oldUser = !empty($this->container->get('session')['user']) ? $this->container->get('session')['user'] : [];
            $oldWpUserId = !empty($oldUser['wp_id']) ? $oldUser['wp_id'] : null;

            $this->container->get('session')['wp_last_request'] = time();
            $user = $this->getUser($sessionId, $oldWpUserId);

            if (empty($user)) {
                if (!empty($this->container->get('session')['wp_last_session_id'])) {
                    $authService->logout(false);
                    $this->container->get('session')['wp_last_request'] = time();

                    $response = new Response();
                    return $response->withStatus(302)->withHeader('Location', '/');
                }
                return $handler->handle($request);
            }

            if (empty($oldUser) || $oldUser['id'] != $user['id']) {
                $authService->logout(false);
                $this->container->get('session')['wp_last_request'] = time();
                $authService->authByUserId($user['id'], true);
                $this->container->get('session')['wp_last_session_id'] = $sessionId;

                $response = new Response();
                return $response->withStatus(302)->withHeader('Location', '/');
            } else if ($oldUser['market_id'] != $user['market_id']) {
                $authService->reload();
            }
        } catch (WPAutoLoginException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->container->get('logger')->addError($e);
        }

        return $handler->handle($request);
    }

    private function getUser($sessionId, $oldWpUserId) {
        $data = json_encode(['session_id' => $sessionId, 'old_user_id' => $oldWpUserId]);

        $ch = curl_init($this->container->get('params')->getParam('WP_AUTOLOGIN_URL'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        if ($this->container->get('settings')['debug']) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200) {
            $this->container->get('logger')->addError('Wordpress get user request error: HTTP ' . $httpCode . ' - ' . $response);
            return null;
        }

        $wpUser = json_decode($response, true);

        if (empty($wpUser['user_id'])) {
            return null;
        }

        $marketDAO = new MarketDAO($this->container->get('pdo'));
        $marketId = !empty($wpUser['market_id']) ? $marketDAO->getSingleField($wpUser['market_id'], 'id', 'wp_id') : null;

        if (empty($marketId)) {
            $this->container->get('logger')->addError('Wordpress get user request market not exist: ' . $response);
            throw new WPAutoLoginException(__('app.error.autologin_market_not_exist'), 403);
        }

        $userDAO = new UserDAO($this->container->get('pdo'));
        $userId = $userDAO->getSingleField($wpUser['user_id'], 'id', 'wp_id');

        if (empty($userId)) {
            $userId = $this->saveUser($wpUser, $marketId);
        } else {
            $this->updateUser($userId, $marketId);
        }

        return $userDAO->getById($userId);
    }

    private function saveUser($wpUser, $marketId) {
        $userDAO = new UserDAO($this->container->get('pdo'));

        $nickname = $wpUser['email'];

        $i = 0;
        while ($userDAO->existsNickname($nickname)) {
            $i++;
            $nickname = $wpUser['email'] . '_' . $i;
        }

        $data = [
            'nickname' => $nickname,
            'personal_information' => json_encode([
                'name' => $wpUser['name'],
                'surnames' => $wpUser['surnames'],
                'email' => $wpUser['email'],
                'phone1' => preg_replace('/\D/', '', $wpUser['phone']),
            ]),
            'password' => null,
            'user_status_id' => UserStatus::Validated,
            'user_profile_id' => UserProfile::User,
            'color' => '#003A78',
            'market_id' => $marketId
        ];
        $userId = $userDAO->save($data);
        $userDAO->updateSingleField($userId, 'wp_id', $wpUser['user_id']);
        $userDAO->updateSingleField($userId, 'password', '-');

        return $userId;
    }

    private function updateUser($userId, $marketId) {
        $userDAO = new UserDAO($this->container->get('pdo'));
        $user = $userDAO->getById($userId);

        if ($user['user_profile_id'] != UserProfile::Administrator && $user['market_id'] != $marketId) {
            $userDAO->updateSingleField($userId, 'market_id', $marketId);
        }
    }
}
