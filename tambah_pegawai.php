<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $jabatan = $_POST['jabatan'];
    $no_whatsapp = $_POST['no_whatsapp'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Validasi
    if (empty($nama) || empty($nip) || empty($jabatan) || empty($username) || empty($password)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($password !== $konfirmasi_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Cek duplikasi NIP dan Username
        $stmt = $pdo->prepare('SELECT id_pegawai FROM pegawai WHERE nip = ? OR username = ?');
        $stmt->execute([$nip, $username]);
        if ($stmt->fetch()) {
            $error = 'NIP atau Username sudah digunakan!';
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO pegawai (nama, nip, jabatan, no_whatsapp, username, password, status) VALUES (?, ?, ?, ?, ?, ?, "Aktif")');
                $stmt->execute([$nama, $nip, $jabatan, $no_whatsapp, $username, $hashed_password]);
                $success = 'Data pegawai berhasil ditambahkan!';
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pegawai - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-[#F9B000] text-white shadow-lg no-print">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <img src="assets/logo.png" alt="Logo" class="w-12 h-12">
                    <div>
                    	<h1 class="text-xl font-bold">S I G M A</h1>
                    	<p class="text-sm text-white">Sistem Informasi Geotagging untuk Monitoring Absensi - Kecamatan Ajibarang</p>
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
    
          <!-- Menu Navigasi Mobile -->
            <div class="md:hidden rounded-lg w-full shadow-lg p-4 mb-6">
                <div class="grid grid-cols-2 gap-2">
                    <a href="dashboard.php" class="bg-blue-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Dashboard</a>
                    <a href="absen.php" class="bg-green-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Absensi</a>
                    <a href="ijin.php" class="bg-yellow-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Pengajuan Cuti</a>
                    
                    
                    <?php if ($user['jabatan'] == 'Administrator'): ?>
                    <a href="data_absensi.php" class="bg-purple-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Data Absensi</a>
                    <a href="persetujuan_cuti.php" class="bg-indigo-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Persetujuan</a>
                    <a href="tambah_pegawai.php" class="bg-pink-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Tambah Pegawai</a>
                    <a href="data_pegawai.php" class="bg-pink-800 text-white text-center py-2 px-4 rounded-lg font-semibold">Data Pegawai</a>
                    <?php endif; ?>
                    
                    
                    <a href="ganti_password.php" class="bg-yellow-600 text-white text-center py-2 px-4 rounded-lg font-semibold">Password</a>
                    <a href="logout.php" class="bg-gray-500 text-white text-center py-2 px-4 rounded-lg font-semibold">Log Out</a>
                </div>
            </div>
    
          <!-- Menu Links (Desktop) -->
          <div id="menu" class="hidden md:flex md:space-x-6 flex-col md:flex-row mt-3 md:mt-0 items-center w-full">
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
    
            <!-- Admin Dropdown -->
            <?php if ($user['jabatan'] == 'Administrator'): ?>
            <div class="relative group">
              <button class="flex items-center space-x-2 py-2 px-3 hover:bg-[#188a4a] rounded transition focus:outline-none">
                <i data-feather="shield"></i>
                <span>Admin</span>
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </button>
    
              <!-- Dropdown -->
              <div class="absolute left-0 mt-2 w-48 bg-[#1F9D55] text-white rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transform -translate-y-2 group-hover:translate-y-0 transition duration-200 z-10">
                <a href="data_absensi.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition rounded-t-lg">
                  <i data-feather="file-text"></i>
                  <span>Data Absensi</span>
                </a>
                <a href="persetujuan_cuti.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition">
                  <i data-feather="check-square"></i>
                  <span>Persetujuan Cuti</span>
                </a>
                <a href="tambah_pegawai.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition rounded-b-lg">
                  <i data-feather="user-plus"></i>
                  <span>Tambah Pegawai</span>
                </a>
                <a href="data_pegawai.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition rounded-b-lg">
                  <i data-feather="users"></i>
                  <span>Data Pegawai</span>
                </a>
              </div>
            </div>
            <?php endif; ?>
    
            <!-- Menu kanan -->
            <div class="flex items-center ml-auto space-x-2">
              <a href="ganti_password.php" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
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
      </div>
    </nav>
    
    <script>
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    </script>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Tambah Data Pegawai</h2>
            <p class="text-gray-600">Isi form berikut untuk menambahkan data pegawai baru.</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kolom Kiri -->
                    <div class="space-y-4">
                        <div>
                            <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                         <div>
                            <label for="nip" class="block text-sm font-medium text-gray-700 mb-2">NIP</label>
                            <input type="text" id="nip" name="nip" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                        <div>
                            <label for="jabatan" class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <select id="jabatan" name="jabatan" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                                <option value="">Pilih Jabatan</option>
                                <option value="Administrator">Administrator</option>
                                <option value="Camat">Camat</option>
                                <option value="Sekretaris Kecamatan">Sekretaris Kecamatan</option>
                                <option value="Staf">Staf</option>
                                <option value="Jaga Malam">Jaga Malam</option>
                                <!-- Tambahkan jabatan lainnya sesuai kebutuhan -->
                            </select>
                        </div>
                         <div>
                            <label for="no_whatsapp" class="block text-sm font-medium text-gray-700 mb-2">Nomor WhatsApp</label>
                            <input type="text" id="no_whatsapp" name="no_whatsapp"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                    </div>

                    <!-- Kolom Kanan -->
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" id="username" name="username" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <input type="password" id="password" name="password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                        <div>
                            <label for="konfirmasi_password" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                         <div class="pt-4">
                            <button type="submit" class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                                <i data-feather="save"></i>
                                <span>Simpan Data Pegawai</span>
                            </button>
                        </div>
                        <div class="pt-2">
                             <a href="data_pegawai.php" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                                <i data-feather="arrow-left"></i>
                                <span>Kembali ke Data Pegawai</span>
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Informasi Jam Kerja Berdasarkan Jabatan -->
                <div class="mt-8 p-6 bg-blue-50 rounded-lg border border-blue-200">
                    <h4 class="font-semibold text-blue-800 mb-3">Informasi Jam Kerja:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg">
                            <h5 class="font-semibold text-green-700 mb-2">Pegawai Reguler</h5>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Jam Masuk: 06:15 - 08:15</li>
                                <li>• (1 jam sebelum & sesudah 07:15)</li>
                                <li>• Jam Pulang: 15:30 - 19:30</li>
                                <li>• (4 jam setelah 15:30)</li>
                                <li>• Shift: Pagi/Siang</li>
                            </ul>
                        </div>
                        <div class="bg-white p-4 rounded-lg">
                            <h5 class="font-semibold text-blue-700 mb-2">Jaga Malam</h5>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Jam Masuk: 14:30 - 16:30</li>
                                <li>• (1 jam sebelum & sesudah 15:30)</li>
                                <li>• Jam Pulang: 00:00 - 10:00</li>
                                <li>• (4 jam setelah 06:00)</li>
                                <li>• Shift: Malam</li>
                            </ul>
                        </div>
                    </div>
                    <p class="text-blue-700 text-sm mt-3">
                        <i data-feather="info" class="w-4 h-4 inline mr-1"></i>
                        <strong>Fleksibilitas:</strong> Absen masuk 1 jam sebelum & sesudah waktu, Absen pulang 4 jam setelah waktu.
                    </p>
                </div>
            </div>
        </div>
    </main>
    <script>
        feather.replace();
    </script>
</body>
</html>