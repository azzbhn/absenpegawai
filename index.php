<?php
require_once 'config/db.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #3BAFDA 0%, #1F9D55 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-[#F9B000] p-6 text-center">
            <img src="../assets/logo.png" alt="Logo Kabupaten Banyumas" class="w-20 h-auto mx-auto mb-2">
            <h1 class="text-3xl font-bold text-white">S I G M A</h1>
            <p class="text-sm text-white">Sistem Informasi Geotagging untuk Monitoring Absensi</p>
            <p class="text-sm text-white/90">Kecamatan Ajibarang</p>
        </div>
        
        <div class="p-8">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-gray-700 mb-2">Username</label>
                    <input type="text" placeholder="NIP atau NIK" id="username" name="username" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700 mb-2">Password</label>
                    <input type="password" placeholder="Password" id="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                </div>
                
                <button type="submit" 
                        class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-4 rounded-lg transition duration-200 transform hover:scale-105">
                    Masuk
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-gray-600 text-sm">Sistem Absensi Berbasis Lokasi GPS</p>
                <p class="text-gray-400 text-xs">© 2025 Kecamatan Ajibarang</p>
            </div>
        </div>
    </div>

    <script>
        <?php if (isset($error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Gagal',
                text: '<?= $error ?>',
                timer: 3000
            });
        <?php endif; ?>
    </script>
</body>
</html>