<?php

declare(strict_types=1);

namespace App\Dao;

class StatusEventVariablesDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_status_event_variables');
    }

}


