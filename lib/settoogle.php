<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizarToggle') {
    $_SESSION['estado_toggle'] = (int)$_POST['estado'];
    echo json_encode(['success' => true, 'estado' => $_SESSION['estado_toggle']]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
