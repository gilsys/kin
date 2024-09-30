<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\App\Device;
use App\Dao\LogDAO;
use App\Util\CommonUtils;
use Mobile_Detect;

class LogService {

    public static function save($container, $translationId, $translationVariables, $table = '', $tableId = '', $userId = null) {
        $message = __($translationId, $translationVariables);
        $container->get('logger')->addInfo('Table: ' . $table . ' - Id: ' . $tableId . ' Message: ' . $message);

        $logDAO = new LogDAO($container->get('pdo'));
        $userId = empty($userId) ? $container->get('session')['user']['id'] : $userId;
        $logDAO->save(['user_id' => $userId, 'translation_id' => $translationId, 'variables' => json_encode($translationVariables), 'table' => $table, 'table_id' => $tableId]);
    }

    public static function saveAuth($container, $translationId, $userId, $app = false) {
        $detect = new Mobile_Detect;

        if ($app) {
            $deviceId = Device::App;
        } else if ($detect->isTablet()) {
            $deviceId = Device::Tablet;
        } else if ($detect->isMobile()) {
            $deviceId = Device::Mobile;
        } else {
            $deviceId = Device::Desktop;
        }

        $translationVariables = ["ip" => CommonUtils::getIpAddress(), "device" => $deviceId, "userAgent" => !$app ? $detect->getUserAgent() : ''];
        $logDAO = new LogDAO($container->get('pdo'));
        $logDAO->save(['user_id' => $userId, 'translation_id' => $translationId, 'variables' => json_encode($translationVariables), 'table' => 'auth', 'table_id' => $userId]);
    }

}
