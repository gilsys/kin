<?php

declare(strict_types=1);

namespace App\Constant\App;

use App\Constant\StaticListTable;
use ReflectionClass;

class MenuSection {

    const MenuSettings = 'M';
    const MenuUsers = 'U';
    const MenuUserData = 'UD';
    const MenuUserLogs = 'UL';
    const MenuLocalitzation = 'ML';
    const MenuStates = 'MLS';
    const MenuCities = 'MLT';
    const MenuCommunities = 'MLC';
    const MenuClientType = 'MA' . StaticListTable::ClientType;
    const MenuClients = 'C';
    const MenuClientData = 'CD';
    const MenuClientProcesses = 'CP';
    const MenuDashboard = 'D';
    const MenuProcess = 'P';
    const MenuProcessType = 'MP';
    const MenuProcessStatus = 'MQ' . StaticListTable::ProcessStatus;
    const MenuProfile = 'F';
    const MenuTasks = 'T';
    const MenuTaskType = 'MT' . StaticListTable::TaskType;
    const MenuTaskStatus = 'MS' . StaticListTable::TaskStatus;
    const MenuTaskPaymentStatus = 'MR' . StaticListTable::TaskPaymentStatus;
    const MenuAdmin = 'A';
    const MenuAdminData = 'AD';
    const MenuAdminLogs = 'AL';

    private static function constantExists($dynamicConstant) {
        $fin = new ReflectionClass(__CLASS__);
        $arrConst = $fin->getConstants();
        $arrConstValues = array_values($arrConst);
        return in_array($dynamicConstant, $arrConstValues);
    }

    public static function getLocationSubmenu() {
        return [
            ['section' => MenuSection::MenuCommunities, 'title' => 'app.communities.title', 'url' => '/app/communities'],
            ['section' => MenuSection::MenuStates, 'title' => 'app.states.title', 'url' => '/app/states'],
            ['section' => MenuSection::MenuCities, 'title' => 'app.cities.title', 'url' => '/app/cities'],
        ];
    }

    public static function getMaintenanceMenu($staticTable) {
        $sections = ['MA', 'MP', 'MT', 'MQ', 'MS', 'MR'];
        foreach ($sections as $section) {
            if (self::constantExists($section . $staticTable)) {
                return $section . $staticTable;
            }
        }
        throw new \Exception();
    }

}
