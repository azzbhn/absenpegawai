<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Ambil parameter bulan dan tahun untuk filter
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

// Validasi bulan dan tahun
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m');
}
if ($selected_year < 2020 || $selected_year > date('Y') + 1) {
    $selected_year = date('Y');
}

// Ambil data absensi hari ini
$today = date('Y-m-d');
$stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
$stmt->execute([$user['id_pegawai'], $today]);
$absensi_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil riwayat absensi bulan ini
$first_day = "$selected_year-$selected_month-01";
$last_day = date('Y-m-t', strtotime($first_day));

$stmt = $pdo->prepare('
    SELECT * FROM absensi 
    WHERE id_pegawai = ? 
    AND tanggal BETWEEN ? AND ?
    ORDER BY tanggal DESC
');
$stmt->execute([$user['id_pegawai'], $first_day, $last_day]);
$riwayat_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik bulan ini
$stmt_stats = $pdo->prepare('
    SELECT 
        COUNT(*) as total_hari,
        SUM(CASE WHEN status = "hadir" THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN status = "cuti tahunan" THEN 1 ELSE 0 END) as cuti_tahunan,
        SUM(CASE WHEN status = "cuti sakit" THEN 1 ELSE 0 END) as cuti_sakit,
        SUM(CASE WHEN status = "izin" THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN status = "sakit" THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN status = "dinas luar" THEN 1 ELSE 0 END) as dinas_luar
    FROM absensi 
    WHERE id_pegawai = ? 
    AND tanggal BETWEEN ? AND ?
');
$stmt_stats->execute([$user['id_pegawai'], $first_day, $last_day]);
$statistik_bulanan = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Generate pilihan bulan dan tahun
$months = [];
for ($i = 1; $i <= 12; $i++) {
    $months[$i] = DateTime::createFromFormat('!m', $i)->format('F');
}

$years = [];
$current_year = date('Y');
for ($i = 2020; $i <= $current_year + 1; $i++) {
    $years[$i] = $i;
}

// Ambil path foto
$nip = htmlspecialchars($user['nip']);
$fotoPath = "assets/pegawai/" . $nip . ".png";
if (!file_exists($fotoPath)) {
    $fotoPath = "assets/pegawai/default.png"; // fallback jika foto tidak ada
}

// ========== ENTERPRISE SECURITY HEADERS ==========
class SecurityManager {
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        // Basic Security Headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        
        // Cache Control
        header("Cache-Control: no-cache, no-store, must-revalidate, private");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        // Remove Server Info
        header_remove("X-Powered-By");
        
        // Enhanced Security Headers
        self::setEnhancedHeaders();
        
        // Start output compression
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
        
        self::$initialized = true;
    }
    
    private static function setEnhancedHeaders() {
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            
            // Scripts
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net",
            
            // Styles
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://fonts.googleapis.com https://unpkg.com https://cdn.jsdelivr.net",
            
            // Fonts
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com https://cdn.jsdelivr.net",
            
            // Images
            "img-src 'self' data: https:",
            
            // AJAX, fetch
            "connect-src 'self'",
            
            // Security
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        
        header("Content-Security-Policy: " . implode('; ', $csp));
        
        // Additional Security Headers
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
        
        // HSTS - hanya di HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }
}

// Initialize security
SecurityManager::init();

// Helper function dengan sanitization
function getPageTitle($default = "Kecamatan Ajibarang") {
    $title = isset($GLOBALS['pageTitle']) ? $GLOBALS['pageTitle'] : $default;
    return htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Set default page title jika belum di-set
if (!isset($GLOBALS['pageTitle'])) {
    $GLOBALS['pageTitle'] = "Kecamatan Ajibarang";
}

// HTML Minifier
ob_start(function($buffer) {
    $buffer = preg_replace('/\s+/', ' ', $buffer);
    $buffer = preg_replace('/>\s+</', '><', $buffer);
    $buffer = preg_replace('/<!--(.*?)-->/', '', $buffer);
    return $buffer;
});
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>
    
    <script>
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    </script>
    
    <!-- Semua submenu Admin sekarang memiliki background hijau utama (#1F9D55) dan efek hover seragam (#188a4a) -->

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Welcome Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Selamat Datang, <?= htmlspecialchars($user['nama']) ?>!</h2>
                    <p class="text-gray-600">Sistem Absensi Berbasis Lokasi GPS Kecamatan Ajibarang</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-semibold text-gray-800" id="current-time"></p>
                    <p class="text-gray-600" id="current-date"></p>
                </div>
            </div>
        </div>

        <!-- Status Absensi Hari Ini -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="clock" class="text-blue-600 w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Status Absensi Hari Ini</h3>
                <p class="text-2xl font-bold <?= $absensi_hari_ini ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $absensi_hari_ini ? strtoupper($absensi_hari_ini['status']) : 'BELUM ABSEN' ?>
                </p>
                <p class="text-sm text-gray-500 mt-1">
                    <?= $absensi_hari_ini && $absensi_hari_ini['jam_masuk'] ? 'Masuk: ' . $absensi_hari_ini['jam_masuk'] : '' ?>
                    <?= $absensi_hari_ini && $absensi_hari_ini['jam_keluar'] ? ' | Pulang: ' . $absensi_hari_ini['jam_keluar'] : '' ?>
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="map-pin" class="text-green-600 w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Lokasi Kantor</h3>
                <p class="text-gray-600">Kecamatan Ajibarang</p>
                <p class="text-sm text-gray-500">Radius: 100 meter</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <!-- Foto pegawai dengan rasio 3:4 -->
                <div class="w-20 h-28 rounded-xl overflow-hidden mx-auto mb-4">
                    <img src="<?= $fotoPath ?>" 
                         alt="Foto Pegawai <?= $nip ?>" 
                         class="w-full h-full object-cover">
                </div>
            
                <h3 class="text-lg font-semibold text-gray-800 mb-2"><?= htmlspecialchars($user['nama']) ?></h3>
                <p class="text-gray-600">NIP: <?= $nip ?></p>
                <p class="text-gray-600">Jabatan: <?= htmlspecialchars($user['jabatan']) ?></p>
            </div>
        </div>

        <!-- Statistik Bulanan -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Statistik Bulan <?= DateTime::createFromFormat('!m', $selected_month)->format('F') ?> <?= $selected_year ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-2xl font-bold text-blue-600"><?= $statistik_bulanan['hadir'] ?></p>
                    <p class="text-sm text-blue-800">Hadir</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-2xl font-bold text-green-600"><?= $statistik_bulanan['cuti_tahunan'] ?></p>
                    <p class="text-sm text-green-800">Cuti Tahunan</p>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <p class="text-2xl font-bold text-yellow-600"><?= $statistik_bulanan['cuti_sakit'] ?></p>
                    <p class="text-sm text-yellow-800">Cuti Sakit</p>
                </div>
                <div class="text-center p-4 bg-orange-50 rounded-lg">
                    <p class="text-2xl font-bold text-orange-600"><?= $statistik_bulanan['izin'] ?></p>
                    <p class="text-sm text-orange-800">Izin</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <p class="text-2xl font-bold text-red-600"><?= $statistik_bulanan['sakit'] ?></p>
                    <p class="text-sm text-red-800">Sakit</p>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <p class="text-2xl font-bold text-purple-600"><?= $statistik_bulanan['dinas_luar'] ?></p>
                    <p class="text-sm text-purple-800">Dinas Luar</p>
                </div>
                 <!--Di bagian Statistik Bulanan, tambahkan kartu informasi shift -->
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                     <!--Foto pegawai dengan rasio 3:4 -->
                    <!--<div class="w-20 h-28 rounded-xl overflow-hidden mx-auto mb-4">-->
                    <!--    <img src="<?= $fotoPath ?>" -->
                    <!--         alt="Foto Pegawai <?= $nip ?>" -->
                    <!--         class="w-full h-full object-cover">-->
                    <!--</div>-->
                
                    <!--<h3 class="text-lg font-semibold text-gray-800 mb-2"><?= htmlspecialchars($user['nama']) ?></h3>-->
                    <!--<p class="text-gray-600">NIP: <?= $nip ?></p>-->
                    <!--<p class="text-gray-600">Jabatan: <?= htmlspecialchars($user['jabatan']) ?></p>-->
                    
                     
                </div>
            </div>
        </div>

        <!-- Filter dan Riwayat Absensi -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 space-y-4 sm:space-y-0">
            <h3 class="text-xl font-bold text-gray-800 text-center sm:text-left">Riwayat Absensi</h3>
            
            <!-- Filter Bulan dan Tahun -->
            <form method="GET" class="flex flex-col sm:flex-row sm:space-x-4 space-y-2 sm:space-y-0 w-full sm:w-auto">
                <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-2 sm:space-y-0 w-full sm:w-auto">
                    <select name="month" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] w-full sm:w-auto">
                        <?php foreach ($months as $key => $month): ?>
                            <option value="<?= $key ?>" <?= $selected_month == $key ? 'selected' : '' ?>>
                                <?= $month ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="year" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] w-full sm:w-auto">
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>" <?= $selected_year == $year ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
        
                <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-2 sm:space-y-0 w-full sm:w-auto">
                    <button type="submit" 
                        class="bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center space-x-2 w-full sm:w-auto">
                        <i data-feather="filter"></i>
                        <span>Filter</span>
                    </button>
                    
                    <a href="dashboard.php" 
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center space-x-2 w-full sm:w-auto">
                        <i data-feather="refresh-cw"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </form>
        </div>


            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Hari</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jam Masuk</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jam Keluar</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat_absensi)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i data-feather="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-2"></i>
                                    <p>Tidak ada data absensi untuk bulan ini</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat_absensi as $absensi): ?>
                            <tr class="border-t hover:bg-gray-50 transition duration-150">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= date('d/m/Y', strtotime($absensi['tanggal'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= 
                                        ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
                                        [date('w', strtotime($absensi['tanggal']))]
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= $absensi['jam_masuk'] ? htmlspecialchars($absensi['jam_masuk']) : '-' ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?= $absensi['jam_keluar'] ? htmlspecialchars($absensi['jam_keluar']) : '-' ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold 
                                        <?= $absensi['status'] == 'hadir' ? 'bg-green-100 text-green-800' : 
                                           ($absensi['status'] == 'izin' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($absensi['status'] == 'sakit' ? 'bg-red-100 text-red-800' : 
                                           ($absensi['status'] == 'dinas luar' ? 'bg-blue-100 text-blue-800' :
                                           ($absensi['status'] == 'cuti tahunan' ? 'bg-purple-100 text-purple-800' :
                                           'bg-orange-100 text-orange-800')))) ?>">
                                        <?= strtoupper($absensi['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?php if ($absensi['lokasi_lat'] && $absensi['lokasi_lng']): ?>
                                        <a href="https://maps.google.com/?q=<?= $absensi['lokasi_lat'] ?>,<?= $absensi['lokasi_lng'] ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                            <i data-feather="map-pin" class="w-4 h-4"></i>
                                            <span>Lokasi</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Info -->
            <div class="mt-4 flex justify-between items-center text-sm text-gray-600">
                <div>
                    Menampilkan <?= count($riwayat_absensi) ?> data dari bulan 
                    <?= DateTime::createFromFormat('!m', $selected_month)->format('F') ?> <?= $selected_year ?>
                </div>
                <div class="flex space-x-2">
                    <?php if ($selected_month > 1 || $selected_year > 2020): ?>
                        <?php
                        $prev_month = $selected_month == 1 ? 12 : $selected_month - 1;
                        $prev_year = $selected_month == 1 ? $selected_year - 1 : $selected_year;
                        ?>
                        <a href="dashboard.php?month=<?= $prev_month ?>&year=<?= $prev_year ?>" 
                           class="flex items-center space-x-1 text-blue-600 hover:text-blue-800">
                            <i data-feather="chevron-left" class="w-4 h-4"></i>
                            <span>Bulan Sebelumnya</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($selected_month < date('m') || $selected_year < date('Y')): ?>
                        <?php
                        $next_month = $selected_month == 12 ? 1 : $selected_month + 1;
                        $next_year = $selected_month == 12 ? $selected_year + 1 : $selected_year;
                        ?>
                        <a href="dashboard.php?month=<?= $next_month ?>&year=<?= $next_year ?>" 
                           class="flex items-center space-x-1 text-blue-600 hover:text-blue-800">
                            <span>Bulan Selanjutnya</span>
                            <i data-feather="chevron-right" class="w-4 h-4"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Update waktu real-time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('id-ID');
            document.getElementById('current-date').textContent = now.toLocaleDateString('id-ID', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Initialize Feather icons
        feather.replace();
        

      const menuToggle = document.getElementById('menu-toggle');
      const menu = document.getElementById('menu');
    
      menuToggle.addEventListener('click', () => {
        menu.classList.toggle('hidden');
      });

    </script>
</body>
</html>