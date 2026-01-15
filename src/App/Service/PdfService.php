<?php

namespace App\Service;

use App\Constant\BookletType;
use App\Constant\FileType;
use App\Constant\Folders;
use App\Constant\StaticListTable;
use App\Dao\BookletDAO;
use App\Dao\BookletFileDAO;
use App\Dao\FileDAO;
use App\Dao\ProductDAO;
use App\Dao\RecipeDAO;
use App\Dao\RecipeFileDAO;
use App\Dao\StaticListDAO;
use App\Dao\SubProductDAO;
use App\Util\FileUtils;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use League\Plates\Template\Folder;

class PdfService extends BaseService {

    private $session;
    private $params;
    private $renderer;
    protected $i18n;

    public function __construct($pdo = null, $session = null, $params = null, $renderer = null, $i18n = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
        $this->renderer = $renderer;
        $this->i18n = $i18n;
    }

    public function generateQrCode($qrUrl, $lang, $productSlug, $qrOptions = []) {

        // Si el idioma es español, eliminamos el segmento '{lang}' de la URL
        if ($lang === 'es') {
            $url = str_replace('/{lang}', '', $qrUrl);
        } else {
            $url = str_replace('{lang}', $lang, $qrUrl);
        }

        // Reemplazar {slug} con el slug del producto
        $url = str_replace('{slug}', $productSlug, $url);

        // Valores por defecto
        $foreground = new Color(0, 0, 0);
        $background = new Color(255, 255, 255);
        $writerOptions = [];

        // Si vienen opciones personalizadas
        if (!empty($qrOptions['foregroundColor'])) {
            $fc = $qrOptions['foregroundColor'];
            $foreground = new Color($fc['r'], $fc['g'], $fc['b']);
        }

        if (!empty($qrOptions['backgroundColor'])) {
            $bc = $qrOptions['backgroundColor'];
            $alpha = isset($bc['a']) ? $bc['a'] : 255;
            $background = new Color($bc['r'], $bc['g'], $bc['b'], $alpha);
        }

        // Generar el código QR en formato JPG
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->size(300)
            ->margin(10)
            ->backgroundColor($background)
            ->foregroundColor($foreground)
            ->build();

        return 'data:image/png;base64,' . base64_encode($qrCode->getString());
    }

    public function bookletPdf($bookletId, $save, $fileType = FileType::BookletFile) {
        $bookletDAO = new BookletDAO($this->pdo);
        $booklet = $bookletDAO->getFullById($bookletId);
        $bookletImages = $bookletDAO->getBookletImages($bookletId, $booklet['main_language_id']);
        $folderPrivate = $this->params->getParam('FOLDER_PRIVATE');
        $qrUrl = $this->params->getParam('KIN.URL');

        $options = new Options();
        $options->setDpi(300);

        $dompdf = new Dompdf($options);

        $pages = [];

        $fileDAO = new FileDAO($this->pdo);

        // Cargar imagen de disco a base64
        if ($booklet['booklet_type_id'] != BookletType::Flyer) {
            $coverFile = $fileDAO->getById($booklet['cover_file_id']);
            if ($coverFile['file_type_id'] == FileType::BookletCoverUpload) {
                $coverPath = $folderPrivate . '/' . FileType::BookletCoverUpload . '/cover_' . $coverFile['id'] . '.' . pathinfo($coverFile['file'], PATHINFO_EXTENSION);
            } else {
                $coverPath = $folderPrivate . '/' . FileType::BookletCover . '/cover_' . $booklet['main_language_id'] . '_' . $coverFile['id'] . '.' . pathinfo($coverFile['file'], PATHINFO_EXTENSION);
            }

            $pages[1] = ['image' => FileUtils::getBase64Image($coverPath)];
        }

        foreach ($bookletImages as $bookletImage) {
            if (empty($bookletImage['page'])) {
                $pages[$bookletImage['page']] = [];
            }

            // Cargar imagen de disco a base64
            $imagePath = $folderPrivate . '/' . FileType::ProductImage . '/image_' . (!empty($bookletImage['is_custom']) ? 'custom' : $booklet['main_language_id']) . '_' . $bookletImage['display_mode'] . '_' . $bookletImage['image_id'] . '.' . pathinfo($bookletImage['file'], PATHINFO_EXTENSION);
            $bookletImage['image'] = FileUtils::getBase64Image($imagePath);

            // Generar el QR code con la URL base, lenguaje y slug del producto en formato base64
            if ($bookletImage['product_id'] != $this->params->getParam('EMPTY_PRODUCT')) {
                $bookletImage['qr'] = $this->generateQrCode($qrUrl, $booklet['qr_language_id'], $bookletImage['slug']);
            }

            $pages[$bookletImage['page']][] = $bookletImage;
        }

        $booklet['pages'] = $pages;

        $data = ['booklet' => $booklet];

        $data['border'] = intval($this->params->getParam('CMYK_BORDER')) . 'px';
        $data['type'] = $fileType == FileType::BookletFileCMYK ? 'CMYK' : 'RGB';

        if ($fileType == FileType::BookletFileCMYK) {
            $data['pageMargin'] = '100px';
            $pxPt = 0.24;
            $widthPt = (2480 + 2 * intval($data['border']) + 2 * intval($data['pageMargin'])) * $pxPt;
            $heightPt = (3508 + 2 * intval($data['border']) + 2 * intval($data['pageMargin'])) * $pxPt;

            $paper = [0, 0, $widthPt, $heightPt];
        } else {
            $data['pageMargin'] = '0';
            $paper = 'A4';
        }

        $html = $this->renderer->fetch("/pdf/booklet/booklet.phtml", $data);
        $dompdf->loadHtml($html);

        $dompdf->setPaper($paper, 'portrait');

        // Renderizamos el HTML como PDF
        $dompdf->render();

        $outputFile = $bookletId . '_' . date('YmdHis') . '.pdf';

        if ($save) {
            $fileId = $fileDAO->save(['file_type_id' => $fileType, 'file' => $outputFile]);

            $bookletFileDAO = new BookletFileDAO($this->get('pdo'));
            $bookletFileDAO->save(['booklet_id' => $booklet['id'], 'file_id' => $fileId]);

            $directory = $this->params->getParam('FOLDER_PRIVATE');
            FileUtils::saveFile($fileType, $directory, $fileId, 'file', $outputFile, $dompdf->output());

            if ($fileType == FileType::BookletFileCMYK) {
                $filePath = FileUtils::getLocalFilepath($outputFile, $directory . DIRECTORY_SEPARATOR . $fileType, $fileId, 'file');
                $filePathTmp = $filePath . '_temp.pdf';

                $gsCommand = 'gs -dSAFER -dBATCH -dNOPAUSE -dNOCACHE -sDEVICE=pdfwrite -sProcessColorModel=DeviceCMYK -sColorConversionStrategy=CMYK -sOutputFile="' . $filePathTmp . '" "' . $filePath . '"';
                exec($gsCommand);
                if (file_exists($filePathTmp)) {
                    rename($filePathTmp, $filePath);
                }
            }
        } else {
            $dompdf->stream($outputFile, ["Attachment" => '0']);
        }
    }

    private function processRecipeImages($privateBasePath, $qrLang, $lang, $international, &$array) {
        foreach ($array as $key => &$value) {
            if (!empty($value['image'])) {
                // De "value", obtener la ultima parte del path, correspondiente al nombre del archivo
                $baseImage = basename($value['image']);
                $imagePath = $privateBasePath . '/upload/' . $baseImage;
                $value['image'] = FileUtils::getBase64Image($imagePath);
            }

            if (!empty($value['logo_override'])) {
                // De "value", obtener la ultima parte del path, correspondiente al nombre del archivo
                $baseLogo = basename($value['logo_override']);
                $logoPath = $privateBasePath . '/upload/' . $baseLogo;
                $value['logo_override'] = FileUtils::getBase64Image($logoPath);
            }

            if (!empty($value['product_id'])) {
                // Obtener toda la información del producto en el idioma de la receta
                $productDAO = new ProductDAO($this->pdo);
                $productLang = !empty($productDAO->getSingleField($value['product_id'], 'parent_product_id')) ? 'custom' : $lang;
                $product = $productDAO->getFullById($value['product_id'], $productLang);

                $qrOptions = [
                    'foregroundColor' => [
                        'r' => 22,
                        'g' => 64,
                        'b' => 112,
                    ],
                    'backgroundColor' => [
                        'r' => 255,
                        'g' => 255,
                        'b' => 255,
                        'a' => 127,
                    ]
                ];

                if (!empty($value['qr'])) {
                    $value['qr'] = $this->generateQrCode($value['qr'], '', '', $qrOptions);
                } else {
                    if (empty($qrLang)) {
                        $value['qr'] = null;
                    } else {
                        $qrUrl = $this->params->getParam('KIN.URL');
                        $value['qr'] = $this->generateQrCode($qrUrl, $qrLang, $product['slug'], $qrOptions);
                    }
                }

                // Cargar imagen de disco a base64
                $logoFilePath = $privateBasePath . '/' . FileType::ProductImage . '/logo_' . $productLang . '_' . $product['logo'] . '.' . pathinfo($product['logo_file'], PATHINFO_EXTENSION);
                $value['logo'] = FileUtils::getBase64Image($logoFilePath);

                $photoFilePath = $privateBasePath . '/' . FileType::ProductImage . '/photo_' . $productLang . '_' . $product['photo'] . '.' . pathinfo($product['photo_file'], PATHINFO_EXTENSION);
                $value['photo'] = FileUtils::getBase64Image($photoFilePath);
            }

            if (!empty($value['icon'])) {
                $iconFilePath = Folders::getPublic() . '/app/img/receipt/ico' . $value['icon'] . '-' . $lang . '.svg';
                $value['icon'] = FileUtils::getBase64Image($iconFilePath);
            }

            if (!empty($value['subproduct_id'])) {
                $subproductDAO = new SubProductDAO($this->pdo);
                $subproduct = $subproductDAO->getFullById($value['subproduct_id'], $lang, $international);

                $value['name'] = empty($value['subproduct_name']) ? $subproduct['name'] : $value['subproduct_name'];
                $value['reference'] = empty($value['subproduct_reference']) ? $subproduct['reference'] : $value['subproduct_reference'];
            }

            if (!empty($value['product_id'])) {
                $productDAO = new ProductDAO($this->pdo);
                $product = $productDAO->getFullById($value['product_id'], $lang);

                if (empty($value['periodicity'])) {
                    $value['periodicity'] = $product['periodicity'];
                }
                if (empty($value['subtitle'])) {
                    $value['subtitle'] = $product['subtitle'];
                }
            }

            if (!empty($value['image_block']['banner'])) {
                $bannerId = $value['image_block']['banner'];
                $bannerFilePath = Folders::getPublic() . '/app/img/receipt/banner' . $bannerId . '-' . $lang . '.jpg';
                $value['image_block']['banner'] = FileUtils::getBase64Image($bannerFilePath);
            }

            if (!empty($value['formdata']['color_id'])) {
                $colorEntity = StaticListTable::getEntity(StaticListTable::Color);
                $colorDAO = new StaticListDAO($this->get('pdo'), 'st_' . $colorEntity);
                $color = $colorDAO->getById($value['formdata']['color_id']);
                $value['formdata']['title_bg_color'] = $color['primary'];
                $value['formdata']['group_bg_color'] = $color['secondary'];
            }

            if (is_array($value)) {
                $this->processRecipeImages($privateBasePath, $qrLang, $lang, $international, $value);
            }
        }
    }

    public function recipePdf($recipeId, $save, $fileType = FileType::RecipeFile) {
        $recipeDAO = new RecipeDAO($this->pdo);
        $recipe = $recipeDAO->getFullById($recipeId);
        //$recipeImages = $recipeDAO->getRecipeImages($recipeId, $recipe['main_language_id']);
        $folderPrivate = $this->params->getParam('FOLDER_PRIVATE');
        $qrUrl = $this->params->getParam('KIN.URL');

        $dompdf = new Dompdf(['enable_remote' => true, 'dpi' => 300]);

        $this->processRecipeImages($folderPrivate, $recipe['qr_language_id'], $recipe['main_language_id'], $recipe['international'], $recipe);

        // echo "<pre>";
        // print_r($recipe);
        // echo "</pre>";


        // Header assets
        $publicPath = Folders::getPublic();
        $lang = $recipe['main_language_id'];
        $currentLang = $this->i18n->getCurrentLang();
        $this->i18n->changeLang($lang);

        $recipe['logo_kin'] = FileUtils::getBase64Image($publicPath . '/app/img/receipt/logo-kin.svg');
        $recipe['product_frequency'] = FileUtils::getBase64Image($publicPath .  '/app/img/receipt/frequency.png');
        $recipe['mas_header'] = FileUtils::getBase64Image($publicPath . '/app/img/receipt/mas-' . $lang . '.svg');

        $recipe['pages'] = [];

        foreach ($recipe['json_data'] as $index => $groups) {
            $page = intdiv($index, 2);
            $side = $index % 2 === 0 ? 'left' : 'right';

            // Si no es la primera página y no tiene datos no se muestra
            if ($page > 0 && (empty($groups) || (count($groups) == 1 && empty(array_values($groups)[0])))) {
                continue;
            }

            if (!isset($recipe['pages'][$page])) {
                $recipe['pages'][$page] = ['left' => [], 'right' => []];
            }

            $recipe['pages'][$page][$side] = $groups;
        }

        $data = ['recipe' => $recipe];

        $data['border'] = intval($this->params->getParam('CMYK_BORDER')) . 'px';
        $data['type'] = $fileType == FileType::RecipeFileCMYK ? 'CMYK' : 'RGB';

        if ($fileType == FileType::RecipeFileCMYK) {
            $data['pageMargin'] = '100px';
            $pxPt = 0.24;
            $widthPt = (2480 + 2 * intval($data['border']) + 2 * intval($data['pageMargin'])) * $pxPt;
            $heightPt = (3508 + 2 * intval($data['border']) + 2 * intval($data['pageMargin'])) * $pxPt;

            $paper = [0, 0, $widthPt, $heightPt];
        } else {
            $data['pageMargin'] = '0';
            $paper = 'A4';
        }

        $html = $this->renderer->fetch("/pdf/recipe/recipe.phtml", $data);

        $dompdf->loadHtml($html);

        $this->i18n->setCurrentLang($currentLang);

        $dompdf->setPaper($paper, 'landscape');

        // Renderizamos el HTML como PDF
        $dompdf->render();

        $this->i18n->changeLang($currentLang);

        $outputFile = $recipeId . '_' . date('YmdHis') . '.pdf';

        if ($save) {
            $fileDAO = new FileDAO($this->pdo);
            $fileId = $fileDAO->save(['file_type_id' => $fileType, 'file' => $outputFile]);

            $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
            $recipeFileDAO->save(['recipe_id' => $recipe['id'], 'file_id' => $fileId]);

            $directory = $this->params->getParam('FOLDER_PRIVATE');
            FileUtils::saveFile($fileType, $directory, $fileId, 'file', $outputFile, $dompdf->output());

            if ($fileType == FileType::BookletFileCMYK) {
                $filePath = FileUtils::getLocalFilepath($outputFile, $directory . DIRECTORY_SEPARATOR . $fileType, $fileId, 'file');
                $filePathTmp = $filePath . '_temp.pdf';

                $gsCommand = 'gs -dSAFER -dBATCH -dNOPAUSE -dNOCACHE -sDEVICE=pdfwrite -sProcessColorModel=DeviceCMYK -sColorConversionStrategy=CMYK -sOutputFile="' . $filePathTmp . '" "' . $filePath . '"';
                exec($gsCommand);
                if (file_exists($filePathTmp)) {
                    rename($filePathTmp, $filePath);
                }
            }
        } else {
            $dompdf->stream($outputFile, ["Attachment" => '0']);
        }
    }
}
