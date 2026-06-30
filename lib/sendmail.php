<?php
//Funciones para acceso a BD
require_once('./lib/db_pendinguser_add.php');

require './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../private/config.php');

// ENVIA UN CORREO A LA DIRECCIÓN INTRODUCIDA
function send_mail($function_call, $address_to, $address_name) {
   global $smtp_host, $smtp_port, $smtp_user, $smtp_pass;
   global $mail_from_address, $mail_from_name, $mail_reply_address, $mail_reply_name;

   $message = array();
   $message_success = '';

   if (!empty($address_to)) {

      if ($function_call === 'rescue') {
         //Generamos la URL de un sólo uso
         //Obtenemos una cadena única de 40 caracteres que usaremos para generar la URL de un solo uso
         $token = sha1(uniqid($address_to, true));
         //Almacenamos el nombre de usuario (pasado en $address_name)
         insert_pending_users($token, $address_name, $_SERVER['REQUEST_TIME']);

         //URL que enviaremos al usuario
         if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $url = "https";
         } else {
            $url = "http";
         }
         // Here append the common URL characters.
         $url .= "://";
         // Append the host(domain name, ip) to the URL.
         $url .= $_SERVER['HTTP_HOST'];
         // Append the requested resource location to the URL
         $url .= dirname($_SERVER['PHP_SELF']);
         $url .= '/login?token='. $token;
      }
 
      $mail = new PHPMailer(true);

      //Server settings
      $mail->isSMTP();
      $mail->SMTPAuth = true;
//      $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
      $mail->SMTPDebug = 0; //2-> para debug 0-> sin debug

      $mail->Host = $smtp_host;
      $mail->Port = $smtp_port; 
//error_log(print_r('--- sendmail.php ---' . PHP_EOL, TRUE),3,'./kk.log');
//error_log(print_r('$smtp_port: ' . $smtp_port . PHP_EOL, TRUE),3,'./kk.log');
//error_log(print_r('Type: ' . gettype($smtp_port) . PHP_EOL, TRUE),3,'./kk.log');
      if ($smtp_port == '587') {
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Habilitar TLS
      } else {
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    //Enable implicit TLS encryption
      }
//      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;     //Enable implicit TLS encryption
//      $mail->Port       = 465;                             //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
      $mail->Username = $smtp_user;
      $mail->Password = $smtp_pass;

      $mail->setFrom($mail_from_address, $mail_from_name);
      if (!empty($mail_reply_address))  $mail->addReplyTo($mail_reply_address, $mail_reply_name);
      $mail->addAddress($address_to, (!empty($address_name)) ? $address_name : $address_to);

      // Embeber Logo Municipal para visibilidad offline/segura
      $logo_path = __DIR__ . '/../images/escudo.png';
      if (file_exists($logo_path)) {
          $mail->addEmbeddedImage($logo_path, 'logo_escudo', 'escudo.png');
      }
      // Copia
      //$mail->addCC('info@example.com');
      // Copia oculta
      //$mail->addBCC('info@example.com', 'name');

      switch($function_call) {
        case 'rescue';
          $subjectmail = 'Recuperación de contraseña solicitada';
          break;
        case 'changePassword';
          $subjectmail = 'Cambio de contraseña realizado';
          break;
        default;
         $subjectmail = 'Correo soporte';
         break;
      }
      $mail->Subject = $subjectmail;

      //Construimos el mensaje que enviaremos en formato HTML
/*      En el mensaje los estilos deben ir en el atributo style, no podemos añadir clases.
 *      Además en Zimbra:  Para prevenir ataques XSS y ofrecer mayor seguridad, la nueva implementación de Defanger elimina el atributo
 *      "display" de los correos electrónicos HTML, lo que afecta la representación de dichos mensajes HTML en el cliente web.
 *      Estos son los impactos conocidos en la representación: Owasp elimina el atributo CSS "display" de las etiquetas HTML y afecta el
 *      diseño del contenido HTML en el panel de lectura del correo.
 */

/*
      $body_message = '<html>';
      $body_message .= '<head>';
      $body_message .= '<title>Recuperaci&oacute;n de contrase&ntilde;a - Ajuntament de Calp</title>';
      $body_message .= '</head>';
      $body_message .= '<body style="background-color: #efefefa3; font-family: \'Arial\', sans-serif; color: #333333; line-height: 1.6; width=768px;">';
      $body_message .= '<div id="escudo" style="margin: 10px;"><img src="https://aplicaciones.ajcalp.es/telefonos/images/escudo.svg" border="0"></div>';
      $body_message .= '<div id="principal" style="text-align: center;">';
      $body_message .= '<div id="cuadro1" style="background-color: white; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); box-sizing: border-box; overflow-x: auto; padding: 30px; width: 768px;">';
      $body_message .= '<p style="font-size: 24px; color: #003366; font-weight: bold; margin-bottom: 10px; text-align: center;">RECUPERAR CONTRASE&Ntilde;A</p>';
      $body_message .= '<a href="' . $url . '" style="background-color: #003366; color: white; border: none; padding: 12px; font-size: 15px; margin-top: 20px; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center;">Pinche aqu&iacute; para iniciar el proceso de cambio de contrase&ntilde;a</a>';
      $body_message .= '</div></div>';
      $body_message .= '<div style="width: 768px; text-align: center; font-size: 12px; color: #777; margin-top: 15px;">&copy;&nbsp;Ayuntamiento de Calp. Todos los derechos reservados.</div><br>';
      $body_message .= '</body></html>';
 */
      switch($function_call) {
        case 'rescue';
          $file_to_send = change_href_in_html_file('./resources/message_rescue.html', $url);
          $body_message = '';
          break;
        case 'changePassword';
	  $file_to_send = change_tag_in_html_file('./resources/message_change.html', '<<--NAME-->>', $address_name);
          $body_message = '';
          break;
       default;
         $file_to_send = '';
         $body_message = 'Mensaje por defecto de correo soporte.';
         break;
      }

      $mail->IsHTML(true);
      if (!empty($file_to_send)) {
         $mail->msgHTML(file_get_contents($file_to_send), __DIR__);
      } else {
         $mail->msgHTML($body_message);
      }
//      $mail->Body = $body_message;
//      $mail->Body = 'Esto es sólo el cuerpo del mensaje en texto plano';
//      $mail->AltBody = 'Este es el cuerpo del correo en texto plano'; // Cuerpo del correo en texto plano
//      $mail->addAttachment('attachment.txt');

      $mail->CharSet = 'UTF-8';
      $mail->Encoding = 'base64';

      if (!$mail->send()) {
         $message[] = $mail->ErrorInfo;
      } else {
         $message[] = '¡El mensaje de correo electrónico se ha enviado! Revise su correo y continue con el proceso.';
         $message_success = 'yes';
      }
   } else{
     $message[] = 'Por favor, completa todos los campos.';
   }
   foreach ($message as &$msg) {
    
   }
   unset($msg);
   if ($function_call === 'rescue') {
      //Por ahora sólo se llama a esta función desde 2 sitios, 
      //y sólo me interesa que fije mensaje cuando se llama desde rescue
      $_SESSION['mensaje'] = $message;
      $_SESSION['mensaje_css'] = $message_success;
   }
}
//A PARTIR DE UNA PLANTILLA HTML, GENERAMOS UN TEMPORAL HTML CON EL ATRIBUTO HREF CAMBIADO PARA SU ENVIO POR MAIL
function change_href_in_html_file($originalFile, $urlToChange) {
   $htmlFile = file_get_contents($originalFile);

   // Crear DOMDocument
   $doc = new DOMDocument();
   libxml_use_internal_errors(true);
   $doc->loadHTML($htmlFile);
   libxml_clear_errors();

   // Modificar los href de los <a>
   $links = $doc->getElementsByTagName('a');
   foreach ($links as $lnk) {
      $href = $lnk->getAttribute('href');

      // Condición de ejemplo: cambiar href si contiene 'google'
      if (strpos($href, 'link_to_change') !== false) {
         $lnk->setAttribute('href', $urlToChange);
      }
    }

    // Crear archivo temporal con nombre aleatorio
    $directorioTemporal = sys_get_temp_dir();
    // Verifica que exista y sea escribible
    if (!is_dir($directorioTemporal) || !is_writable($directorioTemporal)) {
       die("Directorio no existe o no es escribible: $directorioTemporal");
    }

    $nombreAleatorio = uniqid('phpMailer_Html_', true) . '.html';
    $rutaTemporal = $directorioTemporal . DIRECTORY_SEPARATOR . $nombreAleatorio;

     // Guardar el HTML modificado en el archivo temporal
     file_put_contents($rutaTemporal, $doc->saveHTML());

     return($rutaTemporal);
}
//A PARTIR DE UNA PLANTILLA HTML, GENERAMOS UN TEMPORAL HTML CON UNA ETIQUETA DADA CAMBIADA PARA SU ENVIO POR MAIL
//LA ETIQUETA QUE BUSCAMOS SE LA PASAMOS A LA FUNCIÓN
function change_tag_in_html_file($originalFile, $tagToChange, $newValue) {
   //Leer el contenido del fichero   
   $htmlFile = file_get_contents($originalFile);

   // Modificar la etiqueta dada por el valor nuevo
   $htmlFile = str_replace($tagToChange, $newValue, $htmlFile);

    // Crear archivo temporal con nombre aleatorio
    $directorioTemporal = sys_get_temp_dir();
    // Verifica que exista y sea escribible
    if (!is_dir($directorioTemporal) || !is_writable($directorioTemporal)) {
       die("Directorio no existe o no es escribible: $directorioTemporal");
    }

    $nombreAleatorio = uniqid('phpMailer_Html_', true) . '.html';
    $rutaTemporal = $directorioTemporal . DIRECTORY_SEPARATOR . $nombreAleatorio;

     // Guardar el HTML modificado en el archivo temporal
     file_put_contents($rutaTemporal, $htmlFile);

     return($rutaTemporal);
}
?>

