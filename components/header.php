<?php
/**
 * Komponen Header Website
 * Include file ini di setiap halaman untuk menampilkan header
 */

if (!isset($_SESSION['user'])) {
    return;
}

$user = $_SESSION['user'];
?>
<!-- Header -->
<header class="bg-[#F9B000] text-white shadow-lg no-print">
    <div class="container mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <img src="assets/logo.png" alt="Logo" class="w-12 h-12">
                <div>
                    <h1 class="text-xl font-bold">S I G M A</h1>
                    <p class="text-sm text-white">Sistem Informasi Geotagging untuk Monitoring Absensi - Kecamatan Ajibarang</p>
                </div>
            </div>
            <div class="text-right">
                <p class="font-semibold"><?= htmlspecialchars($user['nama']) ?></p>
                <p class="text-white/80 text-sm"><?= htmlspecialchars($user['jabatan']) ?></p>
            </div>
        </div>
    </div>
</header>