<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\FileUtils;

class FileDAO extends BaseDAO
{

    public function __construct($connection)
    {
        parent::__construct($connection, 'st_file');
    }

    public function save($data)
    {
        $query = 'INSERT INTO ' . $this->table . ' (file_type_id, file) VALUES ' .
            '(:file_type_id, :file)';
        $this->query($query, $data);
        return $this->getLastInsertId();
    }

    public function getByProcessId($id)
    {
        $sql = "SELECT f.*
                FROM `" . $this->getTable() . "` f
                INNER JOIN st_file_process fp ON fp.file_id = f.id AND fp.process_id = :id";
        return $this->fetchRecord($sql, compact('id'));
    }

    public function deleteById($id, $hasCustomOrder = false, $deleteFiles = true)
    {
        if ($deleteFiles) {
            $fileTypeId = $this->getSingleField($id, 'file_type_id');
            $paramDAO = new ParamDAO($this->connection);
            $path = $paramDAO->getById('FOLDER_PRIVATE') . DIRECTORY_SEPARATOR . $fileTypeId . DIRECTORY_SEPARATOR;
            FileUtils::deleteSameIdFiles($path, $id);
        }
        parent::deleteById($id, $hasCustomOrder);
    }
}
