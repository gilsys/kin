<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\UserProfile;
use App\Dao\BookletDAO;
use App\Dao\BookletProductDAO;
use App\Dao\MarketProductDAO;
use App\Dao\ProductDAO;
use App\Dao\RecipeDAO;
use App\Dao\UserDAO;
use App\Exception\AuthException;

class SecurityService extends BaseService {

    private $session;
    private $params;

    public function __construct($pdo = null, $session = null, $params = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
    }

    public function getUserId() {
        return !empty($this->session['user']) ? $this->session['user']['id'] : null;
    }

    public function getMarketId() {
        return !empty($this->session['user']) ? $this->session['user']['market_id'] : null;
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

    public function checkMarket($marketId) {
        if ($this->isAdmin() || $this->session['user']['market_id'] == $marketId) {
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
        $recipe = $recipeDAO->getById($recipeId);

        if ($this->getUserId() == $recipe['creator_user_id']) {
            return true;
        }

        $userDAO = new UserDAO($this->pdo);
        if ($readOnlyAccess && $userDAO->getSingleField($recipe['creator_user_id'], 'user_profile_id') == UserProfile::Administrator && $this->checkMarket($recipe['market_id'])) {
            return true;
        }

        throw new AuthException();
    }

    public function checkCustomProductOwner($customProductId) {
        if ($this->isAdmin() || empty($customProductId)) {
            return true;
        }
        $productDAO = new ProductDAO($this->pdo);
        $product = $productDAO->getById($customProductId);

        if (!empty($product['parent_product_id']) && $this->checkUser($product['creator_user_id'])) {
            return true;
        }

        throw new AuthException();
    }

    public function checkProductOwner($productId, $notDeleted = true, $notEmptyProduct = true, $allowUsed = false) {
        if ($this->isAdmin() || empty($productId)) {
            return true;
        }

        $productDAO = new ProductDAO($this->pdo);
        $marketProductDAO = new MarketProductDAO($this->pdo);
        $bookletProductDAO = new BookletProductDAO($this->pdo);

        if ($allowUsed && $bookletProductDAO->checkUserHasProduct($this->getUserId(), $productId)) {
            return true;
        }

        $product = $productDAO->getById($productId);

        if (
            ($notDeleted && !empty($product['date_deleted'])) ||
            ($notEmptyProduct && $product['id'] == $this->params->getParam('EMPTY_PRODUCT')) ||
            !in_array($this->getMarketId(), $marketProductDAO->getMarketsByProductId($productId))
        ) {
            throw new AuthException();
        }

        return true;
    }
}
