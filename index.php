<?php

error_reporting (E_ALL);

require 'vendor/autoload.php';

use Ishenkoyv\KCaptcha\KCaptcha;

session_start();

$params = array(
    'showCredits' => false,
);

$captcha = new KCaptcha($params);

if($_REQUEST[session_name()]){
	$_SESSION['captcha_keystring'] = $captcha->getKeyString();
}

?>
