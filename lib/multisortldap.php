<?php
/**
* @param array $entries
* @param array $attribs
* @desc Sort LDAP result entries by multiple attributes.
*/  
function multisort_results(&$entries, $attribs){
   for ($i=1; $i<$entries['count']; $i++){
      $index = $entries[$i]; 
      $j=$i;
      do { 
         // create comparison variables from attributes:
         $a = $b = null;
         foreach($attribs as $attrib){
            // only do it though if that attribute exists in both records
            if(isset($entries[$j-1][$attrib]) && isset($index[$attrib])) {
               $a .= normaliza($entries[$j-1][$attrib][0]);
               $b .= normaliza($index[$attrib][0]);
            }
         }
         // do the comparison
         if ($a > $b){
            $is_greater = true;
            $entries[$j] = $entries[$j-1];
            $j = $j-1;
         }else{
            $is_greater = false;
         }
      } while ($j>0 && $is_greater);
      $entries[$j] = $index;
   }
   return $entries;
}
function normaliza ($cadena){
  $originales  = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿ¿¿';
  $modificadas = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
  //$cadena = $cadena;
  $cadena = strtr($cadena, $originales, $modificadas);
  $cadena = strtolower($cadena);
  return $cadena;
}
