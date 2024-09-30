<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService extends BaseService {

    private $session;
    private $params;
    private $mailer;

    public function __construct($pdo = null, $session = null, $params = null) {
        parent::__construct($pdo);
        $this->session = $session;
        $this->params = $params;
        $this->init();
    }

    public function init() {
        $this->mailer = new PHPMailer();

        $this->mailer->IsSMTP();
        $this->mailer->SMTPAutoTLS = false;
        $this->mailer->Host = $this->params->getParam('MAIL.HOST');
        $this->mailer->SMTPAuth = true;
        $ssl = $this->params->getParam('MAIL.SSL');
        if ($ssl == 1) {
            $this->mailer->SMTPSecure = 'ssl';
        }
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Port = $this->params->getParam('MAIL.PORT');
        $this->mailer->Username = $this->params->getParam('MAIL.USERNAME');
        $this->mailer->Password = $this->params->getParam('MAIL.PASSWORD');
        $this->mailer->isHTML(true);
        $this->mailer->setFrom($this->params->getParam('MAIL.FROM_ADDRESS'), $this->params->getParam('MAIL.FROM_NAME'));
    }

    /**
     * @param type $to
     * @param type $subject
     * @param type $body
     * @param type $cc
     * @param type $attachment
     * @param type $override from
     */
    public function send($to, $subject, $body, $cc = null, $bcc = null, $attachments = null, $from = null) {
        $this->mailer->ClearAllRecipients();
        foreach ((!is_array($to) ? [$to] : $to) as $e) {
            $this->mailer->addAddress($e);
        }
        if (!empty($cc)) {
            foreach ((!is_array($cc) ? [$cc] : $cc) as $e) {
                $this->mailer->addCC($e);
            }
        }
        if (!empty($bcc)) {
            foreach ((!is_array($bcc) ? [$bcc] : $bcc) as $e) {
                $this->mailer->addBCC($e);
            }
        }
        if (!empty($attachments)) {
            foreach ((!is_array($attachments) ? [$attachments] : $attachments) as $e) {
                $this->mailer->addAttachment($e['path'], $e['name']);
            }
        }
        if (!empty($from)) {
            $this->mailer->setFrom($from['email'], $from['name']);
        }

        $this->mailer->Subject = $subject;
        $this->mailer->Body = $body;

        if (!$this->mailer->send()) {
            throw new Exception($this->mailer->ErrorInfo);
        }
        return true;
    }

}


