<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$current_year = date('Y');

// Default active tab
$active_tab = $_GET['tab'] ?? 'tahunan';

// Validasi tab
$valid_tabs = ['tahunan', 'sakit', 'alasan_penting', 'melahirkan', 'besar', 'luar_tanggungan'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'tahunan';
}

// Parameter filter tahun - default untuk tab tahunan adalah tahun berjalan, untuk tab lain adalah 'all'
$tahun_filter = $_GET['tahun'] ?? ($active_tab == 'tahunan' ? $current_year : 'all');

// Fungsi untuk mendapatkan sisa cuti tahunan
function getSisaCutiTahunan($pdo, $id_pegawai, $tahun) {
    $stmt = $pdo->prepare('SELECT sisa_cuti FROM sisa_cuti_tahunan WHERE id_pegawai = ? AND tahun = ?');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['sisa_cuti'] : 0;
}

// Hitung cuti tahunan yang sudah diambil dari tabel log_input_cuti
function getCutiTahunanDiambil($pdo, $id_pegawai, $tahun) {
    // Pertama, coba ambil dari tabel log_input_cuti (jika tabel ada)
    try {
        // Cek apakah tabel log_input_cuti ada
        $table_check = $pdo->query("SHOW TABLES LIKE 'log_input_cuti'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare('
                SELECT SUM(jumlah_hari) as total 
                FROM log_input_cuti 
                WHERE id_pegawai = ? 
                AND jenis_cuti = "cuti_tahunan"
                AND YEAR(tanggal_mulai) = ?
            ');
            $stmt->execute([$id_pegawai, $tahun]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total'] !== null) {
                return (int)$result['total'];
            }
        }
    } catch (Exception $e) {
        // Jika error, lanjutkan ke metode fallback
        error_log("Error getting cuti from log_input_cuti: " . $e->getMessage());
    }
    
    // Fallback: hitung dari tabel absensi (metode lama)
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total 
        FROM absensi 
        WHERE id_pegawai = ? 
        AND YEAR(tanggal) = ? 
        AND status = "cuti_tahunan"
    ');
    $stmt->execute([$id_pegawai, $tahun]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
}

// Hitung cuti sesuai jenis (semua tahun) - untuk tab lainnya
function getCutiByJenisAllYears($pdo, $id_pegawai, $jenis_cuti) {
    // Untuk cuti selain tahunan, gunakan tabel log_input_cuti jika tersedia
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'log_input_cuti'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare('
                SELECT SUM(jumlah_hari) as total 
                FROM log_input_cuti 
                WHERE id_pegawai = ? 
                AND jenis_cuti = ?
            ');
            $stmt->execute([$id_pegawai, $jenis_cuti]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total'] !== null) {
                return (int)$result['total'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting cuti from log_input_cuti: " . $e->getMessage());
    }
    
    // Fallback ke metode lama
    $sql = '
        SELECT COUNT(*) as total 
        FROM absensi 
        WHERE id_pegawai = ? 
        AND status = ?
    ';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_pegawai, $jenis_cuti]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
}

// Hitung cuti sesuai jenis dan tahun tertentu - untuk tab lainnya
function getCutiByJenisPerTahun($pdo, $id_pegawai, $jenis_cuti, $tahun) {
    // Gunakan tabel log_input_cuti jika tersedia
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'log_input_cuti'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare('
                SELECT SUM(jumlah_hari) as total 
                FROM log_input_cuti 
                WHERE id_pegawai = ? 
                AND jenis_cuti = ?
                AND YEAR(tanggal_mulai) = ?
            ');
            $stmt->execute([$id_pegawai, $jenis_cuti, $tahun]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total'] !== null) {
                return (int)$result['total'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting cuti from log_input_cuti: " . $e->getMessage());
    }
    
    // Fallback ke metode lama
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total 
        FROM absensi 
        WHERE id_pegawai = ? 
        AND YEAR(tanggal) = ? 
        AND status = ?
    ');
    $stmt->execute([$id_pegawai, $tahun, $jenis_cuti]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['total'] : 0;
}

// Get data cuti tahunan untuk 3 tahun terakhir
$cuti_tahunan = [
    'tahun_sekarang' => [
        'tahun' => $current_year,
        'sisa' => getSisaCutiTahunan($pdo, $user['id_pegawai'], $current_year),
        'diambil' => getCutiTahunanDiambil($pdo, $user['id_pegawai'], $current_year)
    ],
    'tahun_lalu' => [
        'tahun' => $current_year - 1,
        'sisa' => getSisaCutiTahunan($pdo, $user['id_pegawai'], $current_year - 1),
        'diambil' => getCutiTahunanDiambil($pdo, $user['id_pegawai'], $current_year - 1)
    ],
    'tahun_dulu' => [
        'tahun' => $current_year - 2,
        'sisa' => getSisaCutiTahunan($pdo, $user['id_pegawai'], $current_year - 2),
        'diambil' => getCutiTahunanDiambil($pdo, $user['id_pegawai'], $current_year - 2)
    ]
];

// Hitung hak cuti (sisa + diambil) untuk setiap tahun
foreach ($cuti_tahunan as $key => &$cuti) {
    $cuti['hak_cuti'] = $cuti['sisa'] + $cuti['diambil'];
    $cuti['persentase'] = $cuti['diambil'] > 0 ? min(100, ($cuti['diambil'] / $cuti['hak_cuti'] * 100)) : 0;
}

// Hitung total untuk statistik
// Total Sisa Cuti = Sisa Cuti tahun ini + Sisa Cuti tahun-1 + Sisa Cuti tahun-2
$total_sisa_cuti = $cuti_tahunan['tahun_sekarang']['sisa'] + 
                  $cuti_tahunan['tahun_lalu']['sisa'] + 
                  $cuti_tahunan['tahun_dulu']['sisa'];

// Total Cuti Diambil = Cuti yang diambil pada tahun ini saja (dari log_input_cuti)
$total_cuti_diambil = $cuti_tahunan['tahun_sekarang']['diambil'];

// Persentase Penggunaan Cuti = (Total Cuti Diambil / Total Sisa Cuti) × 100%
$total_persentase = $total_sisa_cuti > 0 ? ($total_cuti_diambil / $total_sisa_cuti * 100) : 0;

// Persentase penggunaan cuti tahun ini (dari hak cuti tahun ini saja)
$persentase_tahun_ini = $cuti_tahunan['tahun_sekarang']['hak_cuti'] > 0 ? 
                        ($cuti_tahunan['tahun_sekarang']['diambil'] / $cuti_tahunan['tahun_sekarang']['hak_cuti'] * 100) : 0;

// Get detail riwayat cuti untuk semua jenis dengan filter tahun
// Fungsi ini tetap menggunakan tabel absensi untuk riwayat tampilan
function getDetailRiwayatCutiByYear($pdo, $id_pegawai, $jenis_cuti, $tahun_filter = 'all') {
    $sql = '
        SELECT 
            tanggal,
            status,
            YEAR(tanggal) as tahun
        FROM absensi 
        WHERE id_pegawai = ? 
        AND status = ?
    ';
    
    $params = [$id_pegawai, $jenis_cuti];
    
    if ($tahun_filter !== 'all') {
        $sql .= ' AND YEAR(tanggal) = ?';
        $params[] = $tahun_filter;
    }
    
    $sql .= ' ORDER BY tanggal DESC';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_cuti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_cuti)) {
        return [];
    }
    
    // Kelompokkan tanggal berurutan menjadi periode
    $periods = [];
    $current_period = null;
    
    foreach ($all_cuti as $cuti) {
        $current_date = new DateTime($cuti['tanggal']);
        
        if ($current_period === null) {
            $current_period = [
                'tahun' => $cuti['tahun'],
                'tanggal_mulai' => $cuti['tanggal'],
                'tanggal_selesai' => $cuti['tanggal'],
                'jumlah_hari' => 1,
                'status' => $cuti['status']
            ];
        } else {
            $last_date = new DateTime($current_period['tanggal_selesai']);
            $interval = $last_date->diff($current_date);
            
            if ($interval->days == 1) {
                $current_period['tanggal_selesai'] = $cuti['tanggal'];
                $current_period['jumlah_hari']++;
            } else {
                $periods[] = $current_period;
                $current_period = [
                    'tahun' => $cuti['tahun'],
                    'tanggal_mulai' => $cuti['tanggal'],
                    'tanggal_selesai' => $cuti['tanggal'],
                    'jumlah_hari' => 1,
                    'status' => $cuti['status']
                ];
            }
        }
    }
    
    if ($current_period !== null) {
        $periods[] = $current_period;
    }
    
    return $periods;
}

// Map jenis cuti
$jenis_cuti_map = [
    'tahunan' => 'cuti_tahunan',
    'sakit' => 'cuti_sakit',
    'alasan_penting' => 'cuti_alasan_penting',
    'melahirkan' => 'cuti_melahirkan',
    'besar' => 'cuti_besar',
    'luar_tanggungan' => 'cuti_luar_tanggungan'
];

// Get tahun-tahun yang tersedia untuk riwayat cuti
function getAvailableYears($pdo, $id_pegawai, $jenis_cuti) {
    $stmt = $pdo->prepare('
        SELECT DISTINCT YEAR(tanggal) as tahun
        FROM absensi 
        WHERE id_pegawai = ? 
        AND status = ?
        ORDER BY tahun DESC
    ');
    $stmt->execute([$id_pegawai, $jenis_cuti]);
    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($years as $year) {
        $result[] = $year['tahun'];
    }
    return $result;
}

// Get years for all tabs
$available_years_all = [];
foreach ($jenis_cuti_map as $tab_key => $jenis_cuti) {
    $available_years_all[$tab_key] = getAvailableYears($pdo, $user['id_pegawai'], $jenis_cuti);
    
    // Untuk tab tahunan, tambahkan 3 tahun terakhir jika belum ada
    if ($tab_key == 'tahunan') {
        $recent_years = [$current_year, $current_year - 1, $current_year - 2];
        foreach ($recent_years as $year) {
            if (!in_array($year, $available_years_all[$tab_key])) {
                $available_years_all[$tab_key][] = $year;
            }
        }
        rsort($available_years_all[$tab_key]);
    }
}

// Get data untuk tab aktif
$detail_riwayat_cuti_aktif = [];
if (isset($jenis_cuti_map[$active_tab])) {
    $detail_riwayat_cuti_aktif = getDetailRiwayatCutiByYear($pdo, $user['id_pegawai'], $jenis_cuti_map[$active_tab], $tahun_filter);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kendali Cuti - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active {
            background-color: #F9B000;
            color: white;
            font-weight: bold;
        }
        .cuti-card {
            transition: all 0.3s ease;
        }
        .cuti-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        /* Style untuk card statistik */
        .stat-card {
            min-height: 200px;
        }
        /* Loading indicator */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading.active {
            display: block;
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
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Kendali Cuti Pegawai</h2>
            <p class="text-gray-600">Monitor dan kelola cuti Anda dengan mudah</p>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 no-print">
            <div class="flex flex-wrap gap-2 mb-6">
                <!-- Tab Cuti Tahunan -->
                <button class="tab-button px-4 py-2 rounded-lg transition <?= $active_tab == 'tahunan' ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>" 
                        data-tab="tahunan">
                    <i data-feather="calendar" class="w-4 h-4 inline mr-2"></i>
                    Cuti Tahunan
                </button>
                
                <!-- Tab Cuti Sakit -->
                <button class="tab-button px-4 py-2 rounded-lg transition <?= $active_tab == 'sakit' ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>" 
                        data-tab="sakit">
                    <i data-feather="thermometer" class="w-4 h-4 inline mr-2"></i>
                    Cuti Sakit
                </button>
                
                <!-- Tab Cuti Alasan Penting -->
                <button class="tab-button px-4 py-2 rounded-lg transition <?= $active_tab == 'alasan_penting' ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>" 
                        data-tab="alasan_penting">
                    <i data-feather="alert-circle" class="w-4 h-4 inline mr-2"></i>
                    Cuti Alasan Penting
                </button>
                
                <!-- Tab Cuti Melahirkan -->
                <button class="tab-button px-4 py-2 rounded-lg transition <?= $active_tab == 'melahirkan' ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>" 
                        data-tab="melahirkan">
                    <i data-feather="heart" class="w-4 h-4 inline mr-2"></i>
                    Cuti Melahirkan
                </button>
                
                <!-- Tab Cuti Besar -->
                <button class="tab-button px-4 py-2 rounded-lg transition <?= $active_tab == 'besar' ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>" 
                        data-tab="besar">
                    <i data-feather="briefcase" class="w-4 h-4 inline mr-2"></i>
                    Cuti Besar
                </button>
                
                <!-- Tab Cuti Luar Tanggungan -->
                <button class="tab-button px-4 py-2 rounded-lg transition <?= $active_tab == 'luar_tanggungan' ? 'active' : 'bg-gray-100 hover:bg-gray-200' ?>" 
                        data-tab="luar_tanggungan">
                    <i data-feather="external-link" class="w-4 h-4 inline mr-2"></i>
                    Cuti Luar Tanggungan
                </button>
            </div>

            <!-- Loading indicator -->
            <div id="loading" class="loading">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600"></div>
                <p class="mt-2 text-gray-600">Memuat data...</p>
            </div>

            <!-- Tab Content - Cuti Tahunan -->
            <div id="tab-tahunan" class="tab-content <?= $active_tab == 'tahunan' ? 'active' : '' ?>">
                <!-- Konten akan dimuat via AJAX -->
            </div>

            <!-- Tab Content untuk Cuti Lainnya -->
            <?php foreach (['sakit', 'alasan_penting', 'melahirkan', 'besar', 'luar_tanggungan'] as $tab): ?>
                <div id="tab-<?= $tab ?>" class="tab-content <?= $active_tab == $tab ? 'active' : '' ?>">
                    <!-- Konten akan dimuat via AJAX -->
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        feather.replace();
        
        // Global variables
        let currentTab = '<?= $active_tab ?>';
        let currentYearFilter = '<?= $tahun_filter ?>';
        const currentYear = <?= $current_year ?>;
        
        // Load initial tab content
        document.addEventListener('DOMContentLoaded', function() {
            loadTabContent(currentTab, currentYearFilter);
            
            // Handle browser back/forward
            window.addEventListener('popstate', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab') || 'tahunan';
                const tahun = urlParams.get('tahun') || (tab === 'tahunan' ? currentYear : 'all');
                
                currentTab = tab;
                currentYearFilter = tahun;
                loadTabContent(tab, tahun);
            });
        });
        
        // Tab functionality dengan AJAX
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Set tahun filter default berdasarkan tab
                let tahunFilter = currentYearFilter;
                if (tabId === 'tahunan' && tahunFilter === 'all') {
                    tahunFilter = currentYear;
                }
                
                // Remove active class from all buttons
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active');
                    btn.classList.remove('bg-gray-100', 'hover:bg-gray-200');
                });
                
                // Add active class to current button
                this.classList.add('active');
                
                // Load tab content
                loadTabContent(tabId, tahunFilter);
                
                // Update URL tanpa reload
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                url.searchParams.set('tahun', tahunFilter);
                window.history.pushState({}, '', url);
                
                // Update global variables
                currentTab = tabId;
                currentYearFilter = tahunFilter;
            });
        });
        
        // Function to load tab content via AJAX
        function loadTabContent(tabId, tahunFilter) {
            // Show loading indicator
            document.getElementById('loading').classList.add('active');
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            
            // Show loading in target tab
            const targetTab = document.getElementById(`tab-${tabId}`);
            if (targetTab) {
                targetTab.style.display = 'block';
            }
            
            // AJAX request
            const formData = new FormData();
            formData.append('tab', tabId);
            formData.append('tahun', tahunFilter);
            formData.append('ajax', 'true');
            
            fetch('ajax/load_cuti_tab.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Hide loading indicator
                document.getElementById('loading').classList.remove('active');
                
                // Update tab content
                if (targetTab) {
                    targetTab.innerHTML = html;
                    targetTab.classList.add('active');
                    
                    // Re-initialize feather icons
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                    
                    // Attach event listeners to new elements
                    attachEventListeners(tabId);
                }
            })
            .catch(error => {
                console.error('Error loading tab content:', error);
                document.getElementById('loading').classList.remove('active');
                
                // Show error message
                if (targetTab) {
                    targetTab.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                            <i data-feather="alert-triangle" class="w-12 h-12 mx-auto text-red-500 mb-4"></i>
                            <p class="text-red-700 font-semibold">Gagal memuat data. Silakan coba lagi.</p>
                        </div>
                    `;
                    targetTab.classList.add('active');
                    feather.replace();
                }
            });
        }
        
        // Function to attach event listeners to dynamic content
        function attachEventListeners(tabId) {
            // Attach event listener to tahun filter select
            const tahunSelect = document.querySelector(`#tab-${tabId} select[name="tahun"]`);
            if (tahunSelect) {
                tahunSelect.addEventListener('change', function() {
                    const tahunValue = this.value;
                    currentYearFilter = tahunValue;
                    
                    // Update URL
                    const url = new URL(window.location);
                    url.searchParams.set('tahun', tahunValue);
                    window.history.pushState({}, '', url);
                    
                    // Reload tab content with new filter
                    loadTabContent(tabId, tahunValue);
                });
            }
            
            // Attach event listener to print buttons
            const printButtons = document.querySelectorAll(`#tab-${tabId} button[onclick^="printRiwayatCuti"]`);
            printButtons.forEach(button => {
                const oldOnClick = button.getAttribute('onclick');
                if (oldOnClick) {
                    button.removeAttribute('onclick');
                    button.addEventListener('click', function() {
                        const match = oldOnClick.match(/printRiwayatCuti\('([^']+)',\s*'([^']*)'\)/);
                        if (match) {
                            const jenis = match[1];
                            const tahunFilter = match[2] || currentYearFilter;
                            printRiwayatCuti(jenis, tahunFilter);
                        }
                    });
                }
            });
        }
        
        // Print functionality
        function printRiwayatCuti(jenis, tahunFilter) {
            const activeTab = document.getElementById(`tab-${jenis}`);
            if (activeTab) {
                const table = activeTab.querySelector('table');
                if (table) {
                    const printWindow = window.open('', '_blank');
                    const tahunText = tahunFilter !== 'all' ? `Tahun ${tahunFilter}` : 'Semua Tahun';
                    printWindow.document.write(`
                        <html>
                        <head>
                            <title>Laporan Riwayat Cuti ${jenis}</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                h1 { text-align: center; margin-bottom: 10px; }
                                h2, h3, h4 { text-align: center; margin: 5px 0; }
                                h4 { margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; }
                                .print-date { text-align: right; margin-top: 30px; }
                                @media print {
                                    body { margin: 0; }
                                }
                            </style>
                        </head>
                        <body>
                            <h1>LAPORAN RIWAYAT CUTI</h1>
                            <h2>${jenis.toUpperCase()}</h2>
                            <h3>${tahunText}</h3>
                            <h4><?= htmlspecialchars($user["nama"]) ?> | NIP: <?= htmlspecialchars($user["nip"]) ?></h4>
                            ${table.outerHTML}
                            <div class="print-date">
                                Dicetak pada: ${new Date().toLocaleDateString('id-ID')}
                            </div>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                }
            }
        }
    </script>
</body>
</html>