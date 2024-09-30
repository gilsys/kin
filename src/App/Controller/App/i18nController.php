<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Controller\App\BaseController;
use App\Util\ResponseUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class i18nController extends BaseController {

    public function getDAO() {
    }

    public function getNameForLogs($entity) {
    }

    public function i18n(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $lastModifiedTime = $this->get('i18n')->getTranslationsFileLastModified();
        $etag = $this->get('i18n')->getTranslationsFileEtag();
        $response = ResponseUtils::withCache($response, $lastModifiedTime, $etag);
        $translations = $this->get('i18n')->getTranslationsStartingWith(['app.js.', 'js.jquery_validate.', 'table.', 'js.lang.']);
        return ResponseUtils::withJson($response, $translations);
    }

}
