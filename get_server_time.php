<?php
require_once 'config/db.php';

header('Content-Type: application/json');

// Pastikan timezone sudah set ke WIB
if (date_default_timezone_get() != 'Asia/Jakarta') {
    date_default_timezone_set('Asia/Jakarta');
}

$response = [
    'success' => true,
    'time' => date('H:i:s'),
    'timezone' => date_default_timezone_get()
];

echo json_encode($response);
?>