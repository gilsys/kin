<?php

declare(strict_types=1);

namespace App\Command;

use App\Dao\TranslationDAO;
use Exception;
use Psr\Container\ContainerInterface;

class _FilesToTranslationsCommand {

    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function command($args) {
        try {
            $langs = ['es', 'ca', 'en'];
            $translations = [];
            foreach ($langs as $lang) {
                $translationsFile = $this->container->get('i18n')->getTranslationsFile($lang);
                $translations[$lang] = include $translationsFile;
            }

            $translationDAO = new TranslationDAO($this->container->get('pdo'));
            $translationDAO->deleteAll();

            foreach ($translations['es'] as $id => $translation) {
                $translationRow = [];
                foreach ($langs as $lang) {
                    $translationRow[$lang] = empty($translations[$lang][$id]) ? '' : stripslashes($translations[$lang][$id]);
                }
                $translationDAO->save($id, $translationRow);
            }
        } catch (Exception $e) {
            $this->container->get('logger')->addError($e);
        }

        $this->container->get('logger')->addInfo("End command FilesToTranslations");
        return "ok";
    }

}
