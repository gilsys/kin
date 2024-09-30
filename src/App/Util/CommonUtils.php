<?php

declare(strict_types=1);

namespace App\Util;

use DateTime;
use DateTimeZone;
use NumberFormatter;
use function __;
use function mb_strlen;
use function mb_strrpos;
use function mb_substr;
use function Opis\Closure\serialize;
use function Opis\Closure\unserialize;
use function str_contains;

class CommonUtils {

    public static function startsWith($string, $startString) {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    public static function replaceFirst($search, $replace, $subject) {
        $search = '/' . preg_quote($search, '/') . '/';
        return preg_replace($search, $replace, $subject, 1);
    }

    public static function sanatizeString($string) {
        return filter_var(trim($string), FILTER_SANITIZE_STRING);
    }

    public static function recursiveRemoveKeyContainingText(&$array, $text) {
        if (is_array($array)) {
            foreach ($array as $k => &$v) {
                if (is_string($k) && str_contains($k, $text)) {
                    unset($array[$k]);
                }
                self::recursiveRemoveKeyContainingText($v, $text);
            }
        }
    }

    public static function emptyStringsToNull(&$arrayParams) {
        foreach ($arrayParams as $key => $value) {
            if ($value == '') {
                $arrayParams[$key] = null;
            }
        }
        return $arrayParams;
    }

    public static function getSanitizedData($request, $filter = false) {
        $data = $request->getParsedBody();
        unset($data['csrf_name']);
        unset($data['csrf_value']);
        self::recursiveRemoveKeyContainingText($data, '_removeemptyimage');
        if ($filter) {
            foreach ($data as $k => $v) {
                $data[$k] = filter_var($data[$k], FILTER_SANITIZE_STRING);
            }
        }

        return $data;
    }

    public static function getBaseUrl() {
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    }

    public static function getCurrentUrl($excludeParams = false) {
        $url = CommonUtils::getBaseUrl() . $_SERVER['REQUEST_URI'];
        if ($excludeParams) {
            $url = strtok($url, '?');
        }
        return $url;
    }

    public static function isBackoffice() {
        return (preg_match("/\/app/", $_SERVER['REQUEST_URI']));
    }

    public static function generateRandomToken($seed, $maxlength = null) {
        $token = md5(uniqid((string) $seed, true));
        if (!empty($maxlength)) {
            return substr($token, 0, $maxlength);
        }
        return $token;
    }

    public static function isLocalhost($whitelist = ['127.0.0.1', '::1']) {
        return in_array($_SERVER['REMOTE_ADDR'], $whitelist);
    }

    public static function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    public static function hashSecure($times, $p1, $p2, $p3) {
        $fSecure = $p1 . $p2 . $p3;

        for ($zo = 0; $zo < $times; $zo++) {
            $fSecure = hash('sha256', PV_KEY . $fSecure);
        }


        return $fSecure;
    }

    public static function checkPasswordRequirements($password) {
        return
                strlen($password) >= 8 && // enforce length
                preg_match('/[a-z]/', $password) && // contains lowercase
                preg_match('/[A-Z]/', $password) && // contains uppercase
                preg_match('/[0-9]/', $password) && // contains digit
                preg_match('/[^a-zA-Z0-9]/', $password); // contains symbol
    }

    public static function generateValidPassword($length) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-=+?";
        do {
            $password = substr(str_shuffle($chars), 0, $length);
        } while (!CommonUtils::checkPasswordRequirements($password));
        return $password;
    }

    public static function dateOrNull(&$data, $fieldName) {
        if (!empty($data[$fieldName])) {
            return 'STR_TO_DATE(:' . $fieldName . ', \'%d/%m/%Y\')';
        } else {
            unset($data[$fieldName]);
            return 'null';
        }
    }

    public static function convertDate($d, $format = 'd-m-Y H:i:s') {
        return (!empty($d)) ? date($format, strtotime($d)) : '';
    }

    public static function datetimeOrNull(&$data, $fieldName) {
        if (!empty($data[$fieldName])) {
            if ($data[$fieldName][4] == '-') {
                return ':' . $fieldName;
            }
            return 'STR_TO_DATE(:' . $fieldName . ', \'%d/%m/%Y %H:%i\')';
        } else {
            unset($data[$fieldName]);
            return 'null';
        }
    }

    public static function filterDateRange(&$data, $start, $end, $fieldStart = null, $fieldEnd = null) {
        $fieldStart = !empty($fieldStart) ? $fieldStart : $start;
        $fieldEnd = !empty($fieldEnd) ? $fieldEnd : $end;

        if (!empty($data[$start]) && strpos($data[$start], ':') === false) {
            $data[$start] .= ' 00:00:00';
        }

        if (!empty($data[$end]) && strpos($data[$end], ':') === false) {
            $data[$end] .= ' 23:59:59';
        }

        $dateStart = CommonUtils::datetimeOrNull($data, $start);
        $dateEnd = CommonUtils::datetimeOrNull($data, $end);

        $sql = ' AND (' . $fieldStart . ' < ' . $dateEnd . ' AND ' . $fieldEnd . ' >= ' . $dateStart . ') ';

        return $sql;
    }

    public static function filterDate(&$data, $start, $end, $field) {
        if (!empty($data[$start]) && strpos($data[$start], ':') === false) {
            $data[$start] .= ' 00:00:00';
        }

        if (!empty($data[$end]) && strpos($data[$end], ':') === false) {
            $data[$end] .= ' 23:59:59';
        }

        $dateStart = CommonUtils::datetimeOrNull($data, $start);
        $dateEnd = CommonUtils::datetimeOrNull($data, $end);

        $sql = ' AND (' . $field . ' >= ' . $dateStart . ' AND ' . $field . ' <= ' . $dateEnd . ') ';

        return $sql;
    }

    public static function generateRandString($length) {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        return substr(str_shuffle($chars), 0, $length);
    }

    public static function underscoreToCamelCase($string, $capitalizeFirstCharacter = true) {

        $str = str_replace('_', '', ucwords($string, '_'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    public static function camelCaseToUnderscore($string, $separator = '_') {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', $separator . '$0', $string));
    }

    public static function truncate($text, $length = 100, $options = array()) {
        $text = is_null($text) ? '' : $text;
        $default = array(
            'ending' => '...', 'exact' => true, 'html' => false
        );
        $options = array_merge($default, $options);
        extract($options);

        if ($html) {
            if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }
            $totalLength = mb_strlen(strip_tags($ending));
            $openTags = array();
            $truncate = '';

            preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
            foreach ($tags as $tag) {
                if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
                    if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
                        array_unshift($openTags, $tag[2]);
                    } else if (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
                        $pos = array_search($closeTag[1], $openTags);
                        if ($pos !== false) {
                            array_splice($openTags, $pos, 1);
                        }
                    }
                }
                $truncate .= $tag[1];

                $contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));
                if ($contentLength + $totalLength > $length) {
                    $left = $length - $totalLength;
                    $entitiesLength = 0;
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
                        foreach ($entities[0] as $entity) {
                            if ($entity[1] + 1 - $entitiesLength <= $left) {
                                $left--;
                                $entitiesLength += mb_strlen($entity[0]);
                            } else {
                                break;
                            }
                        }
                    }

                    $truncate .= mb_substr($tag[3], 0, $left + $entitiesLength);
                    break;
                } else {
                    $truncate .= $tag[3];
                    $totalLength += $contentLength;
                }
                if ($totalLength >= $length) {
                    break;
                }
            }
        } else {
            if (mb_strlen($text) <= $length) {
                return $text;
            } else {
                $truncate = mb_substr($text, 0, $length - mb_strlen($ending));
            }
        }
        if (!$exact) {
            $spacepos = mb_strrpos($truncate, ' ');
            if (isset($spacepos)) {
                if ($html) {
                    $bits = mb_substr($truncate, $spacepos);
                    preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                    if (!empty($droppedTags)) {
                        foreach ($droppedTags as $closingTag) {
                            if (!in_array($closingTag[1], $openTags)) {
                                array_unshift($openTags, $closingTag[1]);
                            }
                        }
                    }
                }
                $truncate = mb_substr($truncate, 0, $spacepos);
            }
        }

        if ($html) {
            foreach ($openTags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
            $truncate .= '<span>' . $ending . '</span>';
        } else {
            $truncate .= $ending;
        }

        return $truncate;
    }

    public static function arrayChangeKeyCaseRecursive($array, $case) {
        $mutated = [];
        $mutator = $case === CASE_LOWER ? 'strtolower' : 'strtoupper';

        foreach ($array as $key => $value) {
            // Mutate string keys
            if (is_string($key)) {
                $key = $mutator($key);
            }

            // Mutate array values
            if (is_array($value)) {
                $value = self::arrayChangeKeyCaseRecursive($value, $case);
            }

            $mutated[$key] = $value;
        }

        return $mutated;
    }

    public static function checkActive($menu, $section, $class = 'active') {
        if (empty($menu)) {
            return;
        }
        echo CommonUtils::startsWith($menu, $section) ? $class : '';
    }

    public static function openAndCloseTags($html) {
        $html = str_replace('<br>', '<br/>', $html);

        preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        $openedtags = $result[1];
        preg_match_all('#</([a-z]+)>#iU', $html, $result);

        $closedtags = $result[1];
        $len_opened = count($openedtags);

        if (count($closedtags) == $len_opened) {
            return ['', ''];
        }

        $htmlClose = '';
        $htmlReopen = '';

        $openedtags = array_reverse($openedtags);
        for ($i = 0; $i < $len_opened; $i++) {
            if (!in_array($openedtags[$i], $closedtags)) {
                $htmlClose .= '</' . $openedtags[$i] . '>';
                $htmlReopen = '<' . $openedtags[$i] . '>' . $htmlReopen;
            } else {
                unset($closedtags[array_search($openedtags[$i], $closedtags)]);
            }
        }

        return [$htmlClose, $htmlReopen];
    }

    public static function encodeUrlParam($in) {
        return base64_encode(rawurlencode(serialize($in)));
    }

    public static function decodeUrlParam($in) {
        return unserialize(rawurldecode(base64_decode($in)));
    }

    public static function getDecodeParam($request, $param) {
        return !empty(RequestUtils::getParam($request, $param)) ? CommonUtils::decodeUrlParam(RequestUtils::getParam($request, $param)) : '';
    }

    public static function getDurationHours($duration) {
        $duration = str_replace('.', ',', strval(floatval($duration)));
        return $duration == 1 ? __('app.common.duration_hour', $duration) : __('app.common.duration_hours', $duration);
    }

    public static function getArrayElements(&$array, $keys) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = !empty($array[$key]) ? $array[$key] : null;
            unset($array[$key]);
        }
        return $result;
    }

    public static function arrayEmptyValues(&$array, $keys) {
        foreach ($array as $k => &$v) {
            if (in_array($k, $keys)) {
                $v = null;
            }
        }
    }

    public static function arrayNotEmptyValues(&$array, $keys) {
        foreach ($array as $k => &$v) {
            if (!in_array($k, $keys)) {
                $v = null;
            }
        }
    }

    public static function splitName($name) {
        $name = trim($name);
        $parts = explode(" ", $name);
        if (count($parts) > 1) {
            $firstname = array_shift($parts);
            if (count($parts) > 2) {
                $firstname .= ' ' . array_shift($parts);
            }
            $lastname = implode(" ", $parts);
        } else {
            $firstname = $name;
            $lastname = "";
        }
        return [$firstname, trim($lastname)];
    }

    public static function formatCurrency($value) {
        $value = !empty($value) ? $value : 0;
        $fmt = new NumberFormatter('es_ES', NumberFormatter::CURRENCY);
        return $fmt->formatCurrency(floatval($value), "EUR");
    }

    public static function formatDecimal($value) {
        $fmt = new NumberFormatter('es_ES', NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, 2);
        return $fmt->format(floatval($value));
    }

    public static function formatPercent($value) {
        return CommonUtils::formatDecimal($value) . ' %';
    }

    public static function calculateDiff($p1, $p2) {
        if (empty($p2)) {
            return null;
        }

        return ($p1 - $p2) / abs($p2) * 100;
    }

    public static function calculatePercent($p1, $p2) {
        if (empty($p2) || empty($p1)) {
            return null;
        }

        return ($p1 / $p2) * 100;
    }

    public static function splitArrayInGroups($groups, $array) {
        $output = [];
        for ($numBuckets = $groups; $numBuckets > 0; $numBuckets -= 1) {
            $output[] = array_splice($array, 0, intval(floor(count($array) / $numBuckets)));
        }
        return $output;
    }

    public static function hex2RgbaStyle($hex, $alpha) {
        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        return "rgba($r,$g,$b,$alpha)";
    }

    public static function badgeBackgroundColorStyle($color) {
        return 'background-color: ' . self::hex2RgbaStyle($color, 0.1) . '; color: ' . $color;
    }

    public static function calculateAge($birthDate, $format = 'd/m/Y') {
        if (strpos($format, '/')) {
            $birthDate = str_replace('-', '/', $birthDate);
        }
        $from = DateTime::createFromFormat($format, $birthDate);
        $to = new DateTime('today');
        return $from->diff($to)->y;
    }

    public static function getPasswordEncrypted($password, $userId) {
        return md5(PASSWORD_SALT . $userId . $password);
    }

    public static function base64urlEncode($data) {
        return rtrim(strtr($data, '+/', '-_'), '=');
    }

    public static function base64urlDecode($data) {
        return str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
    }

    public static function getFileName($text) {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή', 'º', '€', '/', '·');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η', 'o', 'E', '_', '_');
        $text = str_replace($a, $b, $text);
        $text = preg_replace('`\s`', '_', $text);
        $text = preg_replace('`(_)+`', '_', $text);
        $text = trim(ltrim($text));

        $text = preg_replace('/[^a-zA-Z0-9_-]/', '', $text);
        $text = preg_replace('`(_)+`', '_', $text);

        return $text;
    }

    public static function changeWorkingHoursTimeZone($workingHours, $oldTimeZone, $newTimeZone) {
        foreach ($workingHours as &$hours) {
            if (!empty($hours)) {
                $startTime = new DateTime($hours['start'], new DateTimeZone($oldTimeZone));
                $endTime = new DateTime($hours['end'], new DateTimeZone($oldTimeZone));

                $startTime->setTimezone(new DateTimeZone($newTimeZone));
                $endTime->setTimezone(new DateTimeZone($newTimeZone));

                $hours['start'] = $startTime->format('H:i');
                $hours['end'] = $endTime->format('H:i');
            }
        }

        return $workingHours;
    }

    public static function divideIntoParts($number, $parts) {
        $result = array_fill(0, $parts, floor($number / $parts));
        for ($i = 0; $i < $number % $parts; $i++) {
            $result[$i]++;
        }
        return $result;
    }

}
