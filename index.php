<?php
// Pastikan session dimulai di paling atas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// Cek jika user sudah login, redirect ke dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null; // Inisialisasi variabel error

// Proses form login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Regenerasi session ID untuk keamanan
            session_regenerate_id(true); 
            $_SESSION['user'] = $user;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
    } catch (PDOException $e) {
        // Catat error database (sebaiknya ke file log, bukan ditampilkan ke user)
        // error_log($e->getMessage());
        $error = 'Terjadi masalah koneksi. Coba lagi nanti.';
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
    <title>Login - SIGMA Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            /* Gradien animasi */
            background: linear-gradient(135deg, #3BAFDA, #1F9D55, #F9B000);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
        }
        
        /* Keyframes untuk animasi gradien */
        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Keyframes untuk animasi kartu login */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        /* Kelas untuk menerapkan animasi */
        .login-card-animation {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: fadeInDown 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }

        /* Styling untuk placeholder */
        ::placeholder {
            color: #a0aec0; /* text-gray-400 */
        }
    </style>
</head>
<body class="min-h-screen overflow-hidden">
    
    <div class="login-card-animation bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden mx-4">
        <!-- Bagian Header -->
        <div class="bg-[#F9B000] p-6 text-center relative overflow-hidden">
            <!-- Ikon SVG Pengganti Logo -->
            <svg class="w-20 h-20 mx-auto mb-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            
            <h1 class="text-3xl font-bold text-white drop-shadow-md">S I G M A</h1>
            <p class="text-sm text-white/90">Sistem Informasi Geotagging untuk Monitoring Absensi</p>
            <p class="text-sm text-white/80">Kecamatan Ajibarang</p>
            
            <!-- Efek kilau (shine) opsional -->
            <div class="absolute top-0 left-0 -translate-x-full w-full h-full bg-gradient-to-r from-transparent via-white/30 to-transparent opacity-50 -skew-x-12"></div>
        </div>
        
        <!-- Bagian Form -->
        <div class="p-8">
            <form method="POST" class="space-y-6">
                <!-- Input Username dengan Ikon -->
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </span>
                    <input type="text" placeholder="NIP atau NIK" id="username" name="username" required 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent transition duration-200">
                </div>
                
                <!-- Input Password dengan Ikon -->
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </span>
                    <input type="password" placeholder="Password" id="password" name="password" required 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent transition duration-200">
                </div>
                
                <!-- Tombol Submit dengan Efek Baru -->
                <button type="submit" 
                        class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-4 rounded-lg
                               transition-all duration-300 ease-in-out
                               transform hover:-translate-y-1 hover:shadow-lg hover:shadow-yellow-500/40
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F9B000]">
                    Masuk
                </button>
            </form>
            
            <!-- Footer Kartu -->
            <div class="mt-8 text-center">
                <p class="text-gray-600 text-sm">
                    Sistem Absensi Berbasis Lokasi GPS
                </p>
                <p class="text-gray-400 text-xs mt-1">© <?php echo date("Y"); ?> Kecamatan Ajibarang</p>
            </div>
        </div>
    </div>

    <script>
        <?php if (isset($error) && $error): // Cek jika $error ada dan tidak kosong ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Gagal',
                text: '<?php echo $error; ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        <?php endif; ?>
    </script>
</body>
</html>
