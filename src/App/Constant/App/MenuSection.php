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
    const MenuDashboard = 'D';
    const MenuProfile = 'F';
    const MenuAdmin = 'A';
    const MenuAdminData = 'AD';
    const MenuAdminLogs = 'AL';

    const MenuMarket = 'MM';

    const MenuProduct = 'P';
    const MenuCustomProduct = 'CP';
    const MenuSubProduct = 'SP';

    const MenuBooklet = 'BB';
    const MenuFlyer = 'BF';

    const MenuRecipe = 'R';


    private static function constantExists($dynamicConstant) {
        $fin = new ReflectionClass(__CLASS__);
        $arrConst = $fin->getConstants();
        $arrConstValues = array_values($arrConst);
        return in_array($dynamicConstant, $arrConstValues);
    }

    public static function getMaintenanceMenu($staticTable) {
        $sections = ['M'];
        foreach ($sections as $section) {
            if (self::constantExists($section . $staticTable)) {
                return $section . $staticTable;
            }
        }
        throw new \Exception();
    }
}
