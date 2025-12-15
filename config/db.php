<?php
session_start();

// SET TIMEZONE KE WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$dbname = 'absen_kec_db';
$username = 'absen_kec_root';
$password = '500114899';

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
?>