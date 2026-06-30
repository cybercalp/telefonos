<?php
// ESCRIBE EN LA CONSOLA DEL NAVEGADOR
function debug_to_console($data) {
  // No-op in production — CSP nonces prevent inline scripts without explicit nonce.
  // To re-enable for debugging, pass a valid nonce as second parameter:
  //   debug_to_console($data, $csp_nonce);
  return;
}
