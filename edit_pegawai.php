<?php
require_once 'config/db.php';
require_once 'config/jam_kerja.php';

// pastikan tabel jam kerja terbuat
ensureWorkHoursTable($pdo);

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$id_pegawai = $_GET['id'] ?? null;
if (!$id_pegawai) {
    header('Location: data_pegawai.php');
    exit;
}

$error = '';
$success = '';

// Ambil data pegawai saat ini
$stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id_pegawai = ?');
$stmt->execute([$id_pegawai]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    header('Location: data_pegawai.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $jabatan = $_POST['jabatan'];
    $no_whatsapp = $_POST['no_whatsapp'];
    $username = $_POST['username'];
    $status = $_POST['status'];
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Validasi
    if (empty($nama) || empty($nip) || empty($jabatan) || empty($username) || empty($status)) {
        $error = 'Semua field (kecuali password) wajib diisi!';
    } else {
        // Cek duplikasi NIP dan Username (jika berubah)
        $stmt = $pdo->prepare('SELECT id_pegawai FROM pegawai WHERE (nip = ? OR username = ?) AND id_pegawai != ?');
        $stmt->execute([$nip, $username, $id_pegawai]);
        if ($stmt->fetch()) {
            $error = 'NIP atau Username sudah digunakan oleh pegawai lain!';
        } else {
            try {
                // Siapkan query update dasar
                $sql = 'UPDATE pegawai SET nama = ?, nip = ?, jabatan = ?, no_whatsapp = ?, username = ?, status = ?';
                $params = [$nama, $nip, $jabatan, $no_whatsapp, $username, $status];

                // Jika password diisi, update juga passwordnya
                if (!empty($password)) {
                    if ($password !== $konfirmasi_password) {
                        throw new Exception('Password dan konfirmasi password tidak cocok!');
                    }
                    if (strlen($password) < 6) {
                        throw new Exception('Password minimal 6 karakter!');
                    }
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ', password = ?';
                    $params[] = $hashed_password;
                }

                $sql .= ' WHERE id_pegawai = ?';
                $params[] = $id_pegawai;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $success = 'Data pegawai berhasil diperbarui!';
                 // Re-fetch data terbaru
                $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id_pegawai = ?');
                $stmt->execute([$id_pegawai]);
                $pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pegawai - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>body { font-family: 'Poppins', sans-serif; }</style>
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
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Edit Data Pegawai</h2>
            <p class="text-gray-600">Perbarui data untuk pegawai: <strong><?= htmlspecialchars($pegawai['nama']) ?></strong></p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kolom Kiri -->
                    <div class="space-y-4">
                        <div>
                            <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" required value="<?= htmlspecialchars($pegawai['nama']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        </div>
                         <div>
                            <label for="nip" class="block text-sm font-medium text-gray-700 mb-2">NIP</label>
                            <input type="text" id="nip" name="nip" required value="<?= htmlspecialchars($pegawai['nip']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        </div>
                        <div>
                            <label for="jabatan" class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
                            <select id="jabatan" name="jabatan" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                                <option value="Administrator" <?= $pegawai['jabatan'] == 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                                <option value="Camat" <?= $pegawai['jabatan'] == 'Camat' ? 'selected' : '' ?>>Camat</option>
                                <option value="Sekretaris Kecamatan" <?= $pegawai['jabatan'] == 'Sekretaris Kecamatan' ? 'selected' : '' ?>>Sekretaris Kecamatan</option>
                                <option value="Staf" <?= $pegawai['jabatan'] == 'Staf' ? 'selected' : '' ?>>Staf</option>
                                <option value="Jaga Malam" <?= $pegawai['jabatan'] == 'Jaga Malam' ? 'selected' : '' ?>>Jaga Malam</option>
                            </select>
                        </div>
                         <div>
                            <label for="no_whatsapp" class="block text-sm font-medium text-gray-700 mb-2">Nomor WhatsApp</label>
                            <input type="text" id="no_whatsapp" name="no_whatsapp" value="<?= htmlspecialchars($pegawai['no_whatsapp']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        </div>
                         <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="status" name="status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                                <option value="Aktif" <?= $pegawai['status'] == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="Nonaktif" <?= $pegawai['status'] == 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Kolom Kanan -->
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" id="username" name="username" required value="<?= htmlspecialchars($pegawai['username']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password Baru (opsional)</label>
                            <input type="password" id="password" name="password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]"
                                   placeholder="Kosongkan jika tidak diubah">
                        </div>
                        <div>
                            <label for="konfirmasi_password" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password Baru</label>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]"
                                   placeholder="Ketik ulang password baru">
                        </div>
                         <div class="pt-4">
                            <button type="submit" class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                                <i data-feather="save"></i>
                                <span>Simpan Perubahan</span>
                            </button>
                        </div>
                        <div class="pt-2">
                             <a href="data_pegawai.php" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center space-x-2">
                                <i data-feather="arrow-left"></i>
                                <span>Kembali ke Data Pegawai</span>
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Informasi Jam Kerja -->
                <?php
                    // pilih konfigurasi berdasarkan jabatan dan hari
                    $is_jaga = ($pegawai['jabatan'] == 'Jaga Malam');
                    $is_friday = (date('N') == 5);
                    if ($is_jaga) {
                        $conf = getWorkHours($pdo, 'malam');
                    } else {
                        $conf = $is_friday ? getWorkHours($pdo, 'reguler_jumat') : getWorkHours($pdo, 'reguler');
                    }
                ?>
                <div class="mt-8 p-6 <?= $is_jaga ? 'bg-blue-50' : 'bg-green-50' ?> rounded-lg border <?= $is_jaga ? 'border-blue-200' : 'border-green-200' ?>">
                    <h4 class="font-semibold <?= $is_jaga ? 'text-blue-800' : 'text-green-800' ?> mb-3">Informasi Jam Kerja Saat Ini:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-700 mb-1">Jam Masuk</h5>
                            <p class="text-lg font-bold <?= $is_jaga ? 'text-blue-600' : 'text-green-600' ?>">
                                <?= htmlspecialchars(substr($conf['masuk_mulai'],0,5) ?? '-') ?> - <?= htmlspecialchars(substr($conf['masuk_selesai'],0,5) ?? '-') ?>
                            </p>
                        </div>
                        <div class="bg-white p-4 rounded-lg">
                            <h5 class="font-semibold text-gray-700 mb-1">Jam Pulang</h5>
                            <p class="text-lg font-bold <?= $is_jaga ? 'text-blue-600' : 'text-green-600' ?>">
                                <?= htmlspecialchars(substr($conf['pulang_mulai'],0,5) ?? '-') ?> - <?= htmlspecialchars(substr($conf['pulang_selesai'],0,5) ?? '-') ?>
                            </p>
                        </div>
                    </div>
                    <p class="<?= $is_jaga ? 'text-blue-700' : 'text-green-700' ?> text-sm mt-3">
                        <i data-feather="info" class="w-4 h-4 inline mr-1"></i>
                        <strong>Fleksibilitas:</strong> Absen masuk 1 jam sebelum & sesudah waktu, Absen pulang 4 jam setelah waktu.
                    </p>
                </div>
            </div>
        </div>
    </main>
    <script>
        feather.replace();
    </script>
</body>
</html>