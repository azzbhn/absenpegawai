<?php
require_once 'config/db.php';

// Cek hak akses Admin
if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// --- FUNGSI LOGIKA DATABASE ---

// 1. Fungsi Hapus/Revert Cuti (Disesuaikan untuk menerima durasi lama agar akurat saat revert)
function revertCuti($pdo, $id_pegawai, $tgl_mulai, $tgl_selesai, $jenis_cuti, $lama_lama = null) {
    // Jika durasi lama tidak dikirim, hitung manual (fallback)
    if ($lama_lama === null) {
        $start = new DateTime($tgl_mulai);
        $end = new DateTime($tgl_selesai);
        $days = $start->diff($end)->days + 1;
    } else {
        $days = $lama_lama;
    }

    // Hapus data di tabel absensi
    $stmt = $pdo->prepare("DELETE FROM absensi WHERE id_pegawai = ? AND status = ? AND tanggal BETWEEN ? AND ?");
    $stmt->execute([$id_pegawai, $jenis_cuti, $tgl_mulai, $tgl_selesai]);

    // Jika cuti tahunan, kembalikan saldo
    if ($jenis_cuti == 'cuti_tahunan') {
        $tahun = date('Y', strtotime($tgl_mulai));
        $stmt = $pdo->prepare("SELECT id_sisa_cuti, sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?");
        $stmt->execute([$id_pegawai, $tahun]);
        $quota = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($quota) {
            $new_quota = min(12, $quota['sisa_cuti'] + $days);
            $update = $pdo->prepare("UPDATE sisa_cuti_tahunan SET sisa_cuti = ? WHERE id_sisa_cuti = ?");
            $update->execute([$new_quota, $quota['id_sisa_cuti']]);
        }
    }
}

function checkOverlap($pdo, $id_pegawai, $dates) {
    foreach ($dates as $tanggal) {
        $stmt = $pdo->prepare('SELECT count(*) FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
        $stmt->execute([$id_pegawai, $tanggal]);
        if ($stmt->fetchColumn() > 0) {
            return $tanggal;
        }
    }
    return false;
}

// Fungsi untuk mendapatkan durasi intervensi dari log
function getDurasiIntervensi($pdo, $id_pegawai, $tanggal_mulai, $jenis_cuti) {
    try {
        // Cek apakah tabel log_input_cuti ada
        $table_check = $pdo->query("SHOW TABLES LIKE 'log_input_cuti'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT jumlah_hari 
                FROM log_input_cuti 
                WHERE id_pegawai = ? 
                AND tanggal_mulai = ? 
                AND jenis_cuti = ?
                ORDER BY id_log DESC 
                LIMIT 1
            ");
            $stmt->execute([$id_pegawai, $tanggal_mulai, $jenis_cuti]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int)$result['jumlah_hari'] : null;
        }
    } catch (Exception $e) {
        // Log error tanpa mengganggu proses utama
        error_log("Error getting durasi intervensi: " . $e->getMessage());
    }
    return null;
}

// --- HANDLE POST REQUESTS ---

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        try {
            $pdo->beginTransaction();
            revertCuti($pdo, $_POST['id_pegawai'], $_POST['old_start'], $_POST['old_end'], $_POST['old_jenis'], (int)$_POST['old_lama']);
            
            // Hapus dari log jika ada
            try {
                $stmt = $pdo->prepare("DELETE FROM log_input_cuti WHERE id_pegawai = ? AND tanggal_mulai = ? AND jenis_cuti = ?");
                $stmt->execute([$_POST['id_pegawai'], $_POST['old_start'], $_POST['old_jenis']]);
            } catch (Exception $e) {
                // Log error tanpa mengganggu proses utama
                error_log("Error deleting from log: " . $e->getMessage());
            }
            
            $pdo->commit();
            $success = "Data cuti berhasil dihapus.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal menghapus: " . $e->getMessage();
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        try {
            $pdo->beginTransaction();

            $id_pegawai = $_POST['id_pegawai'];
            $old_start = $_POST['old_start'];
            $old_end = $_POST['old_end'];
            $old_jenis = $_POST['old_jenis'];
            $old_lama = (int)$_POST['old_lama'];
            
            $new_jenis = $_POST['jenis_cuti'];
            $new_start = $_POST['tanggal_mulai'];
            $new_end = $_POST['tanggal_selesai'];
            $new_alasan = $_POST['alasan'];
            $lama_cuti_baru = (int)$_POST['lama_cuti']; // Hasil intervensi Admin

            // Validasi input
            if ($lama_cuti_baru < 1) {
                throw new Exception("Lama cuti minimal 1 hari!");
            }

            // 1. Hapus Data Lama & Revert Saldo
            revertCuti($pdo, $id_pegawai, $old_start, $old_end, $old_jenis, $old_lama);

            // 2. Persiapkan Rentang Tanggal Absensi (menggunakan fungsi dari db.php)
            $dates = [];
            $current = strtotime($new_start);
            $last = strtotime($new_end);
            while ($current <= $last) {
                $dates[] = date('Y-m-d', $current);
                $current = strtotime('+1 day', $current);
            }

            // 3. Validasi Bentrok
            $bentrok = checkOverlap($pdo, $id_pegawai, $dates);
            if ($bentrok) {
                throw new Exception("Tanggal bentrok pada: " . date('d/m/Y', strtotime($bentrok)));
            }

            // 4. Insert Data Baru
            foreach ($dates as $tanggal) {
                $stmt_col = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'input_by_admin'");
                $sql = ($stmt_col->fetch()) 
                    ? "INSERT INTO absensi (id_pegawai, tanggal, status, catatan_admin, input_by_admin) VALUES (?, ?, ?, ?, 1)"
                    : "INSERT INTO absensi (id_pegawai, tanggal, status, catatan_admin) VALUES (?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_pegawai, $tanggal, $new_jenis, $new_alasan]);
            }

            // 5. Potong Saldo (Gunakan $lama_cuti_baru hasil intervensi)
            if ($new_jenis == 'cuti_tahunan') {
                $tahun = date('Y', strtotime($new_start));
                $stmt = $pdo->prepare("SELECT id_sisa_cuti, sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?");
                $stmt->execute([$id_pegawai, $tahun]);
                $quota = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($quota) {
                    $sisa_baru = max(0, $quota['sisa_cuti'] - $lama_cuti_baru);
                    $update = $pdo->prepare("UPDATE sisa_cuti_tahunan SET sisa_cuti = ? WHERE id_sisa_cuti = ?");
                    $update->execute([$sisa_baru, $quota['id_sisa_cuti']]);
                } else {
                    $sisa_awal = max(0, 12 - $lama_cuti_baru);
                    $pdo->prepare("INSERT INTO sisa_cuti_tahunan (id_pegawai, tahun, sisa_cuti) VALUES (?, ?, ?)")
                        ->execute([$id_pegawai, $tahun, $sisa_awal]);
                }
            }

            // 6. Update log input cuti
            try {
                // Hapus log lama
                $stmt = $pdo->prepare("DELETE FROM log_input_cuti WHERE id_pegawai = ? AND tanggal_mulai = ? AND jenis_cuti = ?");
                $stmt->execute([$id_pegawai, $old_start, $old_jenis]);
                
                // Insert log baru
                $stmt = $pdo->prepare("
                    INSERT INTO log_input_cuti (id_pegawai, jenis_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, input_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_pegawai, 
                    $new_jenis, 
                    $new_start, 
                    $new_end, 
                    $lama_cuti_baru, 
                    $new_alasan, 
                    $_SESSION['user']['id_pegawai']
                ]);
            } catch (Exception $e) {
                // Log error tanpa mengganggu proses utama
                error_log("Error updating log: " . $e->getMessage());
            }

            $pdo->commit();
            $success = "Data cuti berhasil diperbarui menjadi $lama_cuti_baru hari!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal memperbarui: " . $e->getMessage();
        }
    }
}

// --- FETCH DATA ---
$filter_pegawai = isset($_GET['pegawai']) ? $_GET['pegawai'] : '';
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : 'all';
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : 'all';

$sql = "SELECT a.*, p.nama, p.nip FROM absensi a JOIN pegawai p ON a.id_pegawai = p.id_pegawai WHERE a.status LIKE 'cuti_%'";
$params = [];
if ($filter_pegawai) { 
    $sql .= " AND a.id_pegawai = ?"; 
    $params[] = $filter_pegawai; 
}
if ($filter_bulan != 'all') { 
    $sql .= " AND MONTH(a.tanggal) = ?"; 
    $params[] = $filter_bulan; 
}
if ($filter_tahun != 'all') { 
    $sql .= " AND YEAR(a.tanggal) = ?"; 
    $params[] = $filter_tahun; 
}
$sql .= " ORDER BY p.nama ASC, a.tanggal DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouping logic dengan penambahan durasi intervensi dari log
$grouped_data = [];
$current_group = null;

foreach ($raw_data as $row) {
    $group_key = $row['id_pegawai'] . '_' . $row['status'] . '_' . ($row['catatan_admin'] ?? '');
    
    // Cek durasi intervensi dari log
    $durasi_intervensi = getDurasiIntervensi($pdo, $row['id_pegawai'], $row['tanggal'], $row['status']);
    
    if ($current_group) {
        $prev_date = new DateTime($current_group['tanggal_akhir']);
        $curr_date = new DateTime($row['tanggal']);
        $diff = $curr_date->diff($prev_date)->days;
        
        if ($diff == 1 && $current_group['group_key'] == $group_key) {
            // Lanjutkan periode yang sama
            $current_group['tanggal_akhir'] = $row['tanggal'];
            
            // Update durasi: gunakan intervensi jika ada, atau tambah 1
            if ($durasi_intervensi !== null) {
                $current_group['lama'] = $durasi_intervensi;
            } else {
                $current_group['lama']++;
            }
            
            continue;
        } else {
            // Simpan grup sebelumnya dan mulai grup baru
            $grouped_data[] = $current_group;
            $current_group = null;
        }
    }
    
    // Mulai grup baru
    $current_group = [
        'group_key' => $group_key, 
        'id_pegawai' => $row['id_pegawai'], 
        'nama' => $row['nama'],
        'nip' => $row['nip'], 
        'jenis_cuti' => $row['status'], 
        'alasan' => $row['catatan_admin'],
        'tanggal_awal' => $row['tanggal'], 
        'tanggal_akhir' => $row['tanggal'], 
        'lama' => $durasi_intervensi !== null ? $durasi_intervensi : 1
    ];
}

// Tambahkan grup terakhir jika ada
if ($current_group) {
    $grouped_data[] = $current_group;
}

$pegawai_list = $pdo->query("SELECT id_pegawai, nama FROM pegawai WHERE status = 'Aktif' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$jenis_cuti_labels = [
    'cuti_tahunan' => 'Cuti Tahunan', 
    'cuti_sakit' => 'Cuti Sakit', 
    'cuti_alasan_penting' => 'Cuti Alasan Penting',
    'cuti_melahirkan' => 'Cuti Melahirkan', 
    'cuti_besar' => 'Cuti Besar', 
    'cuti_luar_tanggungan' => 'Cuti Luar Tanggungan'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Cuti - Administrator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        @media print {
            .no-print { display: none !important; }
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
            .badge {
                background-color: #e5e7eb !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <!-- Header dengan Tombol Input Baru -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Edit Data Cuti</h2>
                    <p class="text-gray-600">Admin Intervensi - Perubahan durasi hari diperbolehkan</p>
                </div>
                <div class="flex gap-2">
                    <a href="input_cuti.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm flex items-center no-print transition duration-200">
                        <i data-feather="plus" class="w-4 h-4 mr-2"></i> Input Baru
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter dan Tombol Print -->
        <div class="bg-white rounded-xl shadow p-6 mb-6 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pegawai</label>
                    <select name="pegawai" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <option value="">Semua Pegawai</option>
                        <?php foreach($pegawai_list as $pgw): ?>
                            <option value="<?= $pgw['id_pegawai'] ?>" <?= $filter_pegawai == $pgw['id_pegawai'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pgw['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
                    <select name="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <option value="all">Semua Bulan</option>
                        <?php for($i=1; $i<=12; $i++): ?>
                            <?php $selected = ($filter_bulan == $i) ? 'selected' : ''; ?>
                            <option value="<?= $i ?>" <?= $selected ?>>
                                <?= date('F', mktime(0,0,0,$i,10)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                    <select name="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        <option value="all">Semua Tahun</option>
                        <?php for($i=date('Y'); $i>=date('Y')-5; $i--): ?>
                            <?php $selected = ($filter_tahun == $i) ? 'selected' : ''; ?>
                            <option value="<?= $i ?>" <?= $selected ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-[#F9B000] hover:bg-[#e6a000] text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                        <i data-feather="filter" class="w-4 h-4 mr-2"></i> Filter
                    </button>
                    <button type="button" onclick="printTable()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                        <i data-feather="printer" class="w-4 h-4 mr-2"></i> Print
                    </button>
                </div>
            </form>
        </div>

        <!-- Notifikasi -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 no-print">
                <div class="flex items-center">
                    <i data-feather="alert-circle" class="w-5 h-5 mr-2"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 no-print">
                <div class="flex items-center">
                    <i data-feather="check-circle" class="w-5 h-5 mr-2"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabel Data Cuti -->
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <!-- Print Header (sembunyi saat normal) -->
            <div class="print-only" style="display: none;">
                <div class="print-header">
                    <div class="print-title">LAPORAN DATA CUTI PEGAWAI</div>
                    <div class="print-subtitle">Kecamatan Ajibarang</div>
                    <div class="print-info">
                        <?php 
                        $filter_info = [];
                        if($filter_pegawai): 
                            $pegawai_terpilih = array_filter($pegawai_list, function($p) use ($filter_pegawai) { 
                                return $p['id_pegawai'] == $filter_pegawai; 
                            });
                            $pegawai_terpilih = reset($pegawai_terpilih);
                            if ($pegawai_terpilih) {
                                $filter_info[] = "Pegawai: " . htmlspecialchars($pegawai_terpilih['nama']);
                            }
                        endif; 
                        if($filter_bulan != 'all'): 
                            $filter_info[] = "Bulan: " . date('F', mktime(0,0,0,$filter_bulan,10));
                        endif; 
                        if($filter_tahun != 'all'): 
                            $filter_info[] = "Tahun: " . $filter_tahun;
                        endif; 
                        
                        if (!empty($filter_info)) {
                            echo implode(' | ', $filter_info) . '<br>';
                        }
                        ?>
                        Dicetak pada: <?= date('d/m/Y H:i:s') ?>
                    </div>
                </div>
            </div>
            
            <table class="w-full text-left">
                <thead class="bg-gray-100 text-gray-600 text-sm uppercase">
                    <tr>
                        <th class="p-4">Pegawai</th>
                        <th class="p-4">Jenis Cuti</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4 text-center">Lama</th>
                        <th class="p-4">Alasan</th>
                        <th class="p-4 text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if (empty($grouped_data)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">
                                <i data-feather="inbox" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                                <p>Tidak ada data cuti untuk filter yang dipilih.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($grouped_data as $row): 
                            // Pastikan tanggal awal <= tanggal akhir
                            $tgl_mulai = $row['tanggal_awal'];
                            $tgl_selesai = $row['tanggal_akhir'];
                            
                            if (strtotime($tgl_mulai) > strtotime($tgl_selesai)) {
                                // Tukar jika tanggal awal > tanggal akhir
                                $temp = $tgl_mulai;
                                $tgl_mulai = $tgl_selesai;
                                $tgl_selesai = $temp;
                            }
                        ?>
                        <tr class="hover:bg-gray-50 border-b">
                            <td class="p-4">
                                <b><?= htmlspecialchars($row['nama']) ?></b><br>
                                <span class="text-xs text-gray-500"><?= $row['nip'] ?></span>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold bg-blue-100 text-blue-800">
                                    <?= isset($jenis_cuti_labels[$row['jenis_cuti']]) ? $jenis_cuti_labels[$row['jenis_cuti']] : $row['jenis_cuti'] ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?>
                            </td>
                            <td class="p-4 text-center font-bold text-blue-600">
                                <?= $row['lama'] ?> Hari
                            </td>
                            <td class="p-4 italic text-gray-500">
                                <?= htmlspecialchars($row['alasan']) ?>
                            </td>
                            <td class="p-4 text-center space-x-2 no-print">
                                <button onclick='openEditModal(<?= json_encode([
                                    "id_pegawai" => $row["id_pegawai"], 
                                    "nama" => $row["nama"], 
                                    "jenis" => $row["jenis_cuti"],
                                    "mulai" => $tgl_mulai, 
                                    "selesai" => $tgl_selesai, 
                                    "alasan" => $row["alasan"], 
                                    "lama" => $row["lama"]
                                ]) ?>)' 
                                class="text-blue-600 hover:text-blue-800 transition duration-200 p-1 rounded hover:bg-blue-50">
                                    <i data-feather="edit-2" class="w-4 h-4"></i>
                                </button>
                                <button onclick="confirmDelete('<?= $row['id_pegawai'] ?>', '<?= $tgl_mulai ?>', '<?= $tgl_selesai ?>', '<?= $row['jenis_cuti'] ?>', <?= $row['lama'] ?>)" 
                                class="text-red-600 hover:text-red-800 transition duration-200 p-1 rounded hover:bg-red-50">
                                    <i data-feather="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal Edit -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4 no-print">
        <div class="bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Edit Data Cuti (Intervensi)</h3>
                <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                    <i data-feather="x"></i>
                </button>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_pegawai" id="modal_id_pegawai">
                <input type="hidden" name="old_start" id="modal_old_start">
                <input type="hidden" name="old_end" id="modal_old_end">
                <input type="hidden" name="old_jenis" id="modal_old_jenis">
                <input type="hidden" name="old_lama" id="modal_old_lama">

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase">Nama Pegawai</label>
                        <input type="text" id="modal_nama" class="w-full p-3 bg-gray-100 border border-gray-300 rounded-lg focus:outline-none" readonly>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Jenis Cuti</label>
                            <select name="jenis_cuti" id="modal_jenis" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                                <?php foreach($jenis_cuti_labels as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-bold text-blue-600">Lama Cuti (Hari) *</label>
                            <input type="number" name="lama_cuti" id="modal_lama_cuti" min="1" 
                                   class="w-full p-3 border-2 border-blue-400 bg-blue-50 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600" 
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Sistem menghitung otomatis, silakan ubah jika ada intervensi.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Mulai *</label>
                            <input type="date" name="tanggal_mulai" id="modal_mulai" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]" 
                                   required>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Selesai *</label>
                            <input type="date" name="tanggal_selesai" id="modal_selesai" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]" 
                                   required>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Alasan</label>
                        <textarea name="alasan" id="modal_alasan" rows="3" 
                                  class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition duration-200">
                            Batal
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Form Delete Hidden -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_pegawai" id="del_id_pegawai">
        <input type="hidden" name="old_start" id="del_old_start">
        <input type="hidden" name="old_end" id="del_old_end">
        <input type="hidden" name="old_jenis" id="del_old_jenis">
        <input type="hidden" name="old_lama" id="del_old_lama">
    </form>

    <script>
        // Inisialisasi feather icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Fungsi untuk menghitung hari otomatis
        function calculateAuto() {
            const start = document.getElementById('modal_mulai');
            const end = document.getElementById('modal_selesai');
            const lamaInput = document.getElementById('modal_lama_cuti');
            
            if (start && end && lamaInput && start.value && end.value) {
                const d1 = new Date(start.value);
                const d2 = new Date(end.value);
                
                if (d2 >= d1) {
                    const diff = Math.ceil(Math.abs(d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
                    lamaInput.value = diff;
                }
            }
        }

        // Event listeners untuk input tanggal
        document.addEventListener('DOMContentLoaded', function() {
            const startInput = document.getElementById('modal_mulai');
            const endInput = document.getElementById('modal_selesai');
            
            if (startInput) {
                startInput.addEventListener('change', calculateAuto);
            }
            if (endInput) {
                endInput.addEventListener('change', calculateAuto);
            }
        });

        function openEditModal(data) {
            const modal = document.getElementById('editModal');
            if (!modal) return;
            
            // Set nilai form
            document.getElementById('modal_id_pegawai').value = data.id_pegawai || '';
            document.getElementById('modal_nama').value = data.nama || '';
            document.getElementById('modal_old_start').value = data.mulai || '';
            document.getElementById('modal_old_end').value = data.selesai || '';
            document.getElementById('modal_old_jenis').value = data.jenis || '';
            document.getElementById('modal_old_lama').value = data.lama || 1;
            
            document.getElementById('modal_jenis').value = data.jenis || '';
            document.getElementById('modal_mulai').value = data.mulai || '';
            document.getElementById('modal_selesai').value = data.selesai || '';
            document.getElementById('modal_alasan').value = data.alasan || '';
            document.getElementById('modal_lama_cuti').value = data.lama || 1;
            
            // Tampilkan modal
            modal.classList.remove('hidden');
        }

        function closeEditModal() { 
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.classList.add('hidden'); 
            }
        }

        function confirmDelete(id, start, end, jenis, lama) {
            Swal.fire({
                title: 'Hapus Data?',
                text: "Saldo cuti akan dikembalikan sejumlah " + lama + " hari.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                width: '400px'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('del_id_pegawai').value = id || '';
                    document.getElementById('del_old_start').value = start || '';
                    document.getElementById('del_old_end').value = end || '';
                    document.getElementById('del_old_jenis').value = jenis || '';
                    document.getElementById('del_old_lama').value = lama || 1;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

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

        // Refresh feather icons setelah modal dibuka
        document.addEventListener('modalShown', function() {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    </script>
</body>
</html>