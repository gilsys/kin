<?php

declare(strict_types=1);

namespace App\Dao;

class UserProfileDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_user_profile');
    }

}


