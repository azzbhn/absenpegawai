<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$current_year = date('Y');
$success = '';
$error = '';

// Ambil semua pegawai untuk dropdown filter
$stmt = $pdo->query('SELECT id_pegawai, nama, nip FROM pegawai WHERE status = "Aktif" ORDER BY nama');
$pegawai_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jenis cuti untuk filter
$jenis_cuti_options = [
    'all' => 'Semua Jenis',
    'cuti_tahunan' => 'Cuti Tahunan',
    'cuti_sakit' => 'Cuti Sakit',
    'cuti_alasan_penting' => 'Cuti Alasan Penting',
    'cuti_melahirkan' => 'Cuti Melahirkan',
    'cuti_besar' => 'Cuti Besar',
    'cuti_luar_tanggungan' => 'Cuti di Luar Tanggungan'
];

// Tahun untuk filter
$tahun_options = [
    $current_year,
    $current_year - 1,
    $current_year - 2,
    'all' => 'Semua Tahun'
];

// Set nilai default filter
$filter_tahun = $_GET['tahun'] ?? $current_year;
$filter_pegawai = $_GET['pegawai'] ?? 'all';
$filter_jenis = $_GET['jenis'] ?? 'all';

// Build query untuk mengambil data dari log_input_cuti
$sql = "
    SELECT 
        l.*, 
        p.nama,
        p.nip,
        YEAR(l.tanggal_mulai) as tahun,
        u.nama as input_by_name
    FROM log_input_cuti l 
    JOIN pegawai p ON l.id_pegawai = p.id_pegawai 
    LEFT JOIN pegawai u ON l.input_by = u.id_pegawai
    WHERE p.status = 'Aktif'
";

$params = [];

// Filter tahun
if ($filter_tahun != 'all') {
    $sql .= " AND YEAR(l.tanggal_mulai) = ?";
    $params[] = $filter_tahun;
}

// Filter pegawai
if ($filter_pegawai != 'all') {
    $sql .= " AND l.id_pegawai = ?";
    $params[] = $filter_pegawai;
}

// Filter jenis cuti
if ($filter_jenis != 'all') {
    $sql .= " AND l.jenis_cuti = ?";
    $params[] = $filter_jenis;
}

$sql .= " ORDER BY l.tanggal_mulai DESC, p.nama ASC";

// Eksekusi query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cuti_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk mendapatkan label jenis cuti
function getJenisCutiLabel($jenis) {
    $labels = [
        'cuti_tahunan' => 'Cuti Tahunan',
        'cuti_sakit' => 'Cuti Sakit',
        'cuti_alasan_penting' => 'Cuti Alasan Penting',
        'cuti_melahirkan' => 'Cuti Melahirkan',
        'cuti_besar' => 'Cuti Besar',
        'cuti_luar_tanggungan' => 'Cuti Luar Tanggungan'
    ];
    return isset($labels[$jenis]) ? $labels[$jenis] : $jenis;
}

// Fungsi untuk mendapatkan warna badge berdasarkan jenis cuti
function getJenisCutiColor($jenis) {
    $colors = [
        'cuti_tahunan' => 'bg-blue-100 text-blue-800',
        'cuti_sakit' => 'bg-red-100 text-red-800',
        'cuti_alasan_penting' => 'bg-orange-100 text-orange-800',
        'cuti_melahirkan' => 'bg-pink-100 text-pink-800',
        'cuti_besar' => 'bg-purple-100 text-purple-800',
        'cuti_luar_tanggungan' => 'bg-indigo-100 text-indigo-800'
    ];
    return isset($colors[$jenis]) ? $colors[$jenis] : 'bg-gray-100 text-gray-800';
}

// Hitung statistik
$total_cuti = count($cuti_data);
$total_hari_cuti = 0;
$cuti_per_jenis = [];
foreach ($cuti_data as $cuti) {
    $total_hari_cuti += $cuti['jumlah_hari'];
    if (!isset($cuti_per_jenis[$cuti['jenis_cuti']])) {
        $cuti_per_jenis[$cuti['jenis_cuti']] = ['jumlah' => 0, 'hari' => 0];
    }
    $cuti_per_jenis[$cuti['jenis_cuti']]['jumlah']++;
    $cuti_per_jenis[$cuti['jenis_cuti']]['hari'] += $cuti['jumlah_hari'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Cuti Pegawai - Kecamatan Ajibarang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { font-size: 12pt; margin: 0; padding: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 8px; }
            .print-header { 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }
            .print-title {
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .print-subtitle {
                font-size: 14pt;
                margin-bottom: 5px;
            }
            .print-info {
                font-size: 11pt;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Daftar Cuti Pegawai</h2>
                    <p class="text-gray-600">Rekapitulasi cuti yang sudah diambil oleh pegawai Kecamatan Ajibarang</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="printTable()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center space-x-2 no-print">
                        <i data-feather="printer"></i>
                        <span>Cetak Laporan</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-xl shadow p-6 mb-6 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                    <select name="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <?php foreach ($tahun_options as $key => $value): ?>
                            <?php if ($key === 'all'): ?>
                                <option value="all" <?= $filter_tahun == 'all' ? 'selected' : '' ?>>Semua Tahun</option>
                            <?php else: ?>
                                <option value="<?= $value ?>" <?= $filter_tahun == $value ? 'selected' : '' ?>><?= $value ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pegawai</label>
                    <select name="pegawai" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <option value="all">Semua Pegawai</option>
                        <?php foreach ($pegawai_list as $pegawai): ?>
                            <option value="<?= $pegawai['id_pegawai'] ?>" <?= $filter_pegawai == $pegawai['id_pegawai'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pegawai['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Cuti</label>
                    <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <?php foreach ($jenis_cuti_options as $key => $value): ?>
                            <option value="<?= $key ?>" <?= $filter_jenis == $key ? 'selected' : '' ?>><?= $value ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-[#F9B000] hover:bg-[#e6a000] text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                        <i data-feather="filter" class="w-4 h-4 mr-2"></i> Filter
                    </button>
                    <a href="list_cuti.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                        <i data-feather="refresh-cw" class="w-4 h-4 mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistik Ringkas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 no-print">
            <div class="bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4">
                        <i data-feather="calendar" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Periode Cuti</p>
                        <p class="text-2xl font-bold"><?= $total_cuti ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4">
                        <i data-feather="clock" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Hari Cuti</p>
                        <p class="text-2xl font-bold"><?= $total_hari_cuti ?></p>
                    </div>
                </div>
            </div>
            
            <?php 
            $jenis_teratas = array_slice($cuti_per_jenis, 0, 2, true);
            $i = 0;
            $color_classes = ['bg-orange-100 text-orange-600', 'bg-purple-100 text-purple-600'];
            foreach ($jenis_teratas as $jenis => $data): 
            ?>
            <div class="bg-white rounded-xl shadow-lg p-4">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg <?= $color_classes[$i] ?> mr-4">
                        <i data-feather="briefcase" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500"><?= getJenisCutiLabel($jenis) ?></p>
                        <p class="text-2xl font-bold"><?= $data['jumlah'] ?></p>
                        <p class="text-xs text-gray-500"><?= $data['hari'] ?> hari</p>
                    </div>
                </div>
            </div>
            <?php $i++; endforeach; ?>
        </div>

        <!-- Tabel Data Cuti -->
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <!-- Print Header (sembunyi saat normal) -->
            <div class="print-only" style="display: none;">
                <div class="print-header">
                    <div class="print-title">LAPORAN DAFTAR CUTI PEGAWAI</div>
                    <div class="print-subtitle">Kecamatan Ajibarang</div>
                    <div class="print-info">
                        <?php 
                        $filter_info = [];
                        if($filter_tahun != 'all'): 
                            $filter_info[] = "Tahun: " . $filter_tahun;
                        endif; 
                        if($filter_pegawai != 'all'): 
                            $pegawai_terpilih = array_filter($pegawai_list, function($p) use ($filter_pegawai) { 
                                return $p['id_pegawai'] == $filter_pegawai; 
                            });
                            $pegawai_terpilih = reset($pegawai_terpilih);
                            if ($pegawai_terpilih) {
                                $filter_info[] = "Pegawai: " . htmlspecialchars($pegawai_terpilih['nama']);
                            }
                        endif; 
                        if($filter_jenis != 'all'): 
                            $filter_info[] = "Jenis: " . $jenis_cuti_options[$filter_jenis];
                        endif; 
                        
                        if (!empty($filter_info)) {
                            echo implode(' | ', $filter_info) . '<br>';
                        }
                        echo "Dicetak pada: " . date('d/m/Y H:i:s');
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if (empty($cuti_data)): ?>
                <div class="p-8 text-center">
                    <i data-feather="inbox" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                    <p class="text-gray-600 font-semibold">Tidak ada data cuti untuk filter yang dipilih</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-100 text-gray-600 text-sm uppercase">
                            <tr>
                                <th class="p-4">No.</th>
                                <th class="p-4">Nama Pegawai</th>
                                <th class="p-4">Jenis Cuti</th>
                                <th class="p-4">Tanggal Mulai</th>
                                <th class="p-4">Tanggal Berakhir</th>
                                <th class="p-4">Jumlah Hari</th>
                                <th class="p-4">Tahun</th>
                                <th class="p-4">Catatan</th>
                                <th class="p-4 no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php $no = 1; ?>
                            <?php foreach ($cuti_data as $cuti): ?>
                                <tr class="hover:bg-gray-50 border-b">
                                    <td class="p-4 text-gray-900"><?= $no++ ?></td>
                                    <td class="p-4">
                                        <b><?= htmlspecialchars($cuti['nama']) ?></b><br>
                                        <span class="text-xs text-gray-500"><?= $cuti['nip'] ?></span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= getJenisCutiColor($cuti['jenis_cuti']) ?>">
                                            <?= getJenisCutiLabel($cuti['jenis_cuti']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?= date('d/m/Y', strtotime($cuti['tanggal_mulai'])) ?>
                                    </td>
                                    <td class="p-4">
                                        <?= date('d/m/Y', strtotime($cuti['tanggal_selesai'])) ?>
                                    </td>
                                    <td class="p-4 font-bold text-blue-600">
                                        <?= $cuti['jumlah_hari'] ?> hari
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                            <?= $cuti['tahun'] ?>
                                        </span>
                                    </td>
                                    <td class="p-4 italic text-gray-500 max-w-xs">
                                        <?= htmlspecialchars($cuti['alasan']) ?>
                                        <?php if ($cuti['input_by_name']): ?>
                                            <div class="text-xs text-gray-400 mt-1">
                                                Input oleh: <?= htmlspecialchars($cuti['input_by_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 space-x-2 no-print">
                                        <a href="edit_cuti.php?pegawai=<?= $cuti['id_pegawai'] ?>&bulan=<?= date('m', strtotime($cuti['tanggal_mulai'])) ?>&tahun=<?= $cuti['tahun'] ?>" 
                                           class="text-blue-600 hover:text-blue-800 transition duration-200 p-1 rounded hover:bg-blue-50 inline-block"
                                           title="Edit Cuti">
                                            <i data-feather="edit-2" class="w-4 h-4"></i>
                                        </a>
                                        <a href="cuti.php?tab=<?= str_replace('cuti_', '', $cuti['jenis_cuti']) ?>&id_pegawai=<?= $cuti['id_pegawai'] ?>" 
                                           class="text-green-600 hover:text-green-800 transition duration-200 p-1 rounded hover:bg-green-50 inline-block"
                                           title="Lihat Detail">
                                            <i data-feather="eye" class="w-4 h-4"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="p-4 bg-gray-50 border-t">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            Menampilkan <span class="font-bold"><?= $total_cuti ?></span> periode cuti 
                            (<span class="font-bold"><?= $total_hari_cuti ?></span> hari)
                        </div>
                        <div class="text-sm text-gray-600 no-print">
                            <button onclick="printTable()" class="text-green-600 hover:text-green-800 flex items-center space-x-1">
                                <i data-feather="printer" class="w-4 h-4"></i>
                                <span>Cetak Laporan</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        feather.replace();
        
        function printTable() {
            // Sembunyikan elemen yang tidak perlu dicetak
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = 'none';
            });
            
            // Tampilkan header untuk cetak
            document.querySelectorAll('.print-only').forEach(el => {
                el.style.display = 'block';
            });
            
            // Cetak
            window.print();
            
            // Kembalikan tampilan normal setelah cetak
            setTimeout(() => {
                document.querySelectorAll('.no-print').forEach(el => {
                    el.style.display = '';
                });
                document.querySelectorAll('.print-only').forEach(el => {
                    el.style.display = 'none';
                });
            }, 100);
        }
    </script>
</body>
</html>