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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pegawai = $_POST['id_pegawai'];
    $jenis_cuti = $_POST['jenis_cuti'];
    $lama_cuti = (int)$_POST['lama_cuti'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $alasan = $_POST['alasan'];
    
    // Validasi
    if (empty($id_pegawai) || empty($jenis_cuti) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($lama_cuti < 1) {
        $error = 'Lama cuti minimal 1 hari!';
    } else {
        try {
            $pdo->beginTransaction();
            
            // PERBAIKAN: Langsung gunakan lama_cuti dari input form (boleh intervensi manual)
            // Generate tanggal-tanggal cuti berdasarkan lama_cuti yang diinput
            $dates = [];
            $current = strtotime($tanggal_mulai);
            for ($i = 0; $i < $lama_cuti; $i++) {
                $dates[] = date('Y-m-d', $current);
                $current = strtotime('+1 day', $current);
                
                // Pastikan tidak melebihi tanggal selesai jika input manual
                if ($i < $lama_cuti - 1) {
                    $next_day = date('Y-m-d', $current);
                    if (strtotime($next_day) > strtotime($tanggal_selesai)) {
                        // Tambahkan 1 hari ke tanggal_selesai agar sesuai dengan lama_cuti
                        $tanggal_selesai = $next_day;
                    }
                }
            }
            
            // Validasi akhir tanggal berdasarkan jumlah hari
            $last_date = end($dates);
            if (strtotime($last_date) > strtotime($tanggal_selesai)) {
                // Update tanggal_selesai berdasarkan perhitungan
                $tanggal_selesai = $last_date;
            }
            
            // PERBAIKAN: Query yang benar untuk mengecek absensi - gunakan kolom yang benar
            foreach ($dates as $tanggal) {
                // Cek struktur tabel absensi dari file yang ada
                $stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
                $stmt->execute([$id_pegawai, $tanggal]);
                if ($stmt->fetch()) {
                    throw new Exception("Pegawai sudah memiliki absensi pada tanggal " . date('d/m/Y', strtotime($tanggal)));
                }
            }
            
            // PERBAIKAN: Insert absensi untuk setiap hari cuti
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
                    // Jika tidak ada kolom input_by_admin
                    $stmt = $pdo->prepare('
                        INSERT INTO absensi (id_pegawai, tanggal, status, jam_masuk, jam_keluar, lokasi_lat, lokasi_lng, catatan_admin) 
                        VALUES (?, ?, ?, NULL, NULL, NULL, NULL, ?)
                    ');
                }
                $stmt->execute([$id_pegawai, $tanggal, $jenis_cuti, $alasan]);
            }
            
            // Update sisa cuti tahunan jika jenis cuti tahunan
            if ($jenis_cuti == 'cuti_tahunan') {
                $tahun = date('Y', strtotime($tanggal_mulai));
                
                // Cek apakah sudah ada data sisa cuti
                $stmt = $pdo->prepare('SELECT id_sisa_cuti, sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
                $stmt->execute([$id_pegawai, $tahun]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Update sisa cuti
                    $sisa_baru = max(0, $result['sisa_cuti'] - $lama_cuti);
                    $stmt = $pdo->prepare('UPDATE sisa_cuti_tahunan SET sisa_cuti = ? WHERE id_sisa_cuti = ?');
                    $stmt->execute([$sisa_baru, $result['id_sisa_cuti']]);
                } else {
                    // Insert baru dengan default 12 hari dikurangi cuti yang diambil
                    $sisa_awal = max(0, 12 - $lama_cuti);
                    $stmt = $pdo->prepare('INSERT INTO sisa_cuti_tahunan (id_pegawai, tahun, sisa_cuti) VALUES (?, ?, ?)');
                    $stmt->execute([$id_pegawai, $tahun, $sisa_awal]);
                }
            }
            
            // Log input cuti (jika tabel log_input_cuti ada)
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
                // Jika tabel tidak ada, lanjutkan tanpa error
                error_log("Tabel log_input_cuti tidak ditemukan: " . $e->getMessage());
            }
            
            $pdo->commit();
            
            $success = 'Cuti berhasil diinput untuk ' . $lama_cuti . ' hari!';
            
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
                        <li>• Untuk cuti tahunan, sistem akan otomatis mengurangi sisa cuti tahunan pegawai</li>
                        <li>• Pastikan tanggal cuti tidak bentrok dengan absensi yang sudah ada</li>
                        <li>• Cuti yang diinput oleh admin akan ditandai dengan status khusus</li>
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