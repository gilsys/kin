<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Dao\MarketDAO;
use App\Dao\ProductDAO;
use App\Dao\RecipeDAO;
use App\Dao\RecipeFileDAO;
use App\Dao\StaticListDAO;
use App\Dao\SubProductDAO;
use App\Dao\UserDAO;
use App\Exception\AuthException;
use App\Service\LogService;
use App\Service\PdfService;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RecipeController extends BaseController {

    const ENTITY_SINGULAR = 'recipe';
    const ENTITY_PLURAL = 'recipes';
    const MENU = MenuSection::MenuRecipe;


    public function getDAO() {
        return new RecipeDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    /**
     * Página de listado de recipes
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));
        $userDAO = new UserDAO($this->get('pdo'));

        $data['data'] = [
            'markets' => $marketDAO->getForSelect(),
            'qr_languages' => $languageDAO->getForSelect('id', 'name', 'custom_order'),
            'users' => $userDAO->getForSelectFullname()
        ];

        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $userId = $this->get('security')->isUser() ? $this->get('security')->getUserId() : null;
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable($userId));
    }

    /**
     * Prepara el formulario de crear/editar
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        if (!empty($args['id'])) {
            $this->get('security')->checkRecipeOwner($args['id'], false);
        }

        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));

        $data['data']['qr_languages'] = $languageDAO->getForSelect('id', 'name', 'custom_order');
        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);
        $data['data']['markets'] = $marketDAO->getForSelect();

        // Obtener el market del usuario y el idioma asociado a ese market
        $marketId = $this->get('session')['user']['market_id'];

        if (!empty($marketId)) {
            $data['data']['default_market_id'] = $marketId;
        }

        if (!empty($args['id'])) {
            $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
            $data['data']['recipeFiles'] = $recipeFileDAO->getFilesByRecipeId($args['id']);
        }

        if (!empty($args['mode']) && !empty($args['id'])) {
            if ($args['mode'] == FormSaveMode::SaveAndPreview) {
                $data['jscustom'] = 'window.open("/app/recipe/pdf/' . $args['id'] . '", "_blank")';
            } else if (in_array($args['mode'], [FormSaveMode::SaveAndGenerateCMYK, FormSaveMode::SaveAndGenerate])) {
                if (!empty($data['data']['recipeFiles'])) {
                    $data['jscustom'] = 'window.open("/app/recipe/pdf/file/' . $data['data']['recipeFiles'][0]['id'] . '", "_blank")';
                }
            }
        }




        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkRecipeOwner($args['id'], true);

        $data = $this->getDAO()->getFullById($args['id']);
        if (empty($data)) {
            throw new AuthException();
        }
        $data['editable'] = $this->get('security')->isAdmin() || $this->get('security')->getUserId() == $data['creator_user_id'] ? 1 : 0;
        return ResponseUtils::withJson($response, $data);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        unset($formData['root']);

        if (empty($formData['id'])) {
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
        } else {
            $this->get('security')->checkRecipeOwner($formData['id']);
        }

        if (!$this->get('security')->isAdmin()) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

        // Validar productos y referencias seleccionadas
        $oldRecipeProductIds = [];
        $oldRecipeSubProductIds = [];
        if (!empty($formData['id'])) {
            $data = $this->getDAO()->getSingleField($formData['id'], 'json_data');
            $data = !empty($data) ? json_decode($data, true) : [];
            $this->processRecipe($data, $oldRecipeProductIds, $oldRecipeSubProductIds);
        }

        $newRecipeProductIds = [];
        $newRecipeSubProductIds = [];
        $newData = !empty($formData['json_data']) ? json_decode($formData['json_data'], true) : [];
        $this->processRecipe($newData, $newRecipeProductIds, $newRecipeSubProductIds);

        $customCreatorUserId = $this->get('session')['user']['user_profile_id'] == UserProfile::User ? $this->get('session')['user']['id'] : null;

        $productDAO = new ProductDAO($this->get('pdo'));
        $productIds = array_column($productDAO->getProducts($oldRecipeProductIds, $formData['main_language_id'], $formData['market_id'], $customCreatorUserId, $oldRecipeSubProductIds), 'id');

        $subProductDAO = new SubProductDAO($this->get('pdo'));
        $subProductIds = array_column($subProductDAO->getSubProducts($formData['main_language_id'], $oldRecipeSubProductIds, $formData['market_id'], $customCreatorUserId, intval($formData['international'])), 'id');

        if (!empty(array_diff($newRecipeProductIds, $productIds)) || !empty(array_diff($newRecipeSubProductIds, $subProductIds))) {
            throw new AuthException();
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $formData['international'] = intval($formData['international']);
        $formData['qr_language_id'] = !empty($formData['qr_language_id']) ? $formData['qr_language_id'] : null;

        parent::savePersist($request, $response, $args, $formData);

        if (!empty($args['mode']) && in_array($args['mode'], [FormSaveMode::SaveAndGenerateCMYK, FormSaveMode::SaveAndGenerate])) {
            $this->get('logger')->addInfo("Generate PDF " . static::ENTITY_SINGULAR . " - id: " . $formData['id']);
            $pdfType = $args['mode'] == FormSaveMode::SaveAndGenerateCMYK ? FileType::RecipeFileCMYK : FileType::RecipeFile;
            $pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'), $this->get('i18n'));
            $pdfService->recipePdf($formData['id'], true, $pdfType);
            LogService::save($this, 'app.log.action.generate_pdf', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        }
    }

    public function savePostSave($request, $response, $args, &$formData) {
        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));

        // Dependiendo de la selección del usuario, se redirige a una pantalla u otra
        switch ($args['mode']) {
            case FormSaveMode::SaveAndGenerateCMYK:
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $formData['id'] . '/' . $args['mode']);
            case FormSaveMode::SaveAndGenerate:
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $formData['id'] . '/' . $args['mode']);
            case FormSaveMode::SaveAndPreview:
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $formData['id'] . '/' . $args['mode']);
            case FormSaveMode::SaveAndContinue:
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $formData['id']);
            case FormSaveMode::SaveAndNew:
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form');
            default:
                return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_PLURAL);
        }
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $this->get('security')->checkRecipeOwner($formData['id']);

        $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
        $recipeFileDAO->clear($formData['id']);
    }

    public function getProducts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        $recipeId = !empty($formData['id']) ? $formData['id'] : null;

        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

        if (empty($formData['market_id'])) {
            throw new AuthException();
        }

        // Obtener ids de los productos seleccionados
        $recipeProductIds = [];
        $recipeSubProductIds = [];
        $oldRecipe = !empty($recipeId) ? $this->getDAO()->getFullById($recipeId) : null;
        if (!empty($recipeId) && $formData['market_id'] == $oldRecipe['market_id']) {
            $data = $oldRecipe['json_data'];
            $this->processRecipe($data, $recipeProductIds, $recipeSubProductIds);
        }

        $customCreatorUserId = $this->get('session')['user']['user_profile_id'] == UserProfile::User ? $this->get('session')['user']['id'] : null;
        $lang = !empty($formData['main_language_id']) ? $formData['main_language_id'] : $this->get('i18n')->getCurrentLang();
        $international = intval($formData['international']);

        $productDAO = new ProductDAO($this->get('pdo'));
        $data['products'] = $productDAO->getProducts($recipeProductIds, $lang, $formData['market_id'], $customCreatorUserId, $recipeSubProductIds);

        $subProductDAO = new SubProductDAO($this->get('pdo'));
        $data['subproducts'] = $subProductDAO->getSubProducts($lang, $recipeSubProductIds, $formData['market_id'], $customCreatorUserId, $international);

        // Iterar los productos y subproductos para montar el html en el campo name
        $data['products'] = array_map(function ($product) {
            $customHtml = '';
            if (!empty($product['is_custom'])) {
                $customHtml = '<span class="badge badge-primary fw-lighter ms-2">' . __('app.js.product.custom') . '</span>';
            }

            $product['name'] = '<div class="product-name">' . $product['name'] . $customHtml . '</div>' .
                '<div class="product-subtitle">' . $product['subtitle'] . '</div>' .
                '<div class="product-periodicity">' . $product['periodicity'] . '</div>';
            return $product;
        }, $data['products']);

        // Iterar subproductos para montar el html adhoc
        $data['subproducts'] = array_map(function ($subproduct) {
            if (!empty($subproduct['reference'])) {
                $subproduct['name'] = $subproduct['reference'] . ' - ' . $subproduct['name'];
            }
            return $subproduct;
        }, $data['subproducts']);

        return ResponseUtils::withJson($response, $data);
    }

    private function processRecipe($array, &$recipeProductIds, &$recipeSubProductIds) {
        foreach ($array as &$value) {
            if (!empty($value['product_id'])) {
                $recipeProductIds[] = $value['product_id'];
            }

            if (!empty($value['subproduct_id'])) {
                $recipeSubProductIds[] = $value['subproduct_id'];
            }

            if (is_array($value)) {
                $this->processRecipe($value, $recipeProductIds, $recipeSubProductIds);
            }
        }
    }

    public function pdfPreview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkRecipeOwner($args['id']);

        $this->get('logger')->addInfo("Preview PDF " . static::ENTITY_SINGULAR . " - id: " . $args['id']);
        $pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'), $this->get('i18n'));
        $pdfService->recipePdf($args['id'], false);
        exit();
    }

    public function pdfFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
        $recipeFile = $recipeFileDAO->getByFileId($args['id']);

        $this->get('security')->checkRecipeOwner($recipeFile['recipe_id'], true);

        if (empty($recipeFile)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }

        return parent::getFileById($response, $recipeFile['file_id']);
    }

    public function pdfDelete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
        $recipeFile = $recipeFileDAO->getByFileId($args['id']);

        $this->get('security')->checkRecipeOwner($recipeFile['recipe_id']);

        $recipeFileDAO->deleteByFileId($recipeFile['file_id']);

        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    public function duplicate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);

        $this->get('security')->checkRecipeOwner($formData['id'], true);

        $this->get('logger')->addInfo("Duplicate " . static::ENTITY_SINGULAR . " - id: " . $formData['id']);
        try {
            $this->get('pdo')->beginTransaction();
            $newId = $this->getDAO()->duplicate($formData['id'], $this->get('security')->getUserId(), __('app.common.duplicate_concat_text'));
            LogService::save($this, 'app.log.action.duplicate', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
            $this->get('pdo')->commit();
        } catch (\Exception $e) {
            $this->get('pdo')->rollback();
            $this->get('logger')->addError($e);
            $this->get('flash')->addMessage('danger', __('app.error.duplicate'));
            return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_PLURAL);
        }

        $this->get('flash')->addMessage('success', __('app.controller.duplicate_ok'));
        return $response->withStatus(302)->withHeader('Location', '/app/' . static::ENTITY_SINGULAR . '/form/' . $newId);
    }

    public function productImage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $productDAO = new ProductDAO($this->get('pdo'));
        $product = $productDAO->getById($args['productId']);

        if (!$this->get('security')->isAdmin()) {
            if (empty($product['parent_product_id'])) {
                try {
                    $this->get('security')->checkProductOwner($product['id'], false);
                } catch (AuthException $e) {
                    if (!empty($args['recipeId'])) {
                        $this->get('security')->checkRecipeOwner($args['recipeId'], true);

                        $recipeProductIds = [];
                        $recipeSubProductIds = [];
                        $data = $this->getDAO()->getSingleField($args['recipeId'], 'json_data');
                        $data = !empty($data) ? json_decode($data, true) : [];
                        $this->processRecipe($data, $recipeProductIds, $recipeSubProductIds);

                        if (!in_array($product['id'], $recipeProductIds)) {
                            throw new AuthException();
                        }
                    } else {
                        throw new AuthException();
                    }
                }
            } else {
                $this->get('security')->checkCustomProductOwner($product['id']);
            }
        }

        $fileId = $productDAO->getSingleField($args['productId'], $args['field']);
        if (empty($fileId)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }
        return parent::getFileById($response, $fileId, $args['field']);
    }

    public function getData(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $colorEntity = StaticListTable::getEntity(StaticListTable::Color);
        $colorDAO = new StaticListDAO($this->get('pdo'), 'st_' . $colorEntity);
        $data = [
            'colors' => $colorDAO->getAll('custom_order')
        ];
        return ResponseUtils::withJson($response, $data);
    }
};
