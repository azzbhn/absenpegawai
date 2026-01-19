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

// Tahun yang akan ditampilkan
$current_year = date('Y');
$years = [
    $current_year,
    $current_year - 1,
    $current_year - 2
];

// Fungsi untuk menghitung jumlah cuti tahunan yang diambil (dari tabel penggunaan_cuti_tahunan)
function getPenggunaanCutiTahunan($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('SELECT jumlah_hari FROM penggunaan_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['jumlah_hari'] : 0;
}

// Fungsi untuk mendapatkan hak cuti dari database (tabel hak_cuti_tahunan) - DIPERBAIKI
function getHakCutiFromDB($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('SELECT hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['hak_cuti'] : null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pegawai = $_POST['id_pegawai'];
    
    // Validasi
    if (empty($id_pegawai)) {
        $error = 'Pegawai wajib diisi!';
    } else {
        try {
            // Loop untuk setiap tahun
            foreach ($years as $tahun) {
                $field_name = 'hak_cuti_' . $tahun; // DIPERBAIKI: ubah dari sisa_cuti ke hak_cuti
                $hak_cuti = isset($_POST[$field_name]) ? (int)$_POST[$field_name] : 0;
                
                if ($hak_cuti < 0 || $hak_cuti > 12) {
                    throw new Exception("Hak cuti untuk tahun $tahun harus antara 0-12!");
                }
                
                // Cek apakah sudah ada data untuk pegawai di tahun tersebut - DIPERBAIKI
                $stmt = $pdo->prepare('SELECT id_sisa_cuti, hak_cuti FROM hak_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
                $stmt->execute([$id_pegawai, $tahun]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Update data yang sudah ada - DIPERBAIKI
                    $stmt = $pdo->prepare('UPDATE hak_cuti_tahunan SET hak_cuti = ? WHERE id_sisa_cuti = ?');
                    $stmt->execute([$hak_cuti, $result['id_sisa_cuti']]);
                } else {
                    // Insert data baru - DIPERBAIKI
                    $stmt = $pdo->prepare('INSERT INTO hak_cuti_tahunan (id_pegawai, tahun, hak_cuti) VALUES (?, ?, ?)');
                    $stmt->execute([$id_pegawai, $tahun, $hak_cuti]);
                }
            }
            
            $success = 'Hak cuti berhasil disimpan untuk 3 tahun terakhir!';
            
            // Reset form
            echo '<script>document.querySelector("form").reset();</script>';
            
        } catch (Exception $e) {
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
    <title>Input Hak Cuti Tahunan - Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .info-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid #0ea5e9;
        }
        .warning-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Input Hak Cuti Tahunan</h2>
            <p class="text-gray-600">Input atau perbarui hak cuti tahunan pegawai untuk 3 tahun terakhir</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
                        <i data-feather="alert-circle" class="w-5 h-5 mr-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                        <i data-feather="check-circle" class="w-5 h-5 mr-2"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
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

                    <!-- Input Hak Cuti untuk 3 Tahun -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($years as $index => $tahun): 
                            $label_class = '';
                            $description = '';
                            
                            if ($tahun == $current_year) {
                                $label_class = 'text-green-600 font-bold';
                                $description = 'Tahun berjalan (default 12)';
                            } elseif ($tahun == $current_year - 1) {
                                $label_class = 'text-yellow-600';
                                $description = 'Tahun sebelumnya';
                            } else {
                                $label_class = 'text-gray-600';
                                $description = 'Dua tahun lalu';
                            }
                        ?>
                        <div>
                            <label class="block text-sm font-medium <?= $label_class ?> mb-2">
                                <?php if ($tahun == $current_year): ?>
                                    <span class="flex items-center">
                                        <i data-feather="calendar" class="w-4 h-4 mr-1"></i>
                                        Tahun Ini (<?= $tahun ?>) *
                                    </span>
                                <?php elseif ($tahun == $current_year - 1): ?>
                                    <span class="flex items-center">
                                        <i data-feather="calendar" class="w-4 h-4 mr-1"></i>
                                        Tahun -1 (<?= $tahun ?>) 
                                    </span>
                                <?php else: ?>
                                    <span class="flex items-center">
                                        <i data-feather="calendar" class="w-4 h-4 mr-1"></i>
                                        Tahun -2 (<?= $tahun ?>)
                                    </span>
                                <?php endif; ?>
                            </label>
                            
                            <div class="flex items-center space-x-2">
                                <!-- DIPERBAIKI: ubah name dari sisa_cuti_ menjadi hak_cuti_ -->
                                <input type="number" name="hak_cuti_<?= $tahun ?>" min="0" max="12" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] hak-cuti-input"
                                       data-tahun="<?= $tahun ?>"
                                       placeholder="Otomatis terhitung"
                                       <?= $tahun == $current_year ? 'required' : '' ?>>
                                <span class="text-sm text-gray-600">hari</span>
                            </div>
                            
                            <p class="text-xs text-gray-500 mt-1">
                                <?= $description ?>
                                <?php if ($tahun != $current_year): ?>
                                    <span class="block mt-1 text-blue-600 font-medium" id="info_<?= $tahun ?>"></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex justify-end space-x-4 pt-4">
                        <a href="edit_sisacuti.php" 
                           class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center space-x-2">
                            <i data-feather="edit"></i>
                            <span>Edit Data Cuti</span>
                        </a>
                        <button type="submit" 
                                class="bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center space-x-2">
                            <i data-feather="save"></i>
                            <span>Simpan Hak Cuti</span>
                        </button>
                    </div>
                </form>

                <!-- Informasi Sistem Baru -->
                <div class="mt-8 p-4 info-box rounded-lg">
                    <h4 class="font-bold text-blue-800 mb-2 flex items-center">
                        <i data-feather="info" class="w-5 h-5 mr-2"></i>
                        Sistem Hak Cuti Baru:
                    </h4>
                    <ul class="text-blue-700 text-sm space-y-2">
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Hak Cuti</strong> disimpan di tabel <code>hak_cuti_tahunan</code> dan <strong>tidak berubah</strong> selama admin tidak melakukan intervensi</span>
                        </li>
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Penggunaan Cuti</strong> dicatat di tabel <code>penggunaan_cuti_tahunan</code> secara terpisah</span>
                        </li>
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Sisa Cuti = Hak Cuti - Penggunaan Cuti</strong> (dihitung otomatis)</span>
                        </li>
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Alokasi Pengambilan:</strong> Tahun Ini > Tahun Lalu > Tahun Dulu</span>
                        </li>
                    </ul>
                </div>

                <!-- Informasi Penting -->
                <div class="mt-6 p-4 warning-box rounded-lg">
                    <h4 class="font-bold text-yellow-800 mb-2 flex items-center">
                        <i data-feather="alert-circle" class="w-5 h-5 mr-2"></i>
                        Informasi Penting:
                    </h4>
                    <ul class="text-yellow-700 text-sm space-y-1">
                        <li>• Hak cuti tahunan maksimal 12 hari per tahun</li>
                        <li>• Data hak cuti akan digunakan sebagai acuan saat pegawai mengambil cuti tahunan</li>
                        <li>• Sistem akan otomatis mencatat penggunaan cuti di tabel terpisah</li>
                        <li>• Hak cuti tahun sebelumnya bisa digunakan di tahun berikutnya (maksimal 2 tahun)</li>
                        <li>• Input hak cuti untuk 3 tahun terakhir secara bersamaan</li>
                        <li>• Data hak cuti yang diinput admin akan tetap dan tidak berubah otomatis</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        feather.replace();
        
        // Fungsi untuk mendapatkan informasi cuti dari server
        function getInfoCutiTahunan(id_pegawai) {
            if (!id_pegawai) return;
            
            fetch(`ajax/get_cuti_tahunan.php?id_pegawai=${id_pegawai}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const tahunIni = <?= $current_year ?>;
                        const tahunLalu = <?= $current_year - 1 ?>;
                        const tahunDulu = <?= $current_year - 2 ?>;
                        
                        // Data hak cuti dari database jika ada - DIPERBAIKI: dari data.sisa_cuti menjadi data.hak_cuti
                        const hakCutiDB = data.hak_cuti || {};
                        
                        // Data cuti yang sudah diambil
                        const cutiDiambil = data.penggunaan_cuti || {};
                        
                        // Tahun Ini - Gunakan dari DB jika ada, jika tidak default 12
                        const inputTahunIni = document.querySelector('input[name="hak_cuti_' + tahunIni + '"]');
                        if (inputTahunIni) {
                            const dbValue = hakCutiDB[tahunIni];
                            inputTahunIni.value = dbValue !== undefined ? dbValue : 12;
                        }
                        
                        // Tahun -1
                        const inputTahunLalu = document.querySelector('input[name="hak_cuti_' + tahunLalu + '"]');
                        const infoTahunLalu = document.getElementById('info_' + tahunLalu);
                        
                        if (inputTahunLalu) {
                            const dbValue = hakCutiDB[tahunLalu];
                            inputTahunLalu.value = dbValue !== undefined ? dbValue : 0;
                            
                            // Tampilkan informasi
                            if (infoTahunLalu) {
                                const cutiDiambilTahunLalu = cutiDiambil[tahunLalu] || 0;
                                infoTahunLalu.innerHTML = `Cuti digunakan: ${cutiDiambilTahunLalu} hari`;
                            }
                        }
                        
                        // Tahun -2
                        const inputTahunDulu = document.querySelector('input[name="hak_cuti_' + tahunDulu + '"]');
                        const infoTahunDulu = document.getElementById('info_' + tahunDulu);
                        
                        if (inputTahunDulu) {
                            const dbValue = hakCutiDB[tahunDulu];
                            inputTahunDulu.value = dbValue !== undefined ? dbValue : 0;
                            
                            // Tampilkan informasi
                            if (infoTahunDulu) {
                                const cutiDiambilTahunDulu = cutiDiambil[tahunDulu] || 0;
                                infoTahunDulu.innerHTML = `Cuti digunakan: ${cutiDiambilTahunDulu} hari`;
                            }
                        }
                        
                    } else {
                        console.error('Error:', data.message);
                        // Set nilai default jika error
                        setDefaultValues();
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    // Set nilai default jika error
                    setDefaultValues();
                });
        }
        
        // Fungsi untuk set nilai default
        function setDefaultValues() {
            const tahunIni = <?= $current_year ?>;
            const tahunLalu = <?= $current_year - 1 ?>;
            const tahunDulu = <?= $current_year - 2 ?>;
            
            const inputTahunIni = document.querySelector('input[name="hak_cuti_' + tahunIni + '"]');
            if (inputTahunIni) inputTahunIni.value = 12;
            
            const inputTahunLalu = document.querySelector('input[name="hak_cuti_' + tahunLalu + '"]');
            if (inputTahunLalu) inputTahunLalu.value = 0;
            
            const inputTahunDulu = document.querySelector('input[name="hak_cuti_' + tahunDulu + '"]');
            if (inputTahunDulu) inputTahunDulu.value = 0;
        }
        
        // Event listener untuk dropdown pegawai
        document.getElementById('id_pegawai').addEventListener('change', function() {
            const pegawaiId = this.value;
            getInfoCutiTahunan(pegawaiId);
        });
        
        // Event listener untuk validasi input manual
        document.querySelectorAll('.hak-cuti-input').forEach(input => {
            input.addEventListener('change', function() {
                const value = parseInt(this.value);
                const tahun = this.dataset.tahun;
                
                if (value < 0 || value > 12) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Nilai Tidak Valid',
                        text: 'Hak cuti harus antara 0-12 hari',
                        confirmButtonText: 'OK'
                    });
                    this.value = Math.min(12, Math.max(0, value));
                }
                
                // Update info text untuk tahun lalu dan dulu
                if (tahun != <?= $current_year ?>) {
                    const infoElement = document.getElementById('info_' + tahun);
                    if (infoElement) {
                        infoElement.innerHTML = `Intervensi manual: ${this.value} hari`;
                    }
                }
            });
        });
        
        // Validasi form sebelum submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const idPegawai = document.getElementById('id_pegawai').value;
            if (!idPegawai) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Pegawai Belum Dipilih',
                    text: 'Silakan pilih pegawai terlebih dahulu',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Validasi semua input
            let valid = true;
            document.querySelectorAll('.hak-cuti-input').forEach(input => {
                const value = parseInt(input.value);
                if (isNaN(value) || value < 0 || value > 12) {
                    valid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Input Tidak Valid',
                    text: 'Pastikan semua nilai hak cuti antara 0-12 hari',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
</body>
</html>