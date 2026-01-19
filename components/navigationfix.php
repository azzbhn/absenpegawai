<?php
/**
 * Komponen Navigasi Dinamis
 * Include file ini di setiap halaman untuk menampilkan navigasi
 */

if (!isset($_SESSION['user'])) {
    return;
}

$user = $_SESSION['user'];
require_once 'config/menu_config.php';

// Get menus berdasarkan jabatan user
$mainMenus = MenuConfig::sortMenus(MenuConfig::getMainNavMenus($user['jabatan']));
$rightMenus = MenuConfig::sortMenus(MenuConfig::getRightNavMenus($user['jabatan']));

// Untuk tampilan mobile, kita perlu mendapatkan semua menu termasuk submenu
$allMenusForMobile = array_merge($mainMenus, $rightMenus);

// Fungsi untuk mendapatkan warna berdasarkan menu
function getMenuColor($menuTitle) {
    $colors = [
        'Dashboard' => 'bg-gradient-to-r from-blue-500 to-cyan-400 hover:from-blue-600 hover:to-cyan-500 shadow-blue-500/30',
        'Absensi' => 'bg-gradient-to-r from-emerald-500 to-green-400 hover:from-emerald-600 hover:to-green-500 shadow-emerald-500/30',
        'Kendali Cuti' => 'bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-600 hover:to-yellow-500 shadow-amber-500/30',
        'Pengajuan Cuti' => 'bg-gradient-to-r from-orange-500 to-amber-400 hover:from-orange-600 hover:to-amber-500 shadow-orange-500/30',
        'Admin' => 'bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 shadow-cyan-500/30',
        'Data Absensi' => 'bg-gradient-to-r from-purple-500 to-violet-400 hover:from-purple-600 hover:to-violet-500 shadow-purple-500/30',
        'Input Cuti' => 'bg-gradient-to-r from-amber-500 to-orange-400 hover:from-amber-600 hover:to-orange-500 shadow-amber-500/30',
        'Persetujuan Cuti' => 'bg-gradient-to-r from-green-500 to-emerald-400 hover:from-green-600 hover:to-emerald-500 shadow-green-500/30',
        'Tambah Pegawai' => 'bg-gradient-to-r from-pink-500 to-rose-400 hover:from-pink-600 hover:to-rose-500 shadow-pink-500/30',
        'Data Pegawai' => 'bg-gradient-to-r from-indigo-500 to-blue-400 hover:from-indigo-600 hover:to-blue-500 shadow-indigo-500/30',
        'Input Sisa Cuti Tahunan' => 'bg-gradient-to-r from-teal-500 to-emerald-400 hover:from-teal-600 hover:to-emerald-500 shadow-teal-500/30',
        'Edit Sisa Cuti Tahunan' => 'bg-gradient-to-r from-yellow-500 to-amber-400 hover:from-yellow-600 hover:to-amber-500 shadow-yellow-500/30',
        'Data Cuti Pegawai' => 'bg-gradient-to-r from-emerald-500 to-green-400 hover:from-emerald-600 hover:to-green-500 shadow-emerald-500/30',
        'Ganti Password' => 'bg-gradient-to-r from-slate-500 to-gray-400 hover:from-slate-600 hover:to-gray-500 shadow-slate-500/30',
        'Logout' => 'bg-gradient-to-r from-rose-500 to-pink-400 hover:from-rose-600 hover:to-pink-500 shadow-rose-500/30',
    ];
    
    foreach ($colors as $key => $color) {
        if (stripos($menuTitle, $key) !== false) {
            return $color;
        }
    }
    
    return 'bg-gradient-to-r from-[#0EA5E9] to-[#0284C7] hover:from-[#0284C7] hover:to-[#0369A1] shadow-blue-500/30';
}

// Fungsi untuk merender dropdown menu (desktop) - KEMBALI KE HOVER
function renderDropdownMenu($menu, $jabatan) {
    $submenus = MenuConfig::sortMenus(MenuConfig::getSubmenus($menu['id'], $jabatan));
    
    // Menggunakan class 'group' untuk menangani hover melalui Tailwind
    $html = '<div class="relative group">';
    $html .= '<button class="flex items-center space-x-2 py-2.5 px-4 hover:bg-white/10 rounded-lg transition-all duration-200 focus:outline-none transform hover:scale-105 shadow-sm hover:shadow-md backdrop-blur-sm bg-white/5 border border-white/10">';
    $html .= '<i data-feather="' . $menu['icon'] . '" class="group-hover:rotate-12 transition-transform duration-200"></i>';
    $html .= '<span class="font-medium">' . $menu['title'] . '</span>';
    $html .= '<svg class="w-4 h-4 ml-1 transition-transform duration-300 group-hover:rotate-180 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    $html .= '</button>';
    
    // Dropdown content: muncul saat parent (group) di-hover
    $html .= '<div class="absolute left-0 mt-2 w-56 bg-gradient-to-br from-white/95 to-white/90 backdrop-blur-lg text-gray-900 rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-hover:translate-y-0 transform transition-all duration-300 z-50 overflow-hidden border border-white/20">';
    $html .= '<div class="py-2">';
    
    // Render submenu dari config
    foreach ($submenus as $index => $submenu) {
        $colorClass = getMenuColor($submenu['title']);
        $baseColor = 'blue'; // default
        
        if (preg_match('/from-([a-z]+)-500/', $colorClass, $matches)) {
            $baseColor = $matches[1];
        }
        
        $html .= '<a href="' . $submenu['url'] . '" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-100/80 transition-all duration-200 group/item">';
        $html .= '<div class="p-2 rounded-lg bg-' . $baseColor . '-100 group-hover/item:bg-' . $baseColor . '-200 transition-colors duration-200">';
        $html .= '<i data-feather="' . $submenu['icon'] . '" class="w-4 h-4 text-' . $baseColor . '-600 group-hover/item:scale-110 transition-transform duration-200"></i>';
        $html .= '</div>';
        $html .= '<span class="group-hover/item:translate-x-1 transition-transform duration-200 font-medium">' . $submenu['title'] . '</span>';
        $html .= '</a>';
    }
    
    $html .= '</div></div></div>';
    return $html;
}

function renderMobileMenuItems($menus, $jabatan) {
    $html = '';
    foreach ($menus as $menu) {
        $colorClass = getMenuColor($menu['title']);
        $icon = isset($menu['icon']) ? '<i data-feather="' . $menu['icon'] . '"></i>' : '';
        $hasSubmenu = isset($menu['has_submenu']) && $menu['has_submenu'];
        
        if ($hasSubmenu) {
            $submenus = MenuConfig::sortMenus(MenuConfig::getSubmenus($menu['id'], $jabatan));
            foreach ($submenus as $submenu) {
                $subColorClass = getMenuColor($submenu['title']);
                $subIcon = isset($submenu['icon']) ? '<i data-feather="' . $submenu['icon'] . '"></i>' : '';
                $html .= '<a href="' . $submenu['url'] . '" class="' . $subColorClass . ' text-white text-center py-4 px-4 rounded-2xl font-semibold transition-all duration-300 transform hover:scale-105 active:scale-95 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl border border-white/30">';
                $html .= $subIcon . '<span class="text-sm font-medium">' . $submenu['title'] . '</span></a>';
            }
        } else {
            $html .= '<a href="' . $menu['url'] . '" class="' . $colorClass . ' text-white text-center py-4 px-4 rounded-2xl font-semibold transition-all duration-300 transform hover:scale-105 active:scale-95 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl border border-white/30">';
            $html .= $icon . '<span class="text-sm font-medium">' . $menu['title'] . '</span></a>';
        }
    }
    return $html;
}

// ============================ RUNNING TEXT UNTUK SERAGAM KERJA ============================
$pesan_seragam = "Informasi seragam sedang tidak tersedia.";

try {
    // Include koneksi database
    require_once 'config/db.php'; // File db.php sudah ada
    
    // Set timezone ke WIB
    date_default_timezone_set('Asia/Jakarta');
    
    $sekarang = time();
    $jam_sekarang = date('H:i', $sekarang);
    $tanggal_besok = date('Y-m-d', strtotime('+1 day', $sekarang));
    $tanggal_hari_ini = date('Y-m-d', $sekarang);
    
    // Tentukan tanggal query berdasarkan waktu
    if ($jam_sekarang >= '14:30' && $jam_sekarang <= '23:59') {
        $tanggal_query = $tanggal_besok;
        $prefix = "Seragam untuk besok, hari ";
    } else {
        $tanggal_query = $tanggal_hari_ini;
        $prefix = "Seragam untuk hari ini ";
    }
    
    // Query database menggunakan PDO
    $sql = "SELECT jenis_seragam FROM seragam_kerja WHERE tanggal = :tanggal";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tanggal', $tanggal_query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $jenis_seragam = $row['jenis_seragam'];
        
        // Format tanggal Indonesia
        $hari_inggris = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $hari_indonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan_inggris = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $bulan_indonesia = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        $nama_hari = $hari_indonesia[date('w', strtotime($tanggal_query))];
        $tanggal_format = date('d', strtotime($tanggal_query));
        $nama_bulan = $bulan_indonesia[date('n', strtotime($tanggal_query)) - 1];
        $tahun = date('Y', strtotime($tanggal_query));
        
        $pesan_seragam = $prefix . $nama_hari . ", " . $tanggal_format . " " . $nama_bulan . " " . $tahun . " adalah <strong>" . $jenis_seragam . "</strong>";
    } else {
        $pesan_seragam = "Tidak ada informasi seragam untuk tanggal tersebut.";
    }
    
} catch (Exception $e) {
    // Fallback jika ada error
    $pesan_seragam = "Informasi seragam sedang tidak tersedia.";
}
?>

<!-- ============================ NAVIGASI UTAMA ============================ -->
<nav class="bg-gradient-to-r from-[#0EA5E9] to-[#0284C7] text-white shadow-xl no-print relative z-40">
    <!-- Efek glass morphism -->
    <div class="absolute inset-0 bg-gradient-to-r from-white/10 to-white/5 backdrop-blur-sm"></div>
    
    <div class="container mx-auto px-4 py-3 relative">
        <div class="flex items-center justify-between">
            <!-- Desktop Menu -->
            <div class="hidden md:flex md:space-x-2 flex-col md:flex-row items-center w-full">
                <?php foreach ($mainMenus as $menu): ?>
                    <?php if (isset($menu['has_submenu']) && $menu['has_submenu']): ?>
                        <?= renderDropdownMenu($menu, $user['jabatan']) ?>
                    <?php else: ?>
                        <?php $colorClass = getMenuColor($menu['title']); ?>
                        <a href="<?= $menu['url'] ?>" class="<?= $colorClass ?> text-white py-2.5 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 flex items-center space-x-2 shadow-md hover:shadow-lg backdrop-blur-sm border border-white/20">
                            <i data-feather="<?= $menu['icon'] ?>" class="hover:rotate-12 transition-transform duration-200"></i>
                            <span class="font-medium"><?= $menu['title'] ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="flex items-center ml-auto space-x-2">
                    <?php foreach ($rightMenus as $menu): ?>
                        <?php $colorClass = getMenuColor($menu['title']); ?>
                        <a href="<?= $menu['url'] ?>" class="<?= $colorClass ?> text-white py-2.5 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 flex items-center space-x-2 shadow-md hover:shadow-lg backdrop-blur-sm border border-white/20">
                            <i data-feather="<?= $menu['icon'] ?>" class="hover:rotate-12 transition-transform duration-200"></i>
                            <span class="font-medium"><?= $menu['title'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div class="md:hidden w-full">
                <div class="grid grid-cols-4 gap-3">
                    <?= renderMobileMenuItems($allMenusForMobile, $user['jabatan']) ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ============================ RUNNING TEXT SERAGAM ============================ -->
<div class="bg-gradient-to-r from-[#1E40AF] to-[#1E3A8A] text-white py-3 border-t border-blue-300/20 shadow-inner relative overflow-hidden">
    <!-- Efek animasi background -->
    <div class="absolute inset-0">
        <div class="absolute -top-24 -left-24 w-48 h-48 bg-blue-400/10 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-purple-400/10 rounded-full blur-2xl"></div>
    </div>
    
    <div class="container mx-auto px-4 relative">
        <div class="flex items-center justify-center md:justify-start">
            <!-- Badge info -->
            <div class="bg-gradient-to-r from-yellow-400 to-amber-400 text-blue-900 font-bold px-2 py-2 rounded-full mr-2 animate-pulse shadow-lg flex items-center space-x-2">
                <i data-feather="bell" class="w-4 h-4"></i>
                <span class="text-sm">SERAGAM</span>
            </div>
            
            <!-- Running text container -->
            <div class="flex-1 overflow-hidden">
                <div class="inline-flex items-center space-x-8 animate-marquee whitespace-nowrap">
                    <span class="text-sm md:text-base"><?= $pesan_seragam ?></span>
                    <!-- Ikon dekoratif -->
                    <i data-feather="shirt" class="w-5 h-5 text-amber-300 hidden md:inline"></i>
                    <span class="text-sm md:text-base"><?= $pesan_seragam ?></span>
                    <i data-feather="shirt" class="w-5 h-5 text-amber-300 hidden md:inline"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Animasi marquee untuk running text */
    @keyframes marquee {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(-50%);
        }
    }
    
    .animate-marquee {
        animation: marquee 30s linear infinite;
        display: inline-flex;
    }
    
    /* Efek hover untuk semua tombol */
    a, button {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .animate-marquee {
            animation: marquee 20s linear infinite;
        }
        
        .grid-cols-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        
        /* Adjust untuk tampilan mobile lebih rapi */
        nav a {
            font-size: 0.75rem;
            padding: 0.75rem 0.5rem;
        }
        
        nav a i {
            width: 1rem;
            height: 1rem;
        }
    }
    
    @media (max-width: 640px) {
        .grid-cols-4 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    
    @media (max-width: 480px) {
        .grid-cols-4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi feather icons
        if (typeof feather !== 'undefined') { 
            feather.replace(); 
        }
        
        // Reset animasi marquee setiap 60 detik untuk mencegah lag
        setInterval(function() {
            const marquee = document.querySelector('.animate-marquee');
            if (marquee) {
                marquee.style.animation = 'none';
                setTimeout(() => {
                    marquee.style.animation = 'marquee 30s linear infinite';
                }, 10);
            }
        }, 60000);
        
        // Efek hover untuk mobile menu items
        const mobileLinks = document.querySelectorAll('nav a');
        mobileLinks.forEach(link => {
            link.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.95)';
            });
            
            link.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    });
</script>