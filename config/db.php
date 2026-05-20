<?php
session_start();

// Include menu configuration
require_once __DIR__ . '/menu_config.php';

// SET TIMEZONE KE WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$dbname = '';
$username = '';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set timezone untuk MySQL juga
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Koordinat kantor Kecamatan Ajibarang
define('KANTOR_LAT', -7.4078711);
define('KANTOR_LNG', 109.0781777);
define('RADIUS_METER', 100); // Radius 100 meter

// Fungsi untuk menghitung jarak antara dua koordinat (Haversine formula)
function hitungJarak($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // Radius bumi dalam meter
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

// Fungsi untuk generate tanggal antara dua tanggal
function generateDateRange($startDate, $endDate) {
    $dates = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    
    while ($current <= $end) {
        $dates[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }
    
    return $dates;
}

// Tambahkan fungsi baru untuk validasi waktu berdasarkan jabatan
function validateJamAbsensi($jabatan, $action, $jam) {
    $jam = date('H:i', strtotime($jam));
    
    if ($jabatan == 'Jaga Malam') {
        if ($action == 'masuk') {
            // Jaga Malam: masuk 15:30 - 23:59
            $batas_awal = '15:30';
            $batas_akhir = '23:59';
            return ($jam >= $batas_awal && $jam <= $batas_akhir);
        } elseif ($action == 'pulang') {
            // Jaga Malam: pulang 00:00 - 06:00
            $batas_awal = '00:00';
            $batas_akhir = '06:00';
            return ($jam >= $batas_awal && $jam <= $batas_akhir);
        }
    } else {
        // Pegawai reguler
        if ($action == 'masuk') {
            // Reguler: masuk maksimal 07:15
            $batas_akhir = '07:15';
            return ($jam <= $batas_akhir);
        } elseif ($action == 'pulang') {
            // Reguler: pulang minimal 15:30
            $batas_awal = '15:30';
            return ($jam >= $batas_awal);
        }
    }
    
    return false;
}

// Fungsi untuk mendapatkan batas waktu berdasarkan jabatan
function getBatasWaktu($jabatan, $action) {
    if ($jabatan == 'Jaga Malam') {
        return $action == 'masuk' ? '15:30 - 23:59' : '00:00 - 06:00';
    } else {
        return $action == 'masuk' ? 'Paling lambat 07:15' : 'Setelah 15:30';
    }
}

// Fungsi untuk menghitung sisa cuti tahunan dengan prioritas
function hitungSisaCutiTahunan($pdo, $id_pegawai, $jumlah_cuti_diambil) {
    $current_year = date('Y');
    $sisa_cuti = [
        'tahun_dulu' => 0,
        'tahun_lalu' => 0,
        'tahun_sekarang' => 0
    ];
    
    // Ambil sisa cuti untuk 3 tahun terakhir
    for ($i = 2; $i >= 0; $i--) {
        $tahun = $current_year - $i;
        $stmt = $pdo->prepare('SELECT sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($i == 2) {
            $sisa_cuti['tahun_dulu'] = $result ? (int)$result['sisa_cuti'] : 0;
        } elseif ($i == 1) {
            $sisa_cuti['tahun_lalu'] = $result ? (int)$result['sisa_cuti'] : 0;
        } else {
            $sisa_cuti['tahun_sekarang'] = $result ? (int)$result['sisa_cuti'] : 0;
        }
    }
    
    // Simulasi pengurangan dengan prioritas
    $sisa_setelah_pengurangan = $sisa_cuti;
    $sisa_yang_dipakai = $jumlah_cuti_diambil;
    
    // Prioritas 1: Tahun-2
    if ($sisa_yang_dipakai > 0 && $sisa_setelah_pengurangan['tahun_dulu'] > 0) {
        $pengurangan = min($sisa_yang_dipakai, $sisa_setelah_pengurangan['tahun_dulu']);
        $sisa_setelah_pengurangan['tahun_dulu'] -= $pengurangan;
        $sisa_yang_dipakai -= $pengurangan;
    }
    
    // Prioritas 2: Tahun-1
    if ($sisa_yang_dipakai > 0 && $sisa_setelah_pengurangan['tahun_lalu'] > 0) {
        $pengurangan = min($sisa_yang_dipakai, $sisa_setelah_pengurangan['tahun_lalu']);
        $sisa_setelah_pengurangan['tahun_lalu'] -= $pengurangan;
        $sisa_yang_dipakai -= $pengurangan;
    }
    
    // Prioritas 3: Tahun Sekarang
    if ($sisa_yang_dipakai > 0 && $sisa_setelah_pengurangan['tahun_sekarang'] > 0) {
        $pengurangan = min($sisa_yang_dipakai, $sisa_setelah_pengurangan['tahun_sekarang']);
        $sisa_setelah_pengurangan['tahun_sekarang'] -= $pengurangan;
        $sisa_yang_dipakai -= $pengurangan;
    }
    
    return [
        'sisa_sebelum' => $sisa_cuti,
        'sisa_setelah' => $sisa_setelah_pengurangan,
        'sisa_tidak_cukup' => $sisa_yang_dipakai > 0,
        'sisa_yang_dipakai' => $jumlah_cuti_diambil - $sisa_yang_dipakai
    ];
}

// Fungsi untuk update sisa cuti setelah pengambilan
function updateSisaCutiSetelahPengambilan($pdo, $id_pegawai, $tahun, $jumlah_hari) {
    // Cek apakah data sudah ada
    $stmt = $pdo->prepare('SELECT id_sisa_cuti, sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Update data yang sudah ada
        $sisa_baru = max(0, $result['sisa_cuti'] - $jumlah_hari);
        $stmt = $pdo->prepare('UPDATE sisa_cuti_tahunan SET sisa_cuti = ? WHERE id_sisa_cuti = ?');
        $stmt->execute([$sisa_baru, $result['id_sisa_cuti']]);
    } else {
        // Insert data baru dengan default 12 hari dikurangi
        $sisa_awal = max(0, 12 - $jumlah_hari);
        $stmt = $pdo->prepare('INSERT INTO sisa_cuti_tahunan (id_pegawai, tahun, sisa_cuti) VALUES (?, ?, ?)');
        $stmt->execute([$id_pegawai, $tahun, $sisa_awal]);
    }
}
?>
