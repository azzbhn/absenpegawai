<?php
echo "<h2>Status Timezone System</h2>";

// Cek timezone PHP
echo "PHP Timezone: " . date_default_timezone_get() . "<br>";
echo "PHP Time: " . date('Y-m-d H:i:s') . "<br>";

// Cek timezone system
if (function_exists('shell_exec')) {
    echo "System Time: " . shell_exec('date') . "<br>";
    
    // Cek timedatectl
    $timedatectl = shell_exec('timedatectl 2>/dev/null');
    if ($timedatectl) {
        echo "<pre>Timedatectl:\n$timedatectl</pre>";
    }
    
    // Cek /etc/timezone
    if (file_exists('/etc/timezone')) {
        echo "Timezone File: " . file_get_contents('/etc/timezone') . "<br>";
    }
}

// Test MySQL time
try {
    require_once 'config/db.php';
    $stmt = $pdo->query("SELECT NOW() as mysql_time, @@global.time_zone as mysql_tz, @@session.time_zone as session_tz");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "MySQL Time: " . $result['mysql_time'] . "<br>";
    echo "MySQL Global Timezone: " . $result['mysql_tz'] . "<br>";
    echo "MySQL Session Timezone: " . $result['session_tz'] . "<br>";
} catch (Exception $e) {
    echo "MySQL Error: " . $e->getMessage() . "<br>";
}

echo "<h3>Jika waktu tidak sesuai WIB, jalankan perintah berikut di server:</h3>";
echo "<pre>";
echo "sudo timedatectl set-timezone Asia/Jakarta\n";
echo "sudo systemctl restart apache2\n";
echo "sudo systemctl restart mysql\n";
echo "</pre>";
?>