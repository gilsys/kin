<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\SubProductStatus;
use App\Dao\ProductDAO;
use App\Dao\SubProductDAO;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SubProductController extends BaseController {

    const ENTITY_SINGULAR = 'subproduct';
    const ENTITY_PLURAL = 'subproducts';
    const MENU = MenuSection::MenuSubProduct;


    public function getDAO() {
        return new SubProductDAO($this->get('pdo'));
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
        $productDAO = new ProductDAO($this->get('pdo'));

        $data['data'] = [
            'products' => $productDAO->getForSelect('id', 'name', 'id', [$this->get('params')->getParam('EMPTY_PRODUCT')]),
            'subProductStatus' => SubProductStatus::getAll()
        ];

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    /**
     * Obtiene los datos para mostrar la datatable
     */
    public function datatable(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        return ResponseUtils::withJson($response, $this->getDAO()->getRemoteDatatable($this->get('i18n')->getCurrentLang()));
    }

    /**
     * Prepara el formulario de crear/editar productos
     */
    public function form(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $data = $this->prepareForm($args);

        // Carga selectores para filtros
        $productDAO = new ProductDAO($this->get('pdo'));

        $data['data']['products'] =  $productDAO->getForSelect('id', 'name', 'id', [$this->get('params')->getParam('EMPTY_PRODUCT')]);

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }

    public function savePreSave($request, $response, $args, &$formData) {
        foreach (['name', 'format'] as $jsonField) {
            $formData[$jsonField] = json_encode($formData[$jsonField]);
        }
    }

    public function deletePreDelete($request, $response, $args, &$formData) {
        $this->getDAO()->updateSingleField($formData['id'], 'date_deleted', date('Y-m-d H:i:s'));
        return true;
    }
}
