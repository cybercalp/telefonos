<?php

require_once __DIR__ . '/../vendor/autoload.php';


use Dolondro\GoogleAuthenticator\SecretFactory;
use Dolondro\GoogleAuthenticator\QrImageGenerator;

// GENERA EL CODIGO QR PARA EL 2FA
function load_Secret($issuer, $accountName, &$qrImage) {
   $secretFactory = new \Dolondro\GoogleAuthenticator\SecretFactory();

//   $issuer = "Ayuntamiento de Calp";
//   $accountName = "jperles@aplicaciones.ajcalp.es";

   $secret = $secretFactory->create($issuer, $accountName);
   $secretKey = $secret->getSecretKey();

   $qrImageGenerator = new \Dolondro\GoogleAuthenticator\QrImageGenerator\EndroidQrImageGenerator();

//   file_put_contents(__DIR__ . '/gauth-example-ori.html', '<img src="' . $qrImageGenerator->generateUri($secret) . '">');
//   echo 'Visit this URL: "file://' . __DIR__ . '/gauth-example-ori.html" to view an image of your secret, and add it to your google authenticator app\n';

   $qrImage = $qrImageGenerator->generateUri($secret);
   return $secretKey;
}
?>

