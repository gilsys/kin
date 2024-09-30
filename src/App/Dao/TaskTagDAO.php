<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class TaskTagDAO extends BaseDAO
{

    public function __construct($connection)
    {
        parent::__construct($connection, 'st_task_tag');
    }

    public function save($data)
    {
        $query = 'INSERT INTO ' . $this->table . ' (task_id, tag_id) '
            . 'VALUES (:task_id, :tag_id)';
        $this->query($query, $data);
        return $this->getLastInsertId();
    }

    public function getTagsByTaskId($taskId)
    {
        $sql = "SELECT 
        tag.name as value
        FROM " . $this->table . " ttag
        INNER JOIN `st_tag` tag ON tag.id = ttag.tag_id 
        WHERE ttag.task_id = :taskId";

        return $this->fetchAll($sql, compact('taskId'));
    }

    public function getTagsWithSameProcessTask($taskId)
    {
        $sql = "SELECT DISTINCT
        tag.name as value
        FROM " . $this->table . " ttag
        INNER JOIN `st_tag` tag ON tag.id = ttag.tag_id 
        INNER JOIN `st_task` t ON t.id = ttag.task_id
        WHERE t.process_id IN (SELECT process_id FROM st_task WHERE id = :taskId)";
        return $this->fetchAll($sql, compact('taskId'));
    }

    public function getTagsForProcess($processId)
    {
        $sql = "SELECT DISTINCT
        tag.name as value
        FROM " . $this->table . " ttag
        INNER JOIN `st_tag` tag ON tag.id = ttag.tag_id 
        INNER JOIN `st_task` t ON t.id = ttag.task_id
        WHERE t.process_id = :processId";
        return $this->fetchAll($sql, compact('processId'));
    }

    public function deleteTaskTag($tagId, $taskId)
    {
        $query = 'DELETE FROM ' . $this->table . ' WHERE tag_id = :tagId AND task_id = :taskId';
        $this->query($query, compact('tagId', 'taskId'));
    }

    public function deleteTaskTagByTaskId($taskId) {
        $query = 'DELETE FROM `' . $this->table . '` WHERE task_id = :taskId';
        $this->query($query, compact('taskId'));
    }
}
