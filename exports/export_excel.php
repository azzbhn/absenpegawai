<?php
require_once '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: ../index.php');
    exit;
}

// Ambil parameter filter
$filter_tanggal_awal = $_GET['tanggal_awal'] ?? '';
$filter_tanggal_akhir = $_GET['tanggal_akhir'] ?? '';
$filter_nama = $_GET['nama'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build query dengan filter
$where = [];
$params = [];

// Filter range tanggal
if ($filter_tanggal_awal && $filter_tanggal_akhir) {
    $where[] = 'a.tanggal BETWEEN ? AND ?';
    $params[] = $filter_tanggal_awal;
    $params[] = $filter_tanggal_akhir;
} elseif ($filter_tanggal_awal) {
    $where[] = 'a.tanggal >= ?';
    $params[] = $filter_tanggal_awal;
} elseif ($filter_tanggal_akhir) {
    $where[] = 'a.tanggal <= ?';
    $params[] = $filter_tanggal_akhir;
}

if ($filter_nama) {
    $where[] = 'p.nama LIKE ?';
    $params[] = '%' . $filter_nama . '%';
}

if ($filter_status) {
    $where[] = 'a.status = ?';
    $params[] = $filter_status;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Query data absensi
$stmt = $pdo->prepare("
    SELECT a.*, p.nama, p.nip, p.jabatan 
    FROM absensi a 
    JOIN pegawai p ON a.id_pegawai = p.id_pegawai 
    $where_clause 
    ORDER BY a.tanggal DESC, a.jam_masuk DESC
");
$stmt->execute($params);
$absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set header untuk download file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="laporan_absensi_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Output Excel
echo "<table border='1'>";
echo "<tr>";
echo "<th colspan='8' style='background-color: #F9B000; color: white; font-size: 16px;'>LAPORAN ABSENSI PEGAWAI KECAMATAN AJIBARANG</th>";
echo "</tr>";
echo "<tr>";
echo "<th colspan='8'>Tanggal Export: " . date('d/m/Y H:i:s') . "</th>";
echo "</tr>";

// Informasi filter
if ($filter_tanggal_awal || $filter_tanggal_akhir || $filter_nama || $filter_status) {
    echo "<tr>";
    echo "<th colspan='8'>Filter: ";
    $filters = [];
    if ($filter_tanggal_awal && $filter_tanggal_akhir) {
        $filters[] = "Periode: " . date('d/m/Y', strtotime($filter_tanggal_awal)) . " - " . date('d/m/Y', strtotime($filter_tanggal_akhir));
    } elseif ($filter_tanggal_awal) {
        $filters[] = "Dari: " . date('d/m/Y', strtotime($filter_tanggal_awal));
    } elseif ($filter_tanggal_akhir) {
        $filters[] = "Sampai: " . date('d/m/Y', strtotime($filter_tanggal_akhir));
    }
    if ($filter_nama) $filters[] = "Nama: $filter_nama";
    if ($filter_status) $filters[] = "Status: $filter_status";
    echo implode(', ', $filters);
    echo "</th>";
    echo "</tr>";
}

echo "<tr>";
echo "<th>No</th>";
echo "<th>Tanggal</th>";
echo "<th>Nama</th>";
echo "<th>NIP</th>";
echo "<th>Jabatan</th>";
echo "<th>Jam Masuk</th>";
echo "<th>Jam Keluar</th>";
echo "<th>Status</th>";
echo "</tr>";

$no = 1;
foreach ($absensi as $absen) {
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . $absen['tanggal'] . "</td>";
    echo "<td>" . $absen['nama'] . "</td>";
    echo "<td>" . $absen['nip'] . "</td>";
    echo "<td>" . $absen['jabatan'] . "</td>";
    echo "<td>" . ($absen['jam_masuk'] ? $absen['jam_masuk'] : '-') . "</td>";
    echo "<td>" . ($absen['jam_keluar'] ? $absen['jam_keluar'] : '-') . "</td>";
    echo "<td>" . strtoupper($absen['status']) . "</td>";
    echo "</tr>";
}

echo "</table>";
exit;