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

    // Validasi input
    if (empty($nama) || empty($nip) || empty($jabatan) || empty($username) || empty($password)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($password !== $konfirmasi_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } else {
        // Cek apakah username sudah ada
        $stmt = $pdo->prepare('SELECT id_pegawai FROM pegawai WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username sudah digunakan!';
        } else {
            // Cek apakah NIP sudah ada
            $stmt = $pdo->prepare('SELECT id_pegawai FROM pegawai WHERE nip = ?');
            $stmt->execute([$nip]);
            if ($stmt->fetch()) {
                $error = 'NIP sudah digunakan!';
            } else {
                try {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert data pegawai baru
                    $stmt = $pdo->prepare('INSERT INTO pegawai (nama, nip, jabatan, no_whatsapp, username, password) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$nama, $nip, $jabatan, $no_whatsapp, $username, $hashed_password]);
                    
                    $success = 'Pegawai berhasil ditambahkan!';
                    
                    // Reset form
                    $_POST = array();
                } catch (PDOException $e) {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                }
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
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-[#F9B000] text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <img src="assets/logo.png" alt="Logo" class="w-12 h-12">
                    <div>
                        <h1 class="text-xl font-bold">Sistem Absensi</h1>
                        <p class="text-white/90 text-sm">Kecamatan Ajibarang</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold"><?= htmlspecialchars($_SESSION['user']['nama']) ?></p>
                    <p class="text-white/80 text-sm">Administrator</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-[#1F9D55] text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex space-x-8">
                <a href="dashboard.php" class="py-3 px-2 border-b-2 border-white font-semibold flex items-center space-x-2">
                    <i data-feather="home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="absen.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="clock"></i>
                    <span>Absensi</span>
                </a>
                <a href="ijin.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="calendar"></i>
                    <span>Pengajuan Cuti</span>
                </a>
                <?php if ($user['jabatan'] == 'Administrator'): ?>
                <a href="data_absensi.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="file-text"></i>
                    <span>Data Absensi</span>
                </a>
                <a href="persetujuan_cuti.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="check-square"></i>
                    <span>Persetujuan Cuti</span>
                </a>
                <?php endif; ?>
                <a href="ganti_password.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2 ml-auto">
                    <i data-feather="key"></i>
                    <span>Ganti Password</span>
                </a>
                <a href="logout.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2 ml-auto">
                    <i data-feather="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Tambah Data Pegawai</h2>
            <p class="text-gray-600">Tambahkan data pegawai baru ke dalam sistem absensi.</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <div class="flex items-center">
                            <i data-feather="alert-circle" class="w-5 h-5 mr-2"></i>
                            <span><?= $error ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <div class="flex items-center">
                            <i data-feather="check-circle" class="w-5 h-5 mr-2"></i>
                            <span><?= $success ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kolom Kiri -->
                    <div class="space-y-4">
                        <div>
                            <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Lengkap <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nama" name="nama" required 
                                   value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent"
                                   placeholder="Masukkan nama lengkap">
                        </div>

                        <div>
                            <label for="nip" class="block text-sm font-medium text-gray-700 mb-2">
                                NIP <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nip" name="nip" required 
                                   value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent"
                                   placeholder="Masukkan NIP">
                        </div>

                        <div>
                            <label for="jabatan" class="block text-sm font-medium text-gray-700 mb-2">
                                Jabatan <span class="text-red-500">*</span>
                            </label>
                            <select id="jabatan" name="jabatan" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                                <option value="">Pilih Jabatan</option>
                                <option value="Administrator" <?= ($_POST['jabatan'] ?? '') == 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                                <option value="Camat" <?= ($_POST['jabatan'] ?? '') == 'Camat' ? 'selected' : '' ?>>Camat</option>
                                <option value="Sekretaris Kecamatan" <?= ($_POST['jabatan'] ?? '') == 'Sekretaris Kecamatan' ? 'selected' : '' ?>>Sekretaris Kecamatan</option>
                                <option value="Kepala Seksi Pemerintahan Desa" <?= ($_POST['jabatan'] ?? '') == 'Kepala Seksi Pemerintahan Desa' ? 'selected' : '' ?>>Kepala Seksi Pemerintahan Desa</option>
                                <option value="Kepala Seksi Pemberdayaan Masyarakat" <?= ($_POST['jabatan'] ?? '') == 'Kepala Seksi Pemberdayaan Masyarakat' ? 'selected' : '' ?>>Kepala Seksi Pemberdayaan Masyarakat</option>
                                <option value="Kepala Seksi Pelayanan" <?= ($_POST['jabatan'] ?? '') == 'Kepala Seksi Pelayanan' ? 'selected' : '' ?>>Kepala Seksi Pelayanan</option>
                                <option value="Kepala Seksi Ekonomi Pembangunan" <?= ($_POST['jabatan'] ?? '') == 'Kepala Seksi Ekonomi Pembangunan' ? 'selected' : '' ?>>Kepala Seksi Ekonomi Pembangunan</option>
                                <option value="Kepala Seksi Ketentraman dan Ketertiban Umum" <?= ($_POST['jabatan'] ?? '') == 'Kepala Seksi Ketentraman dan Ketertiban Umum' ? 'selected' : '' ?>>Kepala Seksi Ketentraman Ketertiban Umum</option>
                                <option value="Kepala Sub Bagian Umum dan Kepegawaian" <?= ($_POST['jabatan'] ?? '') == 'Kepala Sub Bagian Umum dan Kepegawaian' ? 'selected' : '' ?>>Kepala Sub Bagian Umum dan Kepegawaian</option>
                                <option value="Kepala Sub Bagian Perencanaan dan Keuangan" <?= ($_POST['jabatan'] ?? '') == 'Kepala Sub Bagian Perencanaan dan Keuangan' ? 'selected' : '' ?>>Kepala Sub Bagian Perencanaan dan Keuangan</option>
                                <option value="Klerek" <?= ($_POST['jabatan'] ?? '') == 'Klerek' ? 'selected' : '' ?>>Klerek</option>
                                <option value="Operator" <?= ($_POST['jabatan'] ?? '') == 'Operator' ? 'selected' : '' ?>>Operator</option>
                            </select>
                        </div>

                        <div>
                            <label for="no_whatsapp" class="block text-sm font-medium text-gray-700 mb-2">
                                Nomor WhatsApp
                            </label>
                            <input type="text" id="no_whatsapp" name="no_whatsapp"
                                   value="<?= htmlspecialchars($_POST['no_whatsapp'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent"
                                   placeholder="Contoh: 08123456789">
                        </div>
                    </div>

                    <!-- Kolom Kanan -->
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="username" name="username" required 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent"
                                   placeholder="Masukkan username untuk login">
                            <p class="text-xs text-gray-500 mt-1">Username harus unik dan tidak boleh ada spasi</p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password" name="password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent"
                                   placeholder="Masukkan password">
                            <p class="text-xs text-gray-500 mt-1">Minimal 6 karakter</p>
                        </div>

                        <div>
                            <label for="konfirmasi_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Konfirmasi Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent"
                                   placeholder="Ketik ulang password">
                        </div>

                        <div class="pt-4">
                            <button type="submit" 
                                    class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                                <i data-feather="user-plus"></i>
                                <span>Tambah Pegawai</span>
                            </button>
                        </div>

                        <div class="pt-2">
                            <a href="data_pegawai.php" 
                               class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                                <i data-feather="users"></i>
                                <span>Lihat Data Pegawai</span>
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Informasi -->
                <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
                        <i data-feather="info" class="w-4 h-4 mr-2"></i>
                        Informasi
                    </h4>
                    <ul class="text-blue-700 text-sm space-y-1">
                        <li>• Password akan di-hash secara otomatis sebelum disimpan ke database</li>
                        <li>• Field dengan tanda (<span class="text-red-500">*</span>) wajib diisi</li>
                        <li>• Username dan NIP harus unik (tidak boleh duplikat)</li>
                        <li>• Pastikan data yang dimasukkan sudah benar sebelum menyimpan</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        feather.replace();

        <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '<?= $success ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if ($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= $error ?>',
                timer: 5000
            });
        <?php endif; ?>

        // Validasi form client-side
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const konfirmasi = document.getElementById('konfirmasi_password').value;
            
            if (password.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Terlalu Pendek',
                    text: 'Password minimal 6 karakter',
                    timer: 3000
                });
                return false;
            }
            
            if (password !== konfirmasi) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Tidak Cocok',
                    text: 'Password dan konfirmasi password harus sama',
                    timer: 3000
                });
                return false;
            }
        });
    </script>
</body>
</html>