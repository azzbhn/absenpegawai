<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

// Fungsi untuk menghitung hari kerja (exclude Sabtu-Minggu)
function hitungHariKerja($tanggal_mulai, $tanggal_selesai) {
    $start = new DateTime($tanggal_mulai);
    $end = new DateTime($tanggal_selesai);
    $end->modify('+1 day'); // Include end date
    
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    $hari_kerja = 0;
    foreach ($period as $dt) {
        $dayOfWeek = $dt->format('N'); // 1 (Monday) to 7 (Sunday)
        if ($dayOfWeek < 6) { // 1-5 are Monday-Friday
            $hari_kerja++;
        }
    }
    return $hari_kerja;
}

// Fungsi untuk menghitung semua hari (termasuk Sabtu-Minggu)
function hitungSemuaHari($tanggal_mulai, $tanggal_selesai) {
    $start = new DateTime($tanggal_mulai);
    $end = new DateTime($tanggal_selesai);
    $end->modify('+1 day'); // Include end date
    
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    return iterator_count($period);
}

// HAPUS fungsi generateDateRange karena sudah ada di config/db.php
// Gunakan fungsi yang sudah ada di config/db.php

// Proses persetujuan cuti
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id_pengajuan = $_POST['id_pengajuan'];
    $action = $_POST['action'];
    
    try {
        $pdo->beginTransaction();
        
        // Update status pengajuan
        $stmt = $pdo->prepare('UPDATE pengajuan_cuti SET status = ? WHERE id_pengajuan = ?');
        $stmt->execute([$action, $id_pengajuan]);
        
        // Jika disetujui, input ke tabel absensi
        if ($action == 'disetujui') {
            $stmt = $pdo->prepare('
                SELECT pc.id_pegawai, pc.jenis_cuti, pc.tanggal_mulai, pc.tanggal_selesai, pc.jumlah_hari,
                       p.nama, p.cuti_tahunan_diambil, p.total_cuti_sakit
                FROM pengajuan_cuti pc 
                JOIN pegawai p ON pc.id_pegawai = p.id_pegawai
                WHERE pc.id_pengajuan = ?
            ');
            $stmt->execute([$id_pengajuan]);
            $cuti = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate tanggal cuti berdasarkan jenis cuti
            // Gunakan fungsi yang sudah ada di config/db.php
            $tanggal_cuti = generateDateRange($cuti['tanggal_mulai'], $cuti['tanggal_selesai']);
            
            // Tentukan status berdasarkan jenis cuti
            $status_cuti = $cuti['jenis_cuti'] == 'tahunan' ? 'cuti tahunan' : 'cuti sakit';
            
            // Input setiap tanggal cuti ke tabel absensi (hanya hari kerja untuk cuti tahunan)
            foreach ($tanggal_cuti as $tanggal) {
                // Untuk cuti tahunan, hanya input hari kerja (Senin-Jumat)
                if ($cuti['jenis_cuti'] == 'tahunan') {
                    $dayOfWeek = date('N', strtotime($tanggal));
                    if ($dayOfWeek >= 6) { // 6 = Sabtu, 7 = Minggu
                        continue; // Skip weekend untuk cuti tahunan
                    }
                }
                
                // Cek apakah sudah ada data absensi untuk tanggal tersebut
                $stmt_check = $pdo->prepare('
                    SELECT id_absen FROM absensi 
                    WHERE id_pegawai = ? AND tanggal = ?
                ');
                $stmt_check->execute([$cuti['id_pegawai'], $tanggal]);
                
                if (!$stmt_check->fetch()) {
                    // Jika belum ada, insert data cuti
                    $stmt_insert = $pdo->prepare('
                        INSERT INTO absensi 
                        (id_pegawai, tanggal, jam_masuk, jam_keluar, lokasi_lat, lokasi_lng, status) 
                        VALUES (?, ?, NULL, NULL, NULL, NULL, ?)
                    ');
                    $stmt_insert->execute([
                        $cuti['id_pegawai'],
                        $tanggal,
                        $status_cuti
                    ]);
                }
            }
            
            // Update cuti yang diambil berdasarkan jenis cuti
            if ($cuti['jenis_cuti'] == 'tahunan') {
                // Untuk cuti tahunan, update cuti_tahunan_diambil
                $stmt_update = $pdo->prepare('
                    UPDATE pegawai 
                    SET cuti_tahunan_diambil = cuti_tahunan_diambil + ? 
                    WHERE id_pegawai = ?
                ');
                $stmt_update->execute([$cuti['jumlah_hari'], $cuti['id_pegawai']]);
            } else {
                // Untuk cuti sakit, update total_cuti_sakit
                $stmt_update = $pdo->prepare('
                    UPDATE pegawai 
                    SET total_cuti_sakit = total_cuti_sakit + ? 
                    WHERE id_pegawai = ?
                ');
                $stmt_update->execute([$cuti['jumlah_hari'], $cuti['id_pegawai']]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = 'Status pengajuan cuti berhasil diupdate!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
    }
    
    header('Location: persetujuan_cuti.php');
    exit;
}

// Ambil semua pengajuan cuti
$stmt = $pdo->prepare('
    SELECT pc.*, 
           p.nama, p.nip, p.jabatan,
           DATE_FORMAT(pc.tanggal_pengajuan, "%d/%m/%Y %H:%i") as waktu_pengajuan
    FROM pengajuan_cuti pc
    JOIN pegawai p ON pc.id_pegawai = p.id_pegawai
    ORDER BY pc.tanggal_pengajuan DESC
');
$stmt->execute();
$pengajuan_cuti = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

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
    <title>Persetujuan Cuti - Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        #map {
            height: 300px;
            width: 100%;
            border-radius: 12px;
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

    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Persetujuan Pengajuan Cuti</h2>
            <p class="text-gray-600">Kelola dan setujui pengajuan cuti dari pegawai.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Pegawai</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jenis Cuti</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Periode</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jumlah Hari</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Data Dukung</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($pengajuan_cuti)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <i data-feather="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-2"></i>
                                    <p>Belum ada pengajuan cuti</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($pengajuan_cuti as $cuti): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 text-sm text-gray-900"><?= $no++ ?></td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($cuti['nama']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($cuti['nip']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($cuti['jabatan']) ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= $cuti['jenis_cuti'] == 'tahunan' ? 'Cuti Tahunan' : 'Cuti Sakit' ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= date('d/m/Y', strtotime($cuti['tanggal_mulai'])) ?> - 
                                    <?= date('d/m/Y', strtotime($cuti['tanggal_selesai'])) ?>
                                    <br>
                                    <small class="text-gray-500"><?= $cuti['waktu_pengajuan'] ?></small>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= $cuti['jumlah_hari'] ?> hari
                                    <?php if ($cuti['jenis_cuti'] == 'tahunan'): ?>
                                        <br><small class="text-gray-500">(hanya hari kerja)</small>
                                    <?php else: ?>
                                        <br><small class="text-gray-500">(termasuk weekend)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($cuti['link_data_dukung']): ?>
                                        <a href="<?= htmlspecialchars($cuti['link_data_dukung']) ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                            <i data-feather="external-link" class="w-4 h-4"></i>
                                            <span>Lihat</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold 
                                        <?= $cuti['status'] == 'disetujui' ? 'bg-green-100 text-green-800' : 
                                           ($cuti['status'] == 'ditolak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                        <?= $cuti['status'] == 'disetujui' ? 'DISETUJUI' : 
                                           ($cuti['status'] == 'ditolak' ? 'DITOLAK' : 'MENUNGGU') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($cuti['status'] == 'pending'): ?>
                                        <div class="flex space-x-2">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id_pengajuan" value="<?= $cuti['id_pengajuan'] ?>">
                                                <input type="hidden" name="action" value="disetujui">
                                                <button type="submit" 
                                                        class="text-green-600 hover:text-green-800 flex items-center space-x-1"
                                                        onclick="return confirm('Setujui pengajuan cuti ini?')">
                                                    <i data-feather="check" class="w-4 h-4"></i>
                                                    <span>Setujui</span>
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id_pengajuan" value="<?= $cuti['id_pengajuan'] ?>">
                                                <input type="hidden" name="action" value="ditolak">
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-800 flex items-center space-x-1"
                                                        onclick="return confirm('Tolak pengajuan cuti ini?')">
                                                    <i data-feather="x" class="w-4 h-4"></i>
                                                    <span>Tolak</span>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">Telah diproses</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <a href="dashboard.php"
        class="inline-block w-full text-center bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-4 rounded-lg transition duration-200 mt-4">
        Kembali
        </a>
    </main>

    <script>
      const menuToggle = document.getElementById('menu-toggle');
      const menu = document.getElementById('menu');
      menuToggle.addEventListener('click', () => {
        menu.classList.toggle('hidden');
      });
      feather.replace();
    </script>
</body>
</html>
