<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\UserProfile;
use App\Dao\BookletDAO;
use App\Dao\RecipeDAO;
use App\Dao\UserDAO;
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

    public function checkBookletOwner($bookletId) {
        if (empty($bookletId) || $this->isAdmin()) {
            return true;
        }
        $bookletDAO = new BookletDAO($this->pdo);
        $creatorUserId = $bookletDAO->getSingleField($bookletId, 'creator_user_id');

        if ($this->checkUser($creatorUserId)) {
            return true;
        }

        throw new AuthException();
    }

    public function checkRecipeOwner($recipeId, $readOnlyAccess = false) {
        if (empty($recipeId) || $this->isAdmin()) {
            return true;
        }
        $recipeDAO = new RecipeDAO($this->pdo);
        $creatorUserId = $recipeDAO->getSingleField($recipeId, 'creator_user_id');

        if ($this->getUserId() == $creatorUserId) {
            return true;
        }

        $userDAO = new UserDAO($this->pdo);
        if ($readOnlyAccess && $userDAO->getSingleField($creatorUserId, 'user_profile_id') == UserProfile::Administrator) {
            return true;
        }

        throw new AuthException();
    }
}
