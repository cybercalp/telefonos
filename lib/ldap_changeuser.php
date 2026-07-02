<?php
// ACTUALIZA DATOS DE UN USUARIO EN EL AD
require_once('./lib/crypt.php');
require_once(__DIR__ . '/../private/config.php');

use LDAP\Client;

if (!defined('DS')) {
   define('DS', '\\\\');
}

function update_ldap_data($user_dn, ?Client $ldap = null) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_dn, $ldap_user, $ldap_pass, $ldap_admuser, $ldap_admpwd, $app_debug;

   $message = array();
   $message_success = '';

   // Conexión vía Client inyectado
   $conn = $ldap ?? Client::factory();
   $ldap_conn = $conn->getResource();

   if (!$ldap_conn) {
      $message[] = 'No se pudo conectar al servidor LDAP.';
   } else {

      // 1. Determinar si es un cambio PROPIO (comparación robusta de DN)
      $session_dn = isset($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
      $isSelf = !empty($session_dn) && (strcasecmp(trim(str_replace(['\\', ' '], ['', ''], $session_dn)), trim(str_replace(['\\', ' '], ['', ''], $user_dn))) === 0);

      // 3. Comprobar permisos de negocio (¿Puede el usuario actual editar al target?)
      require_once(__DIR__ . '/ldap_permissions.php');
       if (!can_edit_user($ldap_conn, $user_dn)) {
           $message[] = 'No tienes permiso para editar a este usuario (Regla de negocio).';
           $_SESSION['mensaje'] = $message;
          return;
      }

      // Atributos a actualizar
      $modifs = [];
      create_array_datauser($modifs);
      $target_dn = (mb_check_encoding($user_dn, 'UTF-8')) ? $user_dn : utf8_encode($user_dn);

      // --- INTEGRACIÓN TOTP ---
      $logT = date('[Y-m-d H:i:s] ');
      
      if (isset($_SESSION['estado_toggle'])) {
          if ($app_debug) {
              error_log($logT . "Estado Toggle en sesión: " . $_SESSION['estado_toggle']);
          }
          if ($_SESSION['estado_toggle'] == 1 && isset($_SESSION['secretkey']) && isset($_SESSION['guid'])) {
              $encryptedSecret = encryptSecretGCM($_SESSION['secretkey'], $_SESSION['guid']);
              $modifs['pager'] = $encryptedSecret;
              if (isset($_SESSION['image'])) {
                  $imageData = $_SESSION['image'];
                  if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/', $imageData, $matches)) {
                      $modifs['photo'] = base64_decode($matches[2]);
                  }
              }
              if ($app_debug) error_log($logT . "Preparando para ACTIVAR TOTP");
          } else if ($_SESSION['estado_toggle'] == 0) {
              $modifs['pager'] = array();
              $modifs['photo'] = array();
              if ($app_debug) error_log($logT . "Preparando para DESACTIVAR TOTP (limpiando pager/photo)");
          }
      }
      // ------------------------

      // 4. Intentar modificación con cuenta ADMIN
      $result = @ldap_modify($ldap_conn, $target_dn, $modifs);

      if ($result) {
          $message[] = 'Datos actualizados correctamente (usando la cuenta administrativa)';
          $message_success = 'yes';
          if (isset($_SESSION['ldap_user'])) {
              error_log("[AUDIT] acting_user={$_SESSION['ldap_user']} target_dn={$user_dn}");
          }
          if ($app_debug && isset($_SESSION['estado_toggle'])) error_log($logT . "LDAP Modify SUCCESS");
      } else {
          $ldap_err = ldap_error($ldap_conn);
          if ($app_debug && isset($_SESSION['estado_toggle'])) error_log($logT . "LDAP Modify ERROR: " . $ldap_err);
          $message[] = "ERROR: No se han podido guardar los cambios.";

          if (ldap_errno($ldap_conn) == 50) {
              $message[] = "Detalle técnico: La cuenta $ldap_admuser se conectó correctamente al AD, PERO el Active Directory le ha denegado la operación (Insufficient Access). Revisa los permisos explícitos de esta cuenta (Opers. de Cuentas) sobre estos atributos en AD.";
          } else {
              $message[] = "Motivo: $ldap_err";
          }
       }
    }
   $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}


/**
 * CREA UN ARRAY CON LOS DATOS A MODIFICAR EN LDAP
 * Asegura que los datos estén en UTF-8 y no usa ldap_escape (que es solo para filtros)
 */
function create_array_datauser(&$modifs) {
    // Función auxiliar para asegurar UTF-8 sin escapar caracteres para filtros
    $toLdap = function($val) {
        if ($val === null || trim($val) === '') return array(); // Vacía el atributo en AD
        return trim($val);
    };

    $modifs['givenname'] = $toLdap($_POST['txtGivenName'] ?? '');
    $modifs['initials'] = $toLdap($_POST['txtInitials'] ?? '');
    $modifs['sn'] = $toLdap($_POST['txtSN'] ?? '');
    $modifs['displayname'] = $toLdap($_POST['txtDisplayName'] ?? '');
    $modifs['company'] = $toLdap($_POST['txtCompany'] ?? '');
    $modifs['department'] = $toLdap($_POST['txtDept1'] ?? '');
    $modifs['physicalDeliveryOfficeName'] = $toLdap($_POST['txtOffice'] ?? '');
    $modifs['title'] = $toLdap($_POST['txtTitle'] ?? '');
    $modifs['telephonenumber'] = $toLdap($_POST['txtTelPhone'] ?? '');
    $modifs['mobile'] = $toLdap($_POST['txtTelMobile'] ?? '');
    $modifs['othermobile'] = $toLdap($_POST['txtTelotherMobile'] ?? '');
    $modifs['homephone'] = $toLdap($_POST['txtTelDep'] ?? '');
    $modifs['otherhomephone'] = $toLdap($_POST['txtTelotherDep'] ?? '');
    $modifs['facsimiletelephonenumber'] = $toLdap($_POST['txtTelFax'] ?? '');
    $modifs['mail'] = $toLdap($_POST['txtEmailAddress'] ?? '');
    $modifs['othermailbox'] = $toLdap($_POST['txtEmailRestore'] ?? '');
    $modifs['streetaddress'] = $toLdap($_POST['txtAddress'] ?? '');
    $modifs['l'] = $toLdap($_POST['txtCity'] ?? '');
    $modifs['st'] = $toLdap($_POST['txtState'] ?? '');
    $modifs['postalcode'] = $toLdap($_POST['txtPostalCode'] ?? '');
    $modifs['info'] = $toLdap($_POST['txtInfo'] ?? '');

    // Visibilidad (wWWHomePage de 4 bits: Directorio, Foto, Email, Presencia)
    if (isset($_POST['txtSwitch1'])) {
       $sw1 = $_POST['txtSwitch1'] === '1' ? '1' : '0';
       $sw2 = ($_POST['txtSwitch2'] ?? '0') === '1' ? '1' : '0';
       $sw3 = ($_POST['txtSwitch3'] ?? '0') === '1' ? '1' : '0';
       $sw4 = ($_POST['txtSwitch4'] ?? '0') === '1' ? '1' : '0';
       $modifs['wwwhomepage'] = $toLdap($sw1 . $sw2 . $sw3 . $sw4);
    }

    // Fotos (Binario)
    if (!empty($_POST['txtThumbnailPhoto'])) {
       $data = $_POST['txtThumbnailPhoto'];
       if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/', $data, $matches)) {
         $modifs['thumbnailphoto'] = base64_decode($matches[2]);
       }
    } else if (isset($_POST['txtThumbnailPhoto'])) {
       $modifs['thumbnailphoto'] = array();
    }

    if (!empty($_POST['txtJpegPhoto'])) {
       $data = $_POST['txtJpegPhoto'];
       if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/', $data, $matches)) {
         $modifs['jpegphoto'] = base64_decode($matches[2]);
       }
    } else if (isset($_POST['txtJpegPhoto'])) {
       $modifs['jpegphoto'] = array();
    }
}
?>
