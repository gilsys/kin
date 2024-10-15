<?php

declare(strict_types=1);

namespace App\Dao;

class BookletLayoutDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_booklet_layout');
    }
}
