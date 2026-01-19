<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$id_pegawai = isset($_GET['id_pegawai']) ? (int)$_GET['id_pegawai'] : 0;
$tahun_ref = isset($_GET['tahun_ref']) ? (int)$_GET['tahun_ref'] : date('Y');

if ($id_pegawai <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pegawai tidak valid']);
    exit;
}

try {
    // Hitung tahun -1 dan tahun -2 berdasarkan tahun referensi
    $years = [$tahun_ref, $tahun_ref - 1, $tahun_ref - 2];
    
    // Ambil data sisa cuti dari database
    $sisa_cuti = [];
    $stmt = $pdo->prepare('SELECT tahun, sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun IN (?, ?, ?)');
    $stmt->execute([$id_pegawai, $years[0], $years[1], $years[2]]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $sisa_cuti[$row['tahun']] = (int)$row['sisa_cuti'];
    }
    
    // Ambil jumlah cuti tahunan yang diambil untuk setiap tahun
    $cuti_tahunan = [];
    foreach ($years as $tahun) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as jumlah 
            FROM absensi 
            WHERE id_pegawai = ? 
            AND YEAR(tanggal) = ? 
            AND status = "cuti_tahunan"
        ');
        $stmt->execute([$id_pegawai, $tahun]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cuti_tahunan[$tahun] = $result ? (int)$result['jumlah'] : 0;
    }
    
    echo json_encode([
        'success' => true,
        'sisa_cuti' => $sisa_cuti,
        'cuti_tahunan' => $cuti_tahunan
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>