<?php

declare(strict_types=1);

namespace App\Dao;

use App\Util\CommonUtils;

class TagDAO extends BaseDAO {

    public function __construct($connection) {
        parent::__construct($connection, 'st_tag');
    }

    public function getAllByTask($taskId) {
        $sql = '
            SELECT *
            FROM ' . $this->table . " tag
            INNER JOIN `st_task_tag` ttag ON ttag.tag_id = tag.id
            WHERE ttag.task_id = :taskId";
        return $this->fetchAll($sql, compact('taskId'));
    }

    public function getTagNameByTaskAsString($taskId) {
        $sql = '
            SELECT name
            FROM ' . $this->table . " tag
            INNER JOIN `st_task_tag` ttag ON ttag.tag_id = tag.id
            WHERE ttag.task_id = :taskId";
        $result = $this->fetchAll($sql, compact('taskId'));
        $resultString = '';
        foreach ($result as $innerArray) {
            $resultString .= $innerArray['name'] . ', ';
        }
        $resultString = rtrim($resultString, ', ');
        return $resultString;
    }

    public function getExistingTags($tagNames) {
        $sql = '
        SELECT 
        *
        FROM ' . $this->table . ' 
        WHERE FIND_IN_SET(name, :tag_names)';

        return $this->fetchAll($sql, ['tag_names' => implode(',', $tagNames)]);
    }

    public function getTagsByProcessId($processId) {
        $sql = "SELECT DISTINCT
            tag.id, 
            tag.name
        FROM " . $this->table . " tag
        INNER JOIN `st_task_tag` ttag ON ttag.tag_id = tag.id 
        INNER JOIN `st_task` t ON t.id = ttag.task_id
        WHERE t.process_id = :processId";
        return $this->fetchAll($sql, compact('processId'));
    }

    public function getExistingTagsNotAnyTask($tagNames) {
        $sql = '
        SELECT 
        tag.*
        FROM ' . $this->table . ' tag
        LEFT JOIN st_task_tag AS ttag ON ttag.tag_id = tag.id
        WHERE FIND_IN_SET(name, :tag_names) AND ttag.tag_id IS NULL';

        return $this->fetchAll($sql, ['tag_names' => implode(',', $tagNames)]);
    }

    public function getExistingTagsNotInTask($tagNames, $taskId) {
        $sql = '
        SELECT 
        tag.*
        FROM ' . $this->table . ' tag
        LEFT JOIN st_task_tag AS ttag ON ttag.tag_id = tag.id AND ttag.task_id = :task_id
        WHERE FIND_IN_SET(name, :tag_names) AND ttag.tag_id IS NULL';

        return $this->fetchAll($sql, ['tag_names' => implode(',', $tagNames), 'task_id' => $taskId]);
    }

    public function getTagsAlreadyInStorage($tagsToStore) {
        $sql = 'SELECT name FROM ' . $this->table . ' WHERE FIND_IN_SET(name, :tag_names)';
        return $this->fetchAll($sql, ['tag_names' => implode(',', $tagsToStore)]);
    }

    public function deleteUselessTags() {
        $query = "DELETE 
        FROM st_tag
        WHERE NOT EXISTS (SELECT 1 FROM st_task_tag WHERE st_task_tag.tag_id = st_tag.id)";
        return $this->query($query);
    }

    public function save($tagName) {
        $query = 'INSERT IGNORE INTO ' . $this->table . ' (name) '
            . 'VALUES (:name)';
        $data = ['name' => $tagName];
        $this->query($query, $data);

        return $this->getLastInsertId();
    }
}
