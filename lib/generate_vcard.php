<?php
// lib/generate_vcard.php
// Generador dinámico de tarjetas vCard (.vcf) leyendo directamente de LDAP para incluir la foto de perfil y múltiples números.

require_once(__DIR__ . '/../private/config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dn = $_GET['dn'] ?? '';
if (empty($dn)) {
    header('HTTP/1.0 400 Bad Request');
    echo "DN de contacto no especificado.";
    exit;
}

// 1. Conectar a LDAP para extraer los datos reales y frescos, incluyendo la foto binaria
$ldap_conn = ldap_connect(get_ldap_uri());
if (!$ldap_conn) {
    die("No se pudo conectar al servidor de directorio para generar el contacto.");
}

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

if (!@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
    die("Error de autenticación con el servicio de directorio.");
}

$attrs = ['displayname', 'mail', 'title', 'department', 'telephonenumber', 'mobile', 'thumbnailphoto', 'wwwhomepage'];
$result = @ldap_read($ldap_conn, $dn, '(objectClass=*)', $attrs);

if (!$result) {
    die("No se pudo leer el registro del usuario.");
}

$entries = ldap_get_entries($ldap_conn, $result);
if ($entries['count'] === 0) {
    die("Registro no encontrado.");
}

$entry = $entries[0];

$fn = $entry['displayname'][0] ?? 'Contacto';
$email = $entry['mail'][0] ?? '';
$title = $entry['title'][0] ?? '';
$org = $entry['department'][0] ?? '';
$tel = $entry['telephonenumber'][0] ?? '';
$cell = $entry['mobile'][0] ?? '';
$photoBin = $entry['thumbnailphoto'][0] ?? null;

// Determinar visibilidad de la foto según el flag wwwhomepage
$wwwhomepage = $entry['wwwhomepage'][0] ?? '';
$wwwhomepage = substr($wwwhomepage . '0000', 0, 4);
$showPhoto = ($wwwhomepage[1] === '1');

$filename = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $fn)) . '.vcf';

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Construir vCard 3.0 estándar compatible con iOS, Android y Outlook
$vcard = "BEGIN:VCARD\r\n";
$vcard .= "VERSION:3.0\r\n";
$vcard .= "FN;CHARSET=UTF-8:" . $fn . "\r\n";
$vcard .= "N;CHARSET=UTF-8:;" . $fn . ";;;\r\n";

if (!empty($org)) {
    $vcard .= "ORG;CHARSET=UTF-8:" . $org . "\r\n";
}
if (!empty($title)) {
    $vcard .= "TITLE;CHARSET=UTF-8:" . $title . "\r\n";
}
if (!empty($email)) {
    $vcard .= "EMAIL;TYPE=PREF,INTERNET:" . $email . "\r\n";
}

// 2. Procesar teléfono fijo (soporta múltiples números separados por / creando entradas individuales)
if (!empty($tel)) {
    $tParts = explode('/', $tel);
    foreach ($tParts as $tPart) {
        $tPartClean = trim($tPart);
        if (!empty($tPartClean)) {
            $vcard .= "TEL;TYPE=WORK,VOICE:" . $tPartClean . "\r\n";
        }
    }
}

// 3. Procesar teléfono móvil (soporta múltiples números separados por / creando entradas individuales)
if (!empty($cell)) {
    $cParts = explode('/', $cell);
    foreach ($cParts as $cPart) {
        $cPartClean = trim($cPart);
        if (!empty($cPartClean)) {
            $vcard .= "TEL;TYPE=CELL,VOICE:" . $cPartClean . "\r\n";
        }
    }
}

// 4. Inyección de la Fotografía en formato JPEG/PNG Base64 con plegado de línea RFC 2425/2426
if (!empty($photoBin) && $showPhoto) {
    $photoBase64 = base64_encode($photoBin);
    // Las líneas del vCard se deben plegar a un máximo de 75 caracteres,
    // y cada línea plegada debe comenzar obligatoriamente con un espacio en blanco.
    $foldedPhoto = chunk_split($photoBase64, 72, "\r\n ");
    $foldedPhoto = rtrim($foldedPhoto, " "); // Elimina el espacio final sobrante
    $vcard .= "PHOTO;TYPE=JPEG;ENCODING=b:" . $foldedPhoto . "\r\n";
}

$vcard .= "END:VCARD\r\n";

echo $vcard;
ldap_unbind($ldap_conn);
exit;
