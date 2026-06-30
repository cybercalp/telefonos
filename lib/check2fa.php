<?php

require_once ('./vendor/autoload.php');

use Dolondro\GoogleAuthenticator\GoogleAuthenticator;

require_once(__DIR__ . '/../private/config.php');

function check_2FA($secretKey, $code) {
   $message = array();
   $message_success = '';
     
	$googleAuthenticator = new \Dolondro\GoogleAuthenticator\GoogleAuthenticator(['window' => 2]);

   /* 
    * Ejemplo de uso de un adaptador de caché PSR-6, en este caso, el adaptador de cache de sistema de archivos (cache/filesystem adapter),
    * Esta extensión (actualmente v1.2) tiene marcada como dependencia la extensión league/flysystem pero la versión 1.0
    * Esta extensión solo se instala como require-dev.
    */

   //v1.0 de league/flysystem
   // The internal adapter
   $filesystemAdapter = new \League\Flysystem\Adapter\Local(sys_get_temp_dir()."/");
   // The FilesystemOperator
   $filesystem = new \League\Flysystem\Filesystem($filesystemAdapter);

   $pool = new \Cache\Adapter\Filesystem\FilesystemCachePool($filesystem);
   $googleAuthenticator->setCache($pool);
   
   if ($googleAuthenticator->authenticate($secretKey, $code)) {
      $message[] = 'Código válido.';
      $message_success = 'yes';
   } else {
      $message[] = 'Código introducido no válido.';
   }
   foreach ($message as &$msg) {
    
   }
   unset($msg);
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}
?>

