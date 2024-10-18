<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\FormSaveMode;
use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Dao\BookletDAO;
use App\Dao\BookletFileDAO;
use App\Dao\BookletLayoutDAO;
use App\Dao\BookletProductDAO;
use App\Dao\FileDAO;
use App\Dao\MarketDAO;
use App\Dao\ProductDAO;
use App\Dao\StaticListDAO;
use App\Dao\UserDAO;
use App\Exception\AuthException;
use App\Service\LogService;
use App\Util\CommonUtils;
use App\Util\FileUtils;
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

    /**
     * Prepara el formulario de crear/editar booklets
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $languageEntity = StaticListTable::getEntity(StaticListTable::Language);
        $languageDAO = new StaticListDAO($this->get('pdo'), 'st_' . $languageEntity);
        $marketDAO = new MarketDAO($this->get('pdo'));
        $bookletLayoutDAO = new BookletLayoutDAO($this->get('pdo'));

        $data['data']['markets'] = $marketDAO->getForSelect();
        $data['data']['qr_languages'] = $languageDAO->getForSelect('id', 'name', 'custom_order');
        $data['data']['main_languages'] = array_slice($data['data']['qr_languages'], 0, 3);

        $data['data']['layouts'] = $bookletLayoutDAO->getForSelect('id', 'name', 'custom_order');

        // Obtener el market del usuario y el idioma asociado a ese market
        $marketId = $this->get('session')['user']['market_id'];

        if (!empty($marketId)) {
            $marketData = $marketDAO->getById($marketId);
            $data['data']['default_main_language'] = $marketData['main_language_id'];
        } else {
            $data['data']['default_main_language'] = null;
        }

        if (!empty($args['id'])) {
            $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
            $data['data']['bookletFiles'] = $bookletFileDAO->getFilesByBookletId($args['id']);
        }

        if (!empty($args['mode']) && !empty($args['id'])) {
            // TODO generate pdf
            if ($args['mode'] == FormSaveMode::SaveAndPreview) {
                $data['jscustom'] = 'window.open("/app/booklet/pdf/' . $args['id'] . '", "_blank")';
            } else if ($args['mode'] == FormSaveMode::SaveAndGenerate) {
                if (!empty($data['data']['bookletFiles'])) {
                    $data['jscustom'] = 'window.open("/app/booklet/pdf/file/' . $data['data']['bookletFiles'][0]['id'] . '", "_blank")';
                }
            }
        }

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
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
        }

        // Si el usuario no es administrador, se asocia el market del usuario (o el que había si ya estaba creado)
        if ($this->get('session')['user']['user_profile_id'] != UserProfile::Administrator) {
            if (empty($formData['id'])) {
                $formData['market_id'] = $this->get('session')['user']['market_id'];
            } else {
                $formData['market_id'] = $this->getDAO()->getSingleField($formData['id'], 'market_id');
            }
        }

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

        if (!empty($args['mode']) && $args['mode'] == FormSaveMode::SaveAndGenerate) {
            $this->get('logger')->addInfo("Generate PDF " . static::ENTITY_SINGULAR . " - id: " . $formData['id']);

            // TODO generate pdf
            //$pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
            //$pdfService->bookletePdf($id, true);
            $this->testSaveFile($this->getDAO()->getFullById($formData['id']));

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
        // TODO generate pdf
        $this->get('logger')->addInfo("Preview PDF " . static::ENTITY_SINGULAR . " - id: " . $args['id']);
        //$pdfService = new PdfService($this->get('pdo'), $this->get('session'), $this->get('params'), $this->get('renderer'));
        //$pdfService->bookletePdf($args['id'], false);
        exit();
    }

    public function pdfFile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
        $bookletFile = $bookletFileDAO->getByFileId($args['id']);

        if (empty($bookletFile)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }

        return parent::getFileById($response, $bookletFile['file_id']);
    }

    public function pdfDelete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
        $bookletFile = $bookletFileDAO->getByFileId($args['id']);
        $bookletFileDAO->deleteByFileId($bookletFile['file_id']);

        return ResponseUtils::withJson($response, ['success' => 1]);
    }

    // TODO generate pdf
    private function testSaveFile($booklet) {
        $fileDAO = new FileDAO($this->get('pdo'));

        $fileName = 'booklet_' . $booklet['id'] . '_' . $fileDAO->getNextAutoincrement() . '.pdf';
        $fileType = FileType::BookletFile;

        $fileId = $fileDAO->save(['file_type_id' => $fileType, 'file' => $fileName]);

        $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
        $bookletFileDAO->save(['booklet_id' => $booklet['id'], 'file_id' => $fileId]);

        $directory = $this->get('params')->getParam('FOLDER_PRIVATE');
        FileUtils::saveFile($fileType, $directory, $fileId, 'file', $fileName, file_get_contents('Z:/test.pdf'));
    }
};
