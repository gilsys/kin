<?php

declare(strict_types=1);

namespace App\Util;

use App\Constant\Folders;
use Defuse\Crypto\File;
use Exception;

class FileUtils {

    const FILENAME_CACHE = 1;
    const FILENAME_NO_CACHE = 2;
    const FILENAME_HASH = 3;

    // Check if the same folder contains any file with the same id
    public static function deleteSameIdFiles($filepath, $id) {
        $files = glob($filepath . '*_' . $id . '.*');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public static function deleteSameNameFiles($filepath, $removeAll = false) {
        // Check if the same folder contains the same filename with different extension
        $pathinfo = pathinfo($filepath);
        $files = glob($pathinfo['dirname'] . '/' . $pathinfo['filename'] . '*.*');
        if (count($files) > 1 || $removeAll) {
            foreach ($files as $file) {
                $otherPathinfo = pathinfo($file);
                if ($otherPathinfo['basename'] != $pathinfo['basename']) {
                    unlink($file);
                }
            }
        }
    }

    public static function checkRemoveEmptyFile($request, $param) {
        return !empty($request->getParsedBody()[$param . '_removeemptyimage']);
    }

    public static function uploadFile($request, $subfolderId, $directory, $id, $param, $encrypt = false, $override = true, $uploadedFile = null, $deleteEmptyFiles = false) {
        if (!empty($subfolderId)) {
            $directory = $directory . DIRECTORY_SEPARATOR . $subfolderId;
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        if (empty($uploadedFile)) {
            $uploadedFiles = $request->getUploadedFiles();
            if (empty($uploadedFiles) || !isset($uploadedFiles[$param]) || $uploadedFiles[$param]->getError() !== UPLOAD_ERR_OK) {
                // Revisar si existe el parámetro para borrar la imagen
                if ($deleteEmptyFiles && self::checkRemoveEmptyFile($request, $param)) {
                    self::deleteSameNameFiles($directory . '/' . $param . '_' . $id . '.xxx', true);
                }
                return null;
            }
            $uploadedFile = $uploadedFiles[$param];
        }

        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $filename = self::getLocalFilepath($uploadedFile->getClientFilename(), $directory, $id, $param, $override);

            if ($encrypt) {
                File::encryptFileWithPassword($_FILES[$param]['tmp_name'], $filename, ENCRYPT_IMG_PASSWORD);
            } else {
                $uploadedFile->moveTo($filename);
                self::deleteSameNameFiles($filename);
            }
            if ($id === null) {
                return basename($filename);
            }

            return $uploadedFile->getClientFilename();
        }
        return null;
    }

    public static function saveFile($subfolderId, $directory, $id, $param, $fileName, $fileContent, $override = true) {
        if (!empty($subfolderId)) {
            $directory = $directory . DIRECTORY_SEPARATOR . $subfolderId;
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        $localFilename = self::getLocalFilepath($fileName, $directory, $id, $param, $override);

        file_put_contents($localFilename, $fileContent);
        self::deleteSameNameFiles($localFilename);

        return $localFilename;
    }

    public static function getDirectoryFromParamDir($app, $paramDir) {
        if ($paramDir == Folders::getPublicUpload()) {
            return $paramDir;
        }
        return $app->get('params')->getParam($paramDir);
    }

    public static function deleteFile($app, $subfolderId, $paramDir, $realFileName, $id = null, $field = null, $deleteSimilarFiles = false) {
        $directory = self::getDirectoryFromParamDir($app, $paramDir);

        if (!empty($subfolderId)) {
            $directory = $directory . DIRECTORY_SEPARATOR . $subfolderId;
        }

        $path = self::getLocalFilepath($realFileName, $directory, $id, $field);
        if ($deleteSimilarFiles) {
            self::deleteSameNameFiles($path);
        }
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public static function duplicateFile($app, $paramDir, $realFileName, $oldId, $newId, $field) {
        $directory = self::getDirectoryFromParamDir($app, $paramDir);
        $oldFilePath = self::getLocalFilepath($realFileName, $directory, $oldId, $field);
        if (file_exists($oldFilePath)) {
            $newFilePath = str_replace($field . '_' . $oldId, $field . '_' . $newId, $oldFilePath);
            return copy($oldFilePath, $newFilePath);
        }
        return false;
    }

    private static function getUniqueFilenamePath($directory, $filename) {
        if (!file_exists($directory . DIRECTORY_SEPARATOR . $filename)) {
            return $directory . DIRECTORY_SEPARATOR . $filename;
        }
        $filenameWithouExtension = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $i = 1;
        while (file_exists($directory . DIRECTORY_SEPARATOR . $filenameWithouExtension . $i . '.' . $extension)) {
            $i++;
        }
        return $directory . DIRECTORY_SEPARATOR . $filenameWithouExtension . $i . '.' . $extension;
    }

    public static function safeGetUploadedFile($request, $param, $extension) {
        if (self::getFileSize($request, $param) == 0) {
            throw new \Exception();
        }

        // Verificar que sea CSV
        if (strtolower(pathinfo($request->getUploadedFiles()[$param]->getClientFilename(), PATHINFO_EXTENSION)) != $extension) {
            throw new \Exception();
        }

        return $_FILES[$param]['tmp_name'];
    }

    public static function getLocalFilepath($realFilename, $directory, $id, $param, $override = true) {
        if (empty($id)) {
            if ($override === false) {
                return self::getUniqueFilenamePath($directory, $realFilename);
            }
            return $directory . DIRECTORY_SEPARATOR . $realFilename;
        }

        $basename = $param . '_' . $id;
        $extension = pathinfo($realFilename, PATHINFO_EXTENSION);
        $filename = sprintf('%s.%0.8s', $basename, $extension);
        return $directory . DIRECTORY_SEPARATOR . $filename;
    }

    public static function streamVideo($app, $response, $subfolderId, $paramDir, $realFileName, $id = null, $field = null) {
        $directory = self::getDirectoryFromParamDir($app, $paramDir);
        if (!empty($subfolderId)) {
            $directory = $directory . DIRECTORY_SEPARATOR . $subfolderId;
        }

        $path = self::getLocalFilepath($realFileName, $directory, $id, $field);

        if (!file_exists($path)) {
            return null;
        }

        (new VideoStream($path))->start();
    }

    public static function getFilepath($app, $subfolderId, $paramDir, $realFileName, $id, $field) {
        $directory = self::getDirectoryFromParamDir($app, $paramDir);
        if (!empty($subfolderId)) {
            $directory = $directory . DIRECTORY_SEPARATOR . $subfolderId;
        }
        $path = self::getLocalFilepath($realFileName, $directory, $id, $field);
        return $path;
    }

    public static function streamFile($app, $response, $subfolderId, $paramDir, $realFileName, $id = null, $field = null, $encrypt = false, $useCache = self::FILENAME_CACHE, $attachment = false, $filenameContentDisposition = null) {
        $path = self::getFilepath($app, $subfolderId, $paramDir, $realFileName, $id, $field);

        if ($encrypt) {
            $fileContent = self::decryptFileWithPassword($path);
        } else {
            if (file_exists($path) && is_file($path)) {
                $fileContent = @file_get_contents($path);
            } else {
                $fileContent = FALSE;
            }
        }
        if ($fileContent === FALSE) {
            return null;
        }

        if ($encrypt) {
            $response = ResponseUtils::withoutCache($response);
        } else if ($useCache == self::FILENAME_CACHE) {
            $response = ResponseUtils::fileWithCache($response, $path);
        }

        $response->getBody()->write($fileContent);

        $realFileNameInfo = pathinfo($realFileName);
        $contentDisposition = $attachment ? 'attachment' : 'inline';
        if (empty($filenameContentDisposition)) {
            if ($useCache == self::FILENAME_HASH) {
                $filenameContentDisposition = md5($realFileNameInfo['basename']) . '.' . $realFileNameInfo['extension'];
            } else {
                $filenameContentDisposition = $realFileName;
            }
        }

        $response = $response->withHeader('Content-Disposition', $contentDisposition . '; filename="' . $filenameContentDisposition . '"');

        if (in_array($realFileNameInfo['extension'], ['svg', 'svgz'])) {
            return $response->withHeader('Content-Type', 'image/svg+xml');
        }
        return $response->withHeader('Content-Type', mime_content_type($path));
    }

    private static function includeCacheAndHeaders($app, $path, $realFileName, $response) {

        $etag = md5_file($path);
        $lastModified = filemtime($path);

        header('Pragma: public');
        //header('Cache-Control: max-age=86400');
        //header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400 * 365));
        $response = $app->cache->withEtag($response, $etag);
        $response = $app->cache->withLastModified($response, $lastModified);

        $response = $response->withHeader('Content-Length', filesize($path));
        $response = $response->withHeader('Content-Disposition', 'inline; filename="' . $realFileName . '"');
        $response->withHeader('Content-Type', mime_content_type($path));

        $response = $response->withoutHeader('Cache-Control');
        $response = $response->withoutHeader('Pragma');
        $response = $response->withoutHeader('Expires');
        return $response;
    }

    private static function decryptFileWithPassword($inputPath) {
        try {
            $tmpFile = tmpfile();
            $metaDatas = stream_get_meta_data($tmpFile);
            File::decryptFileWithPassword($inputPath, $metaDatas['uri'], ENCRYPT_IMG_PASSWORD);
            $image = @file_get_contents($metaDatas['uri']);
            fclose($tmpFile);
            return $image;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getFileSize($request, $param) {
        $uploadedFiles = $request->getUploadedFiles();
        if (!isset($uploadedFiles[$param])) {
            return 0;
        }

        $uploadedFile = $uploadedFiles[$param];
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            return $uploadedFile->getSize() / 1024;
        }
        return 0;
    }

    public static function getFileSizeSaved($app, $paramDir, $realFileName, $id, $field) {
        $directory = self::getDirectoryFromParamDir($app, $paramDir);
        $path = self::getLocalFilepath($realFileName, $directory, $id, $field);
        if (file_exists($path)) {
            return filesize($path);
        }
        return null;
    }

    public static function deleteDirectoryContent($dir, $deleteDirectory = false) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir")
                        self::rrmdir($dir . "/" . $object, true);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            if ($deleteDirectory) {
                rmdir($dir);
            }
        }
    }

    public static function getBase64Image($image) {
        if (file_exists($image)) {
            return 'data:image/' . pathinfo($image, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($image));
        }
        return null;
    }

    public static function getFilesWithDate($folder, $id, $extension = 'pdf') {
        $files = [];
        foreach (glob($folder . '/' . $id . '_*.' . $extension) as $file) {
            $files[] = [
                'time' => filemtime($file),
                'date' => date('d/m/Y H:i:s', filemtime($file)),
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'path' => $file
            ];
        }

        usort($files, function ($item1, $item2) {
            return $item2['time'] <=> $item1['time'];
        });

        return $files;
    }

    public static function generateAvatar($response, $text, $color) {



        $svg = '<?xml version="1.0" encoding="utf-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="200" height="200">
                <circle cx="100" cy="100" r="100" style="fill-opacity: 0.1;" fill="' . $color . '" />
                <text x="50%" y="50%" text-anchor="middle" fill="' . $color . '" font-size="70px" font-family="Arial" dy=".32em">' . $text . '</text>
            </svg>';

        $response->getBody()->write($svg);
        return $response->withHeader('Content-Type', 'image/svg+xml');
    }

    public static function getImgPdf($img) {
        return 'data:image/' . pathinfo($img, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($img));
    }

    public static function getHashFileName($fileName) {
        $realFileNameInfo = pathinfo($fileName);
        return md5($realFileNameInfo['basename']) . '.' . $realFileNameInfo['extension'];
    }

    public static function streamJson($response, $json, $fileName, $attachment = true, $prettyPrint = true) {
        if ($prettyPrint) {
            $json = json_encode(json_decode($json), JSON_PRETTY_PRINT);
        }

        $response->getBody()->write($json);
        $contentDisposition = $attachment ? 'attachment' : 'inline';
        return $response->withHeader('Content-Disposition', $contentDisposition . '; filename="' . $fileName . '"')->withHeader('Content-Type', 'application/json');
    }

    public static function getFileSavePath($subfolderId, $directory, $id, $param, $fileName, $override = true) {
        if (!empty($subfolderId)) {
            $directory = $directory . DIRECTORY_SEPARATOR . $subfolderId;
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        return self::getLocalFilepath($fileName, $directory, $id, $param, $override);
    }

    public static function getFileUrlToken($id) {
        return md5(PASSWORD_SALT . strval($id));
    }

    public static function addFileUrls(&$array, $fields) {
        foreach ($array as &$item) {
            foreach ($fields as $field) {
                $item[$field] = '/file/' . $item[$field] . '/' . FileUtils::getFileUrlToken($item[$field]);
            }
        }
    }

    public static function checkValidUrl($id, $token) {
        return $token == md5(PASSWORD_SALT . $id);
    }

}
