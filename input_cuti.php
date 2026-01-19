<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Ambil semua pegawai untuk dropdown
$stmt = $pdo->query('SELECT id_pegawai, nama, nip FROM pegawai WHERE status = "Aktif" ORDER BY nama');
$pegawai_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jenis cuti
$jenis_cuti_options = [
    'cuti_tahunan' => 'Cuti Tahunan',
    'cuti_sakit' => 'Cuti Sakit',
    'cuti_alasan_penting' => 'Cuti Alasan Penting',
    'cuti_melahirkan' => 'Cuti Melahirkan',
    'cuti_besar' => 'Cuti Besar',
    'cuti_luar_tanggungan' => 'Cuti di Luar Tanggungan Negara'
];

// Fungsi untuk menghitung alokasi pengambilan cuti tahunan
function hitungAlokasiCutiTahunan($pdo, $id_pegawai, $jumlah_hari, $tahun_pengambilan) {
    $alokasi = [
        'tahun_ini' => 0,
        'tahun_lalu' => 0,
        'tahun_dulu' => 0
    ];
    
    $sisa_hari = $jumlah_hari;
    
    // 1. Ambil dari hak cuti tahun ini terlebih dahulu
    $stmt = $pdo->prepare('SELECT hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun_pengambilan]);
    $hak_tahun_ini = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hak_tahun_ini) {
        // Hitung penggunaan yang sudah ada untuk tahun ini
        $stmt = $pdo->prepare('SELECT SUM(jumlah_hari) as total_penggunaan FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun_pengambilan]);
        $penggunaan_tahun_ini = $stmt->fetch(PDO::FETCH_ASSOC);
        $penggunaan_sekarang = $penggunaan_tahun_ini ? (int)$penggunaan_tahun_ini['total_penggunaan'] : 0;
        
        // Hitung sisa yang benar-benar tersedia (Hak Cuti - Penggunaan)
        $sisa_tersedia = max(0, $hak_tahun_ini['hak_cuti'] - $penggunaan_sekarang);
        $ambil_dari_tahun_ini = min($sisa_tersedia, $sisa_hari);
        
        if ($ambil_dari_tahun_ini > 0) {
            $alokasi['tahun_ini'] = $ambil_dari_tahun_ini;
            $sisa_hari -= $ambil_dari_tahun_ini;
        }
    }
    
    // 2. Jika masih ada sisa, ambil dari tahun lalu
    if ($sisa_hari > 0) {
        $stmt = $pdo->prepare('SELECT hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun_pengambilan - 1]);
        $hak_tahun_lalu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hak_tahun_lalu) {
            // Hitung penggunaan yang sudah ada untuk tahun lalu
            $stmt = $pdo->prepare('SELECT SUM(jumlah_hari) as total_penggunaan FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
            $stmt->execute([$id_pegawai, $tahun_pengambilan - 1]);
            $penggunaan_tahun_lalu = $stmt->fetch(PDO::FETCH_ASSOC);
            $penggunaan_sekarang = $penggunaan_tahun_lalu ? (int)$penggunaan_tahun_lalu['total_penggunaan'] : 0;
            
            // Hitung sisa yang benar-benar tersedia
            $sisa_tersedia = max(0, $hak_tahun_lalu['hak_cuti'] - $penggunaan_sekarang);
            $ambil_dari_tahun_lalu = min($sisa_tersedia, $sisa_hari);
            
            if ($ambil_dari_tahun_lalu > 0) {
                $alokasi['tahun_lalu'] = $ambil_dari_tahun_lalu;
                $sisa_hari -= $ambil_dari_tahun_lalu;
            }
        }
    }
    
    // 3. Jika masih ada sisa, ambil dari tahun dulu
    if ($sisa_hari > 0) {
        $stmt = $pdo->prepare('SELECT hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
        $stmt->execute([$id_pegawai, $tahun_pengambilan - 2]);
        $hak_tahun_dulu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hak_tahun_dulu) {
            // Hitung penggunaan yang sudah ada untuk tahun dulu
            $stmt = $pdo->prepare('SELECT SUM(jumlah_hari) as total_penggunaan FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
            $stmt->execute([$id_pegawai, $tahun_pengambilan - 2]);
            $penggunaan_tahun_dulu = $stmt->fetch(PDO::FETCH_ASSOC);
            $penggunaan_sekarang = $penggunaan_tahun_dulu ? (int)$penggunaan_tahun_dulu['total_penggunaan'] : 0;
            
            // Hitung sisa yang benar-benar tersedia
            $sisa_tersedia = max(0, $hak_tahun_dulu['hak_cuti'] - $penggunaan_sekarang);
            $ambil_dari_tahun_dulu = min($sisa_tersedia, $sisa_hari);
            
            if ($ambil_dari_tahun_dulu > 0) {
                $alokasi['tahun_dulu'] = $ambil_dari_tahun_dulu;
                $sisa_hari -= $ambil_dari_tahun_dulu;
            }
        }
    }
    
    // Jika masih ada sisa yang tidak teralokasi, lempar error
    if ($sisa_hari > 0) {
        throw new Exception("Tidak cukup hak cuti yang tersedia. Sisa yang perlu dialokasikan: $sisa_hari hari");
    }
    
    return $alokasi;
}

// Fungsi untuk mencatat penggunaan cuti tahunan (DIPERBAIKI - tanpa updated_at)
function catatPenggunaanCutiTahunan($pdo, $id_pegawai, $tahun, $jumlah_hari) {
    // Cek apakah sudah ada data untuk tahun ini
    $stmt = $pdo->prepare('SELECT id_penggunaan, jumlah_hari FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update data yang ada
        $new_total = $existing['jumlah_hari'] + $jumlah_hari;
        $stmt = $pdo->prepare('UPDATE penggunaan_cuti_tahunan SET jumlah_hari = ? WHERE id_penggunaan = ?');
        $stmt->execute([$new_total, $existing['id_penggunaan']]);
    } else {
        // Insert data baru (sesuai struktur tabel yang ada)
        $stmt = $pdo->prepare('INSERT INTO penggunaan_cuti_tahunan (id_pegawai, tahun, jumlah_hari, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$id_pegawai, $tahun, $jumlah_hari]);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pegawai = $_POST['id_pegawai'];
    $jenis_cuti = $_POST['jenis_cuti'];
    $lama_cuti = (int)$_POST['lama_cuti'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $alasan = $_POST['alasan'];
    $tahun_pengambilan = date('Y', strtotime($tanggal_mulai));
    
    // Validasi
    if (empty($id_pegawai) || empty($jenis_cuti) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($lama_cuti < 1) {
        $error = 'Lama cuti minimal 1 hari!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Generate tanggal-tanggal cuti
            $dates = [];
            $current = strtotime($tanggal_mulai);
            for ($i = 0; $i < $lama_cuti; $i++) {
                $dates[] = date('Y-m-d', $current);
                $current = strtotime('+1 day', $current);
            }
            
            // Validasi akhir tanggal
            $last_date = end($dates);
            if (strtotime($last_date) > strtotime($tanggal_selesai)) {
                $tanggal_selesai = $last_date;
            }
            
            // Validasi bentrok
            foreach ($dates as $tanggal) {
                $stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
                $stmt->execute([$id_pegawai, $tanggal]);
                if ($stmt->fetch()) {
                    throw new Exception("Pegawai sudah memiliki absensi pada tanggal " . date('d/m/Y', strtotime($tanggal)));
                }
            }
            
            // Insert data absensi
            foreach ($dates as $tanggal) {
                // Cek apakah tabel absensi memiliki kolom input_by_admin
                $stmt = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'input_by_admin'");
                $has_input_by_admin = $stmt->fetch();
                
                if ($has_input_by_admin) {
                    $stmt = $pdo->prepare('
                        INSERT INTO absensi (id_pegawai, tanggal, status, jam_masuk, jam_keluar, lokasi_lat, lokasi_lng, catatan_admin, input_by_admin) 
                        VALUES (?, ?, ?, NULL, NULL, NULL, NULL, ?, TRUE)
                    ');
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO absensi (id_pegawai, tanggal, status, jam_masuk, jam_keluar, lokasi_lat, lokasi_lng, catatan_admin) 
                        VALUES (?, ?, ?, NULL, NULL, NULL, NULL, ?)
                    ');
                }
                $stmt->execute([$id_pegawai, $tanggal, $jenis_cuti, $alasan]);
            }
            
            // Jika cuti tahunan, catat penggunaan dan alokasi
            if ($jenis_cuti == 'cuti_tahunan') {
                // Hitung alokasi pengambilan cuti
                $alokasi = hitungAlokasiCutiTahunan($pdo, $id_pegawai, $lama_cuti, $tahun_pengambilan);
                
                // Catat penggunaan untuk setiap tahun
                if ($alokasi['tahun_ini'] > 0) {
                    catatPenggunaanCutiTahunan($pdo, $id_pegawai, $tahun_pengambilan, $alokasi['tahun_ini']);
                }
                if ($alokasi['tahun_lalu'] > 0) {
                    catatPenggunaanCutiTahunan($pdo, $id_pegawai, $tahun_pengambilan - 1, $alokasi['tahun_lalu']);
                }
                if ($alokasi['tahun_dulu'] > 0) {
                    catatPenggunaanCutiTahunan($pdo, $id_pegawai, $tahun_pengambilan - 2, $alokasi['tahun_dulu']);
                }
                
                // Catat di log dengan informasi alokasi
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO log_input_cuti (id_pegawai, jenis_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, tahun_hak, alasan, input_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    
                    // Buat string tahun hak yang digunakan
                    $tahun_hak_used = [];
                    if ($alokasi['tahun_ini'] > 0) $tahun_hak_used[] = $tahun_pengambilan;
                    if ($alokasi['tahun_lalu'] > 0) $tahun_hak_used[] = $tahun_pengambilan - 1;
                    if ($alokasi['tahun_dulu'] > 0) $tahun_hak_used[] = $tahun_pengambilan - 2;
                    
                    $tahun_hak_str = !empty($tahun_hak_used) ? implode(',', $tahun_hak_used) : '';
                    
                    $stmt->execute([
                        $id_pegawai, 
                        $jenis_cuti, 
                        $tanggal_mulai, 
                        $tanggal_selesai, 
                        $lama_cuti, 
                        $tahun_hak_str,
                        $alasan, 
                        $_SESSION['user']['id_pegawai']
                    ]);
                } catch (Exception $e) {
                    error_log("Error logging cuti: " . $e->getMessage());
                }
            } else {
                // Untuk cuti non-tahunan, log tanpa tahun_hak
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO log_input_cuti (id_pegawai, jenis_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, alasan, input_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $id_pegawai, 
                        $jenis_cuti, 
                        $tanggal_mulai, 
                        $tanggal_selesai, 
                        $lama_cuti, 
                        $alasan, 
                        $_SESSION['user']['id_pegawai']
                    ]);
                } catch (Exception $e) {
                    error_log("Error logging cuti: " . $e->getMessage());
                }
            }
            
            $pdo->commit();
            
            $success = 'Cuti berhasil diinput untuk ' . $lama_cuti . ' hari!';
            
            // Reset form
            echo '<script>document.getElementById("cutiForm").reset();</script>';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Cuti Pegawai - Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .manual-override {
            border-color: #f59e0b !important;
            background-color: #fffbeb !important;
        }
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Input Cuti Pegawai</h2>
            <p class="text-gray-600">Input cuti yang sudah diambil oleh pegawai (admin only)</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6" id="cutiForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Pilih Pegawai -->
                        <div>
                            <label for="id_pegawai" class="block text-sm font-medium text-gray-700 mb-2">Pilih Pegawai *</label>
                            <select id="id_pegawai" name="id_pegawai" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                                <option value="">-- Pilih Pegawai --</option>
                                <?php foreach ($pegawai_list as $pegawai): ?>
                                    <option value="<?= $pegawai['id_pegawai'] ?>">
                                        <?= htmlspecialchars($pegawai['nama']) ?> (NIP: <?= htmlspecialchars($pegawai['nip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Jenis Cuti -->
                        <div>
                            <label for="jenis_cuti" class="block text-sm font-medium text-gray-700 mb-2">Jenis Cuti *</label>
                            <select id="jenis_cuti" name="jenis_cuti" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                                <option value="">-- Pilih Jenis Cuti --</option>
                                <?php foreach ($jenis_cuti_options as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Lama Cuti dengan Tombol Hitung Ulang -->
                        <div>
                            <label for="lama_cuti" class="block text-sm font-medium text-gray-700 mb-2">
                                Lama Cuti (hari) *
                                <span class="text-xs text-gray-500 font-normal">(bisa diubah manual)</span>
                            </label>
                            <div class="flex items-center space-x-2">
                                <input type="number" id="lama_cuti" name="lama_cuti" min="1" required 
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                                <button type="button" id="hitungUlangBtn" 
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-3 rounded-lg transition duration-200 flex items-center space-x-1 whitespace-nowrap"
                                        title="Hitung ulang berdasarkan tanggal">
                                    <i data-feather="refresh-cw" class="w-4 h-4"></i>
                                    <span class="text-sm">Hitung Ulang</span>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Otomatis terhitung dari tanggal. Bisa diubah manual untuk penyesuaian hari libur.
                            </p>
                        </div>

                        <!-- Tanggal Mulai -->
                        <div>
                            <label for="tanggal_mulai" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai *</label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        </div>

                        <!-- Tanggal Berakhir -->
                        <div>
                            <label for="tanggal_selesai" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Berakhir *</label>
                            <input type="date" id="tanggal_selesai" name="tanggal_selesai" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        </div>
                    </div>

                    <!-- Alasan Cuti -->
                    <div>
                        <label for="alasan" class="block text-sm font-medium text-gray-700 mb-2">Alasan Cuti</label>
                        <textarea id="alasan" name="alasan" rows="3" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]"
                                  placeholder="Masukkan alasan cuti..."></textarea>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex justify-end space-x-4 pt-4">
                        <a href="data_absensi.php" 
                           class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center space-x-2">
                            <i data-feather="arrow-left"></i>
                            <span>Kembali</span>
                        </a>
                        <button type="submit" 
                                class="bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center space-x-2">
                            <i data-feather="save"></i>
                            <span>Simpan Cuti</span>
                        </button>
                    </div>
                </form>

                <!-- Informasi -->
                <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <h4 class="font-bold text-blue-800 mb-2 flex items-center">
                        <i data-feather="info" class="w-5 h-5 mr-2"></i>
                        Informasi:
                    </h4>
                    <ul class="text-blue-700 text-sm space-y-1">
                        <li>• <strong>Lama Cuti bisa diubah manual:</strong> Jika cuti melewati hari Sabtu, Minggu, atau hari libur yang tidak dihitung sebagai cuti, admin dapat mengurangi jumlah hari secara manual.</li>
                        <li>• Data cuti yang diinput akan muncul di halaman "Kendali Cuti" masing-masing pegawai</li>
                        <li>• Untuk cuti tahunan, sistem akan otomatis mengurangi hak cuti tahunan pegawai dengan aturan: Tahun Ini > Tahun Lalu > Tahun Dulu</li>
                        <li>• Pastikan tanggal cuti tidak bentrok dengan absensi yang sudah ada</li>
                        <li>• Cuti yang diinput oleh admin akan ditandai dengan status khusus</li>
                        <li>• <strong>Data hak cuti di tabel hak_cuti_tahunan tidak berubah</strong> - hanya data penggunaan di tabel penggunaan_cuti_tahunan yang bertambah</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        feather.replace();
        
        // Variabel untuk melacak apakah admin sudah mengubah manual
        let isManualOverride = false;
        
        // Fungsi untuk menghitung hari otomatis
        function hitungHariOtomatis() {
            const mulai = document.getElementById('tanggal_mulai').value;
            const selesai = document.getElementById('tanggal_selesai').value;
            
            if (mulai && selesai) {
                const start = new Date(mulai);
                const end = new Date(selesai);
                
                // Validasi: tanggal selesai tidak boleh sebelum tanggal mulai
                if (end < start) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tanggal Tidak Valid',
                        text: 'Tanggal berakhir tidak boleh sebelum tanggal mulai',
                        confirmButtonText: 'OK'
                    });
                    document.getElementById('tanggal_selesai').value = '';
                    document.getElementById('lama_cuti').value = '1';
                    return;
                }
                
                // Hitung selisih hari
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                
                // Hanya update jika bukan manual override
                if (!isManualOverride) {
                    document.getElementById('lama_cuti').value = diffDays;
                    document.getElementById('lama_cuti').classList.remove('manual-override');
                }
                
                return diffDays;
            }
            return 0;
        }
        
        // Event listener untuk tanggal mulai
        document.getElementById('tanggal_mulai').addEventListener('change', function() {
            const mulai = this.value;
            const selesai = document.getElementById('tanggal_selesai');
            
            // Set min date untuk tanggal selesai
            if (selesai.value && new Date(selesai.value) < new Date(mulai)) {
                selesai.value = '';
                if (!isManualOverride) {
                    document.getElementById('lama_cuti').value = '1';
                }
            }
            selesai.min = mulai;
            
            // Hitung otomatis
            hitungHariOtomatis();
        });
        
        // Event listener untuk tanggal selesai
        document.getElementById('tanggal_selesai').addEventListener('change', function() {
            const mulai = document.getElementById('tanggal_mulai').value;
            const selesai = this.value;
            
            if (mulai && selesai) {
                // Hitung otomatis
                hitungHariOtomatis();
            }
        });
        
        // Event listener untuk tombol hitung ulang
        document.getElementById('hitungUlangBtn').addEventListener('click', function() {
            const hariOtomatis = hitungHariOtomatis();
            if (hariOtomatis > 0) {
                isManualOverride = false;
                document.getElementById('lama_cuti').classList.remove('manual-override');
                
                Swal.fire({
                    icon: 'success',
                    title: 'Perhitungan Diperbarui',
                    text: `Lama cuti dihitung ulang menjadi ${hariOtomatis} hari`,
                    confirmButtonText: 'OK',
                    timer: 2000
                });
            }
        });
        
        // Event listener untuk input manual lama cuti
        document.getElementById('lama_cuti').addEventListener('input', function() {
            isManualOverride = true;
            this.classList.add('manual-override');
        });
        
        // Event listener untuk klik di luar input lama cuti (jika ingin reset manual override)
        document.addEventListener('click', function(e) {
            const lamaCutiInput = document.getElementById('lama_cuti');
            if (!lamaCutiInput.contains(e.target) && !document.getElementById('hitungUlangBtn').contains(e.target)) {
                // Bisa tambahkan logika di sini jika perlu
            }
        });
        
        // Validasi form sebelum submit
        document.getElementById('cutiForm').addEventListener('submit', function(e) {
            const mulai = document.getElementById('tanggal_mulai').value;
            const selesai = document.getElementById('tanggal_selesai').value;
            const lama = document.getElementById('lama_cuti').value;
            const jenis = document.getElementById('jenis_cuti').value;
            
            // Validasi dasar
            if (!mulai || !selesai || !lama || !jenis) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Data Belum Lengkap',
                    text: 'Harap isi semua field yang wajib diisi',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Validasi tanggal
            const start = new Date(mulai);
            const end = new Date(selesai);
            
            if (end < start) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Tanggal Tidak Valid',
                    text: 'Tanggal berakhir tidak boleh sebelum tanggal mulai',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Validasi lama cuti
            if (lama < 1) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Lama Cuti Tidak Valid',
                    text: 'Lama cuti minimal 1 hari',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Hitung otomatis untuk perbandingan
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            // Jika manual override, tampilkan konfirmasi
            if (isManualOverride && parseInt(lama) !== diffDays) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Konfirmasi Input Manual',
                    html: `Anda telah mengubah lama cuti secara manual:<br>
                           <strong>${lama} hari</strong> (otomatis: ${diffDays} hari)<br><br>
                           Apakah ini untuk penyesuaian hari Sabtu/Minggu/libur?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#F9B000',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Kembali ke Hitungan Otomatis'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Submit form
                        document.getElementById('cutiForm').submit();
                    } else {
                        // Reset ke hitungan otomatis
                        document.getElementById('lama_cuti').value = diffDays;
                        isManualOverride = false;
                        document.getElementById('lama_cuti').classList.remove('manual-override');
                    }
                });
            }
        });
    </script>
</body>
</html>