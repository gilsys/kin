<?php

declare(strict_types=1);

namespace App\Util;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ResponseUtils {

    public static function withJSON($response, $jsonArray) {
        if (!empty($jsonArray) && is_array($jsonArray)) {
            array_walk_recursive($jsonArray, function (&$item, $key) {
                if (is_numeric($item)) {
                    $item = (string) $item;
                }
            });
        }
        $payload = json_encode($jsonArray);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function fileWithCache($response, $file) {
        $lastModifiedTime = filemtime($file);
        $etag = md5_file($file);
        return ResponseUtils::withCache($response, $lastModifiedTime, $etag);
    }

    public static function withoutCache($response) {
        $response = $response->withHeader('Pragma-directive', 'no-cache');
        $response = $response->withHeader('Cache-directive', 'no-cache');
        $response = $response->withHeader('Cache-control', 'no-store');
        $response = $response->withHeader('Pragma', 'no-cache');
        $response = $response->withHeader('Expires', '0');

        return $response;
    }

    public static function withCacheSeconds($response, $seconds) {
        $ts = gmdate("D, d M Y H:i:s", time() + $seconds) . " GMT";
        return $response
            ->withHeader('Cache-Control', "max-age=" . $seconds)
            ->withHeader('Pragma', 'cache')
            ->withHeader('Expires', $ts);
    }

    public static function withCache($response, $lastModifiedTime, $etag) {
        if (
            (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lastModifiedTime) ||
            (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)
        ) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }

        return $response
            ->withHeader('Cache-Control', 'public')
            ->withHeader('Last-Modified', gmdate("D, d M Y H:i:s", $lastModifiedTime) . " GMT")
            ->withHeader('Etag', $etag);
    }

    public static function withExcel($objExcel, $fileName) {
        header('Content-Type: text/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($objExcel, 'Xlsx');
        $writer->save('php://output');
        exit();
    }

    public static function withCsv($response, $data, $fileName, $delimiter = ';') {
        $handle = fopen('php://temp', 'rw+');
        // Agregar BOM para UTF-8
        fwrite($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        foreach ($data as $row) {
            fputcsv($handle, $row, $delimiter);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);

        $response->getBody()->write($csv);
        return $response->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '.csv"');
    }
}
