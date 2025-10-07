<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

// Koordinat kantor
$kantor_lat = KANTOR_LAT;
$kantor_lng = KANTOR_LNG;
$radius = RADIUS_METER;

// Cek apakah sudah absen hari ini - GUNAKAN WAKTU SERVER
$today = date('Y-m-d');
$current_time = date('H:i:s');

$stmt = $pdo->prepare('SELECT * FROM absensi WHERE id_pegawai = ? AND tanggal = ?');
$stmt->execute([$user['id_pegawai'], $today]);
$absensi_hari_ini = $stmt->fetch(PDO::FETCH_ASSOC);

$sudah_absen_masuk = $absensi_hari_ini && $absensi_hari_ini['jam_masuk'] != null;
$sudah_absen_pulang = $absensi_hari_ini && $absensi_hari_ini['jam_keluar'] != null;

// Proses absensi - GUNAKAN WAKTU SERVER
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];

    // Validasi lokasi
    $jarak = hitungJarak($lat, $lng, $kantor_lat, $kantor_lng);
    
    if ($jarak <= $radius) {
        if ($action == 'masuk' && !$sudah_absen_masuk) {
            // GUNAKAN WAKTU SERVER PHP
            $waktu_sekarang = date('H:i:s');
            $stmt = $pdo->prepare('INSERT INTO absensi (id_pegawai, tanggal, jam_masuk, lokasi_lat, lokasi_lng, status) VALUES (?, ?, ?, ?, ?, "hadir")');
            $stmt->execute([$user['id_pegawai'], $today, $waktu_sekarang, $lat, $lng]);
            
            // Set session success dan redirect untuk menghindari resubmission
            $_SESSION['success'] = 'Absen masuk berhasil pukul ' . $waktu_sekarang . '!';
            header('Location: absen.php');
            exit;
            
        } elseif ($action == 'pulang' && $sudah_absen_masuk && !$sudah_absen_pulang) {
            // GUNAKAN WAKTU SERVER PHP
            $waktu_sekarang = date('H:i:s');
            $stmt = $pdo->prepare('UPDATE absensi SET jam_keluar = ? WHERE id_pegawai = ? AND tanggal = ?');
            $stmt->execute([$waktu_sekarang, $user['id_pegawai'], $today]);
            
            // Set session success dan redirect untuk menghindari resubmission
            $_SESSION['success'] = 'Absen pulang berhasil pukul ' . $waktu_sekarang . '!';
            header('Location: absen.php');
            exit;
            
        } else {
            $_SESSION['error'] = 'Anda sudah absen masuk/pulang hari ini.';
            header('Location: absen.php');
            exit;
        }
    } else {
        $_SESSION['error'] = 'Anda berada di luar area kantor Kecamatan Ajibarang. Jarak: ' . round($jarak, 2) . ' meter.';
        header('Location: absen.php');
        exit;
    }
}

// Ambil pesan dari session
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Hapus pesan dari session setelah ditampilkan
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        #map {
            height: 300px;
            width: 100%;
            border-radius: 12px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-[#F9B000] text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <img src="assets/logo.png" alt="Logo" class="w-12 h-12">
                    <div>
                        <h1 class="text-xl font-bold">Sistem Absensi</h1>
                        <p class="text-white/90 text-sm">Kecamatan Ajibarang</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="text-white/80 text-sm"><?= htmlspecialchars($user['jabatan']) ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-[#1F9D55] text-white shadow-md">
        <div class="container mx-auto px-4">
            <div class="flex space-x-8">
                <a href="dashboard.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="absen.php" class="py-3 px-2 border-b-2 border-white font-semibold flex items-center space-x-2">
                    <i data-feather="clock"></i>
                    <span>Absensi</span>
                </a>
                <?php if ($user['jabatan'] == 'Administrator'): ?>
                <a href="data_absensi.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2">
                    <i data-feather="file-text"></i>
                    <span>Data Absensi</span>
                </a>
                <?php endif; ?>
                <a href="logout.php" class="py-3 px-2 hover:bg-[#188a4a] transition duration-200 flex items-center space-x-2 ml-auto">
                    <i data-feather="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Absensi Pegawai</h2>
            <p class="text-gray-600">Silakan lakukan absensi masuk dan pulang di halaman ini.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Form Absensi -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Form Absensi</h3>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="space-y-4 mb-6">
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Nama:</span>
                        <span><?= htmlspecialchars($user['nama']) ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Tanggal Server:</span>
                        <span><?= date('d/m/Y') ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Waktu Server (WIB):</span>
                        <span id="server-time"><?= date('H:i:s') ?></span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Status Absen Masuk:</span>
                        <span class="<?= $sudah_absen_masuk ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                            <?= $sudah_absen_masuk ? 'SUDAH' : 'BELUM' ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                        <span class="font-semibold">Status Absen Pulang:</span>
                        <span class="<?= $sudah_absen_pulang ? 'text-green-600' : 'text-red-600' ?> font-semibold">
                            <?= $sudah_absen_pulang ? 'SUDAH' : 'BELUM' ?>
                        </span>
                    </div>
                </div>

                <form method="POST" id="form-absen">
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                    
                    <div class="space-y-4">
                        <?php if (!$sudah_absen_masuk): ?>
                        <button type="submit" name="action" value="masuk" 
                                class="w-full bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                            <i data-feather="log-in"></i>
                            <span>Absen Masuk</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($sudah_absen_masuk && !$sudah_absen_pulang): ?>
                        <button type="submit" name="action" value="pulang" 
                                class="w-full bg-[#C1272D] hover:bg-[#a82025] text-white font-bold py-4 px-6 rounded-lg transition duration-200 transform hover:scale-105 flex items-center justify-center space-x-2">
                            <i data-feather="log-out"></i>
                            <span>Absen Pulang</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">Informasi Lokasi:</h4>
                    <p class="text-blue-700 text-sm" id="location-info">Mengambil lokasi...</p>
                    <p class="text-blue-700 text-sm" id="distance-info">Menghitung jarak...</p>
                </div>
            </div>

            <!-- Peta Lokasi -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Peta Lokasi</h3>
                <div id="map"></div>
                <div class="mt-4 text-sm text-gray-600">
                    <p><span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span> Lokasi Kantor</p>
                    <p><span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span> Lokasi Anda</p>
                    <p class="mt-2">Pastikan Anda berada dalam radius 100 meter dari kantor untuk dapat melakukan absensi.</p>
                </div>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Update waktu server setiap detik - FIXED untuk WIB
        function updateServerTime() {
            // Buat request ke server untuk mendapatkan waktu server yang akurat
            fetch('get_server_time.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('server-time').textContent = data.time;
                    }
                })
                .catch(error => {
                    console.error('Error fetching server time:', error);
                    // Fallback: update dengan waktu client + offset WIB
                    const now = new Date();
                    const wibTime = new Date(now.getTime() + (7 * 60 * 60 * 1000));
                    document.getElementById('server-time').textContent = wibTime.toLocaleTimeString('id-ID', {
                        hour12: false,
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                });
        }
        
        // Update setiap detik
        setInterval(updateServerTime, 1000);
        updateServerTime();

        // Initialize map
        const map = L.map('map').setView([<?= $kantor_lat ?>, <?= $kantor_lng ?>], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Tambahkan marker untuk kantor
        const kantorMarker = L.marker([<?= $kantor_lat ?>, <?= $kantor_lng ?>]).addTo(map)
            .bindPopup('Kantor Kecamatan Ajibarang')
            .openPopup();

        // Tambahkan circle untuk radius
        const radiusCircle = L.circle([<?= $kantor_lat ?>, <?= $kantor_lng ?>], {
            color: 'green',
            fillColor: '#1F9D55',
            fillOpacity: 0.2,
            radius: <?= $radius ?>
        }).addTo(map);

        let userMarker;
        let watchId;

        // Fungsi untuk mendapatkan lokasi pengguna
        function getLocation() {
            if (navigator.geolocation) {
                watchId = navigator.geolocation.watchPosition(
                    showPosition,
                    showError,
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                document.getElementById('location-info').textContent = 'Geolocation tidak didukung oleh browser ini.';
            }
        }

        function showPosition(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            // Update form inputs
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
            
            // Update location info
            document.getElementById('location-info').textContent = 
                `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)} (Akurasi: ${Math.round(accuracy)}m)`;
            
            // Hitung dan tampilkan jarak
            const distance = calculateDistance(lat, lng, <?= $kantor_lat ?>, <?= $kantor_lng ?>);
            const statusElement = document.getElementById('distance-info');
            statusElement.textContent = `Jarak dari kantor: ${distance.toFixed(2)} meter`;
            
            if (distance <= <?= $radius ?>) {
                statusElement.className = 'text-green-700 text-sm font-semibold';
            } else {
                statusElement.className = 'text-red-700 text-sm font-semibold';
            }
            
            // Update atau buat marker pengguna
            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
            } else {
                userMarker = L.marker([lat, lng]).addTo(map)
                    .bindPopup('Lokasi Anda');
            }
            
            // Adjust map view
            const group = new L.featureGroup([kantorMarker, userMarker]);
            map.fitBounds(group.getBounds().pad(0.1));
        }

        function showError(error) {
            let message = 'Error mengambil lokasi: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Akses lokasi ditolak. Izinkan akses lokasi untuk absensi.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Informasi lokasi tidak tersedia.';
                    break;
                case error.TIMEOUT:
                    message += 'Permintaan lokasi timeout.';
                    break;
                case error.UNKNOWN_ERROR:
                    message += 'Terjadi kesalahan tidak diketahui.';
                    break;
            }
            document.getElementById('location-info').textContent = message;
        }

        // Fungsi untuk menghitung jarak
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
                Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Event listener untuk form submission
        document.getElementById('form-absen').addEventListener('submit', function(e) {
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;
            
            if (!lat || !lng) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Lokasi Tidak Ditemukan',
                    text: 'Tidak dapat mengambil lokasi GPS. Pastikan browser mengizinkan akses lokasi.',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            const distance = calculateDistance(lat, lng, <?= $kantor_lat ?>, <?= $kantor_lng ?>);
            if (distance > <?= $radius ?>) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Lokasi Diluar Area',
                    text: `Anda berada ${distance.toFixed(2)} meter dari kantor. Harus dalam radius <?= $radius ?> meter.`,
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Tampilkan loading
            Swal.fire({
                title: 'Memproses Absensi...',
                text: 'Sedang menyimpan data ke server',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        // Initialize
        getLocation();
        feather.replace();

        // Cleanup
        window.addEventListener('beforeunload', function() {
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
            }
        });

        <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '<?= $success ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if ($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '<?= $error ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>
    </script>
</body>
</html>