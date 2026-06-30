<?php
//Funciones para debug
require_once('./lib/debug_to_console.php');
//Funciones para la carga de los datos del combobox
require_once('./lib/ldap_showresults.php');

// HELPER: Genera variaciones de una palabra sin tildes añadiendo tildes en todas las posiciones de vocales posibles
// Esto permite buscar "area" y que encuentre "area" y "área" en Active Directory
function get_accent_variations($word) {
    if (empty(trim($word))) return array($word);
    
    // Convertimos a minúsculas y quitamos acentos
    $word = mb_strtolower(trim($word), 'UTF-8');
    $accented   = array('á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ');
    $unaccented = array('a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n');
    $word = str_replace($accented, $unaccented, $word);
    
    $map = array(
        'a' => array('á'),
        'e' => array('é'),
        'i' => array('í'),
        'o' => array('ó'),
        'u' => array('ú', 'ü'),
        'n' => array('ñ')
    );
    
    $variations = array($word); // siempre incluimos la versión sin acentos
    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
    
    if (!$chars) {
        return $variations;
    }

    for ($i = 0; $i < count($chars); $i++) {
        $c = $chars[$i];
        if (isset($map[$c])) {
            foreach ($map[$c] as $alt) {
                $temp = $chars;
                $temp[$i] = $alt;
                $variations[] = implode('', $temp);
            }
        }
    }
    return array_unique($variations);
}

// HELPER: Construye un filtro LDAP OR con todas las variaciones de acento
function build_accent_filter($field, $word, $isExact = false) {
    if (empty(trim($word))) return '';
    $word = trim($word); // quitar espacios de los bordes
    
    // Si el término es un comodín (*), devolver el filtro de existencia sin escapar
    if ($word === '*') {
        return "($field=*)";
    }

    $variations = get_accent_variations($word);
    
    if (count($variations) == 1) {
        $esc = ldap_escape($variations[0], '', LDAP_ESCAPE_FILTER);
        return $isExact ? "($field=$esc)" : "($field=*$esc*)";
    }
    
    $filter = '(|';
    foreach ($variations as $var) {
        // En cada variación, si hay un *, lo mantenemos como comodín? 
        // Normalmente las variaciones son para letras con acento.
        $esc = ldap_escape($var, '', LDAP_ESCAPE_FILTER);
        $filter .= $isExact ? "($field=$esc)" : "($field=*$esc*)";
    }
    $filter .= ')';
    return $filter;
}

// HELPER: Construye un filtro LDAP AND para múltiples palabras en un mismo campo,
// sin importar el orden en que aparezcan. Cada palabra debe estar presente en el campo.
// Ejemplo: ["Nieves", "Moreno"] → (&(displayname=*Nieves*)(displayname=*Moreno*)) con variaciones de acento.
function build_any_order_accent_filter($field, $words_array) {
    if (empty($words_array)) return '';

    $word_filters = [];
    foreach ($words_array as $w) {
        if (!empty(trim($w))) {
            $f = build_accent_filter($field, $w, false);
            if ($f !== '') {
                $word_filters[] = $f;
            }
        }
    }

    if (empty($word_filters)) return '';
    if (count($word_filters) === 1) return $word_filters[0];

    return '(&' . implode('', $word_filters) . ')';
}

// CREAR FILTRO NUEVO DE BÚSQUEDA (Refactorizado para lógica AND estricta)
function new_filter() {
  if (isset($_REQUEST['btnBuscar'])) {
    $conditions = [];
    $order = [];

    // 1. Nombre (AND por palabras en cualquier orden en el campo displayname)
    // Así "Moreno Nieves", "Nieves Moreno" y "Moreno, Nieves" encuentran el mismo resultado.
    $nom1 = trim($_REQUEST['txtNombre'] ?? '');
    if ($nom1 !== '') {
        $words = preg_split('/[\s,]+/', $nom1, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($words)) {
            $conditions[] = build_any_order_accent_filter('displayname', $words);
        }
    }

    // 2. Cargo
    $car1 = trim($_REQUEST['txtCargo'] ?? '');
    if ($car1 !== '') {
        $conditions[] = build_accent_filter('title', $car1, false);
    }

    // 3. Extensión (Filtra por cualquier campo de teléfono)
    $ext1 = trim($_REQUEST['txtExtension'] ?? '');
    if ($ext1 !== '') {
        $esc = ldap_escape($ext1, '', LDAP_ESCAPE_FILTER);
        $conditions[] = '(|(telephonenumber=*' . $esc . '*)(othertelephone=*' . $esc . '*)(mobile=*' . $esc . '*)(othermobile=*' . $esc . '*)(homephone=*' . $esc . '*)(otherhomephone=*' . $esc . '*))';
    }

    // 4. Departamento
    $dep1 = $_REQUEST['txtDepartamento'] ?? '0';
    if ($dep1 !== '0') {
        $conditions[] = build_accent_filter('department', $dep1, false);
    }

    // 5. Ubicación
    $ubi1 = $_REQUEST['txtOficina'] ?? '0';
    if ($ubi1 !== '0') {
        $conditions[] = build_accent_filter('physicaldeliveryofficename', $ubi1, false);
    }

    // 6. Localizador (Búsqueda en varios campos; todos los términos deben estar en el MISMO campo, en cualquier orden)
    $tag1 = trim($_REQUEST['txtTag'] ?? '');
    if ($tag1 !== '') {
        $tag_fields = ['displayname', 'samaccountname', 'title', 'department', 'telephonenumber', 'othertelephone', 'mobile', 'othermobile', 'homephone', 'otherhomephone', 'facsimiletelephonenumber', 'otherfacsimiletelephonenumber', 'mail', 'description', 'physicaldeliveryofficename', 'info', 'employeenumber', 'streetaddress', 'postalcode', 'l', 'st', 'co'];
        $tag_words = preg_split('/[\s,]+/', $tag1, -1, PREG_SPLIT_NO_EMPTY);
        
        if (!empty($tag_words)) {
            $field_or = [];
            foreach ($tag_fields as $f) {
                // Creamos un filtro por cada campo que contenga TODAS las palabras (en cualquier orden)
                $field_or[] = build_any_order_accent_filter($f, $tag_words);
            }
            // El registro coincide si AL MENOS UN campo tiene todos los términos
            $conditions[] = '(|' . implode('', $field_or) . ')';
        }
    }

    if (!empty($conditions)) {
        // Unimos todas las condiciones. show_ldapresults las envolverá en un (& ...) de nivel superior.
        $filter = implode('', $conditions);
        $showInactive = (isset($_REQUEST['chkInactivo']) && $_REQUEST['chkInactivo'] == '1');
        
        $order = ['displayname'];
        
        show_ldapresults($filter, $order, $showInactive);
    }
  }
}
