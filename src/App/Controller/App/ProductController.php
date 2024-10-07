<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Constant\StaticListTable;
use App\Dao\StaticListDAO;
use App\Dao\ProductDAO;
use App\Exception\AuthException;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProductController extends BaseController {

    const ENTITY_SINGULAR = 'product';
    const ENTITY_PLURAL = 'products';
    const MENU = MenuSection::MenuProduct;


    public function getDAO() {
        return new ProductDAO($this->get('pdo'));
    }


    public function getNameForLogs($id) {
        return $this->getDAO()->getSingleField($id, 'name');
    }

    /**
     * Página de listado de productos
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $this->prepareList();

        // Carga selectores para filtros
        $areaTypeEntity = StaticListTable::getEntity(StaticListTable::Area);
        $areaTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $areaTypeEntity);

        $data['data'] = [
            'areas' => $areaTypeDAO->getForSelect()
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Prepara el formulario de crear/editar productos
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Obtenemos los valores a mostrar en los desplegables
        $areaTypeEntity = StaticListTable::getEntity(StaticListTable::Area);
        $areaTypeDAO = new StaticListDAO($this->get('pdo'), 'st_' . $areaTypeEntity);
        $data['data']['areas'] = $areaTypeDAO->getForSelect();

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function savePersist($request, $response, $args, &$formData) {
        parent::savePersist($request, $response, $args, $formData);
        $this->saveFile($request, $formData['id'], 'image_es_2', 'image_es_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_es_3', 'image_es_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_es_6', 'image_es_6', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_en_2', 'image_en_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_en_3', 'image_en_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_en_6', 'image_en_6', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_fr_2', 'image_fr_2', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_fr_3', 'image_fr_3', FileType::ProductImage);
        $this->saveFile($request, $formData['id'], 'image_fr_6', 'image_fr_6', FileType::ProductImage);
    }

    public function image(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $fileId = $this->getDAO()->getSingleField($args['id'], $args['field']);
        if (empty($fileId)) {
            throw new \Exception(__('app.error.file_not_found'), 404);
        }
        return parent::getFileById($response, $fileId, $args['field']);
    }
}
