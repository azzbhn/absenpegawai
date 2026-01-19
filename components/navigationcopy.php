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
        'Dashboard' => 'bg-blue-500 hover:bg-blue-600',
        'Absensi' => 'bg-green-500 hover:bg-green-600',
        'Laporan' => 'bg-purple-500 hover:bg-purple-600',
        'Pegawai' => 'bg-yellow-500 hover:bg-yellow-600',
        'Pengaturan' => 'bg-gray-500 hover:bg-gray-600',
        'Master Data' => 'bg-indigo-500 hover:bg-indigo-600',
        'Logout' => 'bg-red-500 hover:bg-red-600',
        'Profil' => 'bg-teal-500 hover:bg-teal-600',
        'Cuti' => 'bg-orange-500 hover:bg-orange-600',
        'Izin' => 'bg-pink-500 hover:bg-pink-600',
        'Admin' => 'bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600',
        'User' => 'bg-lime-500 hover:bg-lime-600',
        'Data Master' => 'bg-indigo-500 hover:bg-indigo-600',
        'Manajemen' => 'bg-purple-500 hover:bg-purple-600',
        'Kelola' => 'bg-amber-500 hover:bg-amber-600',
        'Data' => 'bg-rose-500 hover:bg-rose-600',
        'Daftar Cuti' => 'bg-amber-500 hover:bg-amber-600'
    ];
    
    foreach ($colors as $key => $color) {
        if (stripos($menuTitle, $key) !== false) {
            return $color;
        }
    }
    
    return 'bg-gradient-to-r from-[#1F9D55] to-[#188a4a] hover:from-[#188a4a] hover:to-[#147a40]';
}

// Fungsi untuk merender dropdown menu (desktop) - KEMBALI KE HOVER
function renderDropdownMenu($menu, $jabatan) {
    $submenus = MenuConfig::sortMenus(MenuConfig::getSubmenus($menu['id'], $jabatan));
    
    // Menggunakan class 'group' untuk menangani hover melalui Tailwind
    $html = '<div class="relative group">';
    $html .= '<button class="flex items-center space-x-2 py-2 px-3 hover:bg-[#188a4a] rounded-lg transition-all duration-200 focus:outline-none transform hover:scale-105 shadow-sm hover:shadow-md">';
    $html .= '<i data-feather="' . $menu['icon'] . '" class="group-hover:rotate-12 transition-transform duration-200"></i>';
    $html .= '<span>' . $menu['title'] . '</span>';
    $html .= '<svg class="w-4 h-4 ml-1 transition-transform duration-300 group-hover:rotate-180 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    $html .= '</button>';
    
    // Dropdown content: muncul saat parent (group) di-hover
    $html .= '<div class="absolute left-0 mt-1 w-56 bg-gradient-to-br from-[#1F9D55] to-[#147a40] text-white rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-hover:translate-y-0 transform transition-all duration-300 z-50 overflow-hidden border border-white/10">';
    $html .= '<div class="py-2 backdrop-blur-sm bg-white/5">';
    
    // Render submenu dari config
    foreach ($submenus as $index => $submenu) {
        $html .= '<a href="' . $submenu['url'] . '" class="flex items-center space-x-3 px-4 py-3 hover:bg-white/10 transition-all duration-200 group/item">';
        $html .= '<div class="p-1.5 rounded-lg bg-white/10 group-hover/item:bg-white/20 transition-colors duration-200">';
        $html .= '<i data-feather="' . $submenu['icon'] . '" class="w-4 h-4 group-hover/item:scale-110 transition-transform duration-200"></i>';
        $html .= '</div>';
        $html .= '<span class="group-hover/item:translate-x-1 transition-transform duration-200">' . $submenu['title'] . '</span>';
        $html .= '</a>';
    }

    // INJEKSI: Menu Admin tambahan
    if ($menu['title'] == 'Admin' && $jabatan == 'Administrator') {
        $html .= '<div class="border-t border-white/10 my-1"></div>';
        
        $html .= '<a href="edit_cuti.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-white/10 transition-all duration-200 group/item">';
        $html .= '<div class="p-1.5 rounded-lg bg-cyan-500/20 group-hover/item:bg-cyan-500/30 transition-colors duration-200">';
        $html .= '<i data-feather="edit-3" class="w-4 h-4 text-cyan-300 group-hover/item:scale-110 transition-transform duration-200"></i>';
        $html .= '</div>';
        $html .= '<span class="group-hover/item:translate-x-1 transition-transform duration-200">Edit Cuti</span>';
        $html .= '</a>';
        
        $html .= '<a href="list_cuti.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-white/10 transition-all duration-200 group/item">';
        $html .= '<div class="p-1.5 rounded-lg bg-amber-500/20 group-hover/item:bg-amber-500/30 transition-colors duration-200">';
        $html .= '<i data-feather="list" class="w-4 h-4 text-amber-300 group-hover/item:scale-110 transition-transform duration-200"></i>';
        $html .= '</div>';
        $html .= '<span class="group-hover/item:translate-x-1 transition-transform duration-200">Daftar Cuti Pegawai</span>';
        $html .= '</a>';
        
        $html .= '<a href="data_cuti_pegawai.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-white/10 transition-all duration-200 group/item">';
        $html .= '<div class="p-1.5 rounded-lg bg-emerald-500/20 group-hover/item:bg-emerald-500/30 transition-colors duration-200">';
        $html .= '<i data-feather="database" class="w-4 h-4 text-emerald-300 group-hover/item:scale-110 transition-transform duration-200"></i>';
        $html .= '</div>';
        $html .= '<span class="group-hover/item:translate-x-1 transition-transform duration-200">Data Cuti Pegawai</span>';
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
                $html .= '<a href="' . $submenu['url'] . '" class="' . $subColorClass . ' text-white text-center py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:scale-105 flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">';
                $html .= $subIcon . '<span>' . $submenu['title'] . '</span></a>';
            }
            if ($menu['title'] == 'Admin' && $jabatan == 'Administrator') {
                $html .= '<a href="edit_cuti.php" class="bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-600 hover:to-blue-600 text-white text-center py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:scale-105 flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">';
                $html .= '<i data-feather="edit-3"></i><span>Edit Cuti</span></a>';
                
                $html .= '<a href="list_cuti.php" class="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-center py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:scale-105 flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">';
                $html .= '<i data-feather="list"></i><span>Daftar Cuti</span></a>';
                
                $html .= '<a href="data_cuti_pegawai.php" class="bg-gradient-to-r from-emerald-500 to-green-500 hover:from-emerald-600 hover:to-green-600 text-white text-center py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:scale-105 flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">';
                $html .= '<i data-feather="database"></i><span>Data Cuti</span></a>';
            }
        } else {
            $html .= '<a href="' . $menu['url'] . '" class="' . $colorClass . ' text-white text-center py-3 px-4 rounded-lg font-semibold transition-all duration-200 transform hover:scale-105 flex items-center justify-center space-x-2 shadow-md hover:shadow-lg">';
            $html .= $icon . '<span>' . $menu['title'] . '</span></a>';
        }
    }
    return $html;
}
?>

<nav class="bg-gradient-to-r from-[#0EA5E9] to-[#0284C7] text-white shadow-lg no-print relative z-40">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3">
            <div class="hidden md:flex md:space-x-2 flex-col md:flex-row mt-3 md:mt-0 items-center w-full">
                <?php foreach ($mainMenus as $menu): ?>
                    <?php if (isset($menu['has_submenu']) && $menu['has_submenu']): ?>
                        <?= renderDropdownMenu($menu, $user['jabatan']) ?>
                    <?php else: ?>
                        <a href="<?= $menu['url'] ?>" class="py-2 px-4 hover:bg-white/10 rounded-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2 shadow-sm hover:shadow-md">
                            <i data-feather="<?= $menu['icon'] ?>" class="hover:rotate-12 transition-transform duration-200"></i>
                            <span><?= $menu['title'] ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="flex items-center ml-auto space-x-2">
                    <?php foreach ($rightMenus as $menu): ?>
                        <a href="<?= $menu['url'] ?>" class="py-2 px-4 hover:bg-white/10 rounded-lg transition-all duration-200 transform hover:scale-105 flex items-center space-x-2 shadow-sm hover:shadow-md">
                            <i data-feather="<?= $menu['icon'] ?>" class="hover:rotate-12 transition-transform duration-200"></i>
                            <span><?= $menu['title'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div class="md:hidden w-full">
                <div class="grid grid-cols-2 gap-3">
                    <?= renderMobileMenuItems($allMenusForMobile, $user['jabatan']) ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') { 
            feather.replace(); 
        }
    });
</script>