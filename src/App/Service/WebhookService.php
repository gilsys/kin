<?php

declare(strict_types=1);

namespace App\Service;

class WebhookService extends BaseService {

    private $session;
    private $params;

    public function __construct($pdo = null, $session = null, $params = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
    }

    public function init() {
        
    }

    public function triggerZapierWebhook($data, $zapierWebhookUrl = null) {

        if(!$this->params->getParam('ZAPIER.ENABLED')) return;

        $jsonData = json_encode($data);

        if (empty($zapierWebhookUrl)) {
            return;
        }

        $ch = curl_init(trim($zapierWebhookUrl));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        $responseJson = json_decode($response, true);
        /*
          if (isset($responseJson['status']) && $responseJson['status'] === 'success') {
          echo "The Zap was triggered successfully.";
          } else {
          echo "Error: The Zap was not triggered. Response: " . $response;
          }
         * 
         */
    }

}
