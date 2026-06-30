<?php

require_once(__DIR__ . '/../private/config.php');

// OBTENER LA IP DEL CLIENTE
// Usamos REMOTE_ADDR directamente — es la única fuente confiable.
// HTTP_CLIENT_IP y HTTP_X_FORWARDED_FOR son headers que cualquier cliente
// puede inyectar y NO deben usarse para decisiones de seguridad.
// Si la app corre detrás de un reverse proxy de confianza, configurar
// mod_remoteip en Apache para que REMOTE_ADDR refleje la IP real del cliente.
function getIP() {
    return $_SERVER['REMOTE_ADDR'];
}
// VERIFICAR SI UNA IP ESTÁ DENTRO DE UNA SUBRED CIDR
function ipInRange($ip, $rango) {
   if (strpos($rango, '/') === false) {
        return $ip === $rango;
    }

    list($subred, $bits) = explode('/', $rango);

    // Validar que $bits sea un número
    $bits = (int)$bits;
    if ($bits < 0 || $bits > 128) {
        return false; // fuera de rango
    }

    // IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip_bin = inet_pton($ip);
        $subred_bin = inet_pton($subred);
        $bytes = $bits >> 3;
        $bits_restantes = $bits % 8;

        if (strncmp($ip_bin, $subred_bin, $bytes) !== 0) return false;
        if ($bits_restantes === 0) return true;

        $ip_byte = ord($ip_bin[$bytes]);
        $sub_byte = ord($subred_bin[$bytes]);
        $mask = ~((1 << (8 - $bits_restantes)) - 1) & 0xFF;
        return ($ip_byte & $mask) === ($sub_byte & $mask);
    }

    // IPv4
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if ($bits > 32) return false;
        $ip_long = ip2long($ip);
        $subred_long = ip2long($subred);
        $mask = ~((1 << (32 - $bits)) - 1);
        return ($ip_long & $mask) === ($subred_long & $mask);
    }

    return false;
}
//FUNCIÓN QUE NOS INDICA SI UNA IP ES VÁLIDA O NO DENTRO DEL RANGO DEFINIDO EN CONFIG
function ipAllowed($client_ip) {
   global $ip_range;

   $allowed = false;
   foreach ($ip_range as $range) {
      if (ipInRange($client_ip, $range)) {
         $allowed = true;
         break;
      }
   }

   return $allowed;
}
