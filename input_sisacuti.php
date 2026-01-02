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

// Fungsi untuk menghitung jumlah cuti tahunan yang diambil
function getJumlahCutiTahunan($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as jumlah 
        FROM absensi 
        WHERE id_pegawai = ? 
        AND YEAR(tanggal) = ? 
        AND status = "cuti_tahunan"
    ');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['jumlah'] : 0;
}

// Fungsi untuk mendapatkan sisa cuti dari database
function getSisaCutiFromDB($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('SELECT sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['sisa_cuti'] : null;
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
                $field_name = 'sisa_cuti_' . $tahun;
                $sisa_cuti = isset($_POST[$field_name]) ? (int)$_POST[$field_name] : 0;
                
                if ($sisa_cuti < 0 || $sisa_cuti > 12) {
                    throw new Exception("Sisa cuti untuk tahun $tahun harus antara 0-12!");
                }
                
                // Cek apakah sudah ada data untuk pegawai di tahun tersebut
                $stmt = $pdo->prepare('SELECT id_sisa_cuti, sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
                $stmt->execute([$id_pegawai, $tahun]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Update data yang sudah ada
                    $stmt = $pdo->prepare('UPDATE sisa_cuti_tahunan SET sisa_cuti = ? WHERE id_sisa_cuti = ?');
                    $stmt->execute([$sisa_cuti, $result['id_sisa_cuti']]);
                } else {
                    // Insert data baru
                    $stmt = $pdo->prepare('INSERT INTO sisa_cuti_tahunan (id_pegawai, tahun, sisa_cuti) VALUES (?, ?, ?)');
                    $stmt->execute([$id_pegawai, $tahun, $sisa_cuti]);
                }
            }
            
            $success = 'Sisa cuti berhasil disimpan untuk 3 tahun terakhir!';
            
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
    <title>Input Sisa Cuti Tahunan - Kecamatan Ajibarang</title>
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Input Sisa Cuti Tahunan</h2>
            <p class="text-gray-600">Input atau perbarui sisa cuti tahunan pegawai untuk 3 tahun terakhir</p>
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

                    <!-- Input Sisa Cuti untuk 3 Tahun -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($years as $index => $tahun): 
                            $label_class = '';
                            $description = '';
                            
                            if ($tahun == $current_year) {
                                $label_class = 'text-green-600 font-bold';
                                $description = 'Tahun berjalan (default 12)';
                            } elseif ($tahun == $current_year - 1) {
                                $label_class = 'text-yellow-600';
                                $description = 'Tahun sebelumnya (otomatis dihitung)';
                            } else {
                                $label_class = 'text-gray-600';
                                $description = 'Dua tahun lalu (otomatis dihitung)';
                            }
                        ?>
                        <div>
                            <label class="block text-sm font-medium <?= $label_class ?> mb-2">
                                <?php if ($tahun == $current_year): ?>
                                    <span class="flex items-center">
                                        <i data-feather="calendar" class="w-4 h-4 mr-1"></i>
                                        Tahun Ini (<?= $tahun ?>)
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
                            
                            <?php if ($tahun == $current_year): ?>
                                <!-- Tahun ini: Dropdown 0-12 -->
                                <select name="sisa_cuti_<?= $tahun ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] tahun-ini-select">
                                    <?php for ($i = 0; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i == 12 ? 'selected' : '' ?>><?= $i ?> hari</option>
                                    <?php endfor; ?>
                                </select>
                            <?php else: ?>
                                <!-- Tahun -1 dan -2: Input number (admin bisa intervensi) -->
                                <input type="number" name="sisa_cuti_<?= $tahun ?>" min="0" max="12" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000] sisa-cuti-input"
                                       data-tahun="<?= $tahun ?>"
                                       placeholder="Otomatis terhitung">
                            <?php endif; ?>
                            
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
                            <span>Simpan Sisa Cuti</span>
                        </button>
                    </div>
                </form>

                <!-- Informasi Perhitungan Otomatis -->
                <div class="mt-8 p-4 info-box rounded-lg">
                    <h4 class="font-bold text-blue-800 mb-2 flex items-center">
                        <i data-feather="info" class="w-5 h-5 mr-2"></i>
                        Sistem Perhitungan Otomatis:
                    </h4>
                    <ul class="text-blue-700 text-sm space-y-2">
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Tahun Ini (<?= $current_year ?>):</strong> Default 12 hari, admin dapat mengubah melalui dropdown 0-12</span>
                        </li>
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Tahun -1 (<?= $current_year - 1 ?>):</strong> 
                                <ul class="ml-4 mt-1 space-y-1">
                                    <li>• Jika cuti diambil ≤ 6 hari → Sisa cuti = 6 hari</li>
                                    <li>• Jika cuti diambil > 6 hari → Sisa cuti = 12 - (cuti diambil)</li>
                                    <li>• Admin dapat melakukan intervensi manual</li>
                                </ul>
                            </span>
                        </li>
                        <li class="flex items-start">
                            <i data-feather="check-circle" class="w-4 h-4 mr-2 mt-0.5 text-green-500"></i>
                            <span><strong>Tahun -2 (<?= $current_year - 2 ?>):</strong> 
                                <ul class="ml-4 mt-1 space-y-1">
                                    <li>• Jika TIDAK ambil cuti di tahun -1 DAN tahun -2 → Sisa cuti = 6 hari</li>
                                    <li>• Jika ambil cuti di salah satu tahun → Sisa cuti = 0 hari</li>
                                    <li>• Admin dapat melakukan intervensi manual</li>
                                </ul>
                            </span>
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
                        <li>• Sisa cuti tahunan maksimal 12 hari per tahun</li>
                        <li>• Data sisa cuti akan digunakan saat pegawai mengajukan cuti tahunan</li>
                        <li>• Sistem akan otomatis mengurangi sisa cuti saat cuti tahunan disetujui</li>
                        <li>• Sisa cuti tahun sebelumnya bisa dibawa ke tahun berikutnya (maksimal 2 tahun)</li>
                        <li>• Input sisa cuti untuk 3 tahun terakhir secara bersamaan</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        feather.replace();
        
        // Fungsi untuk menghitung sisa cuti otomatis
        function hitungSisaCutiOtomatis(id_pegawai) {
            if (!id_pegawai) return;
            
            // Ambil data cuti tahunan dari server
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
                        
                        // Data dari database jika ada
                        const sisaCutiDB = data.sisa_cuti || {};
                        
                        // Tahun Ini - Gunakan dari DB jika ada, jika tidak default 12
                        const selectTahunIni = document.querySelector('select[name="sisa_cuti_' + tahunIni + '"]');
                        if (selectTahunIni) {
                            const dbValue = sisaCutiDB[tahunIni];
                            selectTahunIni.value = dbValue !== undefined ? dbValue : 12;
                        }
                        
                        // Tahun -1: Logika perhitungan otomatis
                        const cutiTahunLalu = data.cuti_tahunan[tahunLalu] || 0;
                        let sisaCutiTahunLalu = 6; // Default jika ≤ 6 hari
                        
                        if (cutiTahunLalu > 6) {
                            sisaCutiTahunLalu = 12 - cutiTahunLalu;
                            if (sisaCutiTahunLalu < 0) sisaCutiTahunLalu = 0;
                        }
                        
                        // Gunakan dari DB jika ada, jika tidak gunakan perhitungan otomatis
                        const inputTahunLalu = document.querySelector('input[name="sisa_cuti_' + tahunLalu + '"]');
                        const infoTahunLalu = document.getElementById('info_' + tahunLalu);
                        
                        if (inputTahunLalu) {
                            const dbValue = sisaCutiDB[tahunLalu];
                            inputTahunLalu.value = dbValue !== undefined ? dbValue : sisaCutiTahunLalu;
                            
                            // Tampilkan informasi
                            if (infoTahunLalu) {
                                infoTahunLalu.innerHTML = `Cuti diambil: ${cutiTahunLalu} hari`;
                            }
                        }
                        
                        // Tahun -2: Logika perhitungan otomatis
                        const cutiTahunDulu = data.cuti_tahunan[tahunDulu] || 0;
                        let sisaCutiTahunDulu = 0; // Default jika pernah ambil cuti di salah satu tahun
                        
                        // Cek apakah TIDAK ambil cuti di tahun -1 DAN tahun -2
                        if (cutiTahunLalu === 0 && cutiTahunDulu === 0) {
                            sisaCutiTahunDulu = 6;
                        }
                        
                        // Gunakan dari DB jika ada, jika tidak gunakan perhitungan otomatis
                        const inputTahunDulu = document.querySelector('input[name="sisa_cuti_' + tahunDulu + '"]');
                        const infoTahunDulu = document.getElementById('info_' + tahunDulu);
                        
                        if (inputTahunDulu) {
                            const dbValue = sisaCutiDB[tahunDulu];
                            inputTahunDulu.value = dbValue !== undefined ? dbValue : sisaCutiTahunDulu;
                            
                            // Tampilkan informasi
                            if (infoTahunDulu) {
                                const statusCuti = (cutiTahunLalu === 0 && cutiTahunDulu === 0) ? 
                                    'Tidak ambil cuti' : 'Pernah ambil cuti';
                                infoTahunDulu.innerHTML = `Status: ${statusCuti}`;
                            }
                        }
                        
                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    // Fallback: set nilai default
                    const tahunIni = <?= $current_year ?>;
                    const tahunLalu = <?= $current_year - 1 ?>;
                    const tahunDulu = <?= $current_year - 2 ?>;
                    
                    const selectTahunIni = document.querySelector('select[name="sisa_cuti_' + tahunIni + '"]');
                    if (selectTahunIni) selectTahunIni.value = 12;
                    
                    const inputTahunLalu = document.querySelector('input[name="sisa_cuti_' + tahunLalu + '"]');
                    if (inputTahunLalu) inputTahunLalu.value = 6;
                    
                    const inputTahunDulu = document.querySelector('input[name="sisa_cuti_' + tahunDulu + '"]');
                    if (inputTahunDulu) inputTahunDulu.value = 0;
                });
        }
        
        // Event listener untuk dropdown pegawai
        document.getElementById('id_pegawai').addEventListener('change', function() {
            const pegawaiId = this.value;
            hitungSisaCutiOtomatis(pegawaiId);
        });
        
        // Event listener untuk validasi input manual
        document.querySelectorAll('.sisa-cuti-input').forEach(input => {
            input.addEventListener('change', function() {
                const value = parseInt(this.value);
                const tahun = this.dataset.tahun;
                
                if (value < 0 || value > 12) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Nilai Tidak Valid',
                        text: 'Sisa cuti harus antara 0-12 hari',
                        confirmButtonText: 'OK'
                    });
                    this.value = Math.min(12, Math.max(0, value));
                }
                
                // Update info text
                const infoElement = document.getElementById('info_' + tahun);
                if (infoElement) {
                    infoElement.innerHTML = `Intervensi manual: ${this.value} hari`;
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
            document.querySelectorAll('.sisa-cuti-input, .tahun-ini-select').forEach(input => {
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
                    text: 'Pastikan semua nilai sisa cuti antara 0-12 hari',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
</body>
</html>