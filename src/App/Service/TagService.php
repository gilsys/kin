<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\UserProfile;
use App\Dao\TagDAO;
use App\Dao\TaskTagDAO;
use App\Dao\VisitDAO;
use App\Exception\AuthException;

class TagService extends BaseService
{

    public function extractTagsFromInput($input)
    {
        $tags = json_decode($input, true);

        $tagValues = array_map(function ($tag) {
            return $tag['value'];
        }, $tags);
        return $tagValues;
    }

    public function checkAndSaveNewTags($tags)
    {
        $tagDAO = new TagDAO($this->get('pdo'));
        $existingTags = $tagDAO->getExistingTags($tags);

        $existingTags = array_map(function ($tag) {
            return $tag['name'];
        }, $existingTags);

        $newTags = array_diff($tags, $existingTags);
        foreach ($newTags as $tag) {
            $tagDAO->save($tag);
        }
    }

    public function checkExistingTags($tags)
    {
        $tagDAO = new TagDAO($this->get('pdo'));
        $existingTags = $tagDAO->getExistingTags($tags);

        $existingTags = array_map(function ($tag) {
            return $tag['name'];
        }, $existingTags);

        return array_diff($tags, $existingTags);
    }

    public function compareInputTagsToExistingInTask($tagsToDelete, $taskId)
    {
        $tagDAO = new TagDAO($this->get('pdo'));
        $existingTags = $tagDAO->getAllByTask($taskId);

        $deletedTags = array_filter($existingTags, function ($tag) use ($tagsToDelete) {
            return !in_array($tag['name'], $tagsToDelete);
        });

        return array_values($deletedTags);
    }

    public function saveTags($tags)
    {
        $tagDAO = new TagDAO($this->get('pdo'));
        $tagIds = [];

        foreach ($tags as $tag) {
            $tagIds[] = $tagDAO->save($tag);
        }
        return $tagIds;
    }

    public function saveTagsToTask($tags, $taskId)
    {
        $taskTagDAO = new TaskTagDAO($this->get('pdo'));
        $tagIds = array_map(function ($tag) {
            return $tag['id'];
        }, $tags);

        foreach ($tagIds as $tagId) {
            $tagData = [
                'task_id' => $taskId,
                'tag_id' => $tagId,
            ];
            $taskTagDAO->save($tagData);
        }
    }

    public function deleteRemovedTagsFromTask($deletedTags, $taskId)
    {
        $tagDAO = new TagDAO($this->get('pdo'));
        $taskTagDAO = new TaskTagDAO($this->get('pdo'));

        if (empty($deletedTags)) return;
        foreach ($deletedTags as $tag) {
            $taskTagDAO->deleteTaskTag($tag['id'], $taskId);
        }
        $tagDAO->deleteUselessTags();
    }
}
