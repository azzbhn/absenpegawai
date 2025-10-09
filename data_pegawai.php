<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

// Ambil semua data pegawai
$stmt = $pdo->query('SELECT id_pegawai, nama, nip, jabatan, no_whatsapp, username FROM pegawai ORDER BY nama');
$pegawai = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pegawai - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header sama seperti tambah_pegawai.php -->
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Seluruh Pegawai</h2>
            <p class="text-gray-600">Daftar semua pegawai yang terdaftar dalam sistem.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">NIP</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jabatan</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No WhatsApp</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Username</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($pegawai)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i data-feather="users" class="w-12 h-12 mx-auto text-gray-400 mb-2"></i>
                                    <p>Belum ada data pegawai</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($pegawai as $p): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 text-sm text-gray-900"><?= $no++ ?></td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nama']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($p['nip']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($p['jabatan']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= $p['no_whatsapp'] ? htmlspecialchars($p['no_whatsapp']) : '-' ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($p['username']) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="edit_pegawai.php?id=<?= $p['id_pegawai'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                            <i data-feather="edit" class="w-4 h-4"></i>
                                            <span>Edit</span>
                                        </a>
                                        <button onclick="hapusPegawai(<?= $p['id_pegawai'] ?>, '<?= htmlspecialchars($p['nama']) ?>')"
                                                class="text-red-600 hover:text-red-800 flex items-center space-x-1">
                                            <i data-feather="trash-2" class="w-4 h-4"></i>
                                            <span>Hapus</span>
                                        </button>
                                    </div>
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
        feather.replace();

        function hapusPegawai(id, nama) {
            Swal.fire({
                title: 'Hapus Pegawai?',
                text: `Anda yakin ingin menghapus ${nama}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `hapus_pegawai.php?id=${id}`;
                }
            });
        }
    </script>
</body>
</html>