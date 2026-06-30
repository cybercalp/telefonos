<?php
header('Content-Type: application/json; charset=utf-8');

//error_log(print_r('--- load_photo.php ---' . PHP_EOL, TRUE),3,'./kk.log');
//error_log(print_r($_POST['txtThumbnailPhoto'] . PHP_EOL, TRUE),3,'./kk.log');

if (empty($_POST['txtThumbnailPhoto'])) {
    echo json_encode(['success' => false, 'error' => 'Error al subir la imagen.']);
    exit;
}

$data = $_POST['txtThumbnailPhoto'];
if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/', $data, $matches)) {
//if (preg_match('/^data:image\/(jpeg|png);base64,/', $data, $matches)) {
    $mime = $matches[1];
    $base64_data = $matches[2];
    $decodedImage = base64_decode($base64_data);

    if ($decodedImage === false) {
        echo json_encode(['success' => false, 'error' => 'Base64 inválido.']);
        exit;
    }

    if (strlen($decodedImage) > 100 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Imagen demasiado grande. Máximo 100KB.']);
        exit;
    }

    if (!in_array($mime, ['image/jpeg', 'image/png'])) {
       echo json_encode(['success' => false, 'error' => 'Formato inválido. Solo JPG o PNG.']);
       exit;
    }

    echo json_encode(['success' => true, 'base64' => $data]);
} else {
    echo json_encode(['success' => false, 'error' => 'Formato de imagen no válido.']);
}
?>

