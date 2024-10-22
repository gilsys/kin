<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Constant\App\MenuSection;
use App\Constant\StaticListTable;
use App\Dao\RecipeDAO;
use App\Dao\RecipeFileDAO;
use App\Dao\StaticListDAO;
use App\Exception\AuthException;
use App\Service\LogService;
use App\Service\PdfService;
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
     * Prepara el formulario de crear/editar
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        if (!empty($args['id'])) {
            $this->get('security')->checkRecipeOwner($args['id']);
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

        // ???
        $data['data']['default_main_language'] = null;

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
        $this->get('security')->checkRecipeOwner($args['id']);
        return parent::load($request, $response, $args);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        unset($formData['root']);

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
            //$pdfService->bookletPdf($formData['id'], true);
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
};
