<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Constant\App\MenuSection;
use App\Constant\BookletType;
use App\Constant\FileType;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Dao\BookletDAO;
use App\Dao\BookletFileDAO;
use App\Dao\BookletProductDAO;
use App\Dao\MarketDAO;
use App\Dao\ProductDAO;
use App\Dao\StaticListDAO;
use App\Dao\UserDAO;
use App\Exception\AuthException;
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Service\PdfService;

class BookletController extends BaseController {

    const ENTITY_SINGULAR = 'booklet';
    const ENTITY_PLURAL = 'booklets';
    const MENU = MenuSection::MenuBooklet;


    public function getDAO() {
        return new BookletDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    /**
     * Página de listado de booklets
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $type = !empty($args['type']) ? $args['type'] : BookletType::Booklet;

        $data = $this->prepareList();
        if ($type == BookletType::Flyer) {
            $data['menu'] = MenuSection::MenuFlyer;
            $data['breadcumb'] = [ucfirst(__('table.booklet_type.' . $type . '.plural')), __('app.common.list_of', __('table.booklet_type.' . $type . '.plural'))];
            $data['title'] = implode(' > ', $data['breadcumb']);
        }

        // Carga selectores para filtros
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));
        $userDAO = new UserDAO($this->get('pdo'));

        $data['data'] = [
            'markets' => $marketDAO->getForSelect(),
            'qr_languages' => $languageDAO->getForSelect('id', 'name', 'custom_order'),
            'users' => $userDAO->getForSelectFullname(),
            'type' => $type
        ];
        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $userId = $this->get('security')->isUser() ? $this->get('security')->getUserId() : null;
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable($args['type'], $userId));
    }

    /**
     * Prepara el formulario de crear/editar booklets
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        if (!empty($args['id'])) {
            $this->get('security')->checkBookletOwner($args['id']);
            $type = $this->getDAO()->getSingleField($args['id'], 'booklet_type_id');
        } else {
            $type = $args['type'];
        }

        $data = $this->prepareForm($args);
        if ($type == BookletType::Flyer) {
            $data['menu'] = MenuSection::MenuFlyer;
            $data['breadcumb'] = [ucfirst(__('table.booklet_type.' . $type . '.plural')), empty($data['data']['id']) ? __('app.common.form.add', __('table.booklet_type.' . $type)) : __('app.common.form.update', __('table.booklet_type.' . $type))];
            $data['title'] = implode(' > ', $data['breadcumb']);
        }

        // Obtenemos los valores a mostrar en los desplegables
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));
        $bookletLayoutEntity = StaticListTable::getEntity(StaticListTable::BookletLayout);
        $bookletLayoutDAO = new StaticListDAO($this->get('pdo'), 'st_' . $bookletLayoutEntity);

        $data['data']['type'] = $type;
        $data['data']['markets'] = $marketDAO->getForSelect();
        $data['data']['qr_languages'] = $languageDAO->getForSelect('id', 'name', 'custom_order');
        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);

        $data['data']['layouts'] = $bookletLayoutDAO->getForSelect('id', 'name', 'custom_order');

        // Obtener el market del usuario y el idioma asociado a ese market
        $marketId = $this->get('session')['user']['market_id'];

        if (!empty($marketId)) {
            $data['data']['default_market_id'] = $marketId;
        }

        if (!empty($args['id'])) {
            $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
            $data['data']['bookletFiles'] = $bookletFileDAO->getFilesByBookletId($args['id']);
        }

        if (!empty($args['mode']) && !empty($args['id'])) {
            if ($args['mode'] == FormSaveMode::SaveAndPreview) {
                $data['jscustom'] = 'window.open("/app/booklet/pdf/' . $args['id'] . '", "_blank")';
            } else if (in_array($args['mode'], [FormSaveMode::SaveAndGenerateCMYK, FormSaveMode::SaveAndGenerate])) {
                if (!empty($data['data']['bookletFiles'])) {
                    $data['jscustom'] = 'window.open("/app/booklet/pdf/file/' . $data['data']['bookletFiles'][0]['id'] . '", "_blank")';
                }
            }
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkBookletOwner($args['id']);

        $data = $this->getDAO()->getFullById($args['id']);
        if (empty($data)) {
            throw new AuthException();
        }

        $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
        $data['booklet_products'] = $bookletProductDAO->getByBookletId($args['id']);

        return ResponseUtils::withJson($response, $data);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        // Si es un nuevo booklet
        if (empty($formData['id'])) {
            $formData['creator_user_id'] = $this->get('session')['user']['id'];
        } else {
            $this->get('security')->checkBookletOwner($formData['id']);
        }

        // Si el usuario no es administrador, se asocia el market del usuario (o el que había si ya estaba creado)
        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

        $formData['qr_language_id'] = !empty($formData['qr_language_id']) ? $formData['qr_language_id'] : null;
        $formData['main_language_id'] = !empty($formData['main_language_id']) ? $formData['main_language_id'] : null;
        $formData['page2_booklet_layout_id'] = !empty($formData['page2_booklet_layout_id']) ? $formData['page2_booklet_layout_id'] : null;
        $formData['page3_booklet_layout_id'] = !empty($formData['page3_booklet_layout_id']) ? $formData['page3_booklet_layout_id'] : null;
        $formData['page4_booklet_layout_id'] = !empty($formData['page4_booklet_layout_id']) ? $formData['page4_booklet_layout_id'] : null;
        $formData['market_id'] = !empty($formData['market_id']) ? $formData['market_id'] : null;

        if (!empty($formData['id'])) {
            $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
            $bookletProductDAO->clear($formData['id']);
        }
    }

    public function savePersist($request, $response, $args, &$formData) {
        $bookletProducts = !empty($formData['booklet_product']) ? $formData['booklet_product'] : [];
        unset($formData['booklet_product']);

        parent::savePersist($request, $response, $args, $formData);

        $productDAO = new ProductDAO($this->get('pdo'));
        $productIds = array_column($productDAO->getByMarketId($formData['market_id']), 'id');

        $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
        foreach ($bookletProducts as $page => $pageItems) {
            foreach ($pageItems as $order => $product) {
                $displayMode = key($product);
                $productId = current($product);

                if (!in_array($productId, $productIds)) {
                    continue;
                }

                $bookletProductDAO->save(['booklet_id' => $formData['id'], 'product_id' => $productId, 'page' => $page, 'custom_order' => $order, 'display_mode' => $displayMode]);
            }
        }

        if (!empty($args['mode']) && in_array($args['mode'], [FormSaveMode::SaveAndGenerateCMYK, FormSaveMode::SaveAndGenerate])) {
            $this->get('logger')->addInfo("Generate PDF " . static::ENTITY_SINGULAR . " - id: " . $formData['id']);
            $pdfType = $args['mode'] == FormSaveMode::SaveAndGenerateCMYK ? FileType::BookletFileCMYK : FileType::BookletFile;
            $pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
            $pdfService->bookletPdf($formData['id'], true, $pdfType);
            LogService::save($this, 'app.log.action.generate_pdf', [ucfirst(__('app.entity.' . static::ENTITY_PLURAL)), $this->getNameForLogs($formData['id'])], $this->getDAO()->getTable(), $formData['id']);
        }
    }

    public function savePostSave($request, $response, $args, &$formData) {
        $this->get('flash')->addMessage('success', __('app.controller.save_ok'));

        $type = $this->getDAO()->getSingleField($formData['id'], 'booklet_type_id');

        // Dependiendo de la selección del usuario, se redirige a una pantalla u otra
        switch ($args['mode']) {
            case FormSaveMode::SaveAndGenerateCMYK:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, false) . '/' . $args['mode']);
            case FormSaveMode::SaveAndGenerate:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, false) . '/' . $args['mode']);
            case FormSaveMode::SaveAndPreview:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, false) . '/' . $args['mode']);
            case FormSaveMode::SaveAndContinue:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, false));
            case FormSaveMode::SaveAndNew:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlForm($formData, true));
            default:
                return $response->withStatus(302)->withHeader('Location', $this->getRedirectUrlList($formData));
        }
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $this->get('security')->checkBookletOwner($formData['id']);

        $bookletProductDAO = new BookletProductDAO($this->get('pdo'));
        $bookletProductDAO->clear($formData['id']);

        $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
        $bookletFileDAO->clear($formData['id']);
    }

    public function getProducts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $formData = CommonUtils::getSanitizedData($request);
        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

        $productDAO = new ProductDAO($this->get('pdo'));
        $products = $productDAO->getByMarketId($formData['market_id']);

        return ResponseUtils::withJson($response, $products);
    }

    public function pdfPreview(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $this->get('security')->checkBookletOwner($args['id']);

        $this->get('logger')->addInfo("Preview PDF " . static::ENTITY_SINGULAR . " - id: " . $args['id']);
        $pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
        $pdfService->bookletPdf($args['id'], false);
        exit();
    }

    public function pdfFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
        $bookletFile = $bookletFileDAO->getByFileId($args['id']);

        $this->get('security')->checkBookletOwner($bookletFile['booklet_id']);

        if (empty($bookletFile)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }

        return parent::getFileById($response, $bookletFile['file_id']);
    }

    public function pdfDelete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
        $bookletFile = $bookletFileDAO->getByFileId($args['id']);

        $this->get('security')->checkBookletOwner($bookletFile['booklet_id']);

        $bookletFileDAO->deleteByFileId($bookletFile['file_id']);

        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    public function getRedirectUrlList($formData) {
        $url = '/app/' . static::ENTITY_PLURAL;
        if (!empty($formData['id'])) {
            $url .= '/' . $this->getDAO()->getSingleField($formData['id'], 'booklet_type_id');
        }  else if (!empty($formData['booklet_type_id'])) {
            $url .= '/' . $formData['booklet_type_id'];
        }
        return $url;
    }

    public function getRedirectUrlForm($formData, $new) {
        $url = '/app/' . static::ENTITY_SINGULAR . '/form';
        if (!$new && !empty($formData['id'])) {
            $url .= '/' . $formData['id'];
        } else if (!empty($formData['id'])) {
            $url .= '/' . $this->getDAO()->getSingleField($formData['id'], 'booklet_type_id');
        }  else if (!empty($formData['booklet_type_id'])) {
            $url .= '/' . $formData['booklet_type_id'];
        }
        return $url;
    }
};
