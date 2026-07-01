<?php
/**
 * Handles Google reCAPTCHA verification.
 */
class CaptchaModel
{
    /**
     * Verifies the reCAPTCHA response token with Google's siteverify API.
     *
     * @param string $recaptcha_response the "g-recaptcha-response" POST value
     * @return bool success of the verification
     */
    public static function checkCaptcha($recaptcha_response)
    {
        if (empty($recaptcha_response)) {
            return false;
        }

        $data = http_build_query(array(
            'secret'   => Config::get('RECAPTCHA_SECRET_KEY'),
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ));

        $options = array('http' => array(
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $data
        ));

        $context = stream_context_create($options);
        $result  = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);

        if ($result === false) {
            return false;
        }

        $json = json_decode($result, true);
        return isset($json['success']) && $json['success'] === true;
    }
}
