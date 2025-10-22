<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

// Ambil parameter filter
$filter_tanggal_awal = $_GET['tanggal_awal'] ?? '';
$filter_tanggal_akhir = $_GET['tanggal_akhir'] ?? '';
$filter_nama = $_GET['nama'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build query dengan filter
$where = [];
$params = [];

// Filter range tanggal
if ($filter_tanggal_awal && $filter_tanggal_akhir) {
    $where[] = 'a.tanggal BETWEEN ? AND ?';
    $params[] = $filter_tanggal_awal;
    $params[] = $filter_tanggal_akhir;
} elseif ($filter_tanggal_awal) {
    $where[] = 'a.tanggal >= ?';
    $params[] = $filter_tanggal_awal;
} elseif ($filter_tanggal_akhir) {
    $where[] = 'a.tanggal <= ?';
    $params[] = $filter_tanggal_akhir;
}

if ($filter_nama) {
    $where[] = 'p.nama LIKE ?';
    $params[] = '%' . $filter_nama . '%';
}

if ($filter_status) {
    $where[] = 'a.status = ?';
    $params[] = $filter_status;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Query data absensi
$stmt = $pdo->prepare("
    SELECT a.*, p.nama, p.nip, p.jabatan 
    FROM absensi a 
    JOIN pegawai p ON a.id_pegawai = p.id_pegawai 
    $where_clause 
    ORDER BY a.tanggal DESC, a.jam_masuk DESC
");
$stmt->execute($params);
$absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total data
$total_data = count($absensi);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Absensi - Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* Style untuk cetak */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                font-size: 12px;
                background: white !important;
                color: black !important;
            }
            
            .bg-white {
                background: white !important;
            }
            
            .shadow-lg, .shadow-md, .shadow {
                box-shadow: none !important;
            }
            
            .rounded-2xl, .rounded-lg, .rounded {
                border-radius: 0 !important;
            }
            
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            
            th, td {
                border: 1px solid #000 !important;
                padding: 6px !important;
                text-align: left !important;
            }
            
            th {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            .container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            main {
                padding: 0 !important;
                margin: 0 !important;
            }
        }
        
        .print-only {
            display: none;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
        }
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
        <!-- Header untuk cetak -->
        <div class="print-only mb-6">
            <h1 class="text-2xl font-bold text-center mb-2">LAPORAN ABSENSI PEGAWAI</h1>
            <h2 class="text-xl text-center mb-2">KECAMATAN AJIBARANG</h2>
            <p class="text-center mb-4">
                Periode: 
                <?php 
                if ($filter_tanggal_awal && $filter_tanggal_akhir) {
                    echo date('d F Y', strtotime($filter_tanggal_awal)) . ' - ' . date('d F Y', strtotime($filter_tanggal_akhir));
                } elseif ($filter_tanggal_awal) {
                    echo 'Dari ' . date('d F Y', strtotime($filter_tanggal_awal));
                } elseif ($filter_tanggal_akhir) {
                    echo 'Sampai ' . date('d F Y', strtotime($filter_tanggal_akhir));
                } else {
                    echo date('d F Y');
                }
                ?>
            </p>
            <hr class="border-black mb-4">
        </div>

        <!-- Judul untuk tampilan normal -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 no-print">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Absensi Pegawai</h2>
            <p class="text-gray-600">Kelola dan pantau data absensi seluruh pegawai.</p>
        </div>

        <!-- Filter Section - Sembunyikan saat cetak -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 no-print">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Filter Data</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="tanggal_awal" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Awal</label>
                    <input type="date" id="tanggal_awal" name="tanggal_awal" value="<?= htmlspecialchars($filter_tanggal_awal) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                </div>
                <div>
                    <label for="tanggal_akhir" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
                    <input type="date" id="tanggal_akhir" name="tanggal_akhir" value="<?= htmlspecialchars($filter_tanggal_akhir) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                </div>
                <div>
                    <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">Nama Pegawai</label>
                    <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($filter_nama) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <option value="">Semua Status</option>
                        <option value="hadir" <?= $filter_status == 'hadir' ? 'selected' : '' ?>>Hadir</option>
                        <option value="izin" <?= $filter_status == 'izin' ? 'selected' : '' ?>>Izin</option>
                        <option value="sakit" <?= $filter_status == 'sakit' ? 'selected' : '' ?>>Sakit</option>
                        <option value="dinas luar" <?= $filter_status == 'dinas luar' ? 'selected' : '' ?>>Dinas Luar</option>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" 
                            class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                        <i data-feather="filter"></i>
                        <span>Filter</span>
                    </button>
                    <a href="data_absensi.php" 
                       class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                        <i data-feather="refresh-cw"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </form>

            <!-- Info Filter Aktif -->
            <?php if ($filter_tanggal_awal || $filter_tanggal_akhir || $filter_nama || $filter_status): ?>
            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <h4 class="font-semibold text-blue-800 mb-1">Filter Aktif:</h4>
                <div class="flex flex-wrap gap-2">
                    <?php if ($filter_tanggal_awal && $filter_tanggal_akhir): ?>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Periode: <?= date('d/m/Y', strtotime($filter_tanggal_awal)) ?> - <?= date('d/m/Y', strtotime($filter_tanggal_akhir)) ?></span>
                    <?php elseif ($filter_tanggal_awal): ?>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Dari: <?= date('d/m/Y', strtotime($filter_tanggal_awal)) ?></span>
                    <?php elseif ($filter_tanggal_akhir): ?>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Sampai: <?= date('d/m/Y', strtotime($filter_tanggal_akhir)) ?></span>
                    <?php endif; ?>
                    
                    <?php if ($filter_nama): ?>
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Nama: <?= htmlspecialchars($filter_nama) ?></span>
                    <?php endif; ?>
                    
                    <?php if ($filter_status): ?>
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">Status: <?= ucfirst($filter_status) ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-blue-700 text-sm mt-2">Total data: <strong><?= $total_data ?></strong> record</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons - Sembunyikan saat cetak -->
        <div class="flex justify-between items-center mb-6 no-print">
            <h3 class="text-xl font-bold text-gray-800">
                Data Absensi 
                <?php if ($total_data > 0): ?>
                    <span class="text-sm font-normal text-gray-600">(<?= $total_data ?> data ditemukan)</span>
                <?php endif; ?>
            </h3>
            <div class="flex space-x-4">
                <a href="exports/export_excel.php?<?= http_build_query($_GET) ?>" 
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center space-x-2">
                    <i data-feather="download"></i>
                    <span>Export Excel</span>
                </a>
                <button onclick="window.print()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center space-x-2">
                    <i data-feather="printer"></i>
                    <span>Cetak Laporan</span>
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden print:shadow-none print:rounded-none">
            <div class="overflow-x-auto">
                <table class="w-full table-auto print:table-fixed">
                    <!--Tabel Asli-->
                    <!--<thead>-->
                    <!--    <tr class="bg-gray-50 print:bg-gray-200">-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">No</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">Tanggal</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">Nama</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">NIP</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">Jabatan</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">Jam Masuk</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">Jam Keluar</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs">Status</th>-->
                    <!--        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700 print:px-3 print:py-2 print:text-xs no-print">Lokasi</th>-->
                    <!--    </tr>-->
                    <!--</thead>-->
                    
                    <!--Tabel Editan-->
                    <thead>
                    	<tr class="bg-gray-50 print:bg-gray-200">
                    		<th style="width:4ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">No</th>
                    		<th style="width:12ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">Tanggal</th>
                    		<th class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">Nama</th>
                    		<th style="width:20ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">NIP</th>
                    		<th class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">Jabatan</th>
                    		<th style="width:10ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">Jam Masuk</th>
                    		<th style="width:10ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">Jam Keluar</th>
                    		<th style="width:10ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs">Status</th>
                    		<th style="width:10ch" class="px-2 py-2 text-left text-sm font-semibold text-gray-700 print:text-xs no-print">Lokasi</th>
                    	</tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($absensi)): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-gray-500 print:px-3 print:py-4">
                                    <i data-feather="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-2 no-print"></i>
                                    <p>Tidak ada data absensi</p>
                                    <?php if ($filter_tanggal_awal || $filter_tanggal_akhir || $filter_nama || $filter_status): ?>
                                        <p class="text-sm text-gray-400 mt-2">Coba ubah filter atau reset filter</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($absensi as $absen): ?>
                            <tr class="hover:bg-gray-50 transition duration-150 print:hover:bg-white">
                                <td class="px-6 py-4 text-sm text-gray-900 print:px-3 print:py-2 print:text-xs"><?= $no++ ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900 print:px-3 print:py-2 print:text-xs"><?= htmlspecialchars($absen['tanggal']) ?></td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 print:px-3 print:py-2 print:text-xs"><?= htmlspecialchars($absen['nama']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900 print:px-3 print:py-2 print:text-xs"><?= htmlspecialchars($absen['nip']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900 print:px-3 print:py-2 print:text-xs"><?= htmlspecialchars($absen['jabatan']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900 print:px-3 print:py-2 print:text-xs"><?= $absen['jam_masuk'] ? htmlspecialchars($absen['jam_masuk']) : '-' ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900 print:px-3 print:py-2 print:text-xs"><?= $absen['jam_keluar'] ? htmlspecialchars($absen['jam_keluar']) : '-' ?></td>
                                <td class="px-6 py-4 print:px-3 print:py-2">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold print:px-2 print:py-0.5 
                                        <?= $absen['status'] == 'hadir' ? 'bg-green-100 text-green-800 print:bg-green-200' : 
                                           ($absen['status'] == 'izin' ? 'bg-yellow-100 text-yellow-800 print:bg-yellow-200' : 
                                           ($absen['status'] == 'sakit' ? 'bg-red-100 text-red-800 print:bg-red-200' : 'bg-blue-100 text-blue-800 print:bg-blue-200')) ?>">
                                        <?= strtoupper($absen['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 no-print">
                                    <?php if ($absen['lokasi_lat'] && $absen['lokasi_lng']): ?>
                                        <a href="https://maps.google.com/?q=<?= $absen['lokasi_lat'] ?>,<?= $absen['lokasi_lng'] ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                            <i data-feather="map-pin" class="w-4 h-4"></i>
                                            <span>Lihat</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer untuk cetak -->
        <!--Footer Asli-->
        <!--<div class="print-only mt-8 text-center">-->
        <!--    <div class="mt-12">-->
        <!--        <p>Mengetahui,</p>-->
        <!--        <p>Camat Ajibarang</p>-->
        <!--        <br><br><br>-->
        <!--        <p><strong>Arif Ependi, A.P., M.Si</strong></p>-->
        <!--        <p>Pembina Tingkat I (IV/b)</p>-->
        <!--        <p>NIP. 197306031994031003</p>-->
        <!--    </div>-->
        <!--</div>-->
        
        <!--Footer Editan-->
        <div class="print-only print-footer text-center" style="width: 250px; margin-left: auto;">
            <div class="mt-12">
                <p>Mengetahui,</p>
                <p>Camat Ajibarang</p>
                <br><br><br>
                <p><strong>Arif Ependi, A.P., M.Si</strong></p>
                <p>Pembina Tingkat I (IV/b)</p>
                <p>NIP. 197306031994031003</p>
            </div>
        </div>
    </main>

    <script>
        feather.replace();
        
        // Validasi form filter
        document.querySelector('form').addEventListener('submit', function(e) {
            const tanggalAwal = document.getElementById('tanggal_awal').value;
            const tanggalAkhir = document.getElementById('tanggal_akhir').value;
            
            if (tanggalAwal && tanggalAkhir) {
                if (new Date(tanggalAwal) > new Date(tanggalAkhir)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Tanggal Tidak Valid',
                        text: 'Tanggal awal tidak boleh lebih besar dari tanggal akhir',
                        confirmButtonText: 'OK'
                    });
                }
            }
        });

        // Optimasi untuk cetak
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('.print-only').forEach(el => {
                el.style.display = 'block';
            });
        });
        
        window.addEventListener('afterprint', function() {
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = '';
            });
            document.querySelectorAll('.print-only').forEach(el => {
                el.style.display = 'none';
            });
        });

        // Set tanggal default (7 hari terakhir)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const lastWeek = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            
            // Format tanggal untuk input date (YYYY-MM-DD)
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            // Jika tidak ada filter tanggal, set default 7 hari terakhir
            if (!document.getElementById('tanggal_awal').value && !document.getElementById('tanggal_akhir').value) {
                document.getElementById('tanggal_awal').value = formatDate(lastWeek);
                document.getElementById('tanggal_akhir').value = formatDate(today);
            }
        });
    </script>
</body>
</html>