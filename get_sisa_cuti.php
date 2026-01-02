<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id_pegawai = $_GET['id_pegawai'] ?? null;
$tahun = $_GET['tahun'] ?? null;

if (!$id_pegawai || !$tahun) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$stmt = $pdo->prepare('SELECT sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
$stmt->execute([$id_pegawai, $tahun]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'sisa_cuti' => $result ? $result['sisa_cuti'] : null
]);
?>