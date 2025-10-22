<?php
require_once 'config/db.php';

// Pastikan hanya administrator yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    // Jika bukan admin, bisa redirect ke halaman login atau dashboard biasa
    header('Location: index.php');
    exit;
}

// Ambil ID dari URL
$id_pegawai = $_GET['id'] ?? null;

if ($id_pegawai) {
    try {
        // Amankan agar tidak menghapus diri sendiri
        if ($id_pegawai == $_SESSION['user']['id_pegawai']) {
             // Sebaiknya berikan pesan error, tapi untuk simpelnya kita redirect saja
             header('Location: data_pegawai.php?error=selfdelete');
             exit;
        }

        // Buat prepared statement untuk menghapus
        $stmt = $pdo->prepare('DELETE FROM pegawai WHERE id_pegawai = ?');
        $stmt->execute([$id_pegawai]);

        // Redirect kembali ke halaman data pegawai dengan pesan sukses
        header('Location: data_pegawai.php?status=deleted');
        exit;

    } catch (PDOException $e) {
        // Jika terjadi error, redirect dengan pesan error
        // Di aplikasi production, sebaiknya error ini di-log, bukan ditampilkan ke user
        header('Location: data_pegawai.php?status=error');
        exit;
    }
} else {
    // Jika tidak ada ID, redirect saja
    header('Location: data_pegawai.php');
    exit;
}
?>
