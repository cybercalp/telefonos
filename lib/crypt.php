<?php
/**
 * Cifrado AES-256-CBC y AES-256-GCM para secretos 2FA.
 * La clave debe tener exactamente 32 bytes para AES-256.
 */

// --- Funciones de cifrado seguro con AES-256-CBC ---
function encryptSecret($plaintext, $key) {
    $iv = openssl_random_pseudo_bytes(16); // IV de 16 bytes para AES-CBC
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    $encrypted = base64_encode($iv . $ciphertext); // Concatenamos IV + ciphertext
    return $encrypted;
}

function decryptSecret($encrypted, $key) {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

// --- Funciones de cifrado seguro con AES-256-GCM ---
function encryptSecretGCM($plaintext, $uuid) {
   //Derivar la clave AES-256 desde el UUID usando SHA-256 (32 bytes)
   $key = hash('sha256', $uuid, true); // true ->  salida binaria (32 bytes binarios)

    $iv = random_bytes(12); // GCM usa IV de 12 bytes recomendado
    $tag = '';

    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,    //Se pasa por referencia, la rellena la función openssl_encrypt
        "",      // AAD opcional
        16       // Longitud del tag de autenticación (16 recomendado)
    );
    // Combinar IV + ciphertext + tag
    $combined = $iv . $ciphertext . $tag;

    // Codificamos en base64 para guardar en texto
    $base64 = base64_encode($combined);

    // Validar longitud 1024 => longitud máxima para el campo pager de AD
    if (strlen($base64) > 1024) {
        error_log("encryptSecretGCM: la salida excede los 1024 caracteres del campo pager");
    }


    return $base64;
}

function decryptSecretGCM($base64, $uuid) {
   // Derivar clave de 32 bytes desde UUID con SHA-256
   $key = hash('sha256', $uuid, true);

   $decoded = base64_decode($base64);

   // Verificar que tenga al menos 12 (IV) + 16 (tag)
   if (strlen($decoded) < 28) {
      throw new Exception("Entrada cifrada no válida.");
   }
   
   // Extraer partes
   $iv = substr($decoded, 0, 12);
   $tag = substr($decoded, -16); //Ultimos 16 bytes
   $ciphertext = substr($decoded, 12, -16); //Medio

   //Desencriptar
   $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag, //La rellena la 
        "" // AAD opcional
   );
    if ($plaintext === false) {
        throw new Exception("Error al descifrar. Clave, IV o tag inválidos.");
    }

    return $plaintext;
}
?>

