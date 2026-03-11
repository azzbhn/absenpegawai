<?php
/**
 * Konfigurasi Menu Website Absensi Kecamatan Ajibarang
 * File ini berisi semua konfigurasi menu untuk website
 */

class MenuConfig {
    
    /**
     * Mendapatkan semua menu berdasarkan jabatan user
     */
    public static function getMenus($jabatan = null) {
        $allMenus = self::getAllMenus();
        $filteredMenus = [];
        
        foreach ($allMenus as $menu) {
            // Cek apakah user memiliki akses ke menu ini
            if (self::hasAccess($menu, $jabatan)) {
                $filteredMenus[] = $menu;
            }
        }
        
        return $filteredMenus;
    }
    
    /**
     * Mendapatkan menu untuk navigasi utama (desktop)
     */
    public static function getMainNavMenus($jabatan = null) {
        $menus = self::getMenus($jabatan);
        $mainNav = [];
        
        foreach ($menus as $menu) {
            // Hanya menu yang bukan right-aligned dan bukan submenu saja
            if (($menu['position'] != 'right' && $menu['position'] != 'mobile-only') && 
                (!isset($menu['parent_id']) || $menu['parent_id'] === null)) {
                $mainNav[] = $menu;
            }
        }
        
        return $mainNav;
    }
    
    /**
     * Mendapatkan menu kanan (logout, password)
     */
    public static function getRightNavMenus($jabatan = null) {
        $menus = self::getMenus($jabatan);
        $rightNav = [];
        
        foreach ($menus as $menu) {
            if ($menu['position'] == 'right') {
                $rightNav[] = $menu;
            }
        }
        
        return $rightNav;
    }
    
    /**
     * Mendapatkan menu untuk mobile
     */
    public static function getMobileMenus($jabatan = null) {
        $menus = self::getMenus($jabatan);
        $mobileMenus = [];
        
        foreach ($menus as $menu) {
            if ($menu['position'] != 'right') {
                $mobileMenus[] = $menu;
            }
        }
        
        return $mobileMenus;
    }
    
    /**
     * Mendapatkan submenu untuk menu tertentu
     */
    public static function getSubmenus($parentId, $jabatan = null) {
        $menus = self::getMenus($jabatan);
        $submenus = [];
        
        foreach ($menus as $menu) {
            if (isset($menu['parent_id']) && $menu['parent_id'] == $parentId) {
                $submenus[] = $menu;
            }
        }
        
        return $submenus;
    }
    
    /**
     * Cek akses user ke menu
     */
    private static function hasAccess($menu, $jabatan) {
        // Jika menu tidak punya role restriction, semua bisa akses
        if (!isset($menu['roles']) || empty($menu['roles'])) {
            return true;
        }
        
        // Jika role 'all', semua bisa akses
        if (in_array('all', $menu['roles'])) {
            return true;
        }
        
        // Cek apakah jabatan user ada dalam daftar role yang diizinkan
        return $jabatan && in_array($jabatan, $menu['roles']);
    }
    
    /**
     * Definisi semua menu dalam sistem
     */
    private static function getAllMenus() {
        return [
            // =================== MAIN NAVIGATION ===================
            [
                'id' => 'dashboard',
                'title' => 'Dashboard',
                'icon' => 'home',
                'url' => 'dashboard.php',
                'roles' => ['all'],
                'position' => 'main',
                'order' => 10
            ],
            [
                'id' => 'absensi',
                'title' => 'Absensi',
                'icon' => 'clock',
                'url' => 'absen.php',
                'roles' => ['all'],
                'position' => 'main',
                'order' => 20
            ],
            [
                'id' => 'kendali_cuti',
                'title' => 'Kendali Cuti',
                'icon' => 'calendar',
                'url' => 'cuti.php',
                'roles' => ['all'],
                'position' => 'main',
                'order' => 30
            ],
            [
                'id' => 'pengajuan_cuti',
                'title' => 'Pengajuan Cuti',
                'icon' => 'file-text',
                'url' => 'ijin.php',
                'roles' => ['all'],
                'position' => 'main',
                'order' => 40
            ],
            
            // =================== ADMIN MENU (DROPDOWN) ===================
            [
                'id' => 'admin',
                'title' => 'Admin',
                'icon' => 'shield',
                'url' => '#',
                'roles' => ['Administrator'],
                'position' => 'main',
                'has_submenu' => true,
                'order' => 50
            ],
            
            // Submenu Admin
            [
                'id' => 'data_absensi',
                'title' => 'Data Absensi',
                'icon' => 'file-text',
                'url' => 'data_absensi.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 10
            ],
            [
                'id' => 'input_cuti',
                'title' => 'Input Cuti',
                'icon' => 'calendar',
                'url' => 'input_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 15
            ],
            [
                'id' => 'persetujuan_cuti',
                'title' => 'Persetujuan Cuti',
                'icon' => 'check-square',
                'url' => 'persetujuan_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 20
            ],
            [
                'id' => 'tambah_pegawai',
                'title' => 'Tambah Pegawai',
                'icon' => 'user-plus',
                'url' => 'tambah_pegawai.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 30
            ],
            [
                'id' => 'data_pegawai',
                'title' => 'Data Pegawai',
                'icon' => 'users',
                'url' => 'data_pegawai.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 40
            ],
            [
                'id' => 'jam_kerja',
                'title' => 'Jam Kerja',
                'icon' => 'clock',
                'url' => 'jam_kerja.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 45
            ],
            [
                'id' => 'input_sisa_cuti',
                'title' => 'Input Sisa Cuti Tahunan',
                'icon' => 'plus-circle',
                'url' => 'input_sisacuti.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 50
            ],
            [
                'id' => 'edit_sisa_cuti',
                'title' => 'Edit Sisa Cuti Tahunan',
                'icon' => 'edit',
                'url' => 'edit_sisacuti.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 60
            ],
            [
                'id' => 'data_cuti_pegawai',
                'title' => 'Data Cuti Pegawai',
                'icon' => 'database',
                'url' => 'data_cuti_pegawai.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 70
            ],
            
            // ====== MENU TAMBAHAN UNTUK ADMIN ======
            [
                'id' => 'edit_cuti',
                'title' => 'Edit Cuti',
                'icon' => 'edit-3',
                'url' => 'edit_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 25
            ],
            [
                'id' => 'daftar_cuti',
                'title' => 'Daftar Cuti',
                'icon' => 'list',
                'url' => 'list_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'main',
                'parent_id' => 'admin',
                'order' => 35
            ],
            
            // =================== RIGHT ALIGNED MENU ===================
            [
                'id' => 'ganti_password',
                'title' => 'Ganti Password',
                'icon' => 'key',
                'url' => 'ganti_password.php',
                'roles' => ['all'],
                'position' => 'right',
                'order' => 10
            ],
            [
                'id' => 'logout',
                'title' => 'Logout',
                'icon' => 'log-out',
                'url' => 'logout.php',
                'roles' => ['all'],
                'position' => 'right',
                'order' => 20
            ],
            
            // =================== MOBILE ONLY MENU ===================
            // (Menu yang hanya muncul di tampilan mobile)
            [
                'id' => 'mobile_dashboard',
                'title' => 'Dashboard',
                'icon' => 'home',
                'url' => 'dashboard.php',
                'roles' => ['all'],
                'position' => 'mobile-only',
                'order' => 10
            ],
            [
                'id' => 'mobile_absensi',
                'title' => 'Absensi',
                'icon' => 'clock',
                'url' => 'absen.php',
                'roles' => ['all'],
                'position' => 'mobile-only',
                'order' => 20
            ],
            [
                'id' => 'mobile_kendali_cuti',
                'title' => 'Kendali Cuti',
                'icon' => 'calendar',
                'url' => 'cuti.php',
                'roles' => ['all'],
                'position' => 'mobile-only',
                'order' => 30
            ],
            [
                'id' => 'mobile_pengajuan_cuti',
                'title' => 'Pengajuan Cuti',
                'icon' => 'file-text',
                'url' => 'ijin.php',
                'roles' => ['all'],
                'position' => 'mobile-only',
                'order' => 40
            ],
            [
                'id' => 'mobile_admin_data_absensi',
                'title' => 'Data Absensi',
                'icon' => 'file-text',
                'url' => 'data_absensi.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 50
            ],
            [
                'id' => 'mobile_admin_input_cuti',
                'title' => 'Input Cuti',
                'icon' => 'calendar',
                'url' => 'input_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 55
            ],
            [
                'id' => 'mobile_admin_persetujuan_cuti',
                'title' => 'Persetujuan Cuti',
                'icon' => 'check-square',
                'url' => 'persetujuan_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 60
            ],
            [
                'id' => 'mobile_admin_edit_cuti',
                'title' => 'Edit Cuti',
                'icon' => 'edit-3',
                'url' => 'edit_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 62
            ],
            [
                'id' => 'mobile_admin_daftar_cuti',
                'title' => 'Daftar Cuti',
                'icon' => 'list',
                'url' => 'list_cuti.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 64
            ],
            [
                'id' => 'mobile_admin_tambah_pegawai',
                'title' => 'Tambah Pegawai',
                'icon' => 'user-plus',
                'url' => 'tambah_pegawai.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 66
            ],
            [
                'id' => 'mobile_admin_data_pegawai',
                'title' => 'Data Pegawai',
                'icon' => 'users',
                'url' => 'data_pegawai.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 68
            ],
            [
                'id' => 'mobile_admin_jam_kerja',
                'title' => 'Jam Kerja',
                'icon' => 'clock',
                'url' => 'jam_kerja.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 69
            ],
            [
                'id' => 'mobile_admin_input_sisa_cuti',
                'title' => 'Input Sisa Cuti',
                'icon' => 'plus-circle',
                'url' => 'input_sisacuti.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 70
            ],
            [
                'id' => 'mobile_admin_edit_sisa_cuti',
                'title' => 'Edit Sisa Cuti',
                'icon' => 'edit',
                'url' => 'edit_sisacuti.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 72
            ],
            [
                'id' => 'mobile_admin_data_cuti_pegawai',
                'title' => 'Data Cuti Pegawai',
                'icon' => 'database',
                'url' => 'data_cuti_pegawai.php',
                'roles' => ['Administrator'],
                'position' => 'mobile-only',
                'order' => 74
            ],
            [
                'id' => 'mobile_ganti_password',
                'title' => 'Ganti Password',
                'icon' => 'key',
                'url' => 'ganti_password.php',
                'roles' => ['all'],
                'position' => 'mobile-only',
                'order' => 110
            ],
            [
                'id' => 'mobile_logout',
                'title' => 'Logout',
                'icon' => 'log-out',
                'url' => 'logout.php',
                'roles' => ['all'],
                'position' => 'mobile-only',
                'order' => 120
            ]
        ];
    }
    
    /**
     * Fungsi helper untuk mengurutkan menu berdasarkan order
     */
    public static function sortMenus($menus) {
        usort($menus, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        return $menus;
    }
    
    /**
     * Mendapatkan semua roles/jabatan yang valid
     */
    public static function getAllRoles() {
        return [
            'Administrator',
            'Camat',
            'Sekretaris Kecamatan',
            'Staf',
            'Jaga Malam'
        ];
    }
    
    /**
     * Cek apakah menu memiliki submenu
     */
    public static function hasSubmenu($menuId, $jabatan = null) {
        $submenus = self::getSubmenus($menuId, $jabatan);
        return !empty($submenus);
    }
}
?>