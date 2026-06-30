<?php
// CODIFICA UNA CONTRASEÑA EN TEXTO PLANO PARA EL CAMPO DE AD unicodePwd: entre comillas y en UTF-16LE
function encode_password($password) {
   $quoted_pw = '"' . $password . '"';
   $encoded = mb_convert_encoding($quoted_pw, "UTF-16LE");
   //echo "<pre> Contraseña codificada (hex): " . strtoupper(bin2hex($encoded)) . "\n </pre>";
   return $encoded;
}
?>

