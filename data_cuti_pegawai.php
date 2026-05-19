<?php
require_once 'config/db.php';
require_once 'config/menu_config.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Hanya administrator yang bisa mengakses
if ($user['jabatan'] !== 'Administrator') {
    header('Location: dashboard.php');
    exit;
}

$current_year = date('Y');

// Get all pegawai AKTIF untuk filter - HANYA YANG STATUS = 'Aktif'
try {
    $stmt = $pdo->query("SELECT id_pegawai, nama, nip FROM pegawai WHERE status = 'Aktif' ORDER BY nama");
    $all_pegawai = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching pegawai: " . $e->getMessage());
    $all_pegawai = [];
}

// Filter parameters - default tahun adalah "all" (semua tahun)
$id_pegawai_filter = $_GET['id_pegawai'] ?? '';
$tahun_filter = $_GET['tahun'] ?? 'all';

// Jika ada filter, get data pegawai yang dipilih
$pegawai_selected = null;
if ($id_pegawai_filter) {
    try {
        $stmt = $pdo->prepare("SELECT id_pegawai, nama, nip, jabatan FROM pegawai WHERE id_pegawai = ? AND status = 'Aktif'");
        $stmt->execute([$id_pegawai_filter]);
        $pegawai_selected = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching selected pegawai: " . $e->getMessage());
        $pegawai_selected = null;
    }
}

// Fungsi untuk mendapatkan hak cuti tahunan
function getHakCutiTahunan($pdo, $id_pegawai, $tahun) {
    try {
        $stmt = $pdo->prepare('SELECT hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['hak_cuti'] : 0;
    } catch (Exception $e) {
        error_log("Error in getHakCutiTahunan: " . $e->getMessage());
        return 0;
    }
}

// Fungsi untuk mendapatkan penggunaan cuti tahunan
function getPenggunaanCutiTahunan($pdo, $id_pegawai, $tahun) {
    try {
        $stmt = $pdo->prepare('SELECT jumlah_hari FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['jumlah_hari'] : 0;
    } catch (Exception $e) {
        error_log("Error in getPenggunaanCutiTahunan: " . $e->getMessage());
        return 0;
    }
}

// Fungsi untuk mendapatkan total cuti berdasarkan jenis (semua tahun)
function getTotalCutiByJenisAllYears($pdo, $id_pegawai, $jenis_cuti) {
    try {
        // Cek apakah tabel log_input_cuti ada
        $table_check = $pdo->query("SHOW TABLES LIKE 'log_input_cuti'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare('
                SELECT SUM(jumlah_hari) as total 
                FROM log_input_cuti 
                WHERE id_pegawai = ? 
                AND jenis_cuti = ?
            ');
            $stmt->execute([$id_pegawai, $jenis_cuti]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total'] !== null) {
                return (int)$result['total'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting cuti from log_input_cuti: " . $e->getMessage());
    }
    
    // Fallback ke tabel absensi
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total 
            FROM absensi 
            WHERE id_pegawai = ? 
            AND status = ?
        ');
        $stmt->execute([$id_pegawai, $jenis_cuti]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error in getTotalCutiByJenisAllYears fallback: " . $e->getMessage());
        return 0;
    }
}

// Fungsi untuk mendapatkan total cuti berdasarkan jenis per tahun
function getTotalCutiByJenisPerTahun($pdo, $id_pegawai, $jenis_cuti, $tahun) {
    try {
        // Cek apakah tabel log_input_cuti ada
        $table_check = $pdo->query("SHOW TABLES LIKE 'log_input_cuti'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare('
                SELECT SUM(jumlah_hari) as total 
                FROM log_input_cuti 
                WHERE id_pegawai = ? 
                AND jenis_cuti = ?
                AND YEAR(tanggal_mulai) = ?
            ');
            $stmt->execute([$id_pegawai, $jenis_cuti, $tahun]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total'] !== null) {
                return (int)$result['total'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting cuti from log_input_cuti: " . $e->getMessage());
    }
    
    // Fallback ke tabel absensi
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total 
            FROM absensi 
            WHERE id_pegawai = ? 
            AND YEAR(tanggal) = ? 
            AND status = ?
        ');
        $stmt->execute([$id_pegawai, $tahun, $jenis_cuti]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error in getTotalCutiByJenisPerTahun fallback: " . $e->getMessage());
        return 0;
    }
}

// Fungsi untuk mendapatkan detail riwayat cuti dari log_input_cuti - DIPERBAIKI
function getDetailCutiFromLogInput($pdo, $id_pegawai, $jenis_cuti, $tahun_filter = 'all') {
    try {
        $sql = '
            SELECT 
                tanggal_mulai,
                tanggal_selesai,
                jumlah_hari,
                YEAR(tanggal_mulai) as tahun,
                jenis_cuti,
                alasan,
                tahun_hak
            FROM log_input_cuti 
            WHERE id_pegawai = ? 
            AND jenis_cuti = ?
        ';
        
        $params = [$id_pegawai, $jenis_cuti];
        
        if ($tahun_filter !== 'all') {
            $sql .= ' AND YEAR(tanggal_mulai) = ?';
            $params[] = $tahun_filter;
        }
        
        $sql .= ' ORDER BY tanggal_mulai DESC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $results;
    } catch (Exception $e) {
        error_log("Error in getDetailCutiFromLogInput: " . $e->getMessage());
        return [];
    }
}

// Map jenis cuti
$jenis_cuti_map = [
    'cuti_tahunan' => [
        'nama' => 'Cuti Tahunan',
        'color' => 'bg-blue-50 border-blue-200',
        'text_color' => 'text-blue-700',
        'icon' => 'calendar'
    ],
    'cuti_sakit' => [
        'nama' => 'Cuti Sakit',
        'color' => 'bg-red-50 border-red-200',
        'text_color' => 'text-red-700',
        'icon' => 'thermometer'
    ],
    'cuti_alasan_penting' => [
        'nama' => 'Cuti Alasan Penting',
        'color' => 'bg-orange-50 border-orange-200',
        'text_color' => 'text-orange-700',
        'icon' => 'alert-circle'
    ],
    'cuti_melahirkan' => [
        'nama' => 'Cuti Melahirkan',
        'color' => 'bg-pink-50 border-pink-200',
        'text_color' => 'text-pink-700',
        'icon' => 'heart'
    ],
    'cuti_besar' => [
        'nama' => 'Cuti Besar',
        'color' => 'bg-purple-50 border-purple-200',
        'text_color' => 'text-purple-700',
        'icon' => 'briefcase'
    ],
    'cuti_luar_tanggungan' => [
        'nama' => 'Cuti Luar Tanggungan',
        'color' => 'bg-indigo-50 border-indigo-200',
        'text_color' => 'text-indigo-700',
        'icon' => 'external-link'
    ]
];

// Jika ada filter, ambil data cuti
$data_cuti = [];
if ($id_pegawai_filter && $pegawai_selected) {
    foreach ($jenis_cuti_map as $jenis => $info) {
        try {
            if ($jenis === 'cuti_tahunan') {
                if ($tahun_filter === 'all') {
                    // Jika semua tahun, kita perlu menghitung total dari semua tahun
                    $total_all_years = getTotalCutiByJenisAllYears($pdo, $id_pegawai_filter, $jenis);
                    // Untuk hak cuti, tampilkan info untuk tahun berjalan saja
                    $hak_cuti_current = getHakCutiTahunan($pdo, $id_pegawai_filter, $current_year);
                    $penggunaan_current = getPenggunaanCutiTahunan($pdo, $id_pegawai_filter, $current_year);
                    
                    $data_cuti[$jenis] = [
                        'total' => $total_all_years,
                        'hak_cuti' => $hak_cuti_current,
                        'penggunaan' => $penggunaan_current,
                        'detail' => getDetailCutiFromLogInput($pdo, $id_pegawai_filter, $jenis, $tahun_filter)
                    ];
                } else {
                    $data_cuti[$jenis] = [
                        'total' => getTotalCutiByJenisPerTahun($pdo, $id_pegawai_filter, $jenis, $tahun_filter),
                        'hak_cuti' => getHakCutiTahunan($pdo, $id_pegawai_filter, $tahun_filter),
                        'penggunaan' => getPenggunaanCutiTahunan($pdo, $id_pegawai_filter, $tahun_filter),
                        'detail' => getDetailCutiFromLogInput($pdo, $id_pegawai_filter, $jenis, $tahun_filter)
                    ];
                }
            } else {
                if ($tahun_filter === 'all') {
                    $data_cuti[$jenis] = [
                        'total' => getTotalCutiByJenisAllYears($pdo, $id_pegawai_filter, $jenis),
                        'detail' => getDetailCutiFromLogInput($pdo, $id_pegawai_filter, $jenis, $tahun_filter)
                    ];
                } else {
                    $data_cuti[$jenis] = [
                        'total' => getTotalCutiByJenisPerTahun($pdo, $id_pegawai_filter, $jenis, $tahun_filter),
                        'detail' => getDetailCutiFromLogInput($pdo, $id_pegawai_filter, $jenis, $tahun_filter)
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error processing cuti data for $jenis: " . $e->getMessage());
            $data_cuti[$jenis] = [
                'total' => 0,
                'hak_cuti' => 0,
                'penggunaan' => 0,
                'detail' => []
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Cuti Pegawai - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Cuti Pegawai</h2>
            <p class="text-gray-600">Monitor data cuti semua pegawai aktif</p>
        </div>

        <!-- Filter Form -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 no-print">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Filter Data</h3>
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Pegawai</label>
                    <select name="id_pegawai" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
                        <option value="">-- Pilih Pegawai --</option>
                        <?php foreach ($all_pegawai as $pegawai): ?>
                            <option value="<?= $pegawai['id_pegawai'] ?>" <?= $id_pegawai_filter == $pegawai['id_pegawai'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pegawai['nama']) ?> (NIP: <?= htmlspecialchars($pegawai['nip']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
                    <select name="tahun" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <option value="all" <?= $tahun_filter == 'all' ? 'selected' : '' ?>>Semua Tahun</option>
                        <?php for ($year = $current_year; $year >= $current_year - 5; $year--): ?>
                            <option value="<?= $year ?>" <?= $tahun_filter == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 transform hover:scale-105">
                        <i data-feather="search" class="inline mr-2 w-4 h-4"></i>
                        Tampilkan Data
                    </button>
                </div>
            </form>
        </div>

        <?php if ($id_pegawai_filter && $pegawai_selected): ?>
            <!-- Informasi Pegawai -->
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl shadow-lg p-6 mb-8 border border-blue-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-bold text-blue-800"><?= htmlspecialchars($pegawai_selected['nama']) ?></h3>
                        <div class="mt-2 space-y-1">
                            <p class="text-gray-700"><span class="font-medium">NIP:</span> <?= htmlspecialchars($pegawai_selected['nip']) ?></p>
                            <p class="text-gray-700"><span class="font-medium">Jabatan:</span> <?= htmlspecialchars($pegawai_selected['jabatan']) ?></p>
                            <p class="text-gray-700">
                                <span class="font-medium">Periode:</span> 
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                                    <?= $tahun_filter == 'all' ? 'Semua Tahun' : 'Tahun ' . $tahun_filter ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <button onclick="window.print()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold py-2 px-4 rounded-lg transition duration-200 transform hover:scale-105 flex items-center space-x-2 no-print shadow-lg">
                        <i data-feather="printer"></i>
                        <span>Cetak Laporan</span>
                    </button>
                </div>
            </div>

            <!-- Data Cuti -->
            <div class="space-y-8">
                <?php foreach ($jenis_cuti_map as $jenis => $info): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden transition-all duration-300 hover:shadow-xl">
                        <!-- Header Jenis Cuti -->
                        <div class="<?= $info['color'] ?> border-b p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 rounded-lg <?= str_replace('text-', 'bg-', $info['text_color']) ?> bg-opacity-10">
                                        <i data-feather="<?= $info['icon'] ?>" class="<?= $info['text_color'] ?>"></i>
                                    </div>
                                    <h3 class="text-xl font-bold <?= $info['text_color'] ?>"><?= $info['nama'] ?></h3>
                                </div>
                                <div class="text-right">
                                    <?php if ($jenis === 'cuti_tahunan'): ?>
                                        <div class="space-y-1">
                                            <?php if ($tahun_filter == 'all'): ?>
                                                <p class="text-sm text-gray-600">Total Semua Tahun: <span class="font-bold <?= $info['text_color'] ?>"><?= $data_cuti[$jenis]['total'] ?? 0 ?> hari</span></p>
                                                <p class="text-sm text-gray-600">Hak Cuti <?= $current_year ?>: <span class="font-bold text-blue-600"><?= $data_cuti[$jenis]['hak_cuti'] ?? 0 ?> hari</span></p>
                                                <p class="text-sm text-gray-600">Digunakan <?= $current_year ?>: <span class="font-bold text-orange-600"><?= $data_cuti[$jenis]['penggunaan'] ?? 0 ?> hari</span></p>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-600">Total Tahun <?= $tahun_filter ?>: <span class="font-bold <?= $info['text_color'] ?>"><?= $data_cuti[$jenis]['total'] ?? 0 ?> hari</span></p>
                                                <p class="text-sm text-gray-600">Hak Cuti: <span class="font-bold text-blue-600"><?= $data_cuti[$jenis]['hak_cuti'] ?? 0 ?> hari</span></p>
                                                <p class="text-sm text-gray-600">Digunakan: <span class="font-bold text-orange-600"><?= $data_cuti[$jenis]['penggunaan'] ?? 0 ?> hari</span></p>
                                                <?php $sisa = max(0, ($data_cuti[$jenis]['hak_cuti'] ?? 0) - ($data_cuti[$jenis]['penggunaan'] ?? 0)); ?>
                                                <p class="text-sm text-gray-600">Sisa: <span class="font-bold <?= $sisa > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                                    <?= $sisa ?> hari
                                                </span></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($tahun_filter == 'all'): ?>
                                            <p class="text-lg font-bold <?= $info['text_color'] ?>">Total Semua Tahun: <?= $data_cuti[$jenis]['total'] ?? 0 ?> hari</p>
                                        <?php else: ?>
                                            <p class="text-lg font-bold <?= $info['text_color'] ?>">Total Tahun <?= $tahun_filter ?>: <?= $data_cuti[$jenis]['total'] ?? 0 ?> hari</p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tabel Riwayat -->
                        <div class="p-6">
                            <?php 
                            $detail_cuti = $data_cuti[$jenis]['detail'] ?? [];
                            if (empty($detail_cuti)): ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i data-feather="<?= $info['icon'] ?>" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                                    <p class="text-gray-600 font-medium">
                                        <?php if ($tahun_filter == 'all'): ?>
                                            Belum ada riwayat <?= strtolower($info['nama']) ?> untuk semua tahun
                                        <?php else: ?>
                                            Belum ada riwayat <?= strtolower($info['nama']) ?> tahun <?= $tahun_filter ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="w-full table-auto">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">No.</th>
                                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Mulai</th>
                                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Selesai</th>
                                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jumlah Hari</th>
                                                <?php if ($tahun_filter == 'all'): ?>
                                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tahun</th>
                                                <?php endif; ?>
                                                <?php if ($jenis !== 'cuti_tahunan'): ?>
                                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Alasan</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php $no = 1; ?>
                                            <?php foreach ($detail_cuti as $riwayat): ?>
                                                <tr class="hover:bg-gray-50 transition duration-150">
                                                    <td class="px-4 py-3 text-sm text-gray-900"><?= $no++ ?></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        <?= !empty($riwayat['tanggal_mulai']) ? date('d/m/Y', strtotime($riwayat['tanggal_mulai'])) : '-' ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">
                                                        <?= !empty($riwayat['tanggal_selesai']) ? date('d/m/Y', strtotime($riwayat['tanggal_selesai'])) : '-' ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm font-medium <?= $info['text_color'] ?>">
                                                        <?= $riwayat['jumlah_hari'] ?? 0 ?> hari
                                                    </td>
                                                    <?php if ($tahun_filter == 'all'): ?>
                                                        <td class="px-4 py-3 text-sm text-gray-900">
                                                            <?= $riwayat['tahun'] ?? $riwayat['tahun_hak'] ?? '-' ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <?php if ($jenis !== 'cuti_tahunan'): ?>
                                                        <td class="px-4 py-3 text-sm text-gray-900">
                                                            <?= !empty($riwayat['alasan']) ? htmlspecialchars($riwayat['alasan']) : '-' ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($id_pegawai_filter && !$pegawai_selected): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-8 text-center">
                <i data-feather="alert-triangle" class="w-16 h-16 mx-auto text-red-500 mb-4"></i>
                <p class="text-red-700 font-semibold">Pegawai tidak ditemukan atau tidak aktif</p>
            </div>
        <?php else: ?>
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-12 text-center">
                <i data-feather="users" class="w-20 h-20 mx-auto text-blue-400 mb-6"></i>
                <h3 class="text-xl font-bold text-blue-800 mb-3">Pilih Pegawai untuk Melihat Data Cuti</h3>
                <p class="text-blue-600 max-w-md mx-auto">Silakan pilih pegawai aktif dari filter di atas untuk menampilkan data cuti lengkap.</p>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
            
            // Auto submit form saat select berubah (opsional)
            const idPegawaiSelect = document.querySelector('select[name="id_pegawai"]');
            if (idPegawaiSelect) {
                idPegawaiSelect.addEventListener('change', function() {
                    if (this.value) {
                        this.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>