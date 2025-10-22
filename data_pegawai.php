<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

// Ambil semua data pegawai
$stmt = $pdo->query('SELECT id_pegawai, nama, nip, jabatan, no_whatsapp, username, status FROM pegawai ORDER BY nama');
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    
    <!-- Navigation (Lengkap) - Dropdown Admin dengan background dan efek seragam -->
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
        <div class="flex justify-between items-center bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Seluruh Pegawai</h2>
                <p class="text-gray-600">Daftar semua pegawai yang terdaftar dalam sistem.</p>
            </div>
            <a href="tambah_pegawai.php" class="bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-5 rounded-lg transition duration-200 flex items-center space-x-2">
                <i data-feather="user-plus"></i>
                <span>Tambah Pegawai</span>
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">NIP</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jabatan</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
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
                                <td class="px-6 py-4 text-center">
                                    <?php if ($p['status'] == 'Aktif'): ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center space-x-3">
                                        <a href="toggle_status.php?id=<?= $p['id_pegawai'] ?>" 
                                           class="p-2 rounded-full hover:bg-gray-200 transition duration-200"
                                           title="<?= $p['status'] == 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <?php if ($p['status'] == 'Aktif'): ?>
                                                <i data-feather="user-x" class="w-4 h-4 text-yellow-600"></i>
                                            <?php else: ?>
                                                <i data-feather="user-check" class="w-4 h-4 text-green-600"></i>
                                            <?php endif; ?>
                                        </a>
                                        <a href="edit_pegawai.php?id=<?= $p['id_pegawai'] ?>" 
                                           class="p-2 rounded-full hover:bg-gray-200 transition duration-200" title="Edit">
                                            <i data-feather="edit" class="w-4 h-4 text-blue-600"></i>
                                        </a>
                                        <button onclick="hapusPegawai(<?= $p['id_pegawai'] ?>, '<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>')"
                                                class="p-2 rounded-full hover:bg-gray-200 transition duration-200" title="Hapus">
                                            <i data-feather="trash-2" class="w-4 h-4 text-red-600"></i>
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
                html: `Anda yakin ingin menghapus data <strong>${nama}</strong>? Tindakan ini tidak dapat dibatalkan.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to the delete script
                    window.location.href = `hapus_pegawai.php?id=${id}`;
                }
            });
        }
    </script>
</body>
</html>
