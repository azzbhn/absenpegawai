<?php
require_once 'config/db.php';

// Pastikan hanya administrator yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$id_pegawai = $_GET['id'] ?? null;

if ($id_pegawai) {
    try {
        // Cek status pegawai saat ini
        $stmt = $pdo->prepare("SELECT status FROM pegawai WHERE id_pegawai = ?");
        $stmt->execute([$id_pegawai]);
        $pegawai = $stmt->fetch();

        if ($pegawai) {
            // Tentukan status baru
            $new_status = ($pegawai['status'] == 'Aktif') ? 'Nonaktif' : 'Aktif';

            // Update status di database
            $update_stmt = $pdo->prepare("UPDATE pegawai SET status = ? WHERE id_pegawai = ?");
            $update_stmt->execute([$new_status, $id_pegawai]);
            
            // Redirect kembali
            header('Location: data_pegawai.php?status=toggled');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: data_pegawai.php?status=error');
        exit;
    }
}

// Jika ID tidak ada atau pegawai tidak ditemukan, redirect kembali
header('Location: data_pegawai.php');
exit;
?>
