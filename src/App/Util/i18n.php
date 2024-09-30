<?php

declare(strict_types=1);

namespace App\Util;

use Exception;

class i18n {

    private $translationsPath;
    private $defaultLang;
    private $translations;
    private $environment;
    private $translationsPathApp;

    public function __construct($transationsPath, $defaultLang, $environment, $translationsPathApp = null) {
        $this->translationsPath = $transationsPath;
        $this->defaultLang = $defaultLang;
        $this->environment = $environment;
        $this->translationsPathApp = $translationsPathApp;

        $this->setCurrentLang($defaultLang);
    }

    private function getCaller() {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        do {
            $current = array_pop($dbt);
        } while ($current['function'] != '__');

        $caller = $current['file'] . '@' . $current['line'];
        return $caller;
    }

    public function setCurrentLang($lang) {
        $_SESSION['i18n'][$this->environment]['current_lang'] = $lang;
    }

    public function getCurrentLang() {
        if (empty($_SESSION['i18n'][$this->environment]['current_lang'])) {
            $this->setCurrentLang($this->defaultLang);
        }
        return $_SESSION['i18n'][$this->environment]['current_lang'];
    }

    public function changeLang($lang) {
        if (!file_exists($this->getTranslationsFile($lang))) {
            throw new Exception('Invalid language');
        }
        // Clear translations cache
        $this->translations = null;
        $this->setCurrentLang($lang);
    }

    public function getTranslationsFileEtag() {
        return md5_file($this->getTranslationsFile());
    }

    public function getTranslationsFileLastModified() {
        return filemtime($this->getTranslationsFile());
    }

    public function getTranslationsFile($lang = null) {
        if (empty($lang)) {
            $lang = $this->getCurrentLang();
        }
        return $this->translationsPath . $lang . '/translations.php';
    }
    
    public function getTranslationsFileApp($lang) {
        return $this->translationsPathApp . $lang . '.json';
    }

    private function loadTranslations() {
        if (empty($this->translations)) {
            $this->translations = include $this->getTranslationsFile();
        }
        return $this->translations;
    }

    public function translate($param, $args = null) {
        $args = !empty($args) ? $args : [];
        if (empty($param)) {
            return "";
        }

        if (!is_array($args)) {
            $args = [$args];
        }

        $translations = $this->loadTranslations();
        $caller = $this->getCaller();

        if (isset($translations[$param])) {
            if (isset($_SESSION['i18n'][$this->environment]['missing_translations'][$caller])) {
                unset($_SESSION['i18n'][$this->environment]['missing_translations'][$caller]);
            }
            return vsprintf($translations[$param], $args);
        }

        $_SESSION['i18n'][$this->environment]['missing_translations'][$caller] = preg_replace("/\r|\n/", "", substr($param, 0, 100));
        return vsprintf($param, $args);
    }

    public function t($param, $args = null) {
        echo $this->translate($param, $args);
    }

    public function getTranslationsStartingWith($filters) {
        $result = [];
        foreach ($this->loadTranslations() as $key => $translation) {
            foreach ($filters as $filter) {
                if (substr($key, 0, strlen($filter)) == $filter) {
                    $result[$key] = $translation;
                    break;
                }
            }
        }
        return $result;
    }

}
