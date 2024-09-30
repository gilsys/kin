<?php

declare(strict_types=1);

namespace App\Service;

use App\Dao\FileDAO;
use App\Dao\NoticeDAO;
use App\Dao\NoticeTemplateDAO;
use App\Dao\StatusEventDAO;
use App\Dao\StatusEventGroupDAO;
use App\Dao\StatusEventVariableDAO;
use App\Util\CommonUtils;
use Clegginabox\PDFMerger\PDFMerger;
use NumberFormatter;

class NoticeService extends BaseService {

    private $session;
    private $params;
    private $renderer;
    private $logger;

    public function __construct($pdo = null, $session = null, $params = null, $renderer = null, $logger = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
        $this->renderer = $renderer;
        $this->logger = $logger;
    }

    

    public function generate($notice, $data = [], $fileType = null, $contentOptions = [], $template = 'mail/main.phtml') {
        $notice = $this->replace($notice, $data);
        $notice['client_id'] = !empty($data) && !empty($data['client']) ? $data['client']['id'] : null;        
        $notice['creator_user_id'] = !empty($this->session['user']) ? $this->session['user']['id'] : null;
        $notice['content'] = !empty($notice['content']) ? $notice['content'] : null;
        $noticeDAO = new NoticeDAO($this->pdo);
        $noticeId = $noticeDAO->save($notice);
        $statusEventDAO = new StatusEventDAO($this->pdo);
        $statusEvent = $statusEventDAO->getByNoticeTemplateId($notice['notice_template_id']);
        $statusEventGroupDAO = new StatusEventGroupDAO($this->pdo);
        $statusEventGroup = $statusEventGroupDAO->getById($statusEvent['status_event_group_id']);


        if (!empty($statusEvent['has_content']) && !empty($fileType) && !empty($notice['content'])) {
            $pdfService = new PdfService($this->pdo, $this->params, $this->renderer);

            $currentContentOptions = !empty($contentOptions['multiple']) ? array_shift($contentOptions['multiple']) : $contentOptions;

            $file = $pdfService->noticePdf($noticeId, $notice['content'], $fileType, $currentContentOptions, $data);

            if (!empty($contentOptions['multiple'])) {
                // Juntar todos los ficheros necesarios
                $pdf = new PDFMerger();
                $pdf->addPDF($file['file_path'], 'all');

                $tmpFiles = [];

                foreach ($contentOptions['multiple'] as $currentContentOptions) {
                    if (!empty($currentContentOptions['notice_template_id'])) {
                        $content = isset($currentContentOptions['content']) ? $currentContentOptions['content'] : null;
                        $noticeToAppend = $this->prepare($currentContentOptions['notice_template_id'], $data, $content);
                        $tempFile = $pdfService->noticePdf($noticeId, $noticeToAppend['content'], $fileType, $currentContentOptions, $data);
                        $pdf->addPDF($tempFile['file_path'], 'all');
                        $tmpFiles[] = $tempFile;
                    } else if (isset($currentContentOptions['pdf_path']) && $currentContentOptions['pdf_path'] !== false) {
                        // Anexar documento
                        if (file_exists($currentContentOptions['pdf_path'])) {
                            $pdf->addPDF($currentContentOptions['pdf_path'], 'all');
                        }
                    }
                }

                // Agrupar todo en un PDF 
                $pdf->merge('file', $file['file_path'] . '.new', 'P');

                unlink($file['file_path']);
                rename($file['file_path'] . '.new', $file['file_path']);

                $fileDAO = new FileDAO($this->pdo);
                foreach ($tmpFiles as $tmpFile) {
                    $fileDAO->deleteById($tmpFile['id']);
                }
            }

            $noticeDAO->updateSingleField($noticeId, 'file_id', $file['id']);            
        }

        return $noticeId;
    }

    public function preview($notice, $data = []) {
        $contentOptions = [];
        $notice = $this->replace($notice, $data);
        $pdfService = new PdfService($this->pdo, $this->params, $this->renderer);
        $pdfService->previewNoticePdf($notice['content'], $contentOptions, $data);
    }

    public function sendEmail($statusEventId, $to, $data = [], $template = 'mail/main.phtml', $cc = null, $bcc = null) {
        $noticeTemplateDAO = new NoticeTemplateDAO($this->pdo);
        $noticeTemplates = $noticeTemplateDAO->getForSelectByStatusEventId($statusEventId);
        $notice = $this->prepare($noticeTemplates[0]['id'], $data);
        $emailData = ['content' => $notice['email_content']];
        $emailBody = $this->renderer->fetch($template, ['data' => $emailData]);
        $emailService = new EmailService($this->pdo, $this->session, $this->params);
        $emailService->send($to, $notice['email_subject'], $emailBody, $cc, $bcc);
    }

    public function prepare($noticeTemplateId, $data = [], $content = null) {
        if (!empty($content)) {
            $notice = [
                'notice_template_id' => $noticeTemplateId,
                'content' => $content,
            ];
        } else {
            $noticeTemplateDAO = new NoticeTemplateDAO($this->pdo);
            $noticeTemplate = $noticeTemplateDAO->getFullById($noticeTemplateId);

            $notice = [
                'notice_template_id' => $noticeTemplate['id'],
                'email_subject' => $noticeTemplate['email_subject'],
                'email_content' => $noticeTemplate['email_content'],
                'content' => $noticeTemplate['content'],
            ];
        }

        return $this->replace($notice, $data);
    }

    public function replace($notice, $data = []) {
        if (empty($data)) {
            return $notice;
        }
        
        $notice['email_content'] = empty($notice['email_content']) ? '' : $notice['email_content'];
        $notice['email_subject'] = empty($notice['email_content']) ? '' : $notice['email_subject'];
        
        $statusEventDAO = new StatusEventDAO($this->pdo);
        $statusEvent = $statusEventDAO->getByNoticeTemplateId($notice['notice_template_id']);

        $statusEventVariableDAO = new StatusEventVariableDAO($this->get('pdo'));
        $variables = $statusEventVariableDAO->getByStatusEventId($statusEvent['id']);

        $clonedVariables = [];

        // Preparamos primero las variables de tipo array, clonando la fila tantas veces como elementos tenga el array
        foreach ($variables as $i => $variable) {
            $variableExplode = explode('.', str_replace('%%', '', $variable['code']));

            if (!empty($variable['common'])) {
                $data[$variableExplode[0]][$variableExplode[1]] = $this->processModifier($data, $variable, '');
            }

            if (isset($data[$variableExplode[0]])) {
                $baseReplacedValue = $data[$variableExplode[0]];

                if (is_array($baseReplacedValue) && isset($baseReplacedValue[0]) && strlen(implode('', $baseReplacedValue[0])) == 0) {
                    // Condición especial para que Si el recurso es un array pero no hay contenido, borrar la tabla
                    $this->deleteTable($notice['email_content'], $variable['code']);
                    $this->deleteTable($notice['content'], $variable['code']);
                } else if (is_array($baseReplacedValue) && count($baseReplacedValue) && isset($baseReplacedValue[0][$variableExplode[1]])) {
                    // Reemplazar tambien array's
                    if (!in_array($variableExplode[0], $clonedVariables)) {
                        // Clonar únicamente la primera vez que tratemos una variable del array
                        $this->cloneTR($notice['email_content'], $variable['code'], count($baseReplacedValue));
                        $this->cloneTR($notice['content'], $variable['code'], count($baseReplacedValue));
                        $clonedVariables[] = $variableExplode[0];
                    }

                    foreach ($baseReplacedValue as $replacedValue) {
                        $replacedValue = $replacedValue[$variableExplode[1]];
                        $replacedValue = $this->processModifier($data, $variable, $replacedValue);

                        $notice['email_content'] = CommonUtils::replaceFirst($variable['code'], $replacedValue, $notice['email_content']);
                        if (!empty($statusEvent['has_content']) && !empty($notice['content'])) {
                            $notice['content'] = CommonUtils::replaceFirst($variable['code'], $replacedValue, $notice['content']);
                        }
                    }
                } else if (is_array($data[$variableExplode[0]]) && array_key_exists($variableExplode[1], $data[$variableExplode[0]])) {
                    $replacedValue = $data[$variableExplode[0]][$variableExplode[1]];
                    $replacedValue = !is_null($replacedValue) ? $this->processModifier($data, $variable, $replacedValue) : '';

                    $notice['email_content'] = str_replace($variable['code'], $replacedValue, $notice['email_content']);
                    if (!empty($statusEvent['has_content']) && !empty($notice['content'])) {
                        $notice['content'] = str_replace($variable['code'], $replacedValue, $notice['content']);
                    }
                    if (!empty($notice['email_subject'])) {
                        $notice['email_subject'] = str_replace($variable['code'], $replacedValue, $notice['email_subject']);
                    }
                }
            }

            // Eliminamos las variables NO sustituidas
	    $notice['email_content'] = str_replace($variable['code'], '', $notice['email_content']);
	    if (!empty($notice['email_subject'])) {
            	$notice['email_subject'] = str_replace($variable['code'], '', $notice['email_subject']);
	    }
            if (!empty($statusEvent['has_content']) && !empty($notice['content'])) {
            	$notice['content'] = str_replace($variable['code'], '', $notice['content']);
	    }
        }
        return $notice;
    }

    private function processModifier($data, $variable, $value) {
        if (!empty($variable['modifier']) && method_exists($this, $variable['modifier'])) {
            $modifier = $variable['modifier'];
            return $this->$modifier($value, $data);
        }
        return (string) $value;
    }

    private function formatCurrency($value, $data) {
        if ($value === '-') {
            return $value;
        }
        $fmt = new NumberFormatter('es_ES', NumberFormatter::CURRENCY);
        return $value = $fmt->formatCurrency(floatval($value), "EUR");
    }

    private function formatDate($value, $data) {
        $value = str_replace('/', '-', $value);
        $format = count(explode(' ', $value)) > 1 ? 'd/m/Y H:i:s' : 'd/m/Y';
        return CommonUtils::convertDate($value, $format);
    }

    private function formatDateNoTime($value, $data) {
        $value = str_replace('/', '-', $value);
        return CommonUtils::convertDate($value, 'd/m/Y');
    }

    private function dateToday($value, $data) {
        return date('d/m/Y');
    }

    private function dateTodayLong($value, $data) {
        return __('app.common.date_long', [date('d'), __('app.common.month.' . date('n')), date('Y')]);
    }

    private function dateTodayHour($value, $data) {
        return date('d/m/Y H:i');
    }

    private function firstPhoneArray($value, $data) {
        if (!empty($value)) {
            return $value[0]['phone'];
        }
        return null;
    }
    
    private function firstEmailArray($value, $data) {
        if (!empty($value)) {
            return $value[0]['email'];
        }
        return null;
    }
    
    private function addParenthesisOrEmpty($value, $data) {
        if (empty($value)) {
            return '';
        }
        return " ($value)";
    }

    private function formatDecimal($value, $data) {
        $fmt = new NumberFormatter('es_ES', NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, 2);
        return $fmt->format(floatval($value));
    }

    private function formatPercent($value, $data) {
        if ($value === '-') {
            return $value;
        }
        return $this->formatDecimal($value, $data) . ' %';
    }

    private function deleteTable(&$element, $code) {
        $table = $this->getTag('table', $element, $code);
        if (empty($table)) {
            return;
        }
        $element = str_replace($table, '', $element);
    }

    private function cloneTR(&$element, $code, $rows) {
        $tr = $this->getTag('tr', $element, $code);
        if (empty($tr)) {
            return;
        }
        $trN = '';
        for ($i = 0; $i < $rows; $i++) {
            $trN .= $tr;
        }
        $element = str_replace($tr, $trN, $element);
    }

    private function getTag($tag, $content, $code) {
        $codePos = strpos($content, $code);
        if (empty($codePos)) {
            return;
        }
        $tagStart = strrpos(substr($content, 0, $codePos), '<' . $tag);
        if (empty($tagStart)) {
            return;
        }
        $leftCutString = substr($content, $tagStart);
        $tagCode = substr($leftCutString, 0, strpos($leftCutString, '</' . $tag . '>') + strlen('</' . $tag . '>'));
        return $tagCode;
    }

    public function saveSessionData($data) {
        $token = CommonUtils::generateRandString(20);
        $this->session['redirect_token_' . $token] = $data;
        return $token;
    }

    public function getSessionData($token) {
        return !empty($token) && !empty($this->session['redirect_token_' . $token]) ? $this->session['redirect_token_' . $token] : [];
    }

    public function deleteSessionData($token) {
        unset($this->session['redirect_token_' . $token]);
    }

}
