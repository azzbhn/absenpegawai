<?php
/**
 * Komponen Header Website - VERSI MOBILE EFISIEN RUANG
 */

if (!isset($_SESSION['user'])) {
    return;
}

$user = $_SESSION['user'];
?>
<!-- Header -->
<header class="bg-gradient-to-r from-[#2563EB] to-[#1D4ED8] text-white shadow-lg no-print relative">
    <!-- Efek glow -->
    <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 to-purple-400/20 blur-xl"></div>
    
    <div class="container mx-auto px-3 md:px-4 py-2 md:py-4 relative">
        <div class="flex justify-between items-center">
            <!-- Logo dan Nama Aplikasi -->
            <div class="flex items-center space-x-2 md:space-x-4">
                <!-- Container untuk logo -->
                <div class="relative flex-shrink-0">
                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-400 to-purple-400 rounded-full blur opacity-30"></div>
                    <!-- Logo dengan fixed dimensions -->
                    <div class="relative w-12 h-12 md:w-14 md:h-14 flex items-center justify-center bg-white/10 rounded-full overflow-hidden">
                        <img src="assets/logo.png" alt="Logo" 
                             class="w-8 h-8 md:w-12 md:h-12 object-contain"
                             style="aspect-ratio: 1/1; min-width: 32px; min-height: 32px;">
                    </div>
                </div>
                
                <!-- Teks Aplikasi -->
                <div class="hidden md:block">
                    <h1 class="text-xl md:text-2xl font-bold tracking-tight">S I G M A</h1>
                    <p class="text-xs md:text-sm text-white/90">
                        Sistem Informasi Geotagging untuk Monitoring Absensi<br>
                        Kecamatan Ajibarang
                    </p>
                </div>
                
                <!-- Nama Aplikasi untuk Mobile (singkat) -->
                <div class="md:hidden">
                    <h1 class="text-lg font-bold tracking-tight">S I G M A</h1>
                    <p class="text-xs text-white/90 truncate max-w-[120px]">
                        Absensi Kec. Ajibarang
                    </p>
                </div>
            </div>
            
            <!-- Info User -->
            <!-- DESKTOP: Tampilan tombol seperti sebelumnya -->
            <div class="hidden md:block text-right">
                <div class="inline-block bg-white/10 backdrop-blur-sm px-4 py-2 rounded-xl border border-white/20">
                    <p class="font-semibold text-lg truncate max-w-[200px]"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="text-white/80 text-sm bg-blue-500/30 px-3 py-1 rounded-full inline-block mt-1">
                        <?= htmlspecialchars($user['jabatan']) ?>
                    </p>
                </div>
            </div>
            
            <!-- MOBILE: Tampilan teks efisien -->
            <div class="md:hidden text-right">
                <div class="text-sm">
                    <!-- Nama User -->
                    <p class="font-semibold truncate max-w-[140px] ml-auto" title="<?= htmlspecialchars($user['nama']) ?>">
                        <?= htmlspecialchars($user['nama']) ?>
                    </p>
                    <!-- Jabatan -->
                    <p class="text-white/80 text-xs truncate max-w-[140px] ml-auto">
                        <?= htmlspecialchars($user['jabatan']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
    /* Style untuk memastikan logo tetap proporsional */
    img[alt="Logo"] {
        object-fit: contain;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
    }
    
    /* Truncate text dengan ellipsis */
    .truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .container {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        
        /* Pastikan teks user rata kanan di mobile */
        .text-right {
            text-align: right;
        }
        
        .ml-auto {
            margin-left: auto;
        }
    }
</style>