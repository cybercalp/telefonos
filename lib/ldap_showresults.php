<?php
require_once('./lib/multisortldap.php');
require_once('./lib/db_presencia_select.php');
require_once('./lib/ldap_permissions.php');
require_once(__DIR__ . '/../private/config.php');

/**
 * Obtiene el teléfono fijo de un equipo (computer) a partir de su CN.
 * Usa caché estática para evitar consultas LDAP repetidas en la misma petición.
 *
 * @param  resource $ldap_conn     Conexión LDAP activa
 * @param  string   $computer_name Nombre del equipo (CN)
 * @return string|null  Número de teléfono del equipo o null si no tiene
 */
function get_computer_phone($ldap_conn, string $computer_name): ?string {
    global $ldap_computers_dn;
    static $cache = [];

    if (empty($computer_name) || empty($ldap_computers_dn)) return null;

    // logonWorkstation puede estar en binario/hex; intentar decodificar si no es legible
    if (preg_match('/[^\x20-\x7E]/', $computer_name)) {
        // Valor binario: intentar leer como string UTF-16LE (formato AD)
        $decoded = @iconv('UTF-16LE', 'UTF-8', $computer_name);
        if ($decoded !== false && !empty($decoded)) {
            $computer_name = $decoded;
        }
    }
    // Puede contener valores separados por coma; tomar el primero
    $computer_name = trim(explode(',', $computer_name)[0]);
    if (empty($computer_name)) return null;

    if (array_key_exists($computer_name, $cache)) {
        return $cache[$computer_name];
    }

    $filter = '(cn=' . ldap_escape($computer_name, '', LDAP_ESCAPE_FILTER) . ')';
    $result = @ldap_search($ldap_conn, $ldap_computers_dn, $filter, ['telephonenumber']);

    if ($result) {
        $comp_entries = ldap_get_entries($ldap_conn, $result);
        if ($comp_entries['count'] > 0 && !empty($comp_entries[0]['telephonenumber'][0])) {
            $cache[$computer_name] = $comp_entries[0]['telephonenumber'][0];
            return $cache[$computer_name];
        }
    }

    $cache[$computer_name] = null;
    return null;
}

if (!defined('DS')) {
   define('DS', '\\\\');
}

// MUESTRA LOS RESULTADOS FILTRADOS
function show_ldapresults($filter_to_search, $order, $showInactive = false) {
   global $ldap_protocol, $ldap_host, $ldap_port, $ldap_domain, $ldap_user, $ldap_pass, $ldap_dn, $ldap_dn_ou;

   $message = array();
   $message_success = 'no';

   $ldap_conn = ldap_connect(get_ldap_uri());

   if (!$ldap_conn) {
     $message[] = 'No se pudo conectar al servidor LDAP.';
   } else {
      ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
      ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

      if (ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
         
         $attrs = array('jpegphoto', 'thumbnailphoto', 'info', 'displayname', 'samaccountname', 'useraccountcontrol', 'employeenumber', 'title', 'department', 'mail', 'physicaldeliveryofficename', 'streetaddress', 'postalcode', 'l', 'st', 'co', 'description', 'comment', 'telephonenumber', 'othertelephone', 'homephone', 'otherhomephone', 'mobile', 'othermobile', 'facsimiletelephoneNumber', 'otherfacsimiletelephoneNumber', 'wwwhomepage', 'secretary', 'postofficebox', 'objectclass', 'distinguishedname', 'logonworkstation');

                   // Lógica de Visibilidad dinámica:
          // 1. Por defecto: Sólo públicos (wWWHomePage=1*)
          // 2. Si es Manager: Ve a sus subordinados recursivamente (extensible match "In Chain")
          // 3. Si es Contact Manager: Ve todos los contactos (objectClass=contact)
          $current_dn = !empty($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
          $visibility_parts = ['(wWWHomePage=1*)'];
          
          if (!empty($current_dn)) {
              // Excepción para subordinados (recursive search in chain)
              $visibility_parts[] = '(manager:1.2.840.113556.1.4.1941:=' . ldap_escape($current_dn, '', LDAP_ESCAPE_FILTER) . ')';
          }
          
          if (is_admin_user()) {
              // Si es administrador, ve todos los objetos sin restricciones
              $visibility_filter = '(objectClass=*)';
          } else {
              if (can_manage_contacts()) {
                  // Excepción para ver todos los contactos
                  $visibility_parts[] = '(objectClass=contact)';
              }
              $visibility_filter = (count($visibility_parts) > 1) ? '(|' . implode('', $visibility_parts) . ')' : $visibility_parts[0];
          }

          $filter = '(&' . $filter_to_search . $visibility_filter . '(objectCategory=person)' . ($showInactive ? '' : '(!(userAccountControl:1.2.840.113556.1.4.803:=2))') . ')';

          $all_entries = array('count' => 0);
          $limitReached = false;
          $search_success = false;

          if (!empty($ldap_dn_ou) && is_array($ldap_dn_ou)) {
              foreach ($ldap_dn_ou as $ou_name) {
                  $current_search_dn = "OU={$ou_name}," . $ldap_dn;
                  $result = @ldap_search($ldap_conn, $current_search_dn, $filter, $attrs);
                  
                  if (ldap_errno($ldap_conn) === 4) {
                      $limitReached = true;
                  }
                  
                  if ($result) {
                      $search_success = true;
                      $current_entries = ldap_get_entries($ldap_conn, $result);
                      for ($j = 0; $j < $current_entries['count']; $j++) {
                          $all_entries[] = $current_entries[$j];
                          $all_entries['count']++;
                      }
                  } elseif (ldap_errno($ldap_conn) !== 4) {
                      error_log("LDAP search failed. DN: $current_search_dn Filter: $filter — Error: " . ldap_error($ldap_conn));
                  }
              }
              $entries = $all_entries;
          } else {
              // Fallback si no hay OU definida
              $result = @ldap_search($ldap_conn, $ldap_dn, $filter, $attrs);
              $limitReached = (ldap_errno($ldap_conn) === 4);
              if ($result === false && !$limitReached) {
                  error_log("LDAP search failed. Filter: $filter — Error: " . ldap_error($ldap_conn));
              }
              if ($result) {
                  $search_success = true;
                  $entries = ldap_get_entries($ldap_conn, $result);
              }
          }

          if ($search_success) {
             // FILTRAR POR PRESENCIA (Presentes / Ausentes / Indeterminado / Inactivos)
             $chkPresente = (!isset($_REQUEST['btnBuscar']) || isset($_REQUEST['chkPresente']));
             $chkAusente = (!isset($_REQUEST['btnBuscar']) || isset($_REQUEST['chkAusente']));
             $chkIndeterminado = (!isset($_REQUEST['btnBuscar']) || isset($_REQUEST['chkIndeterminado']));
             $chkInactivo = (isset($_REQUEST['btnBuscar']) && isset($_REQUEST['chkInactivo']));

             // FILTRO MODALIDAD: Teletrabajo / Presencial
             // Por defecto (sin búsqueda activa) se muestran ambos
             $chkTeletrabajo = (!isset($_REQUEST['btnBuscar']) || isset($_REQUEST['chkTeletrabajo']));
             $chkPresencial  = (!isset($_REQUEST['btnBuscar']) || isset($_REQUEST['chkPresencial']));

             $filtered_entries = array('count' => 0);
             for ($j = 0; $j < $entries['count']; $j++) {
                 $entry = $entries[$j];
                 if (in_array('contact', $entry['objectclass'])) {
                     $keep = $chkIndeterminado; // Contacts have no presence, treat as Indeterminado
                 } else {
                     $uac = $entry['useraccountcontrol'][0] ?? 0;
                     $isInactive = ($uac & 2);

                     if ($isInactive) {
                         $keep = $chkInactivo;
                     } else {
                         $entry['wwwhomepage'][0] = substr((isset($entry['wwwhomepage'][0]) ? $entry['wwwhomepage'][0] : '').'0000', 0, 4);
                         if ($entry['wwwhomepage'][0][3] !== '1') {
                             $keep = $chkIndeterminado; // Led gris -> Indeterminado
                         } elseif (isset($entry['employeenumber']) && user_in($entry['employeenumber'][0])) {
                             $keep = $chkPresente; // Led verde -> Presente
                         } else {
                             $keep = $chkAusente; // Led rojo -> Ausente
                         }
                     }

                     // Filtro secundario: modalidad Teletrabajo / Presencial
                     if ($keep && !$isInactive) {
                         $office = $entry['physicaldeliveryofficename'][0] ?? '';
                         $isTeletrabajo = (stripos($office, 'Teletrabajo') === 0);
                         if ($isTeletrabajo && !$chkTeletrabajo) {
                             $keep = false;
                         } elseif (!$isTeletrabajo && !$chkPresencial) {
                             $keep = false;
                         }
                     }
                 }

                 if ($keep) {
                     $filtered_entries[] = $entry;
                     $filtered_entries['count']++;
                 }
             }
             $entries = $filtered_entries;

             if ($entries['count'] > 0) {
               $message_success = 'yes';
               multisort_results($entries, $order);

               // MODO MÓVIL vs ESCRITORIO
               $isMobile = !empty($_SESSION['is_mobile_view']);

               // HEADER RESULTADOS
               $headerMargin = $isMobile ? 'mb-4' : 'mb-6';
               echo '<div class="' . $headerMargin . ' sticky top-0 z-30 px-6 md:px-8 py-3 md:py-4 bg-slate-50 dark:bg-slate-900 border-b border-slate-200/80 dark:border-slate-700/80 shadow-sm flex items-center justify-between">';
               echo '  <h3 class="text-xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">Directorio</h3>';
               echo '  <span class="bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 text-xs font-bold px-3 py-1 rounded-full shadow-sm">' . $entries['count'] . ($limitReached ? '+' : '') . ' Resultados</span>';
               echo '</div>';

               if ($limitReached) {
                   echo '<div class="mx-6 md:mx-8 mb-6 flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-2xl text-amber-800 dark:text-amber-400 shadow-sm border-l-4 border-l-amber-500 animate-pulse-slow">';
                   echo '  <i class="fas fa-exclamation-triangle text-xl shrink-0 text-amber-600 dark:text-amber-500"></i>';
                   echo '  <div>';
                   echo '    <p class="text-sm font-bold leading-tight">Búsqueda limitada</p>';
                   echo '    <p class="text-xs font-medium leading-tight mt-0.5 opacity-90">Se ha alcanzado el límite máximo (1000). Usa filtros más concretos para una búsqueda exacta.</p>';
                   echo '  </div>';
                   echo '</div>';
               }

               if ($isMobile) {
                    echo '<div class="mx-6 md:mx-8 pb-6 md:pb-8 flex flex-col gap-5 pt-1">';
                } else {
                    // Pre-calcular clases por defecto basadas en cookies para evitar FOUC (layout shift)
                    $viewMode = $_COOKIE['viewMode'] ?? 'list';
                    $gridCols = isset($_COOKIE['gridCols']) ? (int)$_COOKIE['gridCols'] : 3;
                    $containerDefaultClass = ($viewMode === 'grid') ? ('grid gap-6 items-stretch ' . ($gridCols === 1 ? 'grid-cols-1' : ($gridCols === 2 ? 'grid-cols-2' : ($gridCols === 3 ? 'grid-cols-3' : 'grid-cols-4')))) : 'flex flex-col gap-4';

                    echo '<div class="mx-6 md:mx-8 pb-6 md:pb-8 ' . $containerDefaultClass . '" :class="{ \'grid gap-6 items-stretch grid-cols-1\': viewMode === \'grid\' && gridCols === 1, \'grid-cols-2 grid gap-6 items-stretch\': viewMode === \'grid\' && gridCols === 2, \'grid-cols-3 grid gap-6 items-stretch\': viewMode === \'grid\' && gridCols === 3, \'grid-cols-4 grid gap-6 items-stretch\': viewMode === \'grid\' && gridCols === 4, \'flex flex-col gap-4\': viewMode === \'list\' }">';
                }

               for ($i=0; $i < $entries['count']; $i++) {
                  if(in_array('contact', $entries[$i]['objectclass'])) {
                     show_contact($ldap_conn, $ldap_dn, $entries, $i, $i);
                  } else {
                     show_user($ldap_conn, $ldap_dn, $entries, $i, $i);
                  }
               }
               echo '</div>'; // Fin grid/list
            } else {
               // No results state
               echo '<div class="m-6 md:m-8 flex flex-col items-center justify-center p-12 !bg-white dark:!bg-slate-800 rounded-3xl !border border-dashed !border-slate-300 dark:!border-slate-600 shadow-sm">';
               echo '  <i class="fas fa-search text-4xl text-slate-300 mb-4"></i>';
               echo '  <p class="text-lg font-medium text-slate-600 dark:text-slate-300">No se encontraron resultados</p>';
               echo '  <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Prueba ajustando los filtros de búsqueda</p>';
               echo '</div>';
            }
         } else {
            $message[] = 'Error en la búsqueda LDAP con el filtro dado.';
         }
         ldap_unbind($ldap_conn);
      } else {
         $message[] = 'Usuario de consulta LDAP o contraseña incorrectos.';
      }
   }
   
   if (count($message)>0) $_SESSION['mensaje'] = $message;
   $_SESSION['mensaje_css'] = $message_success;
}

// ==========================================
// MUESTRA CONTACTO (EMPRESAS EXTERNAS ETC)
// ==========================================
function show_contact($ldap_conn, $base_dn, $entries, $i, $cardIndex = 0) {
    $delay = min($cardIndex * 35, 800); // máx 800ms delay
    $dn = $entries[$i]['distinguishedname'][0];
    $dn_enc = urlencode($dn);
    $canManage = can_edit_contact($dn, $ldap_conn);
    $isAuthenticated = !empty($_SESSION['is_authenticated']);

    $isMobile = !empty($_SESSION['is_mobile_view']);

    // Detectar valores de diseño de las cookies para evitar FOUC (fichas pegadas en carga inicial)
    $viewMode = $_COOKIE['viewMode'] ?? 'list';
    $cardDefaultClass = ($viewMode === 'grid') ? 'py-3 px-2 flex flex-col gap-3 h-full justify-between' : 'p-3.5 flex flex-col gap-2.5';
    $innerFlexDefaultClass = ($viewMode === 'grid') ? 'flex-col gap-3 items-center' : 'flex-row items-center gap-8 md:gap-10';
    $innerPhotoDefaultClass = ($viewMode === 'grid') ? 'mx-auto pt-1' : 'pt-0';
    $innerInfoAlignDefaultClass = ($viewMode === 'grid') ? 'text-center' : 'text-left';
    $innerInfoFlexDefaultClass = ($viewMode === 'grid') ? 'items-start px-2 text-left w-full' : 'items-start';
    $innerPhonesDefaultClass = ($viewMode === 'grid') ? 'w-full px-2 mt-2' : 'md:min-w-[220px] md:border-l border-slate-200 dark:border-slate-700/80 md:pl-6';
    $innerSecDefaultClass = ($viewMode === 'grid') ? 'w-full bg-blue-50/50 dark:bg-blue-900/20 p-3.5 rounded-xl border border-blue-100 dark:border-blue-800/50 mt-2' : 'md:min-w-[240px] md:max-w-[300px] md:border-l border-slate-200/30 dark:border-slate-700/30 md:pl-5 bg-transparent border-0';

    $mobileClasses = $isMobile ? ' p-5 flex flex-col gap-4' : '';

    // (Presencia no configurada para contactos externos)
    echo '<div class="!bg-white dark:!bg-slate-800 !border !border-slate-200 dark:!border-slate-700/80 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 hover:scale-[1.015] hover:border-slate-300 dark:hover:border-slate-600 transition-all duration-300 ease-out relative overflow-hidden group card-animated' . $mobileClasses . ($isMobile ? '' : ' ' . $cardDefaultClass) . '"'
         . ' style="animation-delay:' . $delay . 'ms"'
         . ($isMobile ? '' : " :class=\"{ 'py-3 px-2 flex flex-col gap-3 h-full justify-between': viewMode === 'grid', 'p-3.5 flex flex-col gap-2.5': viewMode === 'list' }\"") . '>';
    
    // Linea superior corporativa CONTACTO verde degradado de alta visibilidad
    echo '<div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-700 to-emerald-600 opacity-60 group-hover:opacity-100 transition-opacity"></div>';

    // DIRECCION CONTACTO (Cálculo disponible para ambos layouts)
    $address1 = array_filter([$entries[$i]['streetaddress'][0] ?? '']);
    $address2 = array_filter([$entries[$i]['postalcode'][0] ?? '', $entries[$i]['l'][0] ?? '', $entries[$i]['st'][0] ?? '', $entries[$i]['co'][0] ?? '']);

    // === FILA SUPERIOR: Foto + Info + Teléfonos (horizontal en lista) ===
    if ($isMobile) {
        // --- MOBILE LAYOUT ---
        echo '<div class="flex items-start gap-4">';
        
        // Columna Izquierda: Foto
        echo '  <div class="flex flex-col items-center flex-shrink-0 w-24">';
        echo '    <div class="relative inline-block mb-1.5">';
        if (isset($entries[$i]['thumbnailphoto']) && isset($entries[$i]['wwwhomepage']) && $entries[$i]['wwwhomepage'][0][1]==='1') {
            echo '      <img src="data:image/jpeg;base64,' . base64_encode($entries[$i]['thumbnailphoto'][0]) . '" class="w-20 h-20 rounded-full object-cover border-4 border-slate-50 dark:border-slate-700 shadow-md">';
        } else {
            echo '      <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800/80 border-4 border-slate-50 dark:border-slate-700 shadow-md flex items-center justify-center text-slate-400 dark:text-slate-500"><i class="fas fa-address-book text-3xl"></i></div>';
        }
        echo '    </div>';

        // Botón Añadir a Agenda (vCard con foto de perfil)
        $vcardUrl = 'lib/generate_vcard.php?dn=' . urlencode($dn);
        echo '    <a href="' . $vcardUrl . '" class="w-full mt-2.5 py-1 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-600 hover:text-white transition-all text-[10px] font-bold flex items-center justify-center gap-1 border border-blue-500/20 active:scale-95 transform">';
        echo '      <i class="fas fa-user-plus text-[9px]"></i> Agenda';
        echo '    </a>';
        echo '  </div>';

        // Columna Derecha: Nombre, Cargo, Departamento
        echo '  <div class="flex-1 min-w-0 pt-1 flex flex-col justify-start">';
        echo '    <h3 class="text-base font-black text-slate-900 dark:text-white leading-tight mb-1 break-words">' . ((isset($entries[$i]['displayname'])) ? htmlspecialchars($entries[$i]['displayname'][0]) : 'Sin Nombre') . '</h3>';
        
        if (!empty($entries[$i]['title'][0])) {
            $cargo = $entries[$i]['title'][0];
            echo '    <a href="mobile.php?btnBuscar=1&chkPresente=1&chkAusente=1&chkIndeterminado=1&chkPresencial=1&chkTeletrabajo=1&txtCargo='.urlencode($cargo).'" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 leading-tight mb-2 uppercase tracking-tight active:scale-95 transition-transform block">' . htmlspecialchars($cargo) . '</a>';
        }
        
        echo '    <div class="flex flex-col gap-1 mt-1.5 items-start">';
        
        if (isset($entries[$i]['department'])) {
            $dep = $entries[$i]['department'][0];
            echo '      <a href="mobile.php?btnBuscar=1&chkPresente=1&chkAusente=1&chkIndeterminado=1&chkPresencial=1&chkTeletrabajo=1&txtDepartamento='.urlencode($dep).'" class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter max-w-full truncate active:scale-95 transition-transform group/link"><i class="fas fa-sitemap opacity-60 flex-shrink-0 group-hover/link:text-blue-500 transition-colors w-4 text-center"></i> <span class="truncate">' . htmlspecialchars($dep) . '</span></a>';
        }

        if ((isset($entries[$i]['mail'])) && isset($entries[$i]['wwwhomepage']) && $entries[$i]['wwwhomepage'][0][2]==='1') {
            $safeMail = htmlspecialchars($entries[$i]['mail'][0]);
            echo '      <a href="mailto:'.$safeMail.'" class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter max-w-full truncate active:scale-95 transition-transform"><i class="fas fa-envelope opacity-60 flex-shrink-0 w-4 text-center"></i> <span class="truncate lowercase">' . $safeMail . '</span></a>';
        }

        if (!empty($address1) || !empty($address2)) {
            echo '      <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-tight flex max-w-full whitespace-normal break-words">';
            echo '        <i class="fas fa-map-marker-alt text-slate-400 dark:text-slate-500 mt-[2px] w-4 text-center flex-shrink-0 text-[10px]"></i>';
            echo '        <div class="flex flex-col min-w-0 ml-1.5">';
            if (!empty($address1)) {
                echo '          <span>' . htmlspecialchars(implode(' - ', $address1)) . '</span>';
            }
            if (!empty($address2)) {
                echo '          <span class="opacity-80 text-[10px]">' . htmlspecialchars(implode(' - ', $address2)) . '</span>';
            }
            echo '        </div>';
            echo '      </div>';
        }
        
        if (!empty($entries[$i]['description'][0])) {
            echo '      <div class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter max-w-full truncate"><i class="fas fa-desktop opacity-60 flex-shrink-0 w-4 text-center"></i> <span class="truncate">' . htmlspecialchars($entries[$i]['description'][0]) . '</span></div>';
        }
        
        echo '    </div>';        
        echo '  </div>';
        echo '</div>'; // Fin flex
    } else {
        // --- DESKTOP LAYOUT ---
        echo '<div class="flex ' . $innerFlexDefaultClass . '" :class="{ \'flex-col gap-3 items-center\': viewMode === \'grid\', \'flex-row items-center gap-8 md:gap-10\': viewMode === \'list\' }">';

        // FOTO Y ESTADO
        echo '<div class="flex-shrink-0 flex flex-col items-center ' . $innerPhotoDefaultClass . '" :class="{ \'mx-auto pt-1\': viewMode === \'grid\', \'pt-0\': viewMode === \'list\' }">';
        echo '  <div class="relative inline-block">';
        
        // TAGS (Igual que en usuarios)
        $tagsTooltip = 'No hay etiquetas asignadas';
        if (isset($entries[$i]['info'])) {
            $rawInfo = $entries[$i]['info'][0];
            $tagsList = preg_split('/[,;]+/', $rawInfo, -1, PREG_SPLIT_NO_EMPTY);
            if (!empty($tagsList)) {
                $tagsTooltip = "<b>Etiquetas de búsqueda:</b><br>• " . implode("<br>• ", array_map('trim', array_map('htmlspecialchars', $tagsList)));
            }
        }

        if (isset($entries[$i]['thumbnailphoto']) && isset($entries[$i]['wwwhomepage']) && $entries[$i]['wwwhomepage'][0][1]==='1') {
            echo '    <img src="data:image/jpeg;base64,' . base64_encode($entries[$i]['thumbnailphoto'][0]) . '" data-html-tooltip="' . htmlspecialchars($tagsTooltip, ENT_QUOTES, 'UTF-8') . '" class="w-20 h-20 rounded-full object-cover border-4 border-slate-50 dark:border-slate-700 shadow-md transform group-hover:scale-105 transition-transform cursor-help">';
        } else {
            echo '    <div data-html-tooltip="' . htmlspecialchars($tagsTooltip, ENT_QUOTES, 'UTF-8') . '" class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800/80 border-4 border-slate-50 dark:border-slate-700 shadow-md flex items-center justify-center text-slate-400 dark:text-slate-500 transform group-hover:scale-105 transition-transform cursor-help"><i class="fas fa-address-book text-3xl"></i></div>';
        }
        echo '  </div>';
        
        // BOTONES ACCION CONTACTO (Bajo la foto)
        if ($canManage && !$isMobile) {
            echo '<div class="flex gap-1.5 mt-2 justify-center">';
            echo '  <button type="button" @click="$dispatch(\'open-secretary-modal\', {dn: \'' . addslashes($entries[$i]['distinguishedname'][0]) . '\', name: \'' . addslashes($entries[$i]['displayname'][0]) . '\', type: \'contacts\'})" class="w-8 h-8 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 flex items-center justify-center transition-all shadow-md" title="Vincular Empresas"><i class="fas fa-link text-[10px]"></i></button>';
            echo '  <a href="./contact_edit?dn='.$dn_enc.'" class="w-8 h-8 rounded-lg bg-blue-600 text-white hover:bg-blue-700 flex items-center justify-center transition-all shadow-md" title="Editar Contacto"><i class="fas fa-user-edit text-[10px]"></i></a>';
            echo '  <button type="button" @click="$dispatch(\'confirm-delete-contact\', {dn: \'' . addslashes($entries[$i]['distinguishedname'][0]) . '\', name: \'' . addslashes($entries[$i]['displayname'][0] ?? '') . '\'})" class="w-8 h-8 rounded-lg bg-rose-500 text-white hover:bg-rose-600 flex items-center justify-center transition-all shadow-md" title="Eliminar Contacto"><i class="fas fa-trash-alt text-[10px]"></i></button>';
            echo '</div>';
        }
        echo '</div>'; // Fin FOTO

        // INFO PRINCIPAL
        $innerDisplayNameDefaultClass = ($viewMode === 'grid') ? 'whitespace-normal break-words' : 'truncate';
        echo '<div class="flex-1 min-w-0 ' . $innerInfoAlignDefaultClass . '" :class="{ \'text-center\': viewMode === \'grid\', \'text-left\': viewMode === \'list\' }">';
        echo '  <h3 class="text-base font-black text-slate-900 dark:text-white mb-1 leading-tight ' . $innerDisplayNameDefaultClass . '" :class="{ \'whitespace-normal break-words\': viewMode === \'grid\', \'truncate\': viewMode === \'list\' }">' . ((isset($entries[$i]['displayname'])) ? htmlspecialchars($entries[$i]['displayname'][0]) : 'Sin Nombre') . '</h3>';
        
        if (!empty($entries[$i]['title'][0])) {
            echo '  <form method="GET" action="index.php" style="margin:0;padding:0">';
            echo '    <input type="hidden" name="btnBuscar" value="1">';
            echo '    <input type="hidden" name="chkPresente" value="1">';
            echo '    <input type="hidden" name="chkAusente" value="1">';
            echo '    <input type="hidden" name="chkIndeterminado" value="1">';
            echo '    <input type="hidden" name="chkPresencial" value="1">';
            echo '    <input type="hidden" name="chkTeletrabajo" value="1">';
            echo '    <input type="hidden" name="txtCargo" value="' . htmlspecialchars($entries[$i]['title'][0]) . '">';
            echo '    <button type="submit" class="inline-flex items-start text-[11px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-tight hover:underline cursor-pointer bg-transparent border-0 p-0 m-0 mb-2" :class="viewMode === \'grid\' ? \'justify-center text-center w-full whitespace-normal break-words leading-tight\' : \'text-left leading-tight truncate\'" title="Filtrar por este cargo"><i class="fas fa-user-tie w-4 text-center opacity-70 mr-1.5 flex-shrink-0" style="margin-top: 2px;"></i> <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars($entries[$i]['title'][0]) . '</span></button>';
            echo '  </form>';
        }
        
        echo '  <div class="flex flex-col gap-1 mt-1.5 ' . $innerInfoFlexDefaultClass . '" :class="{ \'items-start px-2 text-left w-full\': viewMode === \'grid\', \'items-start\': viewMode === \'list\' }">';
        if (isset($entries[$i]['department'])) {
            echo '    <form method="GET" action="index.php" class="flex max-w-full m-0 p-0" :class="viewMode === \'grid\' ? \'whitespace-normal\' : \'truncate\'">';
            echo '      <input type="hidden" name="btnBuscar" value="1">';
            echo '      <input type="hidden" name="chkPresente" value="1">';
            echo '      <input type="hidden" name="chkAusente" value="1">';
            echo '      <input type="hidden" name="chkIndeterminado" value="1">';
            echo '      <input type="hidden" name="chkPresencial" value="1">';
            echo '      <input type="hidden" name="chkTeletrabajo" value="1">';
            echo '      <input type="hidden" name="txtDepartamento" value="' . htmlspecialchars($entries[$i]['department'][0]) . '">';
            echo '      <button type="submit" class="inline-flex items-start text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter hover:text-blue-600 transition-colors cursor-pointer border-0 bg-transparent p-0 m-0 max-w-full" :class="viewMode === \'grid\' ? \'\' : \'text-left truncate\'" title="Filtrar por este departamento"><i class="fas fa-briefcase w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i> <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars($entries[$i]['department'][0]) . '</span></button>';
            echo '    </form>';
        }

        if ((isset($entries[$i]['mail'])) && isset($entries[$i]['wwwhomepage']) && $entries[$i]['wwwhomepage'][0][2]==='1') {
            $safeMail = htmlspecialchars($entries[$i]['mail'][0]);
            echo '    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter m-0 flex max-w-full" :class="viewMode === \'grid\' ? \'whitespace-normal\' : \'truncate\'"><a href="mailto:' . $safeMail .'" class="inline-flex items-start hover:text-blue-600 transition-colors max-w-full" :class="viewMode === \'grid\' ? \'\' : \'truncate\'"><i class="fas fa-envelope w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i> <span class="lowercase" :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . $safeMail . '</span></a></p>';
        }

        if (!empty($address1) || !empty($address2)) {
            echo '    <div class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight m-0 flex max-w-full" :class="viewMode === \'grid\' ? \'whitespace-normal break-words\' : \'truncate\'">';
            echo '      <i class="fas fa-map-marker-alt w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i>';
            echo '      <div class="flex flex-col min-w-0" :class="viewMode === \'grid\' ? \'\' : \'truncate\'">';
            if (!empty($address1)) {
                echo '        <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars(implode(' - ', $address1)) . '</span>';
            }
            if (!empty($address2)) {
                echo '        <span class="opacity-80 text-[10px]" :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars(implode(' - ', $address2)) . '</span>';
            }
            echo '      </div>';
            echo '    </div>';
        }
        echo '  </div>';
        echo '</div>'; // Fin INFO
    }

    // BLOQUE TELEFONOS (Con renderizado estático en vista de lista para evitar descuadre vertical)
    $hasPhones = (!empty($entries[$i]['telephonenumber'][0])) || (!empty($entries[$i]['mobile'][0])) || (!empty($entries[$i]['homephone'][0])) || (!empty($entries[$i]['facsimiletelephonenumber'][0]));
    if ($hasPhones || (!$isMobile && $viewMode === 'list')) {
        if ($isMobile) {
            $telParts = [];
            if (!empty($entries[$i]['telephonenumber'][0])) {
                $tParts = explode('/', $entries[$i]['telephonenumber'][0]);
                foreach ($tParts as $tPart) {
                    $tPart = trim($tPart);
                    if (!empty($tPart)) {
                        $telParts[] = '<a href="tel:'. preg_replace('/[^\d+]/', '', $tPart) . '" class="text-blue-600 dark:text-blue-400 font-bold">' . htmlspecialchars($tPart) . '</a>';
                    }
                }
            }
            if (!empty($entries[$i]['mobile'][0])) {
                $mParts = explode('/', $entries[$i]['mobile'][0]);
                foreach ($mParts as $mPart) {
                    $mPart = trim($mPart);
                    if (!empty($mPart)) {
                        $telParts[] = '<a href="tel:'. preg_replace('/[^\d+]/', '', $mPart) . '" class="text-emerald-600 dark:text-emerald-400 font-bold">' . htmlspecialchars($mPart) . '</a>';
                    }
                }
            }
            if (!empty($telParts)) {
                echo '<div class="mt-0.5 flex items-center bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-2 gap-2 text-xs w-full">';
                echo '  <span class="font-bold text-xs text-slate-500 dark:text-slate-400 uppercase flex-shrink-0 mr-2">LÍNEAS</span>';
                echo '  <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">';
                echo implode(' <span class="text-slate-300 dark:text-slate-600 flex-shrink-0">|</span> ', $telParts);
                echo '  </div>';
                echo '</div>';
            }
        } else {
            echo '<div class="flex flex-col gap-2 flex-shrink-0 ' . $innerPhonesDefaultClass . '" :class="{ \'w-full px-2 mt-2\': viewMode === \'grid\', \'md:min-w-[220px] md:border-l border-slate-200 dark:border-slate-700/80 md:pl-6\': viewMode === \'list\' }" ' . ((!$hasPhones) ? 'x-show="viewMode === \'list\'"' : '') . '>';
            if ($hasPhones) {
                imprime_par_telefonos_tw($entries[$i], 'telephonenumber', 'othertelephone', 'Fijo', 'fa-phone-alt', 'text-slate-800 dark:text-slate-100 !bg-white dark:!bg-slate-700/50 !border !border-slate-200 dark:!border-slate-700/80 shadow-sm', true);
                imprime_par_telefonos_tw($entries[$i], 'mobile', 'othermobile', 'Móvil', 'fa-mobile-alt', 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40 border border-emerald-100 dark:border-emerald-800/50');
            } else {
                echo '  <p class="text-[10px] text-slate-400 dark:text-slate-500 italic text-center md:text-left mt-2"><i class="fas fa-phone-slash mr-1.5 opacity-60"></i>Sin teléfonos</p>';
            }
            echo '</div>';
        }
    }
    // BLOQUE RELACIONES (Secretary) (Con renderizado estático en vista de lista para evitar descuadre vertical)
    $hasSec = isset($entries[$i]['secretary']);
    $canManageSec = ($isAuthenticated && $canManage);
    if ($hasSec || $canManageSec) {
        echo '<div :class="{ \'w-full bg-blue-50/50 dark:bg-blue-900/20 p-3.5 rounded-xl border border-blue-100 dark:border-blue-800/50 mt-2\': viewMode === \'grid\', \'md:min-w-[240px] md:max-w-[300px] md:border-l border-slate-200/30 dark:border-slate-700/30 md:pl-5 bg-transparent border-0\': viewMode === \'list\' }" class="flex flex-col gap-2 ' . $innerSecDefaultClass . '">';
        echo '  <div class="flex items-center justify-between mb-0.5">';
        echo '    <p class="text-[10px] font-extrabold text-blue-600 dark:text-blue-400 uppercase tracking-tight m-0"><i class="fas fa-link mr-1.5"></i>Empresas Relacionadas</p>';
        if ($isAuthenticated && $canManage) {
            echo '    <button type="button" @click="$dispatch(\'open-secretary-modal\', {dn: \'' . addslashes($entries[$i]['distinguishedname'][0]) . '\', name: \'' . addslashes($entries[$i]['displayname'][0]) . '\', type: \'contacts\'})" class="w-6 h-6 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500 hover:text-white flex items-center justify-center transition-all border border-blue-500/20" title="Añadir"><i class="fas fa-plus text-[9px]"></i></button>';
        }
        echo '  </div>';
        echo '  <div class="sortable-secretary-list flex flex-col gap-1" data-target-dn="' . htmlspecialchars($entries[$i]['distinguishedname'][0]) . '">';
        
        // Lógica de ordenación: Preferir campo comment con prefijo SEC-ORDER:
        $ordered_sec_dns = [];
        if (isset($entries[$i]['comment'][0]) && strpos($entries[$i]['comment'][0], 'SEC-ORDER:') === 0) {
            $raw = substr($entries[$i]['comment'][0], 10);
            $ordered_sec_dns = array_filter(explode('|', $raw));
        }

        // Fallback a secretary original si no hay orden guardado
        if (empty($ordered_sec_dns) && isset($entries[$i]['secretary'])) {
            for ($f=0; $f < $entries[$i]['secretary']['count']; $f++) {
                if(!empty($entries[$i]['secretary'][$f])) $ordered_sec_dns[] = $entries[$i]['secretary'][$f];
            }
        }

        if (!empty($ordered_sec_dns)) {
            foreach ($ordered_sec_dns as $dn) {
                $sec_attrs = array('employeenumber','wwwhomepage','displayname','telephonenumber','mobile','distinguishedname');
                $res_sec = @ldap_read($ldap_conn, $dn, '(objectClass=*)', $sec_attrs);
                if($res_sec) {
                    $managed = ldap_get_entries($ldap_conn, $res_sec);
                    if (!empty($managed[0]['displayname'][0])) {
                        $managed_dn = $managed[0]['distinguishedname'][0] ?? $dn;
                        echo '<div class="flex items-center gap-2 min-w-0 secretary-item py-0.5 group/item" data-dn="' . htmlspecialchars($managed_dn) . '">';
                        if ($isAuthenticated && $canManage) echo '  <div class="flex-shrink-0 cursor-grab active:cursor-grabbing text-blue-400/40 hover:text-blue-500 drag-handle"><i class="fas fa-grip-vertical text-[9px]"></i></div>';
                        echo '  <span class="text-[11px] text-slate-700 dark:text-slate-200 font-semibold truncate flex-1">' . htmlspecialchars($managed[0]['displayname'][0]) . '</span>';
                        if (!empty($managed[0]['telephonenumber'][0])) echo ' <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">' . htmlspecialchars($managed[0]['telephonenumber'][0]) . '</span>';
                        if (!empty($managed[0]['mobile'][0])) echo ' <span class="text-[10px] font-bold text-blue-500/80 dark:text-blue-400/80 whitespace-nowrap">' . htmlspecialchars($managed[0]['mobile'][0]) . '</span>';
                        if ($isAuthenticated && $canManage) echo '  <button type="button" onclick="manageSecretary(\'remove\', \'' . addslashes($entries[$i]['distinguishedname'][0]) . '\', \'' . addslashes($managed_dn) . '\', \'contacts\')" class="flex-shrink-0 ml-1 text-slate-300 hover:text-rose-500 transition-colors" title="Quitar"><i class="fas fa-times text-[10px]"></i></button>';
                        echo '</div>';
                    }
                }
            }
        } else {
            echo '  <p class="text-[10px] text-slate-400 dark:text-slate-500 italic"><i class="fas fa-link-slash mr-1.5 opacity-60"></i>Sin asignar</p>';
        }
        echo '  </div>'; // Fin sortable-secretary-list
        echo '</div>';
    }

    if (!$isMobile) {
        echo '</div>'; // Fin de contenedor flex interno
    }
    echo '</div>'; // Fin de Card
}

// ==========================================
// MUESTRA EMPLEADO DIRECTO
// ==========================================
function show_user($ldap_conn, $base_dn, $entries, $i, $cardIndex = 0) {
    global $_SESSION, $ldap_dn;
    
    // Configuración y Permisos
    $isAuthenticated = !empty($_SESSION['is_authenticated']);
    $user_dn = $entries[$i]['distinguishedname'][0] ?? '';
    $sam = $entries[$i]['samaccountname'][0] ?? '';
    
    // PERMISOS GRANULARES
    $auth_user_dn = !empty($_SESSION['auth_user_dn']) ? $_SESSION['auth_user_dn'] : (isset($_SESSION['ldap_user_dn']) ? $_SESSION['ldap_user_dn'] : '');
    $auth_sam = isset($_SESSION['ldap_user']) ? trim($_SESSION['ldap_user']) : '';
    $card_sam = isset($entries[$i]['samaccountname'][0]) ? trim($entries[$i]['samaccountname'][0]) : '';
    $isMeByDN  = ($auth_user_dn !== '' && strcasecmp($auth_user_dn, trim($user_dn)) === 0);
    $isMeBySAM = ($auth_sam !== '' && $card_sam !== '' && strcasecmp($auth_sam, $card_sam) === 0);
    $canEdit   = can_edit_user($ldap_conn, $user_dn, $card_sam);
    $hasPermission = ($canEdit || $isMeByDN || $isMeBySAM);

    $delay = min($cardIndex * 35, 800); // máx 800ms delay
    $isMobile = !empty($_SESSION['is_mobile_view']);

    // Detectar valores de diseño de las cookies para evitar FOUC (fichas pegadas en carga inicial)
    $viewMode = $_COOKIE['viewMode'] ?? 'list';
    $cardDefaultClass = ($viewMode === 'grid') ? 'py-3 px-2 flex flex-col gap-3 h-full justify-between' : 'p-3.5 flex flex-col gap-2.5';
    $innerFlexDefaultClass = ($viewMode === 'grid') ? 'flex-col gap-3 items-center' : 'flex-row items-center gap-8 md:gap-10';
    $innerPhotoDefaultClass = ($viewMode === 'grid') ? 'mx-auto pt-1 flex-row w-full gap-4' : 'pt-0 flex-col items-center';
    $innerInfoAlignDefaultClass = ($viewMode === 'grid') ? 'text-center' : 'text-left';
    $innerInfoFlexDefaultClass = ($viewMode === 'grid') ? 'items-start px-2 text-left w-full' : 'items-start';
    $innerPhonesDefaultClass = ($viewMode === 'grid') ? 'w-full px-2 mt-2' : 'md:min-w-[220px] md:border-l border-slate-200 dark:border-slate-700/80 md:pl-6';
    $innerSecDefaultClass = ($viewMode === 'grid') ? 'w-full bg-amber-50/40 dark:bg-amber-950/20 p-3.5 rounded-xl border border-amber-100 dark:border-amber-900/40 mt-2' : 'md:min-w-[240px] md:max-w-[300px] md:border-l border-amber-200/30 dark:border-amber-700/30 md:pl-5 bg-transparent border-0';

    $mobileClasses = $isMobile ? ' p-5 flex flex-col gap-4' : '';
    echo '<div class="!bg-white dark:!bg-slate-800 !border !border-slate-200 dark:!border-slate-700/80 rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 hover:scale-[1.015] hover:border-slate-300 dark:hover:border-slate-600 transition-all duration-300 ease-out relative overflow-hidden group card-animated' . $mobileClasses . ($isMobile ? '' : ' ' . $cardDefaultClass) . '"'
         . ' style="animation-delay:' . $delay . 'ms"'
         . ($isMobile ? '' : " :class=\"{ 'py-3 px-2 flex flex-col gap-3 h-full justify-between': viewMode === 'grid', 'p-3.5 flex flex-col gap-2.5': viewMode === 'list' }\"") . '>';
    
    // Linea superior corporativa AZUL original (degradado sutil)
    echo '<div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-cyan-400 opacity-60 group-hover:opacity-100 transition-opacity"></div>';

    // CALCULO ESTADO Y TAGS
    $uac = $entries[$i]['useraccountcontrol'][0] ?? 0;
    $isInactive = ($uac & 2);

    $entries[$i]['wwwhomepage'][0] = substr((isset($entries[$i]['wwwhomepage'][0]) ? $entries[$i]['wwwhomepage'][0] : '').'0000', 0, 4);
    $ledColorClass = 'bg-slate-300 border-slate-100'; 
    $titleLed = "Sin presencia reportada";
    if ($entries[$i]['wwwhomepage'][0][3] !== '1') {
        $ledColorClass = 'bg-slate-300';
    } elseif(isset($entries[$i]['employeenumber']) && user_in($entries[$i]['employeenumber'][0])) {
        $ledColorClass = 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.6)] border-green-200';
        $titleLed = "Online - Trabajando";
    } else {
        $ledColorClass = 'bg-rose-500 border-rose-200';
        $titleLed = "Offline - Ausente";
    }

    // TAGS
    $tagsTooltip = 'No hay etiquetas asignadas';
    if (isset($entries[$i]['info'])) {
        $rawInfo = $entries[$i]['info'][0];
        $tagsList = preg_split('/[,;]+/', $rawInfo, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($tagsList)) {
            $tagsTooltip = "<b>Etiquetas de búsqueda:</b><br>• " . implode("<br>• ", array_map('trim', array_map('htmlspecialchars', $tagsList)));
        }
    }

    // === FILA SUPERIOR: Foto + Info + Teléfonos (horizontal en lista) ===
    if ($isMobile) {
        // --- MOBILE LAYOUT ---
        echo '<div class="flex items-start gap-4">';
        
        // Columna Izquierda: Foto y Usuario
        echo '  <div class="flex flex-col items-center flex-shrink-0 w-24">';
        echo '    <div class="relative inline-block mb-1.5">';
        if (isset($entries[$i]['thumbnailphoto']) && $entries[$i]['wwwhomepage'][0][1]==='1') {
            echo '      <img src="data:image/jpeg;base64,' . base64_encode($entries[$i]['thumbnailphoto'][0]) . '" class="w-20 h-20 rounded-full object-cover border-4 border-slate-50 dark:border-slate-700 shadow-md">';
        } else {
            echo '      <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800/80 border-4 border-slate-50 dark:border-slate-700 shadow-md flex items-center justify-center text-slate-400 dark:text-slate-500"><i class="fas fa-user text-3xl"></i></div>';
        }
        echo '      <div class="absolute bottom-0 right-0 w-5 h-5 rounded-full border-2 border-white dark:border-slate-800 ' . $ledColorClass . ' z-10 shadow-sm" title="' . $titleLed . '"></div>';
        echo '    </div>';
        
        if (isset($entries[$i]['samaccountname'])) {
            echo '    <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 uppercase tracking-tight text-center bg-emerald-50 dark:bg-emerald-900/40 px-1.5 py-0.5 rounded">' . htmlspecialchars($entries[$i]['samaccountname'][0]) . '</span>';
        }
        if (isset($entries[$i]['employeenumber'])) {
            echo '    <span class="text-[11px] font-bold text-blue-700 dark:text-blue-300 uppercase tracking-tight text-center bg-blue-50 dark:bg-blue-900/40 px-1.5 py-0.5 rounded mt-1">ID: ' . htmlspecialchars($entries[$i]['employeenumber'][0]) . '</span>';
        }

        if ($isInactive) {
            echo '    <span class="text-[10px] font-black leading-none text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 px-2 py-1 rounded-full uppercase mt-2 shadow-sm border border-rose-200 dark:border-rose-800/50 flex items-center gap-1"><i class="fas fa-ban text-[8px]"></i> Inactivo</span>';
        }

        // Botón Añadir a Agenda (vCard con foto de perfil)
        $vcardUrl = 'lib/generate_vcard.php?dn=' . urlencode($user_dn);
        echo '    <a href="' . $vcardUrl . '" class="w-full mt-2 py-1 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-600 hover:text-white transition-all text-[10px] font-bold flex items-center justify-center gap-1 border border-blue-500/20 active:scale-95 transform">';
        echo '      <i class="fas fa-user-plus text-[9px]"></i> Agenda';
        echo '    </a>';
        echo '  </div>';

        // Columna Derecha: Nombre, Cargo, Departamento
        echo '  <div class="flex-1 min-w-0 pt-1 flex flex-col justify-start">';
        echo '    <h3 class="text-base font-black text-slate-900 dark:text-white leading-tight mb-1 break-words">' . ((isset($entries[$i]['displayname'])) ? htmlspecialchars($entries[$i]['displayname'][0]) : 'Sin Nombre') . '</h3>';
        
        if (!empty($entries[$i]['title'][0])) {
            $cargo = $entries[$i]['title'][0];
            echo '    <a href="mobile.php?btnBuscar=1&chkPresente=1&chkAusente=1&chkIndeterminado=1&chkPresencial=1&chkTeletrabajo=1&txtCargo='.urlencode($cargo).'" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 leading-tight mb-2 uppercase tracking-tight active:scale-95 transition-transform block">' . htmlspecialchars($cargo) . '</a>';
        }
        
        echo '    <div class="flex flex-col gap-1 mt-1.5 items-start">';
        
        if (isset($entries[$i]['department'])) {
            $dep = $entries[$i]['department'][0];
            echo '      <a href="mobile.php?btnBuscar=1&chkPresente=1&chkAusente=1&chkIndeterminado=1&chkPresencial=1&chkTeletrabajo=1&txtDepartamento='.urlencode($dep).'" class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter max-w-full truncate active:scale-95 transition-transform group/link"><i class="fas fa-sitemap opacity-60 flex-shrink-0 group-hover/link:text-blue-500 transition-colors w-4 text-center"></i> <span class="truncate">' . htmlspecialchars($dep) . '</span></a>';
        }

        if ((isset($entries[$i]['mail'])) && $entries[$i]['wwwhomepage'][0][2]==='1') {
            $safeMail = htmlspecialchars($entries[$i]['mail'][0]);
            echo '      <div class="flex items-center gap-2 max-w-full">';
            echo '        <a href="mailto:'.$safeMail.'" class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter truncate active:scale-95 transition-transform"><i class="fas fa-envelope opacity-60 flex-shrink-0 w-4 text-center"></i> <span class="truncate lowercase">' . $safeMail . '</span></a>';
            echo '        <a href="https://teams.microsoft.com/l/chat/0/0?users=' . urlencode($safeMail) . '" target="_blank" class="w-5 h-5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-600 hover:text-white flex items-center justify-center border border-blue-500/20 active:scale-95 transition-transform flex-shrink-0" title="Microsoft Teams"><i class="fab fa-microsoft text-[10px]"></i></a>';
            echo '      </div>';
        }

        if (!empty($entries[$i]['physicaldeliveryofficename'][0])) {
            $oficina = $entries[$i]['physicaldeliveryofficename'][0];
            echo '      <a href="mobile.php?btnBuscar=1&chkPresente=1&chkAusente=1&chkIndeterminado=1&chkPresencial=1&chkTeletrabajo=1&txtOficina='.urlencode($oficina).'" class="inline-flex items-start gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter max-w-full active:scale-95 transition-transform group/link whitespace-normal break-words"><i class="fas fa-map-marker-alt opacity-60 flex-shrink-0 group-hover/link:text-blue-500 transition-colors w-4 text-center mt-[2px]"></i> <span>' . htmlspecialchars($oficina) . '</span></a>';
        }

        // DIRECCION USUARIO (Opcional en móvil si existe streetaddress)
        $uAddr1 = array_filter([$entries[$i]['streetaddress'][0] ?? '']);
        $uAddr2 = array_filter([$entries[$i]['postalcode'][0] ?? '', $entries[$i]['l'][0] ?? '', $entries[$i]['st'][0] ?? '']);
        if (!empty($uAddr1) || !empty($uAddr2)) {
            echo '      <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-tight flex max-w-full whitespace-normal break-words">';
            echo '        <i class="fas fa-home text-slate-400 dark:text-slate-500 mt-[2px] w-4 text-center flex-shrink-0 text-[10px]"></i>';
            echo '        <div class="flex flex-col min-w-0 ml-1.5">';
            if (!empty($uAddr1)) {
                echo '          <span>' . htmlspecialchars(implode(' - ', $uAddr1)) . '</span>';
            }
            if (!empty($uAddr2)) {
                echo '          <span class="opacity-80 text-[10px]">' . htmlspecialchars(implode(' - ', $uAddr2)) . '</span>';
            }
            echo '        </div>';
            echo '      </div>';
        }

        if (!empty($entries[$i]['description'][0])) {
            echo '      <div class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter max-w-full truncate"><i class="fas fa-desktop opacity-60 flex-shrink-0 w-4 text-center"></i> <span class="truncate">' . htmlspecialchars($entries[$i]['description'][0]) . '</span></div>';
        }

        echo '    </div>';

        echo '  </div>';
        echo '</div>'; // Fin flex
        
    } else {
        // --- DESKTOP LAYOUT ---
        echo '<div class="flex ' . $innerFlexDefaultClass . '" :class="{ \'flex-col gap-3 items-center\': viewMode === \'grid\', \'flex-row items-center gap-8 md:gap-10\': viewMode === \'list\' }">';
        
        // FOTO Y ESTADO
        // En grid: layout horizontal (foto izquierda, datos derecha) para fichas más compactas
        echo '<div class="flex-shrink-0 flex items-center ' . $innerPhotoDefaultClass . '" :class="{ \'mx-auto pt-1 flex-row w-full gap-4\': viewMode === \'grid\', \'pt-0 flex-col items-center\': viewMode === \'list\' }">';
        
        // En grid: cada mitad ocupa 50%, foto centrada en su mitad
        echo '  <div class="flex-shrink-0" :class="{ \'w-1/2 flex justify-center\': viewMode === \'grid\' }">'; // SUB-CONTAINER PARA CENTRAR
        echo '    <div class="relative inline-block">'; // WRAPPER RELATIVO PARA EL LED Y LA FOTO (1:30)
        if (isset($entries[$i]['thumbnailphoto']) && $entries[$i]['wwwhomepage'][0][1]==='1') {
            echo '      <img src="data:image/jpeg;base64,' . base64_encode($entries[$i]['thumbnailphoto'][0]) . '" data-html-tooltip="' . htmlspecialchars($tagsTooltip, ENT_QUOTES, 'UTF-8') . '" class="w-20 h-20 rounded-full object-cover border-4 border-slate-50 dark:border-slate-700 shadow-md transform group-hover:scale-105 transition-transform cursor-help">';
        } else {
            echo '      <div data-html-tooltip="' . htmlspecialchars($tagsTooltip, ENT_QUOTES, 'UTF-8') . '" class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800/80 border-4 border-slate-50 dark:border-slate-700 shadow-md flex items-center justify-center text-slate-400 dark:text-slate-500 transform group-hover:scale-105 transition-transform cursor-help"><i class="fas fa-user text-3xl"></i></div>';
        }
        // Dibuja el Led absoluto ENCIMA de la foto (en la esquina superior derecha, superpuesto elegantemente a la 1:30)
        echo '      <div class="absolute top-0.5 right-0.5 w-5 h-5 rounded-full border-2 border-white dark:border-slate-800 ' . $ledColorClass . ' z-10 shadow-sm" title="' . $titleLed . '"></div>';
        echo '    </div>'; // Fin WRAPPER RELATIVO
        echo '  </div>'; // Fin SUB-CONTAINER
        
        // Contenedor de badges: en grid se coloca a la derecha de la foto, en list debajo
        // En grid: 50% del ancho, centrado vertical y horizontalmente
        echo '  <div class="flex flex-col gap-1" :class="{ \'w-1/2 items-center justify-center mt-0\': viewMode === \'grid\', \'items-center mt-2\': viewMode === \'list\' }">';
        if (isset($entries[$i]['samaccountname'])) {
            echo '    <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 uppercase tracking-tight bg-emerald-50 dark:bg-emerald-900/40 px-1.5 py-0.5 rounded">' . htmlspecialchars($entries[$i]['samaccountname'][0]) . '</span>';
        }
        if (isset($entries[$i]['employeenumber'])) {
            echo '    <span class="text-[11px] font-bold text-blue-700 dark:text-blue-300 uppercase tracking-tight bg-blue-50 dark:bg-blue-900/40 px-1.5 py-0.5 rounded">ID: ' . htmlspecialchars($entries[$i]['employeenumber'][0]) . '</span>';
        }

        if ($isInactive) {
            echo '    <span class="text-[10px] font-black leading-none text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 px-2 py-0.5 rounded-full uppercase shadow-sm border border-rose-200 dark:border-rose-800/50 flex items-center gap-1"><i class="fas fa-ban text-[8px]"></i> Inactivo</span>';
        }
        
        // BOTONES ACCION EDITAR
        if ($sam !== '') {
            echo '    <div class="flex gap-1.5 mt-1 justify-start">';
            if ($isAuthenticated && $hasPermission) {
                echo '      <a href="./datos_active?user='.urlencode($sam).'" class="w-7 h-7 rounded-lg bg-blue-600 text-white hover:bg-blue-700 flex items-center justify-center transition-all shadow-md" title="Editar Perfil"><i class="fas fa-user-edit text-[10px]"></i></a>';
            }
            if ($isAuthenticated && ($isMeBySAM || $isMeByDN)) {
                echo '      <a href="./change_pwd?user='.urlencode($sam).'" class="w-7 h-7 rounded-lg bg-orange-500 text-white hover:bg-orange-600 flex items-center justify-center transition-all shadow-md" title="Cambiar Contraseña"><i class="fas fa-key text-[10px]"></i></a>';
            }
            echo '    </div>';
        }
        echo '  </div>';
        echo '</div>'; // Fin FOTO

        // INFO PRINCIPAL
        echo '<div class="flex-1 min-w-0" :class="{ \'text-center\': viewMode === \'grid\', \'text-left\': viewMode === \'list\' }">';
        echo '  <h3 class="text-base font-black text-slate-900 dark:text-white mb-1 leading-tight" :class="{ \'whitespace-normal break-words\': viewMode === \'grid\', \'truncate\': viewMode === \'list\' }">' . ((isset($entries[$i]['displayname'])) ? htmlspecialchars($entries[$i]['displayname'][0]) : 'Sin Nombre') . '</h3>';

        if (!empty($entries[$i]['title'][0])) {
            echo '  <form method="GET" action="index.php" style="margin:0;padding:0">';
            echo '    <input type="hidden" name="btnBuscar" value="1">';
            echo '    <input type="hidden" name="chkPresente" value="1">';
            echo '    <input type="hidden" name="chkAusente" value="1">';
            echo '    <input type="hidden" name="chkIndeterminado" value="1">';
            echo '    <input type="hidden" name="chkPresencial" value="1">';
            echo '    <input type="hidden" name="chkTeletrabajo" value="1">';
            echo '    <input type="hidden" name="txtCargo" value="' . htmlspecialchars($entries[$i]['title'][0]) . '">';
            echo '    <button type="submit" class="inline-flex items-start text-[11px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-tight hover:underline cursor-pointer bg-transparent border-0 p-0 m-0 mb-2" :class="viewMode === \'grid\' ? \'justify-center text-center w-full whitespace-normal break-words leading-tight\' : \'text-left leading-tight truncate\'" title="Filtrar por este cargo"><i class="fas fa-user-tie w-4 text-center opacity-70 mr-1.5 flex-shrink-0" style="margin-top: 2px;"></i> <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars($entries[$i]['title'][0]) . '</span></button>';
            echo '  </form>';
        }
        
        echo '  <div class="flex flex-col gap-1 mt-1.5 ' . $innerInfoFlexDefaultClass . '" :class="{ \'items-start px-2 text-left w-full\': viewMode === \'grid\', \'items-start\': viewMode === \'list\' }">';
        if (isset($entries[$i]['department'])) {
            echo '    <form method="GET" action="index.php" class="flex max-w-full m-0 p-0" :class="viewMode === \'grid\' ? \'whitespace-normal\' : \'truncate\'">';
            echo '      <input type="hidden" name="btnBuscar" value="1">';
            echo '      <input type="hidden" name="chkPresente" value="1">';
            echo '      <input type="hidden" name="chkAusente" value="1">';
            echo '      <input type="hidden" name="chkIndeterminado" value="1">';
            echo '      <input type="hidden" name="chkPresencial" value="1">';
            echo '      <input type="hidden" name="chkTeletrabajo" value="1">';
            echo '      <input type="hidden" name="txtDepartamento" value="' . htmlspecialchars($entries[$i]['department'][0]) . '">';
            echo '      <button type="submit" class="inline-flex items-start text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter hover:text-blue-600 transition-colors cursor-pointer border-0 bg-transparent p-0 m-0 max-w-full" :class="viewMode === \'grid\' ? \'\' : \'text-left truncate\'" title="Filtrar por este departamento"><i class="fas fa-sitemap w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i> <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars($entries[$i]['department'][0]) . '</span></button>';
            echo '    </form>';
        }
 
        if ((isset($entries[$i]['mail'])) && $entries[$i]['wwwhomepage'][0][2]==='1') {
            $safeMail = htmlspecialchars($entries[$i]['mail'][0]);
            echo '    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter m-0 flex max-w-full" :class="viewMode === \'grid\' ? \'whitespace-normal\' : \'truncate\'"><a href="mailto:' . $safeMail .'" class="inline-flex items-start hover:text-blue-600 transition-colors max-w-full" :class="viewMode === \'grid\' ? \'\' : \'truncate\'"><i class="fas fa-envelope w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i> <span class="lowercase" :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . $safeMail . '</span></a></p>';
        }
 
        if (!empty($entries[$i]['physicaldeliveryofficename'][0])) {
            echo '    <form method="GET" action="index.php" class="flex max-w-full m-0 p-0" :class="viewMode === \'grid\' ? \'whitespace-normal\' : \'truncate\'">';
            echo '      <input type="hidden" name="btnBuscar" value="1">';
            echo '      <input type="hidden" name="chkPresente" value="1">';
            echo '      <input type="hidden" name="chkAusente" value="1">';
            echo '      <input type="hidden" name="chkIndeterminado" value="1">';
            echo '      <input type="hidden" name="chkPresencial" value="1">';
            echo '      <input type="hidden" name="chkTeletrabajo" value="1">';
            echo '      <input type="hidden" name="txtOficina" value="' . htmlspecialchars($entries[$i]['physicaldeliveryofficename'][0]) . '">';
            echo '      <button type="submit" class="inline-flex items-start text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter hover:text-blue-600 transition-colors cursor-pointer border-0 bg-transparent p-0 m-0 max-w-full" :class="viewMode === \'grid\' ? \'\' : \'text-left truncate\'" title="Filtrar por esta ubicación"><i class="fas fa-map-marker-alt w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i> <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars($entries[$i]['physicaldeliveryofficename'][0]) . '</span></button>';
            echo '    </form>';
        }
        
        if (!empty($entries[$i]['description'][0])) {
            echo '    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-tighter m-0 inline-flex items-start max-w-full" :class="viewMode === \'grid\' ? \'whitespace-normal\' : \'truncate\'"><i class="fas fa-desktop w-4 text-center flex-shrink-0 opacity-60 mr-1.5" style="margin-top: 2px;"></i> <span :class="viewMode === \'grid\' ? \'whitespace-normal break-words text-left leading-tight\' : \'truncate\'">' . htmlspecialchars($entries[$i]['description'][0]) . '</span></p>';
        }
        echo '  </div>';
        echo '</div>'; // Fin INFO PRINCIPAL
    }

    // Override: Si el usuario tiene estación de trabajo asignada con teléfono, mostrar el del equipo
    $phoneFromComputer = false;
    $computerNameLabel = '';
    if (!empty($entries[$i]['logonworkstation'][0])) {
        $computer_phone = get_computer_phone($ldap_conn, $entries[$i]['logonworkstation'][0]);
        if ($computer_phone !== null) {
            $entries[$i]['telephonenumber'][0] = $computer_phone;
            $phoneFromComputer = true;
            $computerNameLabel = trim(explode(',', $entries[$i]['logonworkstation'][0])[0]);
        }
    }

    // BLOQUE TELEFONOS (Con renderizado estático en vista de lista para evitar descuadre vertical)
    $hasPhones = (!empty($entries[$i]['telephonenumber'][0])) || (!empty($entries[$i]['mobile'][0])) || (!empty($entries[$i]['homephone'][0])) || (!empty($entries[$i]['facsimiletelephonenumber'][0]));
    if ($hasPhones || (!$isMobile && $viewMode === 'list')) {
        if ($isMobile) {
            $telParts = [];
            if (!empty($entries[$i]['telephonenumber'][0])) {
                $tParts = explode('/', $entries[$i]['telephonenumber'][0]);
                $telColorClass = $phoneFromComputer ? 'text-sky-600 dark:text-sky-400 font-bold' : 'text-blue-600 dark:text-blue-400 font-bold';
                foreach ($tParts as $tPart) {
                    $tPart = trim($tPart);
                    if (!empty($tPart)) {
                        $prefix = $phoneFromComputer ? '<i class="fas fa-desktop text-[9px] mr-1 opacity-70"></i>' : '';
                        $phoneTooltip = $phoneFromComputer ? 'Extensi&#243;n asignada por estar en el equipo ' . htmlspecialchars($computerNameLabel) : 'Extensi&#243;n asignada por ficha de usuario';
                        $telParts[] = '<a href="tel:'. preg_replace('/[^\d+]/', '', $tPart) . '" class="' . $telColorClass . '" title="' . $phoneTooltip . '">' . $prefix . htmlspecialchars($tPart) . '</a>';
                    }
                }
            }
            if (!empty($entries[$i]['mobile'][0])) {
                $mParts = explode('/', $entries[$i]['mobile'][0]);
                foreach ($mParts as $mPart) {
                    $mPart = trim($mPart);
                    if (!empty($mPart)) {
                        $telParts[] = '<a href="tel:'. preg_replace('/[^\d+]/', '', $mPart) . '" class="text-emerald-600 dark:text-emerald-400 font-bold">' . htmlspecialchars($mPart) . '</a>';
                    }
                }
            }
            if (!empty($telParts)) {
                echo '<div class="mt-0.5 flex items-center bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-lg p-2 gap-2 text-xs w-full">';
                echo '  <span class="font-bold text-xs text-slate-500 dark:text-slate-400 uppercase flex-shrink-0 mr-2">LÍNEAS</span>';
                echo '  <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">';
                echo implode(' <span class="text-slate-300 dark:text-slate-600 flex-shrink-0">|</span> ', $telParts);
                echo '  </div>';
                echo '</div>';
            }
        } else {
            echo '<div class="flex flex-col gap-2 flex-shrink-0 ' . $innerPhonesDefaultClass . '" :class="{ \'w-full px-2 mt-2\': viewMode === \'grid\', \'md:min-w-[220px] md:border-l border-slate-200 dark:border-slate-700/80 md:pl-6\': viewMode === \'list\' }" ' . ((!$hasPhones) ? 'x-show="viewMode === \'list\'"' : '') . '>';
            if ($hasPhones) {
                if ($phoneFromComputer) {
                    $actionHtml = '';
                    $canManagePhone = $isAuthenticated && (is_admin_user() || (!empty($sam) && strcasecmp($_SESSION['ldap_user'], $sam) === 0));
                    if ($canManagePhone) {
                        $actionHtml = '<button onclick="unassignComputerPhone(\'' . addslashes($sam) . '\', \'' . addslashes($computerNameLabel) . '\')" class="w-6 h-6 rounded-md bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-all border border-rose-500/20 shadow-sm flex-shrink-0" title="Desasignar de la extensión"><i class="fas fa-trash-alt text-[10px]"></i></button>';
                    }
                    imprime_par_telefonos_tw($entries[$i], 'telephonenumber', 'othertelephone', 'Fijo', 'fa-desktop', 'text-slate-800 dark:text-slate-100 bg-sky-50/50 dark:bg-sky-900/20 border border-sky-200/70 dark:border-sky-700/50 shadow-sm', true, 'Extensión asignada por estar en el equipo ' . htmlspecialchars($computerNameLabel), $actionHtml);
                } else {
                    imprime_par_telefonos_tw($entries[$i], 'telephonenumber', 'othertelephone', 'Fijo', 'fa-phone-alt', 'text-slate-800 dark:text-slate-100 !bg-white dark:!bg-slate-700/50 !border !border-slate-200 dark:!border-slate-700/80 shadow-sm', true, 'Extensión asignada por ficha de usuario');
                }
                imprime_par_telefonos_tw($entries[$i], 'mobile', 'othermobile', 'Móvil', 'fa-mobile-alt', 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/40 border border-blue-100 dark:border-blue-800/50');
            } else {
                echo '  <p class="text-[10px] text-slate-400 dark:text-slate-500 italic text-center md:text-left mt-2"><i class="fas fa-phone-slash mr-1.5 opacity-60"></i>Sin teléfonos</p>';
            }
            echo '</div>';
        }
    }

    // === TERCERA COLUMNA (SIDEBAR) / FILA INFERIOR: Bloque Secretary === (Con renderizado estático en vista de lista para evitar descuadre vertical)
    $hasSec = isset($entries[$i]['secretary']);
    $canManageSec = ($isAuthenticated && $hasPermission);
    if ($hasSec || $canManageSec) {
        echo '<div :class="{ \'w-full bg-amber-50/40 dark:bg-amber-950/20 p-3.5 rounded-xl border border-amber-100 dark:border-amber-900/40 mt-2\': viewMode === \'grid\', \'md:min-w-[240px] md:max-w-[300px] md:border-l border-amber-200/30 dark:border-amber-700/30 md:pl-5 bg-transparent border-0\': viewMode === \'list\' }" class="flex flex-col gap-2 transition-all ' . $innerSecDefaultClass . '">';
        echo '  <div class="flex items-center justify-between mb-0.5">';
        echo '    <p class="text-[10px] font-extrabold text-amber-600 dark:text-amber-400 uppercase tracking-tight m-0"><i class="fas fa-share-square mr-1.5"></i>Pasar llamadas a ...</p>';
        if ($sam !== '' && $isAuthenticated && $hasPermission) {
            echo '    <button type="button" @click="$dispatch(\'open-secretary-modal\', {dn: \'' . addslashes($user_dn) . '\', name: \'' . addslashes($entries[$i]['displayname'][0]) . '\'})" class="w-6 h-6 rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500 hover:text-white flex items-center justify-center transition-all border border-amber-500/20" title="Añadir"><i class="fas fa-plus text-[9px]"></i></button>';
        }
        echo '  </div>';
        
        echo '  <div id="secretary-list-' . $sam . '" class="flex flex-col gap-1 sortable-secretary-list" data-target-dn="' . htmlspecialchars($user_dn) . '">';
        
        $ordered_sec_dns = [];
        if (isset($entries[$i]['comment'][0]) && strpos($entries[$i]['comment'][0], 'SEC-ORDER:') === 0) {
            $raw = substr($entries[$i]['comment'][0], 10);
            $ordered_sec_dns = array_filter(explode('|', $raw));
        }
        
        if (empty($ordered_sec_dns) && isset($entries[$i]['secretary'])) {
            for ($f=0; $f < $entries[$i]['secretary']['count']; $f++) {
                if(!empty($entries[$i]['secretary'][$f])) $ordered_sec_dns[] = $entries[$i]['secretary'][$f];
            }
        }

        if (!empty($ordered_sec_dns)) {
            foreach ($ordered_sec_dns as $sec_dn) {
                $sec_attrs = array('employeenumber','wwwhomepage','displayname','telephonenumber','mobile','distinguishedname');
                $res_sec = @ldap_read($ldap_conn, $sec_dn, '(objectClass=*)', $sec_attrs);
                if($res_sec) {
                    $managed = ldap_get_entries($ldap_conn, $res_sec);
                    if (!empty($managed[0]['displayname'][0])) {
                        $managed[0]['wwwhomepage'][0] = substr((isset($managed[0]['wwwhomepage'][0])?$managed[0]['wwwhomepage'][0]:'').'0000', 0, 4);
                        $smLed = ($managed[0]['wwwhomepage'][0][3]==='1' && isset($managed[0]['employeenumber']) && user_in($managed[0]['employeenumber'][0])) ? 'bg-green-500' : ($managed[0]['wwwhomepage'][0][3]==='1' ? 'bg-rose-500' : 'bg-slate-300');
                        $managed_dn = $managed[0]['distinguishedname'][0] ?? $sec_dn;
                        
                        echo '<div class="flex items-center gap-2 min-w-0 secretary-item py-0.5 group/item" data-dn="' . htmlspecialchars($managed_dn) . '">';
                        if ($isAuthenticated && $hasPermission) echo '  <div class="flex-shrink-0 cursor-grab active:cursor-grabbing text-amber-400/40 hover:text-amber-500 drag-handle"><i class="fas fa-grip-vertical text-[9px]"></i></div>';
                        echo '  <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 ' . $smLed . '"></div>';
                        echo '  <span class="text-[11px] text-slate-700 dark:text-slate-200 font-semibold truncate flex-1">' . htmlspecialchars($managed[0]['displayname'][0]) . '</span>';
                        if (!empty($managed[0]['telephonenumber'][0])) echo ' <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap">' . htmlspecialchars($managed[0]['telephonenumber'][0]) . '</span>';
                        if (!empty($managed[0]['mobile'][0])) echo ' <span class="text-[10px] font-bold text-blue-500/80 dark:text-blue-400/80 whitespace-nowrap">' . htmlspecialchars($managed[0]['mobile'][0]) . '</span>';
                        if ($isAuthenticated && $hasPermission) echo '  <button type="button" onclick="manageSecretary(\'remove\', \'' . addslashes($user_dn) . '\', \'' . addslashes($managed_dn) . '\', \'users\')" class="flex-shrink-0 ml-1 text-slate-300 hover:text-rose-500 transition-colors" title="Quitar"><i class="fas fa-times text-[10px]"></i></button>';
                        echo '</div>';
                    }
                }
            }
        } else {
            echo '  <p class="text-[10px] text-slate-400 dark:text-slate-500 italic"><i class="fas fa-share-square mr-1.5 opacity-60"></i>Sin asignar</p>';
        }
        echo '  </div>'; // Fin secretary-list
        echo '</div>'; // Fin bloque relaciones
    }

    if (!$isMobile) {
        echo '</div>'; // Fin flex md:row
    }
    echo '</div>'; // Fin card
}

// Helper auxiliar local para pintar teléfonos Tailwind-style
function imprime_par_telefonos_tw($entries, $tel1, $tel2, $etiqueta, $icon, $extraClasses = 'text-slate-600 bg-white border border-slate-200', $isBig = false, $tooltip = '', $actionHtml = '') {
    if (!empty($entries[$tel1][0])) {
        $todos = array($entries[$tel1][0]);
        if (isset($entries[$tel2])) {
            foreach ($entries[$tel2] as $t) {
                if(!empty($t)) $todos[] = $t;
            }
        }
        
        $sizeClass = $isBig ? 'text-base font-bold' : 'text-sm font-medium';
        $isMobile = !empty($_SESSION['is_mobile_view']);
        $tooltipAttr = !empty($tooltip) ? ' title="' . $tooltip . '"' : '';
        
        echo '<div class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg ' . $extraClasses . '"' . $tooltipAttr . '>';
        echo '  <span class="text-[10px] font-bold uppercase tracking-wider opacity-70 flex items-center gap-1.5 flex-shrink-0 whitespace-nowrap"><i class="fas ' . $icon . '"></i>' . $etiqueta . '</span>';
        echo '  <div class="text-right min-w-0 flex-1">';
        
        if ($isMobile) {
            // En móvil, cada teléfono es un botón de llamada individual
            echo '<div class="flex flex-wrap justify-end items-center gap-2">';
            foreach ($todos as $t) {
                $cleanTel = preg_replace('/[^0-9+]/', '', $t);
                echo '<div class="flex items-center gap-1.5">';
                echo '  <a href="tel:' . $cleanTel . '" class="' . $sizeClass . ' tracking-tight text-blue-600 dark:text-blue-400 underline decoration-blue-500/30 px-2 py-1 rounded-md bg-white/50 dark:bg-slate-800/50">' . htmlspecialchars($t) . '</a>';
                if ($etiqueta === 'Móvil') {
                    $waNumber = $cleanTel;
                    if (strlen($waNumber) === 9 && (strpos($waNumber, '6') === 0 || strpos($waNumber, '7') === 0)) {
                        $waNumber = '34' . $waNumber;
                    }
                    echo '  <a href="https://wa.me/' . $waNumber . '" target="_blank" class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-500/20 active:scale-95 transition-transform" title="WhatsApp"><i class="fab fa-whatsapp text-sm"></i></a>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '      <div class="flex items-center gap-2 justify-end min-w-0">';
            echo '        <div class="'.$sizeClass.' tracking-tight break-all">' . htmlspecialchars(implode(' / ', $todos)) . '</div>';
            if (!empty($actionHtml)) {
                echo $actionHtml;
            }
            echo '      </div>';
        }
        
        echo '  </div>';
        echo '</div>';
    }
}
?>
