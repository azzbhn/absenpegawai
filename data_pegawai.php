<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

// Ambil semua data pegawai
$stmt = $pdo->query('SELECT id_pegawai, nama, nip, jabatan, no_whatsapp, username, status FROM pegawai ORDER BY nama');
$pegawai = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== ENTERPRISE SECURITY HEADERS ==========
class SecurityManager {
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        // Basic Security Headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        
        // Cache Control
        header("Cache-Control: no-cache, no-store, must-revalidate, private");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        // Remove Server Info
        header_remove("X-Powered-By");
        
        // Enhanced Security Headers
        self::setEnhancedHeaders();
        
        // Start output compression
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
        
        self::$initialized = true;
    }
    
    private static function setEnhancedHeaders() {
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            
            // Scripts
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net",
            
            // Styles
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://fonts.googleapis.com https://unpkg.com https://cdn.jsdelivr.net",
            
            // Fonts
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com https://cdn.jsdelivr.net",
            
            // Images
            "img-src 'self' data: https:",
            
            // AJAX, fetch
            "connect-src 'self'",
            
            // Security
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        
        header("Content-Security-Policy: " . implode('; ', $csp));
        
        // Additional Security Headers
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
        
        // HSTS - hanya di HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }
}

// Initialize security
SecurityManager::init();

// Helper function dengan sanitization
function getPageTitle($default = "Kecamatan Ajibarang") {
    $title = isset($GLOBALS['pageTitle']) ? $GLOBALS['pageTitle'] : $default;
    return htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Set default page title jika belum di-set
if (!isset($GLOBALS['pageTitle'])) {
    $GLOBALS['pageTitle'] = "Kecamatan Ajibarang";
}

// HTML Minifier
ob_start(function($buffer) {
    $buffer = preg_replace('/\s+/', ' ', $buffer);
    $buffer = preg_replace('/>\s+</', '><', $buffer);
    $buffer = preg_replace('/<!--(.*?)-->/', '', $buffer);
    return $buffer;
});
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pegawai - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .btn-aksi {
            min-width: 36px;
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media print {
            .no-print { display: none !important; }
            th:last-child, td:last-child { display: none; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>
    
    <script>
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    </script>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:justify-between items-start md:items-center bg-white rounded-2xl shadow-lg p-6 mb-8 space-y-4 md:space-y-0">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Seluruh Pegawai</h2>
                <p class="text-gray-600">Daftar semua pegawai yang terdaftar dalam sistem.</p>
            </div>
            <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-3">
                <a href="tambah_pegawai.php" class="bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-3 px-5 rounded-lg transition duration-200 flex items-center space-x-2 no-print">
                    <i data-feather="user-plus"></i>
                    <span>Tambah Pegawai</span>
                </a>
                <button onclick="window.print()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-5 rounded-lg transition duration-200 flex items-center space-x-2 no-print">
                    <i data-feather="printer"></i>
                    <span>Print</span>
                </button>
            </div>
        </div>
        <!-- filter -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 no-print">
            <form id="filterForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="filterNama" class="block text-sm font-medium text-gray-700">Nama</label>
                    <input type="text" id="filterNama" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-[#F9B000] focus:border-[#F9B000]" placeholder="Cari nama...">
                </div>
                <div>
                    <label for="filterNIP" class="block text-sm font-medium text-gray-700">NIP</label>
                    <input type="text" id="filterNIP" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-[#F9B000] focus:border-[#F9B000]" placeholder="Cari nip...">
                </div>
                <div>
                    <label for="filterJabatan" class="block text-sm font-medium text-gray-700">Jabatan</label>
                    <input type="text" id="filterJabatan" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-[#F9B000] focus:border-[#F9B000]" placeholder="Cari jabatan...">
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">No</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Nama</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">NIP</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Jabatan</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-700">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($pegawai)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <i data-feather="users" class="w-12 h-12 mx-auto text-gray-400 mb-2"></i>
                                    <p>Belum ada data pegawai</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; ?>
                            <?php foreach ($pegawai as $p): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 text-sm text-gray-900"><?= $no++ ?></td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nama']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($p['nip']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($p['jabatan']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($p['status'] == 'Aktif'): ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center space-x-3">
                                        <button onclick="toggleStatus(<?= $p['id_pegawai'] ?>, '<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>', '<?= $p['status'] ?>')" 
                                           class="btn-aksi bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-lg p-2 transition duration-200"
                                           title="<?= $p['status'] == 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <?php if ($p['status'] == 'Aktif'): ?>
                                                <i data-feather="user-x" class="w-4 h-4"></i>
                                            <?php else: ?>
                                                <i data-feather="user-check" class="w-4 h-4"></i>
                                            <?php endif; ?>
                                        </button>
                                        <a href="edit_pegawai.php?id=<?= $p['id_pegawai'] ?>" 
                                           class="btn-aksi bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg p-2 transition duration-200" 
                                           title="Edit">
                                            <i data-feather="edit" class="w-4 h-4"></i>
                                        </a>
                                        <button onclick="hapusPegawai(<?= $p['id_pegawai'] ?>, '<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>')"
                                                class="btn-aksi bg-red-100 hover:bg-red-200 text-red-800 rounded-lg p-2 transition duration-200" 
                                                title="Hapus">
                                            <i data-feather="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        feather.replace();

        // filter functionality
        function applyFilters() {
            const nama = document.getElementById('filterNama').value.toLowerCase();
            const nip = document.getElementById('filterNIP').value.toLowerCase();
            const jabatan = document.getElementById('filterJabatan').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cNama = row.cells[1].textContent.toLowerCase();
                const cNip = row.cells[2].textContent.toLowerCase();
                const cJab = row.cells[3].textContent.toLowerCase();
                if ((nama === '' || cNama.includes(nama)) &&
                    (nip === '' || cNip.includes(nip)) &&
                    (jabatan === '' || cJab.includes(jabatan))) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('filterNama').addEventListener('input', applyFilters);
        document.getElementById('filterNIP').addEventListener('input', applyFilters);
        document.getElementById('filterJabatan').addEventListener('input', applyFilters);

        function toggleStatus(id, nama, status) {
            const action = status === 'Aktif' ? 'menonaktifkan' : 'mengaktifkan';
            const statusBaru = status === 'Aktif' ? 'Nonaktif' : 'Aktif';
            
            Swal.fire({
                title: 'Ubah Status Pegawai?',
                html: `Anda yakin ingin ${action} <strong>${nama}</strong>?<br>Status akan berubah menjadi <strong>${statusBaru}</strong>.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Ubah Status!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `toggle_status.php?id=${id}`;
                }
            });
        }

        function hapusPegawai(id, nama) {
            Swal.fire({
                title: 'Hapus Pegawai?',
                html: `Anda yakin ingin menghapus data <strong>${nama}</strong>? Tindakan ini tidak dapat dibatalkan.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `hapus_pegawai.php?id=${id}`;
                }
            });
        }
    </script>
</body>
</html>