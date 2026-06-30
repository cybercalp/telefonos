<?php
//TRANSFORMA UN GUID EN BINARIO RESCATADO DE AD EN UN FORMATO GUID ESTÁNDAR
function binToGUID($binGUID, $conGuiones = false) {
   $hexGUID = bin2hex($binGUID);

   // El GUID tiene un orden especial: partes están en little-endian
   if ($conGuiones) {
      $GUID =
         substr($hexGUID, 6, 2)  . substr($hexGUID, 4, 2)  . substr($hexGUID, 2, 2) . substr($hexGUID, 0, 2) . '-' .
         substr($hexGUID, 10, 2) . substr($hexGUID, 8, 2)  . '-' .
         substr($hexGUID, 14, 2) . substr($hexGUID, 12, 2) . '-' .
         substr($hexGUID, 16, 4) . '-' .
         substr($hexGUID, 20, 12);
   } else {
      $GUID =
        substr($hexGUID, 6, 2)  . substr($hexGUID, 4, 2)  . substr($hexGUID, 2, 2) . substr($hexGUID, 0, 2) .
        substr($hexGUID, 10, 2) . substr($hexGUID, 8, 2)  .
        substr($hexGUID, 14, 2) . substr($hexGUID, 12, 2) .
        substr($hexGUID, 16, 4) .
        substr($hexGUID, 20, 12);
   }

   return strtolower($GUID);
}
?>

