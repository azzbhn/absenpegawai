<?php
require_once 'config/db.php';
require_once 'config/jam_kerja.php';

// pastikan tabel jam kerja ada dan baris default terisi
ensureWorkHoursTable($pdo);

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
      <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>
    
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

                <!-- Informasi Jam Kerja Berdasarkan Konfigurasi -->
                <?php
                    // ambil jam kerja dari database untuk tampil
                    $wj = getWorkHours($pdo, 'reguler');
                    $wjj = getWorkHours($pdo, 'reguler_jumat');
                    $wm = getWorkHours($pdo, 'malam');
                ?>
                <div class="mt-8 p-6 bg-blue-50 rounded-lg border border-blue-200">
                    <h4 class="font-semibold text-blue-800 mb-3">Informasi Jam Kerja:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg">
                            <h5 class="font-semibold text-green-700 mb-2">Pegawai Reguler (Senin-Kamis)</h5>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Jam Masuk: <?= htmlspecialchars(substr($wj['masuk_mulai'],0,5) ?? '-') ?> - <?= htmlspecialchars(substr($wj['masuk_selesai'],0,5) ?? '-') ?></li>
                                <li>• Jam Pulang: <?= htmlspecialchars(substr($wj['pulang_mulai'],0,5) ?? '-') ?> - <?= htmlspecialchars(substr($wj['pulang_selesai'],0,5) ?? '-') ?></li>
                            </ul>
                        </div>
                        <div class="bg-white p-4 rounded-lg">
                            <h5 class="font-semibold text-blue-700 mb-2">Pegawai Jaga Malam</h5>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Jam Masuk: <?= htmlspecialchars(substr($wm['masuk_mulai'],0,5) ?? '-') ?> - <?= htmlspecialchars(substr($wm['masuk_selesai'],0,5) ?? '-') ?></li>
                                <li>• Jam Pulang: <?= htmlspecialchars(substr($wm['pulang_mulai'],0,5) ?? '-') ?> - <?= htmlspecialchars(substr($wm['pulang_selesai'],0,5) ?? '-') ?></li>
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