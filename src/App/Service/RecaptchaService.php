<?php

declare(strict_types=1);

namespace App\Service;

class RecaptchaService extends BaseService {

    private $recaptchaSecretKey;
    
    public function __construct($recaptchaSecretKey) {
        $this->recaptchaSecretKey = $recaptchaSecretKey;
    }

    public function check($token) {
        $data = http_build_query(array(
            'secret' => $this->recaptchaSecretKey,
            'response' => $token
        ));

        $curl = curl_init();

        $captcha_verify_url = "https://www.google.com/recaptcha/api/siteverify";

        curl_setopt($curl, CURLOPT_URL, $captcha_verify_url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


        $captcha_output = curl_exec($curl);
        curl_close($curl);
        $decoded_captcha = json_decode($captcha_output, true);

        if ($decoded_captcha['success']) {
            return true;
        } else {
            return false;
        }
    }

}


