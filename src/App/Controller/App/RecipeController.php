<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
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
            $this->get('security')->checkRecipeOwner($args['id'], true);
        }

        if (!$this->get('security')->isAdmin() && empty($args['id'])) {
            throw new AuthException();
        }

        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $recipeLayoutEntity = StaticListTable::getEntity(StaticListTable::RecipeLayout);
        $recipeLayoutDAO = new StaticListDAO($this->get('pdo'), 'st_' . $recipeLayoutEntity);

        $data['data']['qr_languages'] = $languageDAO->getForSelect('id', 'name', 'custom_order');
        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);

        $data['data']['layouts'] = $recipeLayoutDAO->getForSelect('id', 'name', 'custom_order');

        if (!empty($args['id'])) {
            $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
            $data['data']['recipeFiles'] = $recipeFileDAO->getFilesByRecipeId($args['id']);
        }

        if (!empty($args['mode']) && !empty($args['id'])) {
            if ($args['mode'] == FormSaveMode::SaveAndPreview) {
                $data['jscustom'] = 'window.open("/app/recipe/pdf/' . $args['id'] . '", "_blank")';
            } else if ($args['mode'] == FormSaveMode::SaveAndGenerate) {
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

        if (!$this->get('security')->isAdmin()) {
            if (empty($formData['id'])) {
                throw new AuthException();
            }

            $old = $this->getDAO()->getById($formData['id']);
            $formData['qr_language_id'] = $old['qr_language_id'];
            $formData['main_language_id'] = $old['main_language_id'];
            $formData['recipe_layout_id'] = $old['recipe_layout_id'];
        }

        if (empty($formData['id'])) {
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
        } else {
            $this->get('security')->checkRecipeOwner($formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        parent::savePersist($request, $response, $args, $formData);

        if (!empty($args['mode']) && $args['mode'] == FormSaveMode::SaveAndGenerate) {
            $this->get('logger')->addInfo("Generate PDF " . static::ENTITY_SINGULAR . " - id: " . $formData['id']);
            $pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
            $pdfService->recipePdf($formData['id'], true);
            LogService::save($this, 'app.log.action.generate_pdf', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        }
    }

    public function savePostSave($request, $response, $args, &$formData) {
        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));

        // Dependiendo de la selección del usuario, se redirige a una pantalla u otra
        switch ($args['mode']) {
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
        $productDAO = new ProductDAO($this->get('pdo'));
        $data['products'] = $productDAO->getProducts();

        $subProductDAO = new SubProductDAO($this->get('pdo'));
        $data['subproducts'] = $subProductDAO->getSubProducts();

        return ResponseUtils::withJson($response, $data);
    }

    public function pdfPreview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkRecipeOwner($args['id']);

        $this->get('logger')->addInfo("Preview PDF " . static::ENTITY_SINGULAR . " - id: " . $args['id']);
        $pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
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
};
