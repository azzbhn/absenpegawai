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
    <header class="bg-[#F9B000] text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <img src="assets/logo.png" alt="Logo" class="w-12 h-12">
                    <div>
                    	<h1 class="text-xl font-bold">S I G M A</h1>
                    	<p class="text-sm text-white">Sistem Informasi Geotagging untuk Monitoring Absensi Kecamatan - Ajibarang</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="text-white/80 text-sm"><?= htmlspecialchars($user['jabatan']) ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-[#1F9D55] text-white shadow-md no-print">
      <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3">
          <!-- Hamburger Menu (Mobile) -->
          <!--<button id="menu-toggle" class="md:hidden focus:outline-none">-->
          <!--  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">-->
          <!--    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>-->
          <!--  </svg>-->
          <!--</button>-->

        <!-- Menu Navigasi Mobile -->
        <div class="md:hidden rounded-lg w-full shadow-lg p-4 mb-6">
            <div class="grid grid-cols-2 gap-2">
                <a href="dashboard.php" class="bg-blue-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Dashboard</a>
                <a href="absen.php" class="bg-green-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Absensi</a>
                <a href="ijin.php" class="bg-yellow-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Pengajuan Cuti</a>
                <?php if ($user['jabatan'] == 'Administrator'): ?>
                <a href="data_absensi.php" class="bg-purple-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Data Absensi</a>
                <a href="persetujuan_cuti.php" class="bg-indigo-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Persetujuan</a>
                <?php endif; ?>
                <a href="ganti_password.php" class="bg-yellow-600 text-white text-center py-2 px-4 rounded-lg font-semibold">Password</a>
                <a href="logout.php" class="bg-gray-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Log Out</a>
            </div>
        </div>

          <!-- Menu Links -->
          <div id="menu" class="hidden md:flex md:space-x-6 flex-col md:flex-row mt-3 md:mt-0">
            <a href="dashboard.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
              <i data-feather="home"></i>
              <span>Dashboard</span>
            </a>
            <a href="absen.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
              <i data-feather="clock"></i>
              <span>Absensi</span>
            </a>
            <a href="ijin.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
              <i data-feather="calendar"></i>
              <span>Pengajuan Cuti</span>
            </a>
            <?php if ($user['jabatan'] == 'Administrator'): ?>
            <a href="data_absensi.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
              <i data-feather="file-text"></i>
              <span>Data Absensi</span>
            </a>
            <a href="persetujuan_cuti.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
              <i data-feather="check-square"></i>
              <span>Persetujuan Cuti</span>
            </a>
            <?php endif; ?>
            <a href="ganti_password.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2 md:ml-auto">
              <i data-feather="key"></i>
              <span>Ganti Password</span>
            </a>
            <a href="logout.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
              <i data-feather="log-out"></i>
              <span>Logout</span>
            </a>
          </div>
        </div>
      </div>
    </nav>

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
