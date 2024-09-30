<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Dao\UserDAO;
use App\Util\FileUtils;
use App\Util\SessionUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FileController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    public function avatar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $userDAO = new UserDAO($this->get('pdo'));
        $user = $userDAO->getById($args['id']);
        if (empty($user)) {
            throw new \Exception(__('file_not_found'));
        }
        $response = FileUtils::streamFile($this, $response, 'user', 'FOLDER_PRIVATE', $user['avatar'], $args['id'], 'avatar');
        if (!empty($response)) {
            return $response;
        }
        throw new \Exception(__('app.error.file_not_found'), 404);
    }

    public function token(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $explodedToken = explode('.', $args['token']);
        $token = reset($explodedToken);
        $data = SessionUtils::getSessionData($token);
        if (empty($data)) {
            throw new \Exception(__('file_not_found'));
        }

        $file = $data['data']['file'];        
        $response = FileUtils::streamFile($this, $response, $file['file_type_id'], 'FOLDER_PRIVATE', $file['file'], $file['id'], 'file', false, FileUtils::FILENAME_HASH, false);
        if (!empty($response)) {
            return $response;
        }
        throw new \Exception(__('app.error.file_not_found'), 404);
    }

}
