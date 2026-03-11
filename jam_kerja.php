<?php
require_once 'config/db.php';
require_once 'config/jam_kerja.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

// pastikan tabel & baris default ada
ensureWorkHoursTable($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    // data dikirim dalam bentuk arrays per shift
    foreach ($_POST['masuk_mulai'] as $shift => $val) {
        // kumpulan data untuk satu shift
        $data = [
            'masuk_mulai' => $_POST['masuk_mulai'][$shift] ?? null,
            'masuk_selesai' => $_POST['masuk_selesai'][$shift] ?? null,
            'pulang_mulai' => $_POST['pulang_mulai'][$shift] ?? null,
            'pulang_selesai' => $_POST['pulang_selesai'][$shift] ?? null,
        ];
        updateWorkHours($pdo, $shift, $data);
    }
    $message = 'Jam kerja berhasil disimpan.';
}

// ambil konfigurasi saat ini
$shifts = ['reguler' => 'Pegawai Reguler (Senin-Kamis)',
            'reguler_jumat' => 'Pegawai Reguler (Jumat)',
            'malam' => 'Pegawai Jaga Malam'];
$hours = [];
foreach ($shifts as $key => $label) {
    $hours[$key] = getWorkHours($pdo, $key);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Jam Kerja - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Konfigurasi Jam Kerja</h2>
            <p class="text-gray-600">Atur jam masuk dan jam pulang untuk setiap jenis shift. Semua pengguna akan mengikuti nilai ini saat melakukan absensi.</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="space-y-8">
                <?php foreach ($shifts as $key => $label): ?>
                    <?php $h = $hours[$key]; ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= $label ?></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Masuk Mulai</label>
                                <input type="time" name="masuk_mulai[<?= $key ?>]" value="<?= htmlspecialchars($h['masuk_mulai'] ?? '') ?>" class="w-full px-3 py-2 border rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Masuk Selesai</label>
                                <input type="time" name="masuk_selesai[<?= $key ?>]" value="<?= htmlspecialchars($h['masuk_selesai'] ?? '') ?>" class="w-full px-3 py-2 border rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Pulang Mulai</label>
                                <input type="time" name="pulang_mulai[<?= $key ?>]" value="<?= htmlspecialchars($h['pulang_mulai'] ?? '') ?>" class="w-full px-3 py-2 border rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Pulang Selesai</label>
                                <input type="time" name="pulang_selesai[<?= $key ?>]" value="<?= htmlspecialchars($h['pulang_selesai'] ?? '') ?>" class="w-full px-3 py-2 border rounded">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6">
                <button type="submit" name="save" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded">Simpan</button>
            </div>
        </form>
    </main>
    <script> feather.replace(); </script>
</body>
</html>
