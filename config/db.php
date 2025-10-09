<?php
session_start();

// SET TIMEZONE KE WIB (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$dbname = 'absensi_ajibarang';
$username = 'root';
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
?>