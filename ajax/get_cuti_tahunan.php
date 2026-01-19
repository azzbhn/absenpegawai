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
    
    // Ambil data hak cuti dari database (hak_cuti_tahunan)
    $hak_cuti = [];
    $stmt = $pdo->prepare('SELECT tahun, hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun IN (?, ?, ?)');
    $stmt->execute([$id_pegawai, $years[0], $years[1], $years[2]]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $hak_cuti[$row['tahun']] = (int)$row['hak_cuti'];
    }
    
    // Ambil data penggunaan cuti dari tabel penggunaan_cuti_tahunan
    $penggunaan_cuti = [];
    foreach ($years as $tahun) {
        $stmt = $pdo->prepare('SELECT jumlah_hari FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $penggunaan_cuti[$tahun] = $result ? (int)$result['jumlah_hari'] : 0;
    }
    
    // Hitung sisa cuti untuk setiap tahun
    $sisa_cuti = [];
    foreach ($years as $tahun) {
        $hak = $hak_cuti[$tahun] ?? 0;
        $penggunaan = $penggunaan_cuti[$tahun] ?? 0;
        $sisa_cuti[$tahun] = max(0, $hak - $penggunaan);
    }
    
    echo json_encode([
        'success' => true,
        'hak_cuti' => $hak_cuti,        // Data hak cuti dari tabel hak_cuti_tahunan (tidak berubah)
        'penggunaan_cuti' => $penggunaan_cuti, // Data penggunaan dari tabel penggunaan_cuti_tahunan
        'sisa_cuti' => $sisa_cuti,      // Sisa = Hak - Penggunaan
        'years' => $years
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>