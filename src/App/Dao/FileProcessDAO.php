<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\FileUtils;

class FileProcessDAO extends BaseDAO
{

    public function __construct($connection)
    {
        parent::__construct($connection, 'st_file_process');
    }

    public function getFilesByProcessId($processId)
    {
        $query = 'SELECT f.file, f.id
              FROM ' . $this->table . ' fp
              INNER JOIN st_file f ON fp.file_id = f.id
              WHERE fp.process_id = :processId';
        return $this->fetchAll($query, compact('processId'));
    }

    public function save($processId, $fileId)
    {
        $query = 'INSERT INTO ' . $this->table . ' (process_id, file_id) VALUES ' .
            '(:processId, :fileId)';
        $this->query($query, compact('processId', 'fileId'));
        return $this->getLastInsertId();
    }

    public function deleteByFileId($fileId)
    {
        $query = 'DELETE FROM ' . $this->table . ' WHERE file_id = :fileId';
        $this->query($query, compact('fileId'));
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
