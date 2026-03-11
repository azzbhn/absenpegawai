<?php
/**
 * Helper untuk jam kerja (reguler / malam / jumat)
 *
 * Fungsi ini mengelola konfigurasi jam kerja yang dapat diedit
 * oleh administrator melalui halaman "Jam Kerja".
 *
 * Tabel yang digunakan:
 *   jam_kerja (
 *       id INT AUTO_INCREMENT PRIMARY KEY,
 *       shift VARCHAR(30) UNIQUE NOT NULL,
 *       masuk_mulai TIME NULL,
 *       masuk_selesai TIME NULL,
 *       pulang_mulai TIME NULL,
 *       pulang_selesai TIME NULL
 *   )
 *
 * Shift yang dipakai oleh sistem:
 *   - reguler      (hari biasa)
 *   - reguler_jumat (hari Jumat)
 *   - malam        (pegawai jaga malam)
 */

function ensureWorkHoursTable(PDO $pdo) {
    // buat tabel jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS jam_kerja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift VARCHAR(30) UNIQUE NOT NULL,
        masuk_mulai TIME NULL,
        masuk_selesai TIME NULL,
        pulang_mulai TIME NULL,
        pulang_selesai TIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // defaults yang sudah ada di aplikasi sebelumnya
    $defaults = [
        'reguler' => [
            'masuk_mulai' => '06:15:00',
            'masuk_selesai' => '08:15:00',
            'pulang_mulai' => '14:30:00',
            'pulang_selesai' => '19:30:00',
        ],
        'reguler_jumat' => [
            'masuk_mulai' => '06:15:00',
            'masuk_selesai' => '08:15:00',
            // Jumat mulai 14:15 (1 jam lebih awal) sampai 19:15
            'pulang_mulai' => '14:15:00',
            'pulang_selesai' => '19:15:00',
        ],
        'malam' => [
            'masuk_mulai' => '14:30:00',
            'masuk_selesai' => '18:30:00',
            'pulang_mulai' => '00:00:00',
            'pulang_selesai' => '10:00:00',
        ],
    ];

    // pastikan setiap shift ada dalam tabel
    foreach ($defaults as $shift => $times) {
        $stmt = $pdo->prepare("SELECT id FROM jam_kerja WHERE shift = ?");
        $stmt->execute([$shift]);
        if ($stmt->rowCount() == 0) {
            $ins = $pdo->prepare("INSERT INTO jam_kerja (shift, masuk_mulai, masuk_selesai, pulang_mulai, pulang_selesai)
                VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$shift, $times['masuk_mulai'], $times['masuk_selesai'], $times['pulang_mulai'], $times['pulang_selesai']]);
        }
    }
}

function getWorkHours(PDO $pdo, $shift) {
    $stmt = $pdo->prepare("SELECT * FROM jam_kerja WHERE shift = ? LIMIT 1");
    $stmt->execute([$shift]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function updateWorkHours(PDO $pdo, $shift, $data) {
    $fields = [];
    $params = [];
    foreach (['masuk_mulai','masuk_selesai','pulang_mulai','pulang_selesai'] as $col) {
        if (isset($data[$col])) {
            $fields[] = "$col = ?";
            $params[] = $data[$col] ?: null;
        }
    }
    if (empty($fields)) return;
    $params[] = $shift;
    $sql = "UPDATE jam_kerja SET " . implode(', ', $fields) . " WHERE shift = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
