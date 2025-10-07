<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Ambil data absensi hari ini
$today = date('Y-m-d');
$stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
$stmt->execute([$user['id_pegawai'], $today]);
$absensi_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil riwayat absensi 7 hari terakhir
$stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? ORDER BY tanggal DESC LIMIT 7');
$stmt->execute([$user['id_pegawai']]);
$riwayat_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil path file photo
$nip = htmlspecialchars($user['nip']);
$fotoPath = "assets/pegawai/" . $nip . ".png";
if (!file_exists($fotoPath)) {
    $fotoPath = "assets/pegawai/default.png"; // fallback jika foto tidak ada
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Absensi Kecamatan Ajibarang</title>
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
                    <p class="font-semibold"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="text-white/80 text-sm"><?= htmlspecialchars($user['jabatan']) ?></p>
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
                <?php if ($user['jabatan'] == 'Administrator'): ?>
                <a href="data_absensi.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="file-text"></i>
                    <span>Data Absensi</span>
                </a>
                <?php endif; ?>
                <a href="logout.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2 ml-auto">
                    <i data-feather="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Welcome Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Selamat Datang, <?= htmlspecialchars($user['nama']) ?>!</h2>
                    <p class="text-gray-600">Sistem Absensi Berbasis Lokasi GPS Kecamatan Ajibarang</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-semibold text-gray-800" id="current-time"></p>
                    <p class="text-gray-600" id="current-date"></p>
                </div>
            </div>
        </div>

        <!-- Status Absensi Hari Ini -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="clock" class="text-blue-600 w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Status Absensi</h3>
                <p class="text-2xl font-bold <?= $absensi_hari_ini ? 'text-green-600' : 'text-red-600' ?>">
                    <?= $absensi_hari_ini ? 'SUDAH ABSEN' : 'BELUM ABSEN' ?>
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="map-pin" class="text-green-600 w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Lokasi Kantor</h3>
                <p class="text-gray-600">Kecamatan Ajibarang</p>
                <p class="text-sm text-gray-500">Radius: 100 meter</p>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
                <div class="w-20 h-20 rounded-full overflow-hidden mx-auto mb-4">
                    <img src="<?= $fotoPath ?>" 
                        alt="Foto Pegawai <?= $nip ?>" 
                        class="w-full h-full object-cover">
                </div>

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Informasi Pegawai</h3>
                <p class="text-gray-600">NIP: <?= $nip ?></p>
                <p class="text-gray-600">Jabatan: <?= htmlspecialchars($user['jabatan']) ?></p>
            </div>
        </div>

        <!-- Riwayat Absensi -->
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Riwayat Absensi 7 Hari Terakhir</h3>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jam Masuk</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jam Keluar</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat_absensi)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-gray-500">Tidak ada data absensi</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat_absensi as $absensi): ?>
                            <tr class="border-t">
                                <td class="px-4 py-3"><?= htmlspecialchars($absensi['tanggal']) ?></td>
                                <td class="px-4 py-3"><?= $absensi['jam_masuk'] ? htmlspecialchars($absensi['jam_masuk']) : '-' ?></td>
                                <td class="px-4 py-3"><?= $absensi['jam_keluar'] ? htmlspecialchars($absensi['jam_keluar']) : '-' ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold 
                                        <?= $absensi['status'] == 'hadir' ? 'bg-green-100 text-green-800' : 
                                           ($absensi['status'] == 'izin' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($absensi['status'] == 'sakit' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>">
                                        <?= strtoupper($absensi['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Update waktu real-time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('id-ID');
            document.getElementById('current-date').textContent = now.toLocaleDateString('id-ID', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Initialize Feather icons
        feather.replace();
    </script>
</body>
</html>