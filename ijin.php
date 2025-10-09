<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Ambil data cuti dari database
$stmt = $pdo->prepare('
    SELECT 
        jatah_cuti_tahunan,
        cuti_tahunan_diambil,
        total_cuti_sakit
    FROM pegawai 
    WHERE id_pegawai = ?
');
$stmt->execute([$user['id_pegawai']]);
$data_cuti = $stmt->fetch(PDO::FETCH_ASSOC);

$jatah_cuti = $data_cuti['jatah_cuti_tahunan'];
$cuti_dipakai = $data_cuti['cuti_tahunan_diambil'];
$sisa_cuti = $jatah_cuti - $cuti_dipakai;
$total_sakit = $data_cuti['total_cuti_sakit'];

// Fungsi untuk menghitung hari kerja (exclude Sabtu-Minggu)
function hitungHariKerja($tanggal_mulai, $tanggal_selesai) {
    $start = new DateTime($tanggal_mulai);
    $end = new DateTime($tanggal_selesai);
    $end->modify('+1 day'); // Include end date
    
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    $hari_kerja = 0;
    foreach ($period as $dt) {
        $dayOfWeek = $dt->format('N'); // 1 (Monday) to 7 (Sunday)
        if ($dayOfWeek < 6) { // 1-5 are Monday-Friday
            $hari_kerja++;
        }
    }
    return $hari_kerja;
}

// Fungsi untuk menghitung semua hari (termasuk Sabtu-Minggu)
function hitungSemuaHari($tanggal_mulai, $tanggal_selesai) {
    $start = new DateTime($tanggal_mulai);
    $end = new DateTime($tanggal_selesai);
    $end->modify('+1 day'); // Include end date
    
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    return iterator_count($period);
}

// Proses pengajuan cuti
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jenis_cuti = $_POST['jenis_cuti'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $link_data_dukung = $_POST['link_data_dukung'];

    // Validasi
    if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
        $error = 'Tanggal mulai dan tanggal selesai harus diisi!';
    } elseif ($tanggal_mulai > $tanggal_selesai) {
        $error = 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai!';
    } else {
        // Cek apakah ada tanggal yang sudah ada di absensi
        $stmt_check = $pdo->prepare('
            SELECT tanggal FROM absensi 
            WHERE id_pegawai = ? 
            AND tanggal BETWEEN ? AND ?
            AND status IN ("hadir", "izin", "sakit", "dinas luar")
        ');
        $stmt_check->execute([$user['id_pegawai'], $tanggal_mulai, $tanggal_selesai]);
        $tanggal_konflik = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($tanggal_konflik)) {
            $error = 'Terdapat konflik dengan tanggal yang sudah ada absensi: ' . 
                     implode(', ', array_map(function($date) { 
                         return date('d/m/Y', strtotime($date)); 
                     }, $tanggal_konflik));
        } else {
            // Hitung jumlah hari cuti berdasarkan jenis cuti
            if ($jenis_cuti == 'tahunan') {
                $jumlah_hari = hitungHariKerja($tanggal_mulai, $tanggal_selesai); // Exclude weekend
            } else {
                $jumlah_hari = hitungSemuaHari($tanggal_mulai, $tanggal_selesai); // Include semua hari
            }

            // Validasi khusus cuti tahunan
            if ($jenis_cuti == 'tahunan') {
                if ($sisa_cuti <= 0) {
                    $error = 'Sisa cuti tahunan Anda sudah habis!';
                } elseif ($jumlah_hari > $sisa_cuti) {
                    $error = 'Jumlah hari cuti (' . $jumlah_hari . ' hari) melebihi sisa cuti tahunan Anda (' . $sisa_cuti . ' hari)!';
                }
            }

            if (!$error) {
                try {
                    // Simpan pengajuan cuti
                    $stmt = $pdo->prepare('
                        INSERT INTO pengajuan_cuti 
                        (id_pegawai, jenis_cuti, tanggal_mulai, tanggal_selesai, link_data_dukung, status, jumlah_hari) 
                        VALUES (?, ?, ?, ?, ?, "pending", ?)
                    ');
                    $stmt->execute([
                        $user['id_pegawai'],
                        $jenis_cuti,
                        $tanggal_mulai,
                        $tanggal_selesai,
                        $link_data_dukung,
                        $jumlah_hari
                    ]);

                    $success = 'Pengajuan cuti berhasil dikirim! Status: Menunggu persetujuan.';
                    
                    // Refresh data cuti
                    $stmt = $pdo->prepare('
                        SELECT 
                            jatah_cuti_tahunan,
                            cuti_tahunan_diambil,
                            total_cuti_sakit
                        FROM pegawai 
                        WHERE id_pegawai = ?
                    ');
                    $stmt->execute([$user['id_pegawai']]);
                    $data_cuti = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $jatah_cuti = $data_cuti['jatah_cuti_tahunan'];
                    $cuti_dipakai = $data_cuti['cuti_tahunan_diambil'];
                    $sisa_cuti = $jatah_cuti - $cuti_dipakai;
                    $total_sakit = $data_cuti['total_cuti_sakit'];

                } catch (PDOException $e) {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
        }
    }
}

// Ambil riwayat pengajuan cuti
$stmt = $pdo->prepare('
    SELECT *, 
           DATE_FORMAT(tanggal_pengajuan, "%d/%m/%Y %H:%i") as waktu_pengajuan
    FROM pengajuan_cuti 
    WHERE id_pegawai = ? 
    ORDER BY tanggal_pengajuan DESC
');
$stmt->execute([$user['id_pegawai']]);
$riwayat_cuti = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Cuti - Kecamatan Ajibarang</title>
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Pengajuan Cuti</h2>
            <p class="text-gray-600">Ajukan cuti tahunan atau cuti sakit melalui form berikut.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Informasi Cuti -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Informasi Cuti Anda</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Nama:</span>
                        <span><?= htmlspecialchars($user['nama']) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                        <span class="font-semibold">Jatah Cuti Tahunan:</span>
                        <span><?= $jatah_cuti ?> hari</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-orange-50 rounded-lg">
                        <span class="font-semibold">Cuti Tahunan Diambil:</span>
                        <span><?= $cuti_dipakai ?> hari</span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                        <span class="font-semibold">Sisa Cuti Tahunan:</span>
                        <span class="font-bold <?= $sisa_cuti > 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $sisa_cuti ?> hari
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-yellow-50 rounded-lg">
                        <span class="font-semibold">Total Cuti Sakit:</span>
                        <span><?= $total_sakit ?> hari</span>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-purple-50 rounded-lg">
                    <h4 class="font-semibold text-purple-800 mb-2">Informasi:</h4>
                    <ul class="text-purple-700 text-sm space-y-1">
                        <li>• Cuti tahunan hanya menghitung hari kerja (Senin-Jumat)</li>
                        <li>• Cuti sakit menghitung semua hari (termasuk Sabtu-Minggu)</li>
                        <li>• Cuti tahunan mengurangi sisa cuti tahunan</li>
                        <li>• Cuti sakit tidak mengurangi jatah cuti tahunan</li>
                        <li>• Pengajuan cuti harus disetujui oleh admin</li>
                    </ul>
                </div>
            </div>

            <!-- Form Pengajuan -->
            <div class="bg-white rounded-2xl shadow-lg p-6 lg:col-span-2">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Form Pengajuan Cuti</h3>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="tanggal_mulai" class="block text-sm font-medium text-gray-700 mb-2">
                                Tanggal Mulai <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai" required 
                                   min="<?= date('Y-m-d') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>

                        <div>
                            <label for="tanggal_selesai" class="block text-sm font-medium text-gray-700 mb-2">
                                Tanggal Selesai <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="tanggal_selesai" name="tanggal_selesai" required 
                                   min="<?= date('Y-m-d') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label for="jenis_cuti" class="block text-sm font-medium text-gray-700 mb-2">
                            Jenis Cuti <span class="text-red-500">*</span>
                        </label>
                        <select id="jenis_cuti" name="jenis_cuti" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                            <option value="">Pilih Jenis Cuti</option>
                            <option value="tahunan" <?= $sisa_cuti <= 0 ? 'disabled' : '' ?>>
                                Cuti Tahunan <?= $sisa_cuti <= 0 ? '(Sisa cuti habis)' : '' ?>
                            </option>
                            <option value="sakit">Cuti Sakit</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1" id="info-cuti">
                            <?php if ($sisa_cuti <= 0): ?>
                                <span class="text-red-600">Sisa cuti tahunan Anda sudah habis. Silakan pilih cuti sakit.</span>
                            <?php else: ?>
                                Sisa cuti tahunan: <?= $sisa_cuti ?> hari
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <label for="link_data_dukung" class="block text-sm font-medium text-gray-700 mb-2">
                            Link Data Dukung
                        </label>
                        <input type="url" id="link_data_dukung" name="link_data_dukung"
                               placeholder="https://drive.google.com/... atau link lainnya"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">
                            Optional. Link Google Drive, Dropbox, atau platform berbagi file lainnya untuk mengunggah dokumen pendukung.
                        </p>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i data-feather="info" class="w-5 h-5 text-yellow-600 mr-2"></i>
                            <span class="font-semibold text-yellow-800">Informasi Hari Cuti</span>
                        </div>
                        <p class="text-yellow-700 text-sm mt-2" id="info-hari-cuti">
                            Pilih tanggal mulai, selesai, dan jenis cuti untuk melihat jumlah hari cuti
                        </p>
                        <p class="text-yellow-600 text-xs mt-1" id="info-catatan">
                            <!-- Untuk menampilkan catatan perhitungan -->
                        </p>
                    </div>

                    <button type="submit" 
                            class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                        <i data-feather="send"></i>
                        <span>Ajukan Cuti</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Riwayat Pengajuan Cuti -->
        <div class="mt-12">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Riwayat Pengajuan Cuti</h3>
                
                <?php if (empty($riwayat_cuti)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i data-feather="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-2"></i>
                        <p>Belum ada riwayat pengajuan cuti</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">No</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Tanggal Pengajuan</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jenis Cuti</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Periode</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Jumlah Hari</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Data Dukung</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php $no = 1; ?>
                                <?php foreach ($riwayat_cuti as $cuti): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= $no++ ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= $cuti['waktu_pengajuan'] ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?= $cuti['jenis_cuti'] == 'tahunan' ? 'Cuti Tahunan' : 'Cuti Sakit' ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?= date('d/m/Y', strtotime($cuti['tanggal_mulai'])) ?> - 
                                        <?= date('d/m/Y', strtotime($cuti['tanggal_selesai'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?= $cuti['jumlah_hari'] ?> hari</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold 
                                            <?= $cuti['status'] == 'disetujui' ? 'bg-green-100 text-green-800' : 
                                               ($cuti['status'] == 'ditolak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                            <?= $cuti['status'] == 'disetujui' ? 'DISETUJUI' : 
                                               ($cuti['status'] == 'ditolak' ? 'DITOLAK' : 'MENUNGGU') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($cuti['link_data_dukung']): ?>
                                            <a href="<?= htmlspecialchars($cuti['link_data_dukung']) ?>" 
                                               target="_blank" 
                                               class="text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                                <i data-feather="external-link" class="w-4 h-4"></i>
                                                <span>Lihat</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        feather.replace();

        // Fungsi untuk menghitung hari kerja (exclude Sabtu-Minggu)
        function hitungHariKerja(tanggalMulai, tanggalSelesai) {
            const start = new Date(tanggalMulai);
            const end = new Date(tanggalSelesai);
            end.setDate(end.getDate() + 1); // Include end date
            
            let hariKerja = 0;
            const current = new Date(start);
            
            while (current < end) {
                const dayOfWeek = current.getDay(); // 0 (Minggu) to 6 (Sabtu)
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    hariKerja++;
                }
                current.setDate(current.getDate() + 1);
            }
            return hariKerja;
        }

        // Fungsi untuk menghitung semua hari (termasuk Sabtu-Minggu)
        function hitungSemuaHari(tanggalMulai, tanggalSelesai) {
            const start = new Date(tanggalMulai);
            const end = new Date(tanggalSelesai);
            const timeDiff = end.getTime() - start.getTime();
            const dayDiff = timeDiff / (1000 * 3600 * 24);
            return dayDiff + 1; // Include both start and end dates
        }

        // Fungsi untuk mendapatkan nama hari
        function getNamaHari(tanggal) {
            const hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const date = new Date(tanggal);
            return hari[date.getDay()];
        }

        // Hitung jumlah hari cuti berdasarkan jenis cuti
        function hitungHariCuti() {
            const tanggalMulai = document.getElementById('tanggal_mulai').value;
            const tanggalSelesai = document.getElementById('tanggal_selesai').value;
            const jenisCuti = document.getElementById('jenis_cuti').value;
            const infoElement = document.getElementById('info-hari-cuti');
            const catatanElement = document.getElementById('info-catatan');

            if (tanggalMulai && tanggalSelesai && jenisCuti) {
                let jumlahHari, catatan;
                
                if (jenisCuti === 'tahunan') {
                    jumlahHari = hitungHariKerja(tanggalMulai, tanggalSelesai);
                    catatan = 'Cuti tahunan hanya menghitung hari kerja (Senin-Jumat)';
                } else {
                    jumlahHari = hitungSemuaHari(tanggalMulai, tanggalSelesai);
                    catatan = 'Cuti sakit menghitung semua hari (termasuk Sabtu-Minggu)';
                }
                
                if (jumlahHari > 0) {
                    const hariMulai = getNamaHari(tanggalMulai);
                    const hariSelesai = getNamaHari(tanggalSelesai);
                    
                    infoElement.innerHTML = `<strong>${jumlahHari} hari</strong> cuti (${hariMulai}, ${tanggalMulai} sampai ${hariSelesai}, ${tanggalSelesai})`;
                    infoElement.className = 'text-green-700 text-sm mt-2';
                    
                    catatanElement.textContent = catatan;
                    catatanElement.className = 'text-yellow-600 text-xs mt-1';
                } else {
                    infoElement.innerHTML = 'Tidak ada hari cuti dalam periode yang dipilih';
                    infoElement.className = 'text-red-700 text-sm mt-2';
                    catatanElement.textContent = '';
                }
            } else {
                infoElement.innerHTML = 'Pilih tanggal mulai, selesai, dan jenis cuti untuk melihat jumlah hari cuti';
                infoElement.className = 'text-yellow-700 text-sm mt-2';
                catatanElement.textContent = '';
            }
        }

        // Event listeners
        document.getElementById('tanggal_mulai').addEventListener('change', function() {
            const tanggalSelesai = document.getElementById('tanggal_selesai');
            if (this.value) {
                tanggalSelesai.min = this.value;
            }
            hitungHariCuti();
        });

        document.getElementById('tanggal_selesai').addEventListener('change', hitungHariCuti);
        document.getElementById('jenis_cuti').addEventListener('change', hitungHariCuti);

        // Validasi form
        document.querySelector('form').addEventListener('submit', function(e) {
            const tanggalMulai = document.getElementById('tanggal_mulai').value;
            const tanggalSelesai = document.getElementById('tanggal_selesai').value;
            const jenisCuti = document.getElementById('jenis_cuti').value;
            const sisaCuti = <?= $sisa_cuti ?>;

            if (!tanggalMulai || !tanggalSelesai || !jenisCuti) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Data Tidak Lengkap',
                    text: 'Semua field yang bertanda * harus diisi',
                    confirmButtonText: 'OK'
                });
                return false;
            }

            if (tanggalMulai > tanggalSelesai) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Tanggal Tidak Valid',
                    text: 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai',
                    confirmButtonText: 'OK'
                });
                return false;
            }

            // Hitung hari untuk validasi cuti tahunan
            if (jenisCuti === 'tahunan') {
                let jumlahHari;
                if (jenisCuti === 'tahunan') {
                    jumlahHari = hitungHariKerja(tanggalMulai, tanggalSelesai);
                } else {
                    jumlahHari = hitungSemuaHari(tanggalMulai, tanggalSelesai);
                }

                if (jumlahHari > sisaCuti) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Cuti Tidak Mencukupi',
                        text: `Anda mengajukan ${jumlahHari} hari cuti, tetapi sisa cuti tahunan hanya ${sisaCuti} hari`,
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
            }

            // Tampilkan loading
            Swal.fire({
                title: 'Mengajukan Cuti...',
                text: 'Sedang mengirim pengajuan cuti',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

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
                title: 'Gagal',
                text: '<?= $error ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
    </script>
</body>
</html>