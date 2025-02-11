<?php

namespace App\Service;

use App\Constant\BookletType;
use App\Constant\FileType;
use App\Dao\BookletDAO;
use App\Dao\BookletFileDAO;
use App\Dao\FileDAO;
use App\Dao\RecipeDAO;
use App\Dao\RecipeFileDAO;
use App\Util\FileUtils;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;


class PdfService extends BaseService {

    private $session;
    private $params;
    private $renderer;

    public function __construct($pdo = null, $session = null, $params = null, $renderer = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
        $this->renderer = $renderer;
    }

    public function generateQrCode($qrUrl, $lang, $productSlug) {

        // Si el idioma es español, eliminamos el segmento '{lang}' de la URL
        if ($lang === 'es') {
            $url = str_replace('/{lang}', '', $qrUrl);
        } else {
            $url = str_replace('{lang}', $lang, $qrUrl);
        }

        // Reemplazar {slug} con el slug del producto
        $url = str_replace('{slug}', $productSlug, $url);

        // Generar el código QR en formato JPG
        $qrCode = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            // Tamaño QR - Indicar el valor en px dividido por 3, del tamaño del template
            ->size(300)
            ->margin(10)
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

        // Cargar imagen de disco a base64
        if ($booklet['booklet_type_id'] != BookletType::Flyer) {
            $imagePath = $folderPrivate . '/BP/' . $booklet['main_language_id'] . '.jpg';
            $pages[1] = ['image' => FileUtils::getBase64Image($imagePath)];
        }

        foreach ($bookletImages as $bookletImage) {
            if (empty($bookletImage['page'])) {
                $pages[$bookletImage['page']] = [];
            }

            // Cargar imagen de disco a base64
            $imagePath = $folderPrivate . '/' . FileType::ProductImage . '/image_' . $booklet['main_language_id'] . '_' . $bookletImage['display_mode'] . '_' . $bookletImage['image_id'] . '.' . pathinfo($bookletImage['file'], PATHINFO_EXTENSION);
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
            $fileDAO = new FileDAO($this->pdo);
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

    public function recipePdf($recipeId, $save) {
        $recipeDAO = new RecipeDAO($this->pdo);
        $recipe = $recipeDAO->getFullById($recipeId);

        $options = new Options();
        $options->setDpi(300);

        $dompdf = new Dompdf($options);

        $html = "test";
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');

        // Renderizamos el HTML como PDF
        $dompdf->render();

        $outputFile = $recipeId . '_' . date('YmdHis') . '.pdf';

        if ($save) {
            $fileType = FileType::RecipeFile;

            $fileDAO = new FileDAO($this->pdo);
            $fileId = $fileDAO->save(['file_type_id' => $fileType, 'file' => $outputFile]);

            $recipeFileDAO = new RecipeFileDAO($this->get('pdo'));
            $recipeFileDAO->save(['recipe_id' => $recipe['id'], 'file_id' => $fileId]);

            $directory = $this->params->getParam('FOLDER_PRIVATE');
            FileUtils::saveFile($fileType, $directory, $fileId, 'file', $outputFile, $dompdf->output());
        } else {
            $dompdf->stream($outputFile, ["Attachment" => '0']);
        }
    }
}
