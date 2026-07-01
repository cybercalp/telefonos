<?php
//Funcion de conversión de utf8 a iso


require_once(__DIR__ . '/../private/config.php');
require_once(__DIR__ . '/ldap_permissions.php');

use LDAP\Client;
if (!defined('DS')) {
   define('DS', '\\\\');
}

// CREA UNA LISTBOX CON LOS VALORES LDAP DISTINTOS
function fill_combobox($module_name, $attrs, $name_object_html, $classes = null, $selected_value = null, ?Client $ldap = null) {
   global $ldap_dn, $ldap_dn_ubi, $ldap_dn_ou, $filter_ubi, $ldap_user, $ldap_pass;

   $message = array();
   $message_success = (isset($_SESSION['mensaje_css'])) ? $_SESSION['mensaje_css'] : '';
   $consult_ok = false;

   $module_name = basename($module_name, '.php');

   $usuario = $ldap_user;
   $clave = $ldap_pass;

   if (!empty($usuario) && !empty($clave)) {
      // Parámetros de conexión LDAP vía Client (service account)
      if ($ldap) {
         $ldap_conn = $ldap->getResource();
      } else {
         $uri = get_ldap_uri();
         $parts = parse_url($uri);
         $host = $parts['host'] ?? '';
         $port = $parts['port'] ?? 389;
         $scheme = $parts['scheme'] ?? 'ldap';
         $client = new Client($host, (int)$port, $ldap_user, $ldap_pass, $scheme);
         $ldap_conn = $client->getResource();
      }

      if (!$ldap_conn) {
         $message[] = 'No se pudo conectar al servidor LDAP.';
      } else {

         //Array con las distintas OU que vamos a analizar
         $ous = array();

            switch (true) {
              case (($name_object_html === 'txtOficina') || ($name_object_html === 'txtOffice')):
               // Creo el filtro para la busqueda      
               $filter_to_search = $filter_ubi;
               
               // Lógica de Visibilidad dinámica (Misma que en ldap_showresults.php)
               $current_dn = !empty($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
               $visibility_parts = ['(wWWHomePage=1*)'];
               if (!empty($current_dn)) {
                   $visibility_parts[] = '(manager:1.2.840.113556.1.4.1941:=' . ldap_escape($current_dn, '', LDAP_ESCAPE_FILTER) . ')';
               }
               if (is_admin_user()) {
                   $visibility_filter = '(objectClass=*)';
               } else {
                   if (can_manage_contacts()) {
                       $visibility_parts[] = '(objectClass=contact)';
                   }
                   $visibility_filter = (count($visibility_parts) > 1) ? '(|' . implode('', $visibility_parts) . ')' : $visibility_parts[0];
               }
               
               $filter = '(&' . $filter_to_search . $visibility_filter . ')';
               
               //Fijamos la raíz de búsqueda para las estaciones
               array_push($ous, $ldap_dn_ubi);
               break;
             default:
               // Creo el filtro para la busqueda
               $filter_to_search = '(displayname=*)';

               // Creo el filtro para la busqueda
               // objectClass=user -  asegura que el objeto sea de tipo usuario.
               // objectCategory=person - excluye objetos como grupos y equipos (esto es importante).
               // userAccountControl - Asegura que el objeto este activo (No tiene el bit de cuenta deshabilitada, es decir, está activo)
               //Tengo que quitar (objectClass=user) ya que sino no aparecen los contactos (en este caso en Departamentos sino no aparece EMPRESAS EXTERNAS
//               $filter = '(&' . $filter_to_search . '(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
               $filter = '(&' . $filter_to_search . '(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';
               //Montamos la raíz de búsqueda: DN base y las distintas OU dentro de esta DN base
	       foreach ($ldap_dn_ou as $ou_name) {
                  //Si txtDept1 venimos de la página datos_active.php y no quiero que aparezca la opción de Empresas Externas
                  if (($name_object_html === 'txtDept1') && ($ou_name === 'Contactos')) continue;
                  array_push($ous, 'OU=' . $ou_name .',' . $ldap_dn);
               }
           }
           $resultsByDn  = ["count" => 0]; // iniciamos estructura igual que ldap_get_entries
           foreach ($ous as $baseDn) {
               // Atributos que queremos recuperar
               // En esta función los $attrs los pasamos a la funcion, esta llamada a ldap_search pasamos el atributo por el que queremos que ordene LDAP_CONTROL_SORTREQUEST
               $result = ldap_search($ldap_conn, $baseDn, $filter, array($attrs), 0, -1, -1, LDAP_DEREF_NEVER, [['oid' => LDAP_CONTROL_SORTREQUEST, 'value' => [['attr'=>$attrs]]]]);

	       if ($result) {
                  $entries = ldap_get_entries($ldap_conn, $result);
                  if ($entries['count'] > 0) {
//Comento $message_succes debido a que desde datos_active, si fallara la funcion load_user esta llamada (fill_combobox) es posterior y pone $message_success a yes con lo que
//la funcion print_message sacaria  el mensaje en verde en vez de rojo.
//                  $message_success = 'yes';
//Pero como también utilizo esta variable $message_success más abajo para montar el select, he tenido que añadir $consult_ok para este menester.
                     for ($i = 0; $i < $entries['count']; $i++) {
                        $resultsByDn[$resultsByDn['count']] = $entries[$i];
                        $resultsByDn['count']++;
                     }
                     $consult_ok = true;
                  }
               } else {
                  $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
                  $message[] = 'Filtro: ' . $filter;
		  $message[] = 'Base búsqueda: ' . $baseDn;
                  continue;
               }
            }
      }
   } else {
      $message[] = 'Falta usuario o contraseña.';
   }
   foreach ($message as &$msg) {
      
   }
   unset($msg); //La referencia a $value del último elemento del array permanece incluso después del bucle foreach. Se recomienda destruir estas referencias utilizando unset().
   if ($consult_ok) {
      $resultsByDn = sortLdapEntries($resultsByDn, $attrs);
      
      $valor = '';
      $classes .= ' select-height select-border select-background-color';
      // Construimos el Select
      echo '<select id="' . $name_object_html . '" name="' . $name_object_html . '"';
      switch ($attrs) {
         case 'department':
            echo ' title="Departamento al que pertenece el usuario.<br>(ej. Intervenci&oacute;n)"';
            break;
         case 'physicaldeliveryofficename':
            echo ' title="Introduzca la ubicaci&oacute;n de su oficina.<br>(ej. Ayuntamiento - 3&ordf; Planta - Alcald&iacute;a)"';
            break;
      }
      echo ' class="' . $classes . '" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-html="true"';
      echo (($module_name === 'datos_active') && ($name_object_html === 'txtOffice')) ? ' disabled>' : ' >';
      echo '<option value=0>Elija opci&oacute;n</option>';

      // Recorremos los datos devueltos
      for ($i=0; $i < $resultsByDn['count']; $i++) {
         if (isset($resultsByDn[$i][$attrs])) {
            if($valor!=$resultsByDn[$i][$attrs][0]) {
               $currentVal = $resultsByDn[$i][$attrs][0];
               $isSelected = ($selected_value !== null && strcasecmp($currentVal, $selected_value) === 0) ? ' selected' : '';
               $safeVal = htmlspecialchars($currentVal);
               echo '<option value="'. $safeVal . '"' . $isSelected . '>';
               echo $safeVal . '</option>';
               $valor=$currentVal;
            }
         }
      }
      echo '</select>';
   }else{
      echo '<select id="' . $name_object_html . '" name="' . $name_object_html . '" class="' . $classes . '">';
      echo '<option value=0>Sin Datos</option>';
      echo '</select>';
   }
   if (count($message)>0) $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}

/**
 * Ordena un array tipo ldap_get_entries por el atributo que se indique.
 *
 * @param array $entries  Array devuelto por ldap_get_entries (combinado o no).
 * @param string $attr    Nombre del atributo LDAP por el que ordenar (ej: 'displayname').
 * @return array          Array con la misma estructura que ldap_get_entries pero ordenado.
 */
function sortLdapEntries(array $entries, string $attr = 'displayname'): array
{
    if (!isset($entries['count']) || $entries['count'] === 0) {
        return $entries; // nada que ordenar
    }

    // Copiar las entradas numéricas
    $items = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $items[] = $entries[$i];
    }

    // Ordenar con usort (teniendo en cuenta acentos)
    usort($items, function ($a, $b) use ($attr) {
        $valA = isset($a[strtolower($attr)][0]) ? $a[strtolower($attr)][0] : '';
        $valB = isset($b[strtolower($attr)][0]) ? $b[strtolower($attr)][0] : '';
        
        if (class_exists('Collator')) {
            $collator = new Collator('es_ES.UTF-8');
            return $collator->compare($valA, $valB);
        } else {
            // Fallback si no está instalada la extensión intl
            $unwanted = array(
                'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
                'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
                'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
                'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
                'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
            );
            $valA_clean = strtr($valA, $unwanted);
            $valB_clean = strtr($valB, $unwanted);
            return strcasecmp($valA_clean, $valB_clean);
        }
    });

    // Reconstruir la estructura ldap_get_entries
    $sorted = ['count' => count($items)];
    foreach ($items as $i => $entry) {
        $sorted[$i] = $entry;
    }

    return $sorted;
}
?>
