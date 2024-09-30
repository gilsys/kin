<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\UserProfile;
use App\Dao\VisitDAO;
use App\Exception\AuthException;

class SecurityService extends BaseService {

    private $session;

    public function __construct($pdo = null, $session = null) {
        parent::__construct($pdo);
        $this->session = $session;
    }

    public function getUserId() {
        return !empty($this->session['user']) ? $this->session['user']['id'] : null;
    }

    public function isAdmin() {
        return ($this->session['user']['user_profile_id'] == UserProfile::Administrator);
    }

    public function isUser() {
        return ($this->session['user']['user_profile_id'] == UserProfile::User);
    }

    public function checkUser($userId) {
        if ($this->isAdmin() || $this->session['user']['id'] == $userId) {
            return true;
        }
        throw new AuthException();
    }

    public function checkAdmin() {
        if ($this->isAdmin()) {
            return true;
        }
        throw new AuthException();
    }

    public function checkStaticList($staticList) {
        if ($this->isAdmin()) {
            return true;
        }

        throw new AuthException();
    }
}
