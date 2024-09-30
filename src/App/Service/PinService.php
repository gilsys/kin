<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\StatusEvent;
use App\Dao\UserDAO;
use App\Service\BaseService;
use App\Util\CommonUtils;

class PinService extends BaseService {

    private $session;
    private $params;
    private $renderer;

    public function __construct($pdo = null, $session = null, $params = null, $renderer = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
        $this->renderer = $renderer;
    }

    public function generate($userId = null) {
        if (empty($userId)) {
            $userId = $this->session['user']['id'];
        }

        $userDAO = new UserDAO($this->pdo);
        $user = $userDAO->getById($userId, false);
        $personalInformation = json_decode($user['personal_information'], true);

        $pinLenght = intval($this->params->getParam('PIN_LENGTH'));
        do {
            $pin = strtoupper(CommonUtils::generateRandomToken($userId, $pinLenght));
        } while (!empty($userDAO->getByPin($pin)));

        $personalInformation['pin'] = [
            'pin' => $pin,
            'time' => time()
        ];

        $userDAO->updateProfile(['id' => $userId, 'personal_information' => json_encode($personalInformation)]);

        if (!empty($this->session['user'])) {
            $authService = new AuthService($this->pdo, $this->session, $this->params);
            $authService->reload();
        }

        return $pin;
    }

    public function check($pin, $userId = null) {
        if (empty($userId)) {
            $userId = $this->session['user']['id'];
        }

        $userDAO = new UserDAO($this->pdo);
        $user = $userDAO->getByPin($pin, $userId);

        if (empty($user)) {
            return false;
        }

        $diff = time() - intval($user['pin']['time']);
        $secondsLive = intval($this->params->getParam('PIN_CADUCITY')) * 60;

        if ($diff > intval($secondsLive)) {
            return false;
        }

        return true;
    }

    public function sendEmail($userId = null, $statusEvent = StatusEvent::EmailPIN) {
        if (empty($userId)) {
            $userId = $this->session['user']['id'];
        }

        $userDAO = new UserDAO($this->pdo);
        $user = $userDAO->getById($userId);

        $data = [
            'user' => $user,
            'pin' => [
                'pin' => $this->generate($user['id']),
                'caducity' => $this->params->getParam('PIN_CADUCITY')
            ]
        ];

        $noticeService = new NoticeService($this->pdo, $this->session, $this->params, $this->renderer);
        $noticeService->sendEmail($statusEvent, $user['email'], $data);
    }

    public function savePinToken() {
        $pinToken = CommonUtils::generateRandString(20);
        $this->session['pin_token_' . $pinToken] = 1;
        return $pinToken;
    }

    public function checkPinToken($pinToken) {
        if (empty($this->session['pin_token_' . $pinToken])) {
            return false;
        }
        unset($this->session['pin_token_' . $pinToken]);
        return true;
    }

}


