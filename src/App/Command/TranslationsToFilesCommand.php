<?php

declare(strict_types=1);

namespace App\Command;

use App\Dao\TranslationDAO;
use Exception;
use Psr\Container\ContainerInterface;

class TranslationsToFilesCommand {

    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function command($args) {
        try {
            $appTranslations = !empty($args[0]) && $args[0] == 'app';

            $langs = ['es', 'ca', 'en'];
            $translationDAO = new TranslationDAO($this->container->get('pdo'));
            $translations = $appTranslations ? $translationDAO->getTranslations(['c2m']) : $translationDAO->getTranslations([], ['c2m']);

            $translationFiles = [];
            foreach ($translations as $translation) {
                foreach ($langs as $lang) {
                    $translationFiles[$lang][$translation['id']] = $translation[$lang];
                }
            }

            if ($appTranslations) {
                foreach ($langs as $lang) {
                    $file = $this->container->get('i18n')->getTranslationsFileApp($lang);
                    file_put_contents($file, json_encode($translationFiles[$lang]));
                }
            } else {
                foreach ($langs as $lang) {
                    $translationsLang = $translationFiles[$lang];
                    $result = '';
                    foreach ($translationsLang as $id => $text) {
                        if ($text === null) {
                            $text = '';
                        }
                        $text = str_replace('\'', '\\\'', $text);
                        $result .= "    '$id' => '$text',\n";
                    }
                    $result = "<?php\n\nreturn [\n" . $result . "];";
                    $file = $this->container->get('i18n')->getTranslationsFile($lang);
                    file_put_contents($file, $result);
                }
            }
        } catch (Exception $e) {
            $this->container->get('logger')->addError($e);
            throw $e;
        }

        $this->container->get('logger')->addInfo("End command FilesToTranslations");
        return "ok";
    }

}
