<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$user = $_SESSION['user'];
$current_year = date('Y');

// Get parameters
$tab = $_POST['tab'] ?? 'tahunan';
$tahun_filter = $_POST['tahun'] ?? ($tab == 'tahunan' ? $current_year : 'all');

// Validasi tab
$valid_tabs = ['tahunan', 'sakit', 'alasan_penting', 'melahirkan', 'besar', 'luar_tanggungan'];
if (!in_array($tab, $valid_tabs)) {
    $tab = 'tahunan';
}

// Map jenis cuti
$jenis_cuti_map = [
    'tahunan' => 'cuti_tahunan',
    'sakit' => 'cuti_sakit',
    'alasan_penting' => 'cuti_alasan_penting',
    'melahirkan' => 'cuti_melahirkan',
    'besar' => 'cuti_besar',
    'luar_tanggungan' => 'cuti_luar_tanggungan'
];

// Fungsi yang sama seperti di cuti.php
function getSisaCutiTahunan($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('SELECT sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['sisa_cuti'] : 0;
}

function getCutiTahunanDiambil($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total 
        FROM absensi 
        WHERE id_pegawai = ? 
        AND YEAR(tanggal) = ? 
        AND status = "cuti_tahunan"
    ');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
}

function getCutiByJenisAllYears($pdo, $id_pegawai, $jenis_cuti) {
    $sql = '
        SELECT COUNT(*) as total 
        FROM absensi 
        WHERE id_pegawai = ? 
        AND status = ?
    ';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_pegawai, $jenis_cuti]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
}

function getCutiByJenisPerTahun($pdo, $id_pegawai, $jenis_cuti, $tahun) {
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
}

function getDetailRiwayatCutiByYear($pdo, $id_pegawai, $jenis_cuti, $tahun_filter = 'all') {
    $sql = '
        SELECT 
            tanggal,
            status,
            YEAR(tanggal) as tahun
        FROM absensi 
        WHERE id_pegawai = ? 
        AND status = ?
    ';
    
    $params = [$id_pegawai, $jenis_cuti];
    
    if ($tahun_filter !== 'all') {
        $sql .= ' AND YEAR(tanggal) = ?';
        $params[] = $tahun_filter;
    }
    
    $sql .= ' ORDER BY tanggal DESC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_cuti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_cuti)) {
        return [];
    }
    
    $periods = [];
    $current_period = null;
    
    foreach ($all_cuti as $cuti) {
        $current_date = new DateTime($cuti['tanggal']);
        
        if ($current_period === null) {
            $current_period = [
                'tahun' => $cuti['tahun'],
                'tanggal_mulai' => $cuti['tanggal'],
                'tanggal_selesai' => $cuti['tanggal'],
                'jumlah_hari' => 1,
                'status' => $cuti['status']
            ];
        } else {
            $last_date = new DateTime($current_period['tanggal_selesai']);
            $interval = $last_date->diff($current_date);
            
            if ($interval->days == 1) {
                $current_period['tanggal_selesai'] = $cuti['tanggal'];
                $current_period['jumlah_hari']++;
            } else {
                $periods[] = $current_period;
                $current_period = [
                    'tahun' => $cuti['tahun'],
                    'tanggal_mulai' => $cuti['tanggal'],
                    'tanggal_selesai' => $cuti['tanggal'],
                    'jumlah_hari' => 1,
                    'status' => $cuti['status']
                ];
            }
        }
    }
    
    if ($current_period !== null) {
        $periods[] = $current_period;
    }
    
    return $periods;
}

function getAvailableYears($pdo, $id_pegawai, $jenis_cuti) {
    $stmt = $pdo->prepare('
        SELECT DISTINCT YEAR(tanggal) as tahun
        FROM absensi 
        WHERE id_pegawai = ? 
        AND status = ?
        ORDER BY tahun DESC
    ');
    $stmt->execute([$id_pegawai, $jenis_cuti]);
    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($years as $year) {
        $result[] = $year['tahun'];
    }
    return $result;
}

// Get available years for current tab
$available_years = getAvailableYears($pdo, $user['id_pegawai'], $jenis_cuti_map[$tab]);
if ($tab == 'tahunan') {
    $recent_years = [$current_year, $current_year - 1, $current_year - 2];
    foreach ($recent_years as $year) {
        if (!in_array($year, $available_years)) {
            $available_years[] = $year;
        }
    }
    rsort($available_years);
}

// Get detail riwayat untuk tab aktif
$detail_riwayat_cuti_aktif = getDetailRiwayatCutiByYear($pdo, $user['id_pegawai'], $jenis_cuti_map[$tab], $tahun_filter);

// Render content berdasarkan tab
if ($tab == 'tahunan') {
    // Get data cuti tahunan untuk 3 tahun terakhir
    $cuti_tahunan = [
        'tahun_sekarang' => [
            'tahun' => $current_year,
            'sisa' => getSisaCutiTahunan($pdo, $user['id_pegawai'], $current_year),
            'diambil' => getCutiTahunanDiambil($pdo, $user['id_pegawai'], $current_year)
        ],
        'tahun_lalu' => [
            'tahun' => $current_year - 1,
            'sisa' => getSisaCutiTahunan($pdo, $user['id_pegawai'], $current_year - 1),
            'diambil' => getCutiTahunanDiambil($pdo, $user['id_pegawai'], $current_year - 1)
        ],
        'tahun_dulu' => [
            'tahun' => $current_year - 2,
            'sisa' => getSisaCutiTahunan($pdo, $user['id_pegawai'], $current_year - 2),
            'diambil' => getCutiTahunanDiambil($pdo, $user['id_pegawai'], $current_year - 2)
        ]
    ];
    
    // Hitung hak cuti
    foreach ($cuti_tahunan as $key => &$cuti) {
        $cuti['hak_cuti'] = $cuti['sisa'] + $cuti['diambil'];
    }
    
    // Hitung total untuk statistik (hanya tahun ini)
    // Total Sisa Cuti = Sisa Cuti tahun ini + Sisa Cuti tahun-1 + Sisa Cuti tahun-2
    $total_sisa_cuti = $cuti_tahunan['tahun_sekarang']['sisa'] + 
                       $cuti_tahunan['tahun_lalu']['sisa'] + 
                       $cuti_tahunan['tahun_dulu']['sisa'];
    
    // Total Cuti Diambil = Cuti yang diambil pada tahun ini saja
    $total_cuti_diambil = $cuti_tahunan['tahun_sekarang']['diambil'];
    
    // Persentase Penggunaan Cuti = (Total Cuti Diambil / Total Sisa Cuti) × 100%
    $total_persentase = $total_sisa_cuti > 0 ? ($total_cuti_diambil / $total_sisa_cuti * 100) : 0;
    
    // Persentase penggunaan cuti tahun ini (dari hak cuti tahun ini saja)
    $persentase_tahun_ini = $cuti_tahunan['tahun_sekarang']['hak_cuti'] > 0 ? 
                            ($cuti_tahunan['tahun_sekarang']['diambil'] / $cuti_tahunan['tahun_sekarang']['hak_cuti'] * 100) : 0;

    
    // Render HTML untuk tab tahunan
    ?>
    <h3 class="text-xl font-bold text-gray-800 mb-6">Informasi Cuti Tahunan</h3>
    
    <!-- Layout baru: 3 kolom (tahun sekarang, tahun lalu, tahun dulu) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Card Tahun Sekarang -->
        <div class="cuti-card bg-white rounded-xl shadow-lg p-6 border border-gray-200 h-full">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-lg text-green-600">Tahun <?= $cuti_tahunan['tahun_sekarang']['tahun'] ?></h4>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                    Berjalan
                </span>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Sisa Cuti:</span>
                    <span class="font-bold text-lg <?= $cuti_tahunan['tahun_sekarang']['sisa'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $cuti_tahunan['tahun_sekarang']['sisa'] ?> hari
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Sudah Diambil:</span>
                    <span class="font-bold text-orange-600"><?= $cuti_tahunan['tahun_sekarang']['diambil'] ?> hari</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Hak Cuti:</span>
                    <span class="font-bold text-blue-600"><?= $cuti_tahunan['tahun_sekarang']['hak_cuti'] ?> hari</span>
                </div>
            </div>
        </div>

        <!-- Card Tahun Lalu -->
        <div class="cuti-card bg-white rounded-xl shadow-lg p-6 border border-gray-200 h-full">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-lg text-yellow-600">Tahun <?= $cuti_tahunan['tahun_lalu']['tahun'] ?></h4>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                    Tahun Lalu
                </span>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Sisa Cuti:</span>
                    <span class="font-bold text-lg <?= $cuti_tahunan['tahun_lalu']['sisa'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $cuti_tahunan['tahun_lalu']['sisa'] ?> hari
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Sudah Diambil:</span>
                    <span class="font-bold text-orange-600"><?= $cuti_tahunan['tahun_lalu']['diambil'] ?> hari</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Hak Cuti:</span>
                    <span class="font-bold text-blue-600"><?= $cuti_tahunan['tahun_lalu']['hak_cuti'] ?> hari</span>
                </div>
            </div>
        </div>

        <!-- Card Tahun Dulu -->
        <div class="cuti-card bg-white rounded-xl shadow-lg p-6 border border-gray-200 h-full">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-lg text-gray-600">Tahun <?= $cuti_tahunan['tahun_dulu']['tahun'] ?></h4>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                    2 Tahun Lalu
                </span>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Sisa Cuti:</span>
                    <span class="font-bold text-lg <?= $cuti_tahunan['tahun_dulu']['sisa'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $cuti_tahunan['tahun_dulu']['sisa'] ?> hari
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Sudah Diambil:</span>
                    <span class="font-bold text-orange-600"><?= $cuti_tahunan['tahun_dulu']['diambil'] ?> hari</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Hak Cuti:</span>
                    <span class="font-bold text-blue-600"><?= $cuti_tahunan['tahun_dulu']['hak_cuti'] ?> hari</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Statistik (lebar 2 kolom) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="cuti-card bg-white rounded-xl shadow-lg p-6 border border-gray-200 stat-card md:col-span-2">
            <h4 class="font-bold text-lg text-gray-800 mb-4">Statistik Penggunaan Cuti</h4>
            <div class="space-y-6">
                <!-- Baris 1: Total Sisa Cuti dan Total Cuti Diambil -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Total Sisa Cuti (Sisa Cuti 3 Tahun) -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-center">
                            <h5 class="font-bold text-lg text-blue-800 mb-2">Total Sisa Cuti</h5>
                            <div class="text-5xl font-bold text-blue-600 mb-4"><?= $total_sisa_cuti ?></div>
                            <div class="text-xs text-gray-600 space-y-1">
                                <div>Tahun <?= $cuti_tahunan['tahun_sekarang']['tahun'] ?>: 
                                    <span class="font-semibold"><?= $cuti_tahunan['tahun_sekarang']['sisa'] ?> hari</span>
                                </div>
                                <div>Tahun <?= $cuti_tahunan['tahun_lalu']['tahun'] ?>: 
                                    <span class="font-semibold"><?= $cuti_tahunan['tahun_lalu']['sisa'] ?> hari</span>
                                </div>
                                <div>Tahun <?= $cuti_tahunan['tahun_dulu']['tahun'] ?>: 
                                    <span class="font-semibold"><?= $cuti_tahunan['tahun_dulu']['sisa'] ?> hari</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Cuti Diambil (Hanya Tahun Ini) -->
                    <div class="bg-orange-50 p-4 rounded-lg">
                        <div class="text-center">
                            <h5 class="font-bold text-lg text-orange-800 mb-2">Total Cuti Diambil</h5>
                            <div class="text-5xl font-bold text-orange-600"><?= $total_cuti_diambil ?></div>
                            <p class="text-gray-600 text-sm mt-4">Cuti Diambil Tahun <?= $current_year ?></p>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Baris 2: Progress Bar untuk Persentase Penggunaan Cuti -->
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <h5 class="font-bold text-gray-800 mb-3">Persentase Penggunaan Cuti</h5>
                    
                    <!-- Progress Bar Utama: (Total Cuti Diambil Tahun Ini / Total Sisa Cuti 3 Tahun) -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-1">
                            <div>
                                <span class="text-sm text-gray-600">Penggunaan Cuti Tahun <?= $current_year ?> dari Total Sisa Cuti 3 Tahun</span>
                                <span class="ml-2 text-sm font-bold text-orange-600"><?= number_format($total_persentase, 1) ?>%</span>
                            </div>
                            <span class="text-xs font-bold <?= $total_persentase > 20 ? 'text-red-600' : ($total_persentase > 10 ? 'text-yellow-600' : 'text-green-600') ?>">
                                <?= $total_persentase > 20 ? 'Tinggi' : ($total_persentase > 10 ? 'Sedang' : 'Rendah') ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-orange-500 h-3 rounded-full" 
                                 style="width: <?= min(100, $total_persentase) ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>0%</span>
                            <span>10%</span>
                            <span>20%</span>
                            <span>30%</span>
                            <span>40%+</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Rumus: (<?= $total_cuti_diambil ?> hari / <?= $total_sisa_cuti ?> hari) × 100% = <?= number_format($total_persentase, 1) ?>%
                        </p>
                    </div>
                    
                    <!-- Progress Bar untuk Penggunaan Cuti Tahun Ini -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <div>
                                <span class="text-sm text-gray-600">Penggunaan Cuti Tahun <?= $current_year ?> dari Hak Cuti Tahun Ini</span>
                                <span class="ml-2 text-sm font-bold text-green-600"><?= number_format($persentase_tahun_ini, 1) ?>%</span>
                            </div>
                            <span class="text-xs font-bold <?= $persentase_tahun_ini > 80 ? 'text-red-600' : ($persentase_tahun_ini > 50 ? 'text-yellow-600' : 'text-green-600') ?>">
                                <?= $persentase_tahun_ini > 80 ? 'Tinggi' : ($persentase_tahun_ini > 50 ? 'Sedang' : 'Rendah') ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-green-500 h-2.5 rounded-full" 
                                 style="width: <?= min(100, $persentase_tahun_ini) ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>0%</span>
                            <span>50%</span>
                            <span>100%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat Cuti Tahunan -->
    <div class="mt-8">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-bold text-gray-800">
                Riwayat Pengambilan Cuti Tahunan 
                <?= $tahun_filter !== 'all' ? 'Tahun ' . $tahun_filter : '(Semua Tahun)' ?>
            </h4>
            <div class="flex items-center space-x-4">
                <!-- Filter Tahun -->
                <form method="GET" action="" class="flex items-center">
                    <input type="hidden" name="tab" value="tahunan">
                    <select name="tahun" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <option value="all" <?= $tahun_filter == 'all' ? 'selected' : '' ?>>Semua Tahun</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?= $year ?>" <?= $tahun_filter == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center space-x-2 no-print print-button">
                    <i data-feather="printer"></i>
                    <span>Cetak Riwayat</span>
                </button>
            </div>
        </div>
        
        <?php if (empty($detail_riwayat_cuti_aktif)): ?>
            <div class="bg-gray-50 rounded-xl p-8 text-center">
                <i data-feather="calendar" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                <p class="text-gray-600 font-semibold">Belum ada riwayat cuti tahunan</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">No.</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tahun</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Jumlah Hari</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Mulai</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Selesai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $no = 1; ?>
                            <?php foreach ($detail_riwayat_cuti_aktif as $riwayat): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $no++ ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= $riwayat['tahun'] ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $riwayat['jumlah_hari'] ?> hari</td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('d/m/Y', strtotime($riwayat['tanggal_mulai'])) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('d/m/Y', strtotime($riwayat['tanggal_selesai'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
} else {
    // Render untuk tab lainnya (sakit, alasan_penting, melahirkan, besar, luar_tanggungan)
    $tab_nama = [
        'sakit' => 'Cuti Sakit',
        'alasan_penting' => 'Cuti Alasan Penting',
        'melahirkan' => 'Cuti Melahirkan',
        'besar' => 'Cuti Besar',
        'luar_tanggungan' => 'Cuti di Luar Tanggungan Negara'
    ];
    
    $tab_icons = [
        'sakit' => 'thermometer',
        'alasan_penting' => 'alert-circle',
        'melahirkan' => 'heart',
        'besar' => 'briefcase',
        'luar_tanggungan' => 'external-link'
    ];
    
    $tab_colors = [
        'sakit' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'text-red-600'],
        'alasan_penting' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'icon' => 'text-orange-600'],
        'melahirkan' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-800', 'icon' => 'text-pink-600'],
        'besar' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'text-purple-600'],
        'luar_tanggungan' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'icon' => 'text-indigo-600']
    ];
    
    $total_semua_tahun = getCutiByJenisAllYears($pdo, $user['id_pegawai'], $jenis_cuti_map[$tab]);
    $cuti_tahun_ini = getCutiByJenisPerTahun($pdo, $user['id_pegawai'], $jenis_cuti_map[$tab], $current_year);
    ?>
    <h3 class="text-xl font-bold text-gray-800 mb-6"><?= $tab_nama[$tab] ?></h3>
    
    <!-- Statistik Cuti (Semua Tahun) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="cuti-card bg-white rounded-xl shadow-lg p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-lg <?= $tab_colors[$tab]['icon'] ?>">Total <?= $tab_nama[$tab] ?></h4>
                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $tab_colors[$tab]['bg'] ?> <?= $tab_colors[$tab]['text'] ?>">
                    Semua Tahun
                </span>
            </div>
            <div class="text-center py-4">
                <div class="text-4xl font-bold <?= $tab_colors[$tab]['icon'] ?> mb-2"><?= $total_semua_tahun ?></div>
                <p class="text-gray-600">Total hari cuti yang pernah diambil</p>
            </div>
        </div>
        
        <div class="cuti-card bg-white rounded-xl shadow-lg p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-bold text-lg <?= $tab_colors[$tab]['icon'] ?>">Tahun <?= $current_year ?></h4>
                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $tab_colors[$tab]['bg'] ?> <?= $tab_colors[$tab]['text'] ?>">
                    Tahun Ini
                </span>
            </div>
            <div class="text-center py-4">
                <div class="text-4xl font-bold <?= $tab_colors[$tab]['icon'] ?> mb-2"><?= $cuti_tahun_ini ?></div>
                <p class="text-gray-600">Hari diambil tahun ini</p>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat Cuti -->
    <div class="mt-8">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-bold text-gray-800">
                Riwayat <?= $tab_nama[$tab] ?> 
                <?= $tahun_filter !== 'all' ? 'Tahun ' . $tahun_filter : '(Semua Tahun)' ?>
            </h4>
            <div class="flex items-center space-x-4">
                <!-- Filter Tahun -->
                <form method="GET" action="" class="flex items-center">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <select name="tahun" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                        <option value="all" <?= $tahun_filter == 'all' ? 'selected' : '' ?>>Semua Tahun</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?= $year ?>" <?= $tahun_filter == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center space-x-2 no-print print-button">
                    <i data-feather="printer"></i>
                    <span>Cetak Riwayat</span>
                </button>
            </div>
        </div>
        
        <?php if (empty($detail_riwayat_cuti_aktif)): ?>
            <div class="bg-gray-50 rounded-xl p-8 text-center">
                <i data-feather="<?= $tab_icons[$tab] ?>" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                <p class="text-gray-600 font-semibold">Belum ada riwayat <?= strtolower($tab_nama[$tab]) ?></p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">No.</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tahun</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Jumlah Hari</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Mulai</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Selesai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $no = 1; ?>
                            <?php foreach ($detail_riwayat_cuti_aktif as $riwayat): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $no++ ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= $riwayat['tahun'] ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $riwayat['jumlah_hari'] ?> hari</td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('d/m/Y', strtotime($riwayat['tanggal_mulai'])) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('d/m/Y', strtotime($riwayat['tanggal_selesai'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>