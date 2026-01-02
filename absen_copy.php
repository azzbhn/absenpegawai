<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Tentukan apakah user adalah Jaga Malam
$is_jaga_malam = ($user['jabatan'] == 'Jaga Malam');

// Koordinat kantor
$kantor_lat = KANTOR_LAT;
$kantor_lng = KANTOR_LNG;
$radius = RADIUS_METER;

// Cek apakah sudah absen hari ini - GUNAKAN WAKTU SERVER
$today = date('Y-m-d');
$current_time = date('H:i:s');
$current_hour = date('H:i');

// Tentukan tanggal yang akan digunakan untuk pengecekan absensi
$check_date = $today;

// Untuk Jaga Malam di jam 00:00-10:00, kita cek absensi di hari sebelumnya
if ($is_jaga_malam && $current_hour >= '00:00' && $current_hour <= '10:00') {
    $check_date = date('Y-m-d', strtotime('-1 day'));
}

// CEK APAKAH USER SEDANG CUTI HARI INI
$stmt_cuti = $pdo->prepare('
    SELECT * FROM absensi 
    WHERE id_pegawai = ? 
    AND tanggal = ? 
    AND status IN ("cuti tahunan", "cuti sakit")
');
$stmt_cuti->execute([$user['id_pegawai'], $today]);
$cuti_hari_ini = $stmt_cuti->fetch(PDO::FETCH_ASSOC);

$sedang_cuti = $cuti_hari_ini ? true : false;
$jenis_cuti = $sedang_cuti ? $cuti_hari_ini['status'] : '';

// Gunakan $check_date untuk mengecek absensi
$stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
$stmt->execute([$user['id_pegawai'], $check_date]);
$absensi = $stmt->fetch(PDO::FETCH_ASSOC);

$sudah_absen_masuk = $absensi && $absensi['jam_masuk'] != null;
$sudah_absen_pulang = $absensi && $absensi['jam_keluar'] != null;

// Untuk kompatibilitas dengan kode lain
$absensi_hari_ini = $absensi;

// Tentukan apakah hari ini Jumat (5 = Friday)
$is_friday = (date('N') == 5); // 1=Monday, 5=Friday, 7=Sunday

// Proses absensi - GUNAKAN WAKTU SERVER
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CEK APAKAH SEDANG CUTI - TAMBAHKAN VALIDASI INI
    if ($sedang_cuti) {
        $error = 'Anda sedang cuti ' . $jenis_cuti . ' hari ini. Tidak dapat melakukan absensi.';
    } else {
        $action = $_POST['action'];
        $lat = $_POST['lat'];
        $lng = $_POST['lng'];

        // Validasi lokasi
        $jarak = hitungJarak($lat, $lng, $kantor_lat, $kantor_lng);
        
        if ($jarak <= $radius) {
            // Validasi waktu berdasarkan jabatan dengan fleksibilitas baru untuk masuk
            $waktu_sekarang = date('H:i:s');
            $jam_sekarang = date('H:i', strtotime($waktu_sekarang));
            
            if ($is_jaga_malam) {
                // Validasi untuk Jaga Malam dengan fleksibilitas baru
                if ($action == 'masuk') {
                    // Jaga Malam: masuk 14:30 - 18:30 (1 jam sebelum dan sesudah 15:30)
                    // TAPI tidak boleh di jam 00:00 - 10:00 (waktu pulang)
                    if ($jam_sekarang >= '00:00' && $jam_sekarang <= '10:00') {
                        $_SESSION['error'] = 'Jam masuk untuk Jaga Malam adalah antara 14:30 - 18:30. Saat ini adalah waktu pulang (00:00-10:00). Waktu sekarang: ' . $jam_sekarang;
                        header('Location: absen.php');
                        exit;
                    }
                    if ($jam_sekarang < '14:30' || $jam_sekarang > '18:30') {
                        $_SESSION['error'] = 'Jam masuk untuk Jaga Malam adalah antara 14:30 - 18:30 (1 jam sebelum dan sesudah 15:30). Waktu sekarang: ' . $jam_sekarang;
                        header('Location: absen.php');
                        exit;
                    }
                } elseif ($action == 'pulang') {
                    // Jaga Malam: pulang 00:00 - 10:00 (4 jam setelah 06:00)
                    // Tidak boleh di jam 14:30 - 18:30 (waktu masuk)
                    if ($jam_sekarang >= '14:30' && $jam_sekarang <= '18:30') {
                        $_SESSION['error'] = 'Jam pulang untuk Jaga Malam adalah antara 00:00 - 10:00. Saat ini adalah waktu masuk (14:30-18:30). Waktu sekarang: ' . $jam_sekarang;
                        header('Location: absen.php');
                        exit;
                    }
                    if (!(($jam_sekarang >= '00:00' && $jam_sekarang <= '10:00') || 
                          ($jam_sekarang > '18:30' && $jam_sekarang <= '23:59'))) {
                        $_SESSION['error'] = 'Jam pulang untuk Jaga Malam adalah antara 00:00 - 10:00 (4 jam setelah 06:00). Waktu sekarang: ' . $jam_sekarang;
                        header('Location: absen.php');
                        exit;
                    }
                }
            } else {
                // Validasi untuk pegawai reguler dengan fleksibilitas baru untuk masuk
                if ($action == 'masuk') {
                    // Reguler: masuk bisa 1 jam sebelum 07:15 (06:15) sampai 1 jam setelah 07:15 (08:15)
                    if ($jam_sekarang < '06:15' || $jam_sekarang > '08:15') {
                        $_SESSION['error'] = 'Jam masuk untuk pegawai reguler adalah antara 06:15 - 08:15 (1 jam sebelum dan sesudah 07:15). Waktu sekarang: ' . $jam_sekarang;
                        header('Location: absen.php');
                        exit;
                    }
                } elseif ($action == 'pulang') {
                    // PERBAIKAN: Kebijakan baru ditambah 1 jam sebelumnya
                    // Reguler: pulang 15:30 (atau 15:15 Jumat) dikurangi 1 jam, sampai 4 jam setelahnya.
                    
                    if ($is_friday) {
                        // Hari Jumat: 14:15 - 19:15 (15:15 minus 1 jam s/d 15:15 plus 4 jam)
                        if ($jam_sekarang < '14:15' || $jam_sekarang > '19:15') {
                            $_SESSION['error'] = 'Jam pulang untuk pegawai reguler hari Jumat adalah antara 14:15 - 19:15 (1 jam sebelum 15:15 s/d 4 jam sesudahnya). Waktu sekarang: ' . $jam_sekarang;
                            header('Location: absen.php');
                            exit;
                        }
                    } else {
                        // Hari selain Jumat: 14:30 - 19:30 (15:30 minus 1 jam s/d 15:30 plus 4 jam)
                        if ($jam_sekarang < '14:30' || $jam_sekarang > '19:30') {
                            $_SESSION['error'] = 'Jam pulang untuk pegawai reguler adalah antara 14:30 - 19:30 (1 jam sebelum 15:30 s/d 4 jam sesudahnya). Waktu sekarang: ' . $jam_sekarang;
                            header('Location: absen.php');
                            exit;
                        }
                    }
                }
            }
            
            if ($action == 'masuk' && !$sudah_absen_masuk) {
                // Tentukan tanggal untuk absensi masuk
                $absen_tanggal = $today;
                
                // GUNAKAN WAKTU SERVER PHP
                $waktu_sekarang = date('H:i:s');
                $stmt = $pdo->prepare('INSERT INTO absensi (id_pegawai, tanggal, jam_masuk, lokasi_lat, lokasi_lng, status) VALUES (?, ?, ?, ?, ?, "hadir")');
                $stmt->execute([$user['id_pegawai'], $absen_tanggal, $waktu_sekarang, $lat, $lng]);
                
                // NOTIFIKASI KHUSUS UNTUK JAGA MALAM
                if ($is_jaga_malam) {
                    $_SESSION['success'] = 'Absen masuk shift malam berhasil pukul ' . $waktu_sekarang . '! Selamat bertugas.';
                } else {
                    $_SESSION['success'] = 'Absen masuk berhasil pukul ' . $waktu_sekarang . '!';
                }
                
                header('Location: absen.php');
                exit;
                
            } elseif ($action == 'pulang') {
                // PERBAIKAN: Izinkan absen pulang meskipun belum absen masuk (Dihapus blok yang memblokir jika belum absen masuk)
                
                if ($sudah_absen_pulang) {
                    $_SESSION['error'] = 'Anda sudah absen pulang hari ini.';
                    header('Location: absen.php');
                    exit;
                }
                
                // Tentukan tanggal untuk update absensi pulang
                $update_date = $check_date; // Gunakan tanggal yang sama dengan pengecekan absensi
                
                // GUNAKAN WAKTU SERVER PHP
                $waktu_sekarang = date('H:i:s');
                
                // Cek apakah sudah ada record absensi
                if ($absensi) {
                    // Update record yang sudah ada
                    $stmt = $pdo->prepare('UPDATE absensi SET jam_keluar = ? WHERE id_pegawai = ? AND tanggal = ?');
                    $stmt->execute([$waktu_sekarang, $user['id_pegawai'], $update_date]);
                } else {
                    // Buat record baru dengan jam_masuk NULL (untuk kasus tidak absen masuk)
                    $stmt = $pdo->prepare('INSERT INTO absensi (id_pegawai, tanggal, jam_masuk, jam_keluar, lokasi_lat, lokasi_lng, status) VALUES (?, ?, NULL, ?, ?, ?, "hadir")');
                    $stmt->execute([$user['id_pegawai'], $update_date, $waktu_sekarang, $lat, $lng]);
                }
                
                // NOTIFIKASI KHUSUS UNTUK JAGA MALAM
                if ($is_jaga_malam) {
                    $_SESSION['success'] = 'Absen pulang shift malam berhasil pukul ' . $waktu_sekarang . '! Istirahat yang cukup.';
                } else {
                    $_SESSION['success'] = 'Absen pulang berhasil pukul ' . $waktu_sekarang . '!';
                }
                
                header('Location: absen.php');
                exit;
                
            } else {
                $_SESSION['error'] = 'Anda sudah absen masuk/pulang hari ini.';
                header('Location: absen.php');
                exit;
            }
        } else {
            $_SESSION['error'] = 'Anda berada di luar area kantor Kecamatan Ajibarang. Jarak: ' . round($jarak, 2) . ' meter.';
            header('Location: absen.php');
            exit;
        }
    }
}

// Ambil pesan dari session
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Hapus pesan dari session setelah ditampilkan
unset($_SESSION['success']);
unset($_SESSION['error']);


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Kecamatan Ajibarang</title>
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
        .jaga-malam-notif {
            border-left: 4px solid #3B82F6;
            background: linear-gradient(135deg, #1e40af20, #3b82f610);
        }
        .reguler-notif {
            border-left: 4px solid #10B981;
            background: linear-gradient(135deg, #04785720, #10b98110);
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Absensi Pegawai</h2>
            <p class="text-gray-600">Silakan lakukan absensi masuk dan pulang di halaman ini.</p>
            <?php if ($is_friday && !$is_jaga_malam): ?>
                <p class="text-yellow-600 font-semibold mt-2"><i data-feather="info" class="inline mr-1"></i> Hari Jumat: Jam pulang reguler diperbolehkan mulai pukul 14:15</p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Form Absensi</h3>
                
                <?php if ($success): ?>
                    <div class="<?= $is_jaga_malam ? 'jaga-malam-notif' : 'reguler-notif' ?> text-gray-700 px-4 py-3 rounded mb-4 flex items-start">
                        <?php if ($is_jaga_malam): ?>
                            <i data-feather="moon" class="w-5 h-5 mr-2 text-blue-600 mt-0.5"></i>
                        <?php else: ?>
                            <i data-feather="check-circle" class="w-5 h-5 mr-2 text-green-600 mt-0.5"></i>
                        <?php endif; ?>
                        <div>
                            <p class="font-semibold <?= $is_jaga_malam ? 'text-blue-800' : 'text-green-800' ?>">
                                <?= $is_jaga_malam ? 'Shift Malam Berhasil!' : 'Berhasil!' ?>
                            </p>
                            <p class="text-sm"><?= $success ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($sedang_cuti): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i data-feather="calendar" class="w-5 h-5 mr-2"></i>
                        <div>
                            <p class="font-semibold">Anda Sedang Cuti</p>
                            <p class="text-sm">Status: <span class="font-bold"><?= ucfirst($jenis_cuti) ?></span></p>
                            <p class="text-sm">Anda tidak dapat melakukan absensi selama periode cuti.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="space-y-4 mb-6">
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Nama:</span>
                        <span><?= htmlspecialchars($user['nama']) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Jabatan:</span>
                        <span><?= htmlspecialchars($user['jabatan']) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 <?= $is_jaga_malam ? 'bg-blue-50' : 'bg-gray-50' ?> rounded-lg">
                        <span class="font-semibold">Shift:</span>
                        <span class="font-semibold <?= $is_jaga_malam ? 'text-blue-600' : 'text-green-600' ?>">
                            <?= $is_jaga_malam ? 'JAGA MALAM' : 'REGULER' ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-4 <?= $is_jaga_malam ? 'bg-blue-50' : 'bg-gray-50' ?> rounded-lg">
                        <span class="font-semibold">Jam Absen Masuk:</span>
                        <span class="font-semibold <?= $is_jaga_malam ? 'text-blue-600' : 'text-green-600' ?>">
                            <?= $is_jaga_malam ? '14:30 - 18:30' : '06:15 - 08:15' ?>
                        </span>
                        <span class="text-xs <?= $is_jaga_malam ? 'text-blue-500' : 'text-green-500' ?> ml-2">
                            <?= $is_jaga_malam ? '(1 jam sebelum & sesudah 15:30)' : '(1 jam sebelum & sesudah 07:15)' ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-4 <?= $is_jaga_malam ? 'bg-blue-50' : 'bg-gray-50' ?> rounded-lg">
                        <span class="font-semibold">Jam Absen Pulang:</span>
                        <span class="font-semibold <?= $is_jaga_malam ? 'text-blue-600' : 'text-green-600' ?>">
                            <?php 
                            if ($is_jaga_malam) {
                                echo '00:00 - 10:00';
                            } else {
                                echo $is_friday ? '14:15 - 19:15' : '14:30 - 19:30';
                            }
                            ?>
                        </span>
                        <span class="text-xs <?= $is_jaga_malam ? 'text-blue-500' : 'text-green-500' ?> ml-2">
                            <?php 
                            if ($is_jaga_malam) {
                                echo '(4 jam setelah 06:00)';
                            } else {
                                echo $is_friday ? '(1 jam sebelum 15:15 s/d 4 jam sesudahnya)' : '(1 jam sebelum 15:30 s/d 4 jam sesudahnya)';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Tanggal Server:</span>
                        <span><?= date('d/m/Y') ?> (<?= $is_friday ? 'Jumat' : date('l') ?>)</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Waktu Server (WIB):</span>
                        <span id="server-time"><?= date('H:i:s') ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Status Absen Masuk:</span>
                        <span class="<?= $sudah_absen_masuk ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                            <?= $sudah_absen_masuk ? 'SUDAH' : ($sedang_cuti ? 'CUTI' : 'BELUM') ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Status Absen Pulang:</span>
                        <span class="<?= $sudah_absen_pulang ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                            <?= $sudah_absen_pulang ? 'SUDAH' : ($sedang_cuti ? 'CUTI' : 'BELUM') ?>
                        </span>
                    </div>
                    
                    <?php if ($is_jaga_malam && $current_hour >= '00:00' && $current_hour <= '10:00'): ?>
                    <div class="flex justify-between items-center p-4 bg-blue-100 rounded-lg border border-blue-300">
                        <span class="font-semibold">Periode Absensi:</span>
                        <span class="text-blue-700 font-semibold">
                            MENGECEK ABSENSI HARI: <?= $check_date ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($sedang_cuti): ?>
                    <div class="flex justify-between items-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <span class="font-semibold">Status Hari Ini:</span>
                        <span class="text-yellow-700 font-semibold">
                            <?= strtoupper($jenis_cuti) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$sedang_cuti): ?>
                <form method="POST" id="form-absen">
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                    
                    <div class="space-y-4">
                        <?php if ($is_jaga_malam): ?>
                            <?php if ($current_hour >= '00:00' && $current_hour <= '10:00'): ?>
                                <!-- Periode pulang (00:00 - 10:00) - PERBAIKAN: Izinkan absen pulang meskipun belum absen masuk -->
                                <?php if (!$sudah_absen_pulang): ?>
                                    <button type="submit" name="action" value="pulang" 
                                            class="w-full bg-[#C1272D] hover:bg-[#a82025] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                                        <i data-feather="log-out"></i>
                                        <span>Absen Pulang Shift Malam</span>
                                    </button>
                                <?php else: ?>
                                    <div class="w-full bg-green-100 text-green-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="check-circle" class="inline mr-2"></i>
                                        Anda sudah menyelesaikan absensi shift malam.
                                    </div>
                                <?php endif; ?>
                            
                            <?php elseif ($current_hour >= '14:30' && $current_hour <= '18:30'): ?>
                                <!-- Periode masuk (14:30 - 18:30) -->
                                <?php if (!$sudah_absen_masuk): ?>
                                    <button type="submit" name="action" value="masuk" 
                                            class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                                        <i data-feather="log-in"></i>
                                        <span>Absen Masuk Shift Malam</span>
                                    </button>
                                <?php elseif ($sudah_absen_masuk && !$sudah_absen_pulang): ?>
                                    <div class="w-full bg-blue-100 text-blue-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="info" class="inline mr-2"></i>
                                        Anda sudah absen masuk. Belum waktunya absen pulang.
                                    </div>
                                <?php else: ?>
                                    <div class="w-full bg-green-100 text-green-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="check-circle" class="inline mr-2"></i>
                                        Anda sudah menyelesaikan absensi shift malam.
                                    </div>
                                <?php endif; ?>
                            
                            <?php elseif ($current_hour > '18:30' && $current_hour <= '23:59'): ?>
                                <!-- Periode setelah jam masuk sampai tengah malam (18:31 - 23:59) -->
                                <?php if (!$sudah_absen_pulang): ?>
                                    <button type="submit" name="action" value="pulang" 
                                            class="w-full bg-[#C1272D] hover:bg-[#a82025] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                                        <i data-feather="log-out"></i>
                                        <span>Absen Pulang Shift Malam</span>
                                    </button>
                                <?php else: ?>
                                    <div class="w-full bg-green-100 text-green-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="check-circle" class="inline mr-2"></i>
                                        Anda sudah menyelesaikan absensi shift malam.
                                    </div>
                                <?php endif; ?>
                            
                            <?php elseif ($current_hour > '10:00' && $current_hour < '14:30'): ?>
                                <!-- Periode antara jam pulang dan jam masuk (10:01 - 14:29) -->
                                <div class="w-full bg-gray-100 text-gray-600 font-bold py-4 px-6 rounded-lg text-center">
                                    <i data-feather="clock" class="inline mr-2"></i>
                                    <?php if ($sudah_absen_masuk && !$sudah_absen_pulang): ?>
                                        Waktu absen pulang sudah lewat (00:00-10:00). Hubungi administrator.
                                    <?php else: ?>
                                        Belum waktunya absen shift malam.
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        
                        <?php else: ?>
                            <!-- Logika untuk pegawai reguler -->
                            <?php if ($current_hour >= '06:15' && $current_hour <= '08:15'): ?>
                                <!-- Periode masuk reguler (06:15 - 08:15) -->
                                <?php if (!$sudah_absen_masuk): ?>
                                    <button type="submit" name="action" value="masuk" 
                                            class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                                        <i data-feather="log-in"></i>
                                        <span>Absen Masuk</span>
                                    </button>
                                <?php elseif ($sudah_absen_masuk && !$sudah_absen_pulang): ?>
                                    <div class="w-full bg-blue-100 text-blue-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="info" class="inline mr-2"></i>
                                        Anda sudah absen masuk. Belum waktunya absen pulang.
                                    </div>
                                <?php else: ?>
                                    <div class="w-full bg-green-100 text-green-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="check-circle" class="inline mr-2"></i>
                                        Anda sudah menyelesaikan absensi hari ini.
                                    </div>
                                <?php endif; ?>
                            
                            <?php elseif (($is_friday && $current_hour >= '14:15' && $current_hour <= '19:15') || 
                                         (!$is_friday && $current_hour >= '14:30' && $current_hour <= '19:30')): ?>
                                <!-- Periode pulang reguler - PERBAIKAN: Ditambah 1 jam sebelumnya & bisa absen pulang tanpa absen masuk -->
                                <?php if (!$sudah_absen_pulang): ?>
                                    <button type="submit" name="action" value="pulang" 
                                            class="w-full bg-[#C1272D] hover:bg-[#a82025] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                                        <i data-feather="log-out"></i>
                                        <span>Absen Pulang</span>
                                    </button>
                                <?php else: ?>
                                    <div class="w-full bg-green-100 text-green-800 font-bold py-4 px-6 rounded-lg text-center">
                                        <i data-feather="check-circle" class="inline mr-2"></i>
                                        Anda sudah menyelesaikan absensi hari ini.
                                    </div>
                                <?php endif; ?>
                            
                            <?php else: ?>
                                <!-- Diluar jam absensi reguler -->
                                <div class="w-full bg-gray-100 text-gray-600 font-bold py-4 px-6 rounded-lg text-center">
                                    <i data-feather="clock" class="inline mr-2"></i>
                                    <?php if ($current_hour < '06:15'): ?>
                                        Belum waktunya absen masuk.
                                    <?php elseif ($current_hour > '08:15' && $current_hour < ($is_friday ? '14:15' : '14:30')): ?>
                                        <?php if ($sudah_absen_masuk && !$sudah_absen_pulang): ?>
                                            Belum waktunya absen pulang.
                                        <?php else: ?>
                                            Belum waktunya absen pulang.
                                        <?php endif; ?>
                                    <?php elseif ($current_hour > ($is_friday ? '19:15' : '19:30')): ?>
                                        <?php if ($sudah_absen_masuk && !$sudah_absen_pulang): ?>
                                            Waktu absen pulang sudah lewat. Hubungi administrator.
                                        <?php else: ?>
                                            Waktu absensi sudah lewat untuk hari ini.
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tambahan: Informasi Lokasi Real-time -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i data-feather="map-pin" class="w-5 h-5 mr-2"></i>
                            Informasi Lokasi Real-time
                        </h4>
                        
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="text-sm text-gray-600">Koordinat Anda:</div>
                                <div class="text-sm font-semibold text-gray-800" id="display-coordinates">Mengambil lokasi...</div>
                                
                                <div class="text-sm text-gray-600">Jarak dari Kantor:</div>
                                <div class="text-sm font-semibold text-gray-800" id="display-distance">Menghitung...</div>
                                
                                <div class="text-sm text-gray-600">Status Lokasi:</div>
                                <div class="text-sm font-semibold">
                                    <span id="status-lokasi" class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">Pending</span>
                                </div>
                                
                                <div class="text-sm text-gray-600">Akurasi GPS:</div>
                                <div class="text-sm font-semibold text-gray-800" id="display-accuracy">-</div>
                            </div>
                            
                            <!-- Progress bar untuk jarak -->
                            <div class="mt-4">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Jarak dari Kantor</span>
                                    <span id="progress-text">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div id="distance-progress" class="bg-green-500 h-2.5 rounded-full" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>0 meter</span>
                                    <span><?= $radius ?> meter</span>
                                </div>
                            </div>
                            
                            <!-- Pesan status -->
                            <div id="status-message" class="mt-3 p-3 rounded-lg hidden">
                                <p class="text-sm font-semibold" id="status-text"></p>
                            </div>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-6 text-center">
                    <i data-feather="calendar" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                    <p class="text-gray-600 font-semibold">Absensi Tidak Tersedia</p>
                    <p class="text-gray-500 text-sm mt-1">Anda sedang cuti <?= $jenis_cuti ?> hari ini</p>
                </div>
                <?php endif; ?>

                <div class="mt-6 p-4 <?= $is_jaga_malam ? 'bg-blue-50 border border-blue-200' : 'bg-green-50 border border-green-200' ?> rounded-lg">
                    <h4 class="font-semibold <?= $is_jaga_malam ? 'text-blue-800' : 'text-green-800' ?> mb-2">Informasi Fleksibilitas Waktu:</h4>
                    <p class="<?= $is_jaga_malam ? 'text-blue-700' : 'text-green-700' ?> text-sm">• Absen masuk dapat dilakukan 1 jam sebelum dan sesudah waktu yang ditentukan</p>
                    <p class="<?= $is_jaga_malam ? 'text-blue-700' : 'text-green-700' ?> text-sm">• Absen pulang dapat dilakukan walau lupa absen masuk.</p>
                    <?php if ($is_jaga_malam): ?>
                        <p class="text-blue-700 text-sm">• Absen pulang tetap maksimal 4 jam setelah waktu yang ditentukan</p>
                        <p class="text-blue-700 text-sm font-semibold">• Untuk Jaga Malam: Absen pulang di jam 00:00-10:00 dapat dilakukan meskipun belum absen masuk</p>
                    <?php else: ?>
                        <p class="<?= $is_friday ? 'text-yellow-600 font-semibold' : 'text-green-700' ?> text-sm">• <?= $is_friday ? 'Hari Jumat' : 'Hari Senin-Kamis' ?>: Absen pulang dimulai pukul <?= $is_friday ? '14:15' : '14:30' ?> (1 jam sebelum waktu normal s/d 4 jam sesudahnya)</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Peta Lokasi</h3>
                <div id="map"></div>
                <div class="mt-4 text-sm text-gray-600">
                    <p><span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span> Lokasi Kantor</p>
                    <p><span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span> Lokasi Anda</p>
                    <p><span class="inline-block w-3 h-3 bg-green-300 rounded-full mr-2"></span> Area Absensi (<?= $radius ?>m)</p>
                    <p class="mt-2">Pastikan Anda berada dalam radius 100 meter dari kantor untuk dapat melakukan absensi.</p>
                    
                    <?php if ($sedang_cuti): ?>
                    <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-yellow-700 text-sm">
                            <i data-feather="info" class="w-4 h-4 inline mr-1"></i>
                            <strong>Informasi:</strong> Anda sedang cuti <?= $jenis_cuti ?>. Absensi dinonaktifkan untuk hari ini.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Statistik Lokasi -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Statistik Lokasi</h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="text-center p-3 bg-white rounded-lg shadow">
                            <div class="text-2xl font-bold text-blue-600" id="stat-distance">0</div>
                            <div class="text-gray-600">Meter dari Kantor</div>
                        </div>
                        <div class="text-center p-3 bg-white rounded-lg shadow">
                            <div class="text-2xl font-bold text-green-600" id="stat-coverage">0%</div>
                            <div class="text-gray-600">Cakupan Area</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Update waktu server setiap detik - FIXED untuk WIB
        function updateServerTime() {
            // Buat request ke server untuk mendapatkan waktu server yang akurat
            fetch('get_server_time.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('server-time').textContent = data.time;
                    }
                })
                .catch(error => {
                    console.error('Error fetching server time:', error);
                    // Fallback: update dengan waktu client + offset WIB
                    const now = new Date();
                    const wibTime = new Date(now.getTime() + (7 * 60 * 60 * 1000));
                    document.getElementById('server-time').textContent = wibTime.toLocaleTimeString('id-ID', {
                        hour12: false,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                });
        }
        
        // Update setiap detik
        setInterval(updateServerTime, 1000);
        updateServerTime();

        // Initialize map
        const map = L.map('map').setView([<?= $kantor_lat ?>, <?= $kantor_lng ?>], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Tambahkan marker untuk kantor
        const kantorMarker = L.marker([<?= $kantor_lat ?>, <?= $kantor_lng ?>]).addTo(map)
            .bindPopup('Kantor Kecamatan Ajibarang')
            .openPopup();

        // Tambahkan circle untuk radius
        const radiusCircle = L.circle([<?= $kantor_lat ?>, <?= $kantor_lng ?>], {
            color: 'green',
            fillColor: '#1F9D55',
            fillOpacity: 0.2,
            radius: <?= $radius ?>
        }).addTo(map);

        let userMarker;
        let watchId;

        // Fungsi untuk mendapatkan lokasi pengguna
        function getLocation() {
            <?php if (!$sedang_cuti): ?>
                // HANYA AKTIFKAN GEOLOCATION JIKA TIDAK SEDANG CUTI
                if (navigator.geolocation) {
                    // Set status ke "Mencoba mendapatkan lokasi..."
                    updateLocationStatus('Mencoba mendapatkan lokasi...', 'pending');
                    
                    watchId = navigator.geolocation.watchPosition(
                        showPosition,
                        showError,
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                } else {
                    updateLocationStatus('Geolocation tidak didukung', 'error');
                    document.getElementById('display-coordinates').textContent = 'Geolocation tidak didukung oleh browser ini.';
                }
            <?php else: ?>
                updateLocationStatus('Cuti - Tidak aktif', 'cuti');
                document.getElementById('display-coordinates').textContent = 'Lokasi tidak dilacak - Anda sedang cuti';
                document.getElementById('display-distance').textContent = 'Tidak perlu menghitung jarak';
                document.getElementById('display-accuracy').textContent = 'N/A';
            <?php endif; ?>
        }

        function updateLocationStatus(status, type) {
            const statusElement = document.getElementById('status-lokasi');
            statusElement.textContent = status;
            
            switch(type) {
                case 'pending':
                    statusElement.className = 'px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800';
                    break;
                case 'success':
                    statusElement.className = 'px-2 py-1 rounded-full text-xs bg-green-100 text-green-800';
                    break;
                case 'error':
                    statusElement.className = 'px-2 py-1 rounded-full text-xs bg-red-100 text-red-800';
                    break;
                case 'cuti':
                    statusElement.className = 'px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800';
                    break;
            }
        }

        function showPosition(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            // Update form inputs
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
            
            // Update location info
            document.getElementById('display-coordinates').textContent = 
                `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
            document.getElementById('display-accuracy').textContent = `${Math.round(accuracy)} meter`;
            
            // Hitung dan tampilkan jarak
            const distance = calculateDistance(lat, lng, <?= $kantor_lat ?>, <?= $kantor_lng ?>);
            document.getElementById('display-distance').textContent = `${distance.toFixed(2)} meter`;
            
            // Update statistik
            document.getElementById('stat-distance').textContent = distance.toFixed(2);
            const coverage = Math.max(0, 100 - (distance / <?= $radius ?> * 100));
            document.getElementById('stat-coverage').textContent = `${Math.min(100, coverage).toFixed(2)}%`;
            
            // Update progress bar
            const progress = Math.min(100, (distance / <?= $radius ?> * 100));
            const progressWidth = 100 - progress;
            document.getElementById('distance-progress').style.width = `${progressWidth}%`;
            document.getElementById('progress-text').textContent = `${(100 - progress).toFixed(2)}%`;
            
            // Update progress bar color based on distance
            const progressBar = document.getElementById('distance-progress');
            if (distance <= <?= $radius ?>) {
                progressBar.className = 'bg-green-500 h-2.5 rounded-full';
                updateLocationStatus('Dalam Area Absensi', 'success');
                
                // Show success message
                document.getElementById('status-message').className = 'mt-3 p-3 rounded-lg bg-green-100 text-green-700';
                document.getElementById('status-text').textContent = 'Anda berada dalam area absensi. Anda dapat melakukan absensi.';
                document.getElementById('status-message').classList.remove('hidden');
            } else {
                progressBar.className = 'bg-red-500 h-2.5 rounded-full';
                updateLocationStatus('Di Luar Area', 'error');
                
                // Show error message
                document.getElementById('status-message').className = 'mt-3 p-3 rounded-lg bg-red-100 text-red-700';
                document.getElementById('status-text').textContent = `Anda berada di luar area absensi. Jarak Anda ${distance.toFixed(2)} meter, melebihi batas <?= $radius ?> meter.`;
                document.getElementById('status-message').classList.remove('hidden');
            }
            
            // Update atau buat marker pengguna
            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
            } else {
                userMarker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'user-marker',
                        html: '<div class="w-6 h-6 bg-blue-500 rounded-full border-2 border-white shadow-lg"></div>'
                    })
                }).addTo(map)
                    .bindPopup('Lokasi Anda');
            }
            
            // Adjust map view jika perlu
            if (userMarker && kantorMarker) {
                const group = new L.featureGroup([kantorMarker, userMarker]);
                map.fitBounds(group.getBounds().pad(0.2));
            }
        }

        function showError(error) {
            let message = 'Error mengambil lokasi: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Akses lokasi ditolak. Izinkan akses lokasi untuk absensi.';
                    updateLocationStatus('Akses Ditolak', 'error');
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Informasi lokasi tidak tersedia.';
                    updateLocationStatus('Tidak Tersedia', 'error');
                    break;
                case error.TIMEOUT:
                    message += 'Permintaan lokasi timeout.';
                    updateLocationStatus('Timeout', 'error');
                    break;
                case error.UNKNOWN_ERROR:
                    message += 'Terjadi kesalahan tidak diketahui.';
                    updateLocationStatus('Error', 'error');
                    break;
            }
            
            document.getElementById('display-coordinates').textContent = message;
            document.getElementById('display-distance').textContent = 'Error';
            document.getElementById('display-accuracy').textContent = 'Error';
            document.getElementById('progress-text').textContent = '0%';
            document.getElementById('distance-progress').style.width = '0%';
            document.getElementById('stat-distance').textContent = '0';
            document.getElementById('stat-coverage').textContent = '0%';
            
            // Show error message
            document.getElementById('status-message').className = 'mt-3 p-3 rounded-lg bg-red-100 text-red-700';
            document.getElementById('status-text').textContent = message;
            document.getElementById('status-message').classList.remove('hidden');
        }

        // Fungsi untuk menghitung jarak
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371000; // Radius Bumi dalam meter
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c; // Hasil dalam meter
        }

        // Event listener untuk form submission - HANYA JIKA TIDAK CUTI
        <?php if (!$sedang_cuti): ?>
        document.getElementById('form-absen').addEventListener('submit', function(e) {
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;
            const isJagaMalam = <?= $is_jaga_malam ? 'true' : 'false' ?>;
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const currentTime = hours + ':' + (minutes < 10 ? '0' : '') + minutes;
            
            if (!lat || !lng) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Lokasi Tidak Ditemukan',
                    text: 'Tidak dapat mengambil lokasi GPS. Pastikan browser mengizinkan akses lokasi.',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            const distance = calculateDistance(lat, lng, <?= $kantor_lat ?>, <?= $kantor_lng ?>);
            if (distance > <?= $radius ?>) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Lokasi Diluar Area',
                    text: `Anda berada ${distance.toFixed(2)} meter dari kantor. Harus dalam radius <?= $radius ?> meter.`,
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Validasi waktu berdasarkan jabatan (client-side)
            const action = e.submitter.value;
            let isValid = true;
            let errorMessage = '';
            
            if (isJagaMalam) {
                if (action === 'masuk') {
                    // Jaga Malam: masuk 14:30 - 18:30
                    // Tidak boleh di jam 00:00 - 10:00 (waktu pulang)
                    if ((hours >= 0 && hours < 10) || (hours === 10 && minutes === 0)) {
                        isValid = false;
                        errorMessage = 'Jam masuk Jaga Malam hanya antara 14:30 - 18:30. Saat ini adalah waktu pulang (00:00-10:00). Waktu sekarang: ' + currentTime;
                    }
                    if (hours < 14 || (hours === 14 && minutes < 30) || hours > 18 || (hours === 18 && minutes > 30)) {
                        isValid = false;
                        errorMessage = 'Jam masuk Jaga Malam hanya antara 14:30 - 18:30 (1 jam sebelum dan sesudah 15:30). Waktu sekarang: ' + currentTime;
                    }
                } else if (action === 'pulang') {
                    // Jaga Malam: pulang 00:00 - 10:00
                    // Tidak boleh di jam 14:30 - 18:30 (waktu masuk)
                    if (hours >= 14 && hours <= 18) {
                        if (hours === 14 && minutes >= 30) {
                            isValid = false;
                            errorMessage = 'Jam pulang Jaga Malam hanya antara 00:00 - 10:00. Saat ini adalah waktu masuk (14:30-18:30). Waktu sekarang: ' + currentTime;
                        } else if (hours > 14 && hours < 18) {
                            isValid = false;
                            errorMessage = 'Jam pulang Jaga Malam hanya antara 00:00 - 10:00. Saat ini adalah waktu masuk (14:30-18:30). Waktu sekarang: ' + currentTime;
                        } else if (hours === 18 && minutes <= 30) {
                            isValid = false;
                            errorMessage = 'Jam pulang Jaga Malam hanya antara 00:00 - 10:00. Saat ini adalah waktu masuk (14:30-18:30). Waktu sekarang: ' + currentTime;
                        }
                    }
                    // Validasi utama: harus antara 00:00 - 10:00 ATAU setelah 18:30 - 23:59
                    if (!((hours >= 0 && hours < 10) || (hours === 10 && minutes === 0) || (hours > 18) || (hours === 18 && minutes > 30))) {
                        isValid = false;
                        errorMessage = 'Jam pulang Jaga Malam hanya antara 00:00 - 10:00 (4 jam setelah 06:00). Waktu sekarang: ' + currentTime;
                    }
                }
            } else {
                // Tentukan apakah hari ini Jumat
                const today = new Date();
                const isFriday = (today.getDay() === 5); // 0=Sunday, 5=Friday
                
                if (action === 'masuk') {
                    // Reguler: 06:15 - 08:15 (1 jam sebelum dan sesudah 07:15)
                    if (hours < 6 || (hours === 6 && minutes < 15) || hours > 8 || (hours === 8 && minutes > 15)) {
                        isValid = false;
                        errorMessage = 'Jam masuk reguler hanya antara 06:15 - 08:15 (1 jam sebelum dan sesudah 07:15). Waktu sekarang: ' + currentTime;
                    }
                } else if (action === 'pulang') {
                    if (isFriday) {
                        // Hari Jumat: 14:15 - 19:15 (1 jam sebelum 15:15 s/d 4 jam sesudahnya)
                        if (hours < 14 || (hours === 14 && minutes < 15) || hours > 19 || (hours === 19 && minutes > 15)) {
                            isValid = false;
                            errorMessage = 'Jam pulang reguler hari Jumat adalah antara 14:15 - 19:15 (1 jam sebelum 15:15 s/d 4 jam sesudahnya). Waktu sekarang: ' + currentTime;
                        }
                    } else {
                        // Hari selain Jumat: 14:30 - 19:30 (1 jam sebelum 15:30 s/d 4 jam sesudahnya)
                        if (hours < 14 || (hours === 14 && minutes < 30) || hours > 19 || (hours === 19 && minutes > 30)) {
                            isValid = false;
                            errorMessage = 'Jam pulang reguler adalah antara 14:30 - 19:30 (1 jam sebelum 15:30 s/d 4 jam sesudahnya). Waktu sekarang: ' + currentTime;
                        }
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Waktu Tidak Sesuai',
                    text: errorMessage,
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Tampilkan loading dengan pesan khusus untuk Jaga Malam
            const loadingTitle = isJagaMalam ? 'Memproses Absensi Shift Malam...' : 'Memproses Absensi...';
            Swal.fire({
                title: loadingTitle,
                text: 'Sedang menyimpan data ke server',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
        <?php endif; ?>

        // Initialize
        getLocation();
        feather.replace();

        // Cleanup
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });

        <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: '<?= $is_jaga_malam ? "Shift Malam Berhasil!" : "Berhasil!" ?>',
                text: '<?= $success ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if ($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '<?= $error ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
    </script>
</body>
</html>