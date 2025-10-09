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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    
    
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
</body>
</html>