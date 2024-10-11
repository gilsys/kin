<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Constant\App\MenuSection;
use App\Constant\FileType;
use App\Constant\StaticListTable;
use App\Constant\UserProfile;
use App\Dao\BookletDAO;
use App\Dao\MarketDAO;
use App\Dao\StaticListDAO;
use App\Dao\UserDAO;
use App\Exception\AuthException;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
            'languages' => $languageDAO->getForSelect(),
            'users' => $userDAO->getForSelectFullname()
        ];

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

        $data['data']['markets'] = $marketDAO->getForSelect();

        $data['data']['languages'] = $languageDAO->getForSelect();

        return $this->get('renderer')->render($response, "main.phtml", $data);
    }
};
