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
        'Admin' => 'bg-cyan-500 hover:bg-cyan-600',
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
    
    return 'bg-[#1F9D55] hover:bg-[#188a4a]';
}

// Fungsi untuk merender menu item
function renderMenuItem($menu, $isSubmenu = false) {
    $icon = isset($menu['icon']) ? '<i data-feather="' . $menu['icon'] . '"></i>' : '';
    $classes = $isSubmenu ? 'flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition' 
                         : 'py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2';
    
    return '<a href="' . $menu['url'] . '" class="' . $classes . '">' 
           . $icon . '<span>' . $menu['title'] . '</span></a>';
}

// Fungsi untuk merender dropdown menu (desktop) - DIPERBARUI: Klik Only
function renderDropdownMenu($menu, $jabatan) {
    $submenus = MenuConfig::sortMenus(MenuConfig::getSubmenus($menu['id'], $jabatan));
    
    // Buat ID unik untuk dropdown ini agar JS bisa menargetkannya
    $menuId = 'dropdown-' . preg_replace('/[^a-zA-Z0-9]/', '', $menu['title']);
    
    // Hapus class 'group' agar hover tidak memicu CSS
    $html = '<div class="relative dropdown-container">'; 
    
    // Tambahkan onclick
    $html .= '<button onclick="toggleDropdown(\''.$menuId.'\')" class="flex items-center space-x-2 py-2 px-3 hover:bg-[#188a4a] rounded transition focus:outline-none dropdown-btn">';
    $html .= '<i data-feather="' . $menu['icon'] . '"></i>';
    $html .= '<span>' . $menu['title'] . '</span>';
    $html .= '<svg class="w-4 h-4 ml-1 transition-transform duration-200" id="arrow-'.$menuId.'" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    $html .= '</button>';
    
    // Hapus class hover (opacity-0 group-hover:opacity-100 dll)
    // Ganti dengan class 'hidden' secara default dan mekanisme JS
    $html .= '<div id="'.$menuId.'" class="dropdown-content absolute left-0 mt-2 w-56 bg-[#1F9D55] text-white rounded-lg shadow-lg hidden transform origin-top transition duration-200 z-50 md:block" style="display:none;">';
    
    // Render submenu dari config
    foreach ($submenus as $index => $submenu) {
        $html .= '<a href="' . $submenu['url'] . '" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition">';
        $html .= '<i data-feather="' . $submenu['icon'] . '"></i>';
        $html .= '<span>' . $submenu['title'] . '</span>';
        $html .= '</a>';
    }

    // INJEKSI: Jika ini menu Admin, tambahkan Edit Cuti dan Daftar Cuti Pegawai di dalamnya
    if ($menu['title'] == 'Admin' && $jabatan == 'Administrator') {
        $html .= '<a href="edit_cuti.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition border-t border-white/10">';
        $html .= '<i data-feather="edit-3"></i>';
        $html .= '<span>Edit Cuti</span>';
        $html .= '</a>';
        
        $html .= '<a href="list_cuti.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition">';
        $html .= '<i data-feather="list"></i>';
        $html .= '<span>Daftar Cuti Pegawai</span>';
        $html .= '</a>';
    }
    
    $html .= '</div></div>';
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
                $html .= '<a href="' . $submenu['url'] . '" class="' . $subColorClass . ' text-white text-center py-3 px-4 rounded-lg font-semibold transition flex items-center justify-center space-x-2">';
                $html .= $subIcon . '<span>' . $submenu['title'] . '</span></a>';
            }
            // Tambahkan Edit Cuti dan Daftar Cuti Pegawai di mobile jika menu Admin
            if ($menu['title'] == 'Admin' && $jabatan == 'Administrator') {
                $html .= '<a href="edit_cuti.php" class="bg-cyan-600 text-white text-center py-3 px-4 rounded-lg font-semibold transition flex items-center justify-center space-x-2">';
                $html .= '<i data-feather="edit-3"></i><span>Edit Cuti</span></a>';
                
                $html .= '<a href="list_cuti.php" class="bg-amber-600 text-white text-center py-3 px-4 rounded-lg font-semibold transition flex items-center justify-center space-x-2">';
                $html .= '<i data-feather="list"></i><span>Daftar Cuti</span></a>';
            }
        } else {
            $html .= '<a href="' . $menu['url'] . '" class="' . $colorClass . ' text-white text-center py-3 px-4 rounded-lg font-semibold transition flex items-center justify-center space-x-2">';
            $html .= $icon . '<span>' . $menu['title'] . '</span></a>';
        }
    }
    return $html;
}
?>
<nav class="bg-[#1F9D55] text-white shadow-md no-print">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3">
            <div class="hidden md:flex md:space-x-6 flex-col md:flex-row mt-3 md:mt-0 items-center w-full">
                <?php foreach ($mainMenus as $menu): ?>
                    <?php if (isset($menu['has_submenu']) && $menu['has_submenu']): ?>
                        <?= renderDropdownMenu($menu, $user['jabatan']) ?>
                    <?php else: ?>
                        <a href="<?= $menu['url'] ?>" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
                            <i data-feather="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['title'] ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="flex items-center ml-auto space-x-2">
                    <?php foreach ($rightMenus as $menu): ?>
                        <a href="<?= $menu['url'] ?>" class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
                            <i data-feather="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['title'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="md:hidden w-full">
                <div class="grid grid-cols-2 gap-3">
                    <?= renderMobileMenuItems($allMenusForMobile, $user['jabatan']) ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    if (typeof feather !== 'undefined') { feather.replace(); }

    /**
     * Fungsi untuk toggle dropdown menu (klik untuk buka/tutup)
     */
    function toggleDropdown(menuId) {
        var dropdown = document.getElementById(menuId);
        var arrow = document.getElementById('arrow-' + menuId);
        var isHidden = dropdown.style.display === 'none';
        
        // Tutup semua dropdown yang sedang terbuka terlebih dahulu
        closeAllDropdowns();
        
        // Jika tadinya tertutup, sekarang buka
        if (isHidden) {
            dropdown.style.display = 'block';
            // Animasi kecil (optional)
            dropdown.classList.remove('opacity-0', '-translate-y-2');
            dropdown.classList.add('opacity-100', 'translate-y-0');
            
            // Putar panah jika ada
            if(arrow) arrow.style.transform = 'rotate(180deg)';
        }
    }

    /**
     * Menutup semua dropdown di halaman
     */
    function closeAllDropdowns() {
        var dropdowns = document.querySelectorAll('.dropdown-content');
        dropdowns.forEach(function(d) {
            d.style.display = 'none';
            d.classList.add('opacity-0', '-translate-y-2');
            d.classList.remove('opacity-100', 'translate-y-0');
        });

        // Reset semua panah
        var arrows = document.querySelectorAll('svg[id^="arrow-"]');
        arrows.forEach(function(a) {
            a.style.transform = 'rotate(0deg)';
        });
    }

    // Event Listener: Klik di luar menu untuk menutup dropdown
    window.onclick = function(event) {
        if (!event.target.closest('.dropdown-btn') && !event.target.closest('.dropdown-content')) {
            closeAllDropdowns();
        }
    }
</script>