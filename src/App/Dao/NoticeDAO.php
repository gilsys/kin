<?php

declare(strict_types=1);

namespace App\Dao;

use App\Constant\BudgetStatus;
use App\Util\CommonUtils;

class NoticeDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_notice');
    }

    public function getRemoteDatatable($clientId, $statusEventIds) {
        //Columnas a tratar en el datatable
        $columns = [
            ['db' => 'id', 'dt' => 'id', 'exact' => true],
            ['db' => 'notice_template_id', 'dt' => 'notice_template_id', 'exact' => true],
            ['db' => 'budget_id', 'dt' => 'budget_id', 'exact' => true],
            ['db' => 'name', 'dt' => 'name'],
            ['db' => 'budget_name', 'dt' => 'budget_name'],
            ['db' => 'signature_error', 'dt' => 'signature_error'],
            ['db' => 'sign_request', 'dt' => 'sign_request'],
            ['db' => 'date_created', 'dt' => 'date_created', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            ['db' => 'date_updated', 'dt' => 'date_updated', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
            ['db' => 'date_signed', 'dt' => 'date_signed', 'date' => true,
                'formatter' => function ($d, $row) {
                    return CommonUtils::convertDate($d);
                }
            ],
        ];

        $table = ' (
            SELECT
                n.id,
                nt.name,
                n.date_created,
                n.date_updated,
                n.date_signed,
                n.notice_template_id,
                CASE
                    WHEN signature_token IS NOT NULL THEN "1"
                    ELSE "0"
                END as sign_request,
                CASE
                    WHEN signature_error IS NOT NULL THEN "1"
                    ELSE "0"
                END as signature_error,
                n.budget_id,
                b.name as budget_name
            FROM '
                . $this->table . ' n
            INNER JOIN st_notice_template nt ON nt.id = n.notice_template_id AND nt.status_event_id IN (' . implode(',', array_map('intval', $statusEventIds)) . ')
            LEFT JOIN st_budget b ON b.id = n.budget_id
            WHERE 
                n.client_id = ' . intval($clientId) . ' AND (b.id IS NULL OR b.budget_status_id = "' . BudgetStatus::Accepted . '") 
         ) temp';

        return $this->datatablesSimple($table, 'id', $columns);
    }

    public function save($data) {
        $query = 'INSERT INTO ' . $this->table . ' (creator_user_id, notice_template_id, email_subject, email_content, content, client_id) VALUES ' .
                '(:creator_user_id, :notice_template_id, :email_subject, :email_content, :content, :client_id)';
        $this->query($query, $data);
        return $this->getLastInsertId();
    }

}
