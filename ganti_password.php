<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    $user = $_SESSION['user'];
    
    // Verifikasi password lama
    if (password_verify($password_lama, $user['password'])) {
        if ($password_baru === $konfirmasi_password) {
            $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE pegawai SET password = ? WHERE id_pegawai = ?');
            $stmt->execute([$hash_baru, $user['id_pegawai']]);
            
            // Update session
            $_SESSION['user']['password'] = $hash_baru;
            $success = 'Password berhasil diubah!';
        } else {
            $error = 'Password baru dan konfirmasi tidak cocok!';
        }
    } else {
        $error = 'Password lama salah!';
    }
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
    <title>Ganti Password - Absensi Kecamatan Ajibarang</title>
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
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
      <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-2xl shadow-lg p-6">
            <img src="assets/logo.png" alt="Logo Banyumas" class="w-1/4 mx-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Ganti Password</h2>
            
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label for="password_lama" class="block text-gray-700 mb-2">Password Lama</label>
                    <input type="password" id="password_lama" name="password_lama" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                </div>
                
                <div>
                    <label for="password_baru" class="block text-gray-700 mb-2">Password Baru</label>
                    <input type="password" id="password_baru" name="password_baru" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                </div>
                
                <div>
                    <label for="konfirmasi_password" class="block text-gray-700 mb-2">Konfirmasi Password Baru</label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                </div>
                
                <button type="submit" 
                        class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                    Ganti Password
                </button>
                <a href="dashboard.php"
                class="inline-block w-full text-center bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                Kembali
                </a>
            </form>
        </div>
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
