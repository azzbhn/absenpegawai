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
        'Data' => 'bg-rose-500 hover:bg-rose-600'
    ];
    
    foreach ($colors as $key => $color) {
        if (stripos($menuTitle, $key) !== false) {
            return $color;
        }
    }
    
    // Default color
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

// Fungsi untuk merender dropdown menu (desktop)
function renderDropdownMenu($menu, $jabatan) {
    $submenus = MenuConfig::sortMenus(MenuConfig::getSubmenus($menu['id'], $jabatan));
    if (empty($submenus)) return '';
    
    $html = '<div class="relative group">';
    $html .= '<button class="flex items-center space-x-2 py-2 px-3 hover:bg-[#188a4a] rounded transition focus:outline-none">';
    $html .= '<i data-feather="' . $menu['icon'] . '"></i>';
    $html .= '<span>' . $menu['title'] . '</span>';
    $html .= '<svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    $html .= '</button>';
    $html .= '<div class="absolute left-0 mt-2 w-56 bg-[#1F9D55] text-white rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transform -translate-y-2 group-hover:translate-y-0 transition duration-200 z-10 hidden md:block">';
    
    foreach ($submenus as $index => $submenu) {
        $roundedClass = '';
        if ($index === 0) $roundedClass = 'rounded-t-lg';
        if ($index === count($submenus) - 1) $roundedClass = 'rounded-b-lg';
        
        $html .= '<a href="' . $submenu['url'] . '" class="flex items-center space-x-2 px-4 py-2 hover:bg-[#188a4a] transition ' . $roundedClass . '">';
        $html .= '<i data-feather="' . $submenu['icon'] . '"></i>';
        $html .= '<span>' . $submenu['title'] . '</span>';
        $html .= '</a>';
    }
    
    $html .= '</div></div>';
    return $html;
}

// Fungsi untuk merender semua menu mobile (termasuk submenu sebagai tombol terpisah)
function renderMobileMenuItems($menus, $jabatan) {
    $html = '';
    
    foreach ($menus as $menu) {
        $colorClass = getMenuColor($menu['title']);
        $icon = isset($menu['icon']) ? '<i data-feather="' . $menu['icon'] . '"></i>' : '';
        
        // Cek apakah menu memiliki submenu
        $hasSubmenu = isset($menu['has_submenu']) && $menu['has_submenu'];
        
        if ($hasSubmenu) {
            // Untuk mobile, tampilkan semua submenu sebagai tombol terpisah
            $submenus = MenuConfig::sortMenus(MenuConfig::getSubmenus($menu['id'], $jabatan));
            
            // Render setiap submenu sebagai tombol terpisah
            foreach ($submenus as $submenu) {
                $subColorClass = getMenuColor($submenu['title']);
                $subIcon = isset($submenu['icon']) ? '<i data-feather="' . $submenu['icon'] . '"></i>' : '';
                
                $html .= '<a href="' . $submenu['url'] . '" ';
                $html .= 'class="' . $subColorClass . ' text-white text-center py-3 px-4 rounded-lg font-semibold transition flex items-center justify-center space-x-2">';
                $html .= $subIcon;
                $html .= '<span>' . $submenu['title'] . '</span>';
                $html .= '</a>';
            }
        } else {
            // Menu tanpa submenu, tampilkan normal
            $html .= '<a href="' . $menu['url'] . '" ';
            $html .= 'class="' . $colorClass . ' text-white text-center py-3 px-4 rounded-lg font-semibold transition flex items-center justify-center space-x-2">';
            $html .= $icon;
            $html .= '<span>' . $menu['title'] . '</span>';
            $html .= '</a>';
        }
    }
    
    return $html;
}
?>
<!-- Navigation -->
<nav class="bg-[#1F9D55] text-white shadow-md no-print">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3">
            <!-- Menu Desktop -->
            <div class="hidden md:flex md:space-x-6 flex-col md:flex-row mt-3 md:mt-0 items-center w-full">
                <!-- Menu utama -->
                <?php foreach ($mainMenus as $menu): ?>
                    <?php if (isset($menu['has_submenu']) && $menu['has_submenu']): ?>
                        <?= renderDropdownMenu($menu, $user['jabatan']) ?>
                    <?php else: ?>
                        <a href="<?= $menu['url'] ?>" 
                           class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
                            <i data-feather="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['title'] ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Menu kanan -->
                <div class="flex items-center ml-auto space-x-2">
                    <?php foreach ($rightMenus as $menu): ?>
                        <a href="<?= $menu['url'] ?>" 
                           class="py-2 px-3 hover:bg-[#188a4a] rounded transition flex items-center space-x-2">
                            <i data-feather="<?= $menu['icon'] ?>"></i>
                            <span><?= $menu['title'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Menu Mobile -->
            <div class="md:hidden w-full">
                <div class="grid grid-cols-2 gap-3">
                    <?= renderMobileMenuItems($allMenusForMobile, $user['jabatan']) ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    // Initialize Feather Icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
</script>