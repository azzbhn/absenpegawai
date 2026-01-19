<?php
session_start();
// Simulasikan user login
$_SESSION['user'] = [
    'nama' => 'Test User',
    'jabatan' => 'Administrator'
];

// Test koneksi database langsung
echo "<h1>Test Koneksi Database Langsung</h1>";

$host = "localhost";
$username = "fgqqlzxt_absen_kec_root";
$password = "aLk.25474!";
$database = "fgqqlzxt_absen_kec_db";

try {
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>✗ Connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Database connection successful</p>";
        
        // Test query untuk tanggal hari ini (2026-01-15)
        $testDate = '2026-01-15';
        $stmt = $conn->prepare("SELECT jenis_seragam FROM seragam_kerja WHERE tanggal = ?");
        $stmt->bind_param("s", $testDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "<p style='color: green;'>✓ Data found for {$testDate}: " . htmlspecialchars($row['jenis_seragam']) . "</p>";
            
            // Test logic
            date_default_timezone_set('Asia/Jakarta');
            $now = new DateTime('now');
            echo "<p>Current time in Jakarta: " . $now->format('Y-m-d H:i:s') . "</p>";
            echo "<p>Current hour: " . $now->format('H') . ", minute: " . $now->format('i') . "</p>";
            
            $currentHour = (int)$now->format('H');
            $currentMinute = (int)$now->format('i');
            
            if ($currentHour >= 14 && $currentMinute >= 30) {
                echo "<p>Mode: Reminder for tomorrow</p>";
                $targetDate = clone $now;
                $targetDate->modify('+1 day');
            } else {
                echo "<p>Mode: Reminder for today</p>";
                $targetDate = clone $now;
            }
            
            echo "<p>Target date: " . $targetDate->format('Y-m-d') . "</p>";
            
            // Format tanggal Indonesia
            $dayNames = [
                'Sunday' => 'Minggu',
                'Monday' => 'Senin',
                'Tuesday' => 'Selasa',
                'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis',
                'Friday' => 'Jumat',
                'Saturday' => 'Sabtu'
            ];
            
            $monthNames = [
                'January' => 'Januari',
                'February' => 'Februari',
                'March' => 'Maret',
                'April' => 'April',
                'May' => 'Mei',
                'June' => 'Juni',
                'July' => 'Juli',
                'August' => 'Agustus',
                'September' => 'September',
                'October' => 'Oktober',
                'November' => 'November',
                'December' => 'Desember'
            ];
            
            $englishDay = $targetDate->format('l');
            $englishMonth = $targetDate->format('F');
            
            $indonesianDay = $dayNames[$englishDay] ?? $englishDay;
            $indonesianMonth = $monthNames[$englishMonth] ?? $englishMonth;
            
            $formattedDate = $indonesianDay . ', ' . $targetDate->format('d') . ' ' . $indonesianMonth . ' ' . $targetDate->format('Y');
            
            echo "<p>Formatted date: " . $formattedDate . "</p>";
            echo "<p>Reminder text would be: <strong>📢 Pengingat: Untuk hari ini {$formattedDate}, gunakan {$row['jenis_seragam']}</strong></p>";
            
        } else {
            echo "<p style='color: red;'>✗ No data found for {$testDate}</p>";
        }
        
        $stmt->close();
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Testing dengan include header.php</h2>";
echo "<p>Di bawah ini akan muncul header dengan running text jika berhasil:</p>";
echo "<div style='border: 2px dashed #ccc; padding: 20px;'>";

// Include header
require_once 'components/header.php';

echo "</div>";
?>