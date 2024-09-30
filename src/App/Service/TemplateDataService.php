<?php

declare(strict_types=1);

namespace App\Service;

class TemplateDataService extends BaseService {

    private $session;
    private $params;

    public function __construct($pdo = null, $session = null, $params = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
    }

    /**
     * Obtiene datos a utilizar en TODOS los templates de la plataforma
     */
    public function fetchCommonData() {
        $data = [];        
        $data['jsFromSession'] = $this->getJsFromSession();
        return $data;
    }

    private function getJsFromSession() {
        if (empty($this->session) || empty($this->session['jsFromSession'])) {
            return null;
        }
        $jsFromSession = $this->session['jsFromSession'];
        $this->session->delete('jsFromSession');
        return $jsFromSession;
    }
}
