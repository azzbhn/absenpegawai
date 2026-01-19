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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGMA - Sistem Informasi Geotagging untuk Monitoring Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Tambahkan Font Awesome untuk ikon bell -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
            background: linear-gradient(135deg, #ff3333 0%, #33ff33 50%, #3333ff 100%);
            background-attachment: fixed;
        }
        
        .dots-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }
        
        .dot {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.8);
            pointer-events: none;
            transform: translate(-50%, -50%);
            transition: transform 0.3s ease-out;
            mix-blend-mode: screen;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
        }
        
        .header-glass {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 10;
        }
        
        .menu-item {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #ff3333, #33ff33, #3333ff);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .menu-item:hover::after {
            width: 80%;
        }
        
        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .logout-item:hover {
            background: linear-gradient(135deg, rgba(255, 51, 51, 0.3), rgba(255, 0, 0, 0.5));
        }
        
        .menu-container {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        .logo-glow {
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
        }

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
            animation: marquee 40s linear infinite;
            display: flex;
            width: max-content;
        }
        
        .animate-marquee:hover {
            animation-play-state: paused;
        }
        
        /* Efek gradien di sisi kiri dan kanan */
        .marquee-container {
            position: relative;
            overflow: hidden;
        }
        
        .marquee-container::before,
        .marquee-container::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 60px;
            z-index: 2;
            pointer-events: none;
        }
        
        .marquee-container::before {
            left: 0;
            background: linear-gradient(to right, rgba(31, 157, 85, 0.8), transparent);
        }
        
        .marquee-container::after {
            right: 0;
            background: linear-gradient(to left, rgba(20, 122, 64, 0.8), transparent);
        }
        
        /* Responsive adjustments */
        @media (max-width: 640px) {
            .animate-marquee {
                animation-duration: 50s;
            }
            
            .marquee-container::before,
            .marquee-container::after {
                width: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Container untuk titik-titik dinamis -->
    <div id="dots-container" class="dots-container"></div>

    <!-- Header -->
    <header class="header-glass text-white no-print">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <img src="assets/logo.png" alt="Logo" class="w-12 h-12 rounded-full border-2 border-white/30 logo-glow">
                    <div>
                        <h1 class="text-2xl font-bold tracking-wider text-white">S I G M A</h1>
                        <p class="text-sm text-white/80">Sistem Informasi Geotagging untuk Monitoring Absensi - Kecamatan Ajibarang</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-semibold text-lg text-white"><?= htmlspecialchars($user['nama']) ?></p>
                    <p class="text-white/70 text-sm"><?= htmlspecialchars($user['jabatan']) ?></p>
                </div>
            </div>
        </div>
        
        <!-- RUNNING TEXT PENGINGAT SERAGAM -->
        <?php
        // Inisialisasi variabel untuk running text
        $reminderText = null;
        
        // Coba koneksi database langsung (tanpa config file)
        try {
            // Koneksi langsung dengan informasi yang Anda berikan
            $host = "localhost";
            $username = "fgqqlzxt_absen_kec_root";
            $password = "aLk.25474!";
            $database = "fgqqlzxt_absen_kec_db";
            
            $conn = new mysqli($host, $username, $password, $database);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // Set timezone untuk Indonesia
            date_default_timezone_set('Asia/Jakarta');
            
            $now = new DateTime('now');
            $currentHour = (int)$now->format('H');
            $currentMinute = (int)$now->format('i');
            
            // Logika waktu:
            // 14:30 - 23:59: pengingat untuk esok hari
            // 00:00 - 14:29: pengingat untuk hari ini
            $isReminderTime = false;
            $isTomorrowReminder = false;
            
            if ($currentHour >= 14 && $currentMinute >= 30) {
                // Waktu pengingat untuk esok hari (14:30 - 23:59)
                $isReminderTime = true;
                $isTomorrowReminder = true;
                $targetDate = clone $now;
                $targetDate->modify('+1 day');
            } elseif ($currentHour < 14 || ($currentHour == 14 && $currentMinute < 30)) {
                // Waktu pengingat untuk hari ini (00:00 - 14:29)
                $isReminderTime = true;
                $isTomorrowReminder = false;
                $targetDate = clone $now;
            }
            
            if ($isReminderTime) {
                $formattedDate = $targetDate->format('Y-m-d');
                
                $stmt = $conn->prepare("SELECT jenis_seragam FROM seragam_kerja WHERE tanggal = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $formattedDate);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        $uniform = $row['jenis_seragam'];
                        
                        // Format tanggal dalam bahasa Indonesia
                        $dayNames = [
                            'Sunday' => 'Minggu',
                            'Monday' => 'Senin',
                            'Tuesday' => 'Selasa',
                            'Wednesday' => 'Rabu',
                            'Thursday' => 'Kamis',
                            'Friday' => 'Jumat',
                            'Saturday' => 'Sabtu'
                        ];
                        
                        $monthNames = [
                            'January' => 'Januari',
                            'February' => 'Februari',
                            'March' => 'Maret',
                            'April' => 'April',
                            'May' => 'Mei',
                            'June' => 'Juni',
                            'July' => 'Juli',
                            'August' => 'Agustus',
                            'September' => 'September',
                            'October' => 'Oktober',
                            'November' => 'November',
                            'December' => 'Desember'
                        ];
                        
                        $englishDay = $targetDate->format('l');
                        $englishMonth = $targetDate->format('F');
                        
                        $indonesianDay = $dayNames[$englishDay] ?? $englishDay;
                        $indonesianMonth = $monthNames[$englishMonth] ?? $englishMonth;
                        
                        $formattedDateDisplay = $indonesianDay . ', ' . $targetDate->format('d') . ' ' . $indonesianMonth . ' ' . $targetDate->format('Y');
                        
                        if ($isTomorrowReminder) {
                            $reminderText = "📢 Pengingat: Untuk besok {$formattedDateDisplay}, gunakan {$uniform}";
                        } else {
                            $reminderText = "📢 Pengingat: Untuk hari ini {$formattedDateDisplay}, gunakan {$uniform}";
                        }
                    }
                    
                    $stmt->close();
                }
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            // Tidak tampilkan error jika gagal
            $reminderText = null;
        }
        
        // Tampilkan running text jika ada
        if ($reminderText): ?>
        <div class="w-full bg-gradient-to-r from-yellow-600/20 via-yellow-500/20 to-yellow-600/20 backdrop-blur-sm border-t border-b border-yellow-500/30">
            <div class="py-1.5">
                <div class="marquee-container">
                    <div class="flex whitespace-nowrap animate-marquee">
                        <!-- Teks berjalan (loop 5x untuk efek yang lebih smooth) -->
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <span class="inline-flex items-center text-yellow-200 font-medium text-sm md:text-base px-4">
                            <i class="fas fa-bell mr-2 text-yellow-400"></i>
                            <?php echo htmlspecialchars($reminderText); ?>
                        </span>
                        <span class="inline-block text-yellow-400 px-4">•</span>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- END RUNNING TEXT -->
        
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dotsContainer = document.getElementById('dots-container');
            const dotsCount = 80; // Jumlah titik lebih banyak
            
            // Warna-warna yang sesuai dengan gradasi background
            const dotColors = [
                'rgba(255, 100, 100, 0.8)',   // Merah muda
                'rgba(100, 255, 100, 0.8)',   // Hijau muda
                'rgba(100, 100, 255, 0.8)',   // Biru muda
                'rgba(255, 255, 255, 0.9)'    // Putih
            ];
            
            // Membuat titik-titik
            for (let i = 0; i < dotsCount; i++) {
                const dot = document.createElement('div');
                dot.classList.add('dot');
                
                // Random size antara 3px sampai 15px
                const size = Math.random() * 12 + 3;
                dot.style.width = `${size}px`;
                dot.style.height = `${size}px`;
                
                // Random position
                dot.style.left = `${Math.random() * 100}%`;
                dot.style.top = `${Math.random() * 100}%`;
                
                // Random color dari array warna
                const colorIndex = Math.floor(Math.random() * dotColors.length);
                dot.style.backgroundColor = dotColors[colorIndex];
                
                // Random opacity
                dot.style.opacity = Math.random() * 0.7 + 0.3;
                
                // Simpan posisi awal sebagai atribut data
                dot.dataset.originalX = dot.style.left;
                dot.dataset.originalY = dot.style.top;
                
                dotsContainer.appendChild(dot);
            }
            
            let mouseX = 0;
            let mouseY = 0;
            let mouseSpeed = 0;
            let lastMouseX = 0;
            let lastMouseY = 0;
            let lastTime = Date.now();
            
            // Track mouse movement untuk menghitung kecepatan
            document.addEventListener('mousemove', function(e) {
                const now = Date.now();
                const timeDiff = now - lastTime;
                
                if (timeDiff > 0) {
                    mouseSpeed = Math.sqrt(
                        Math.pow(e.clientX - lastMouseX, 2) + 
                        Math.pow(e.clientY - lastMouseY, 2)
                    ) / timeDiff * 16; // Normalize speed
                    
                    mouseSpeed = Math.min(mouseSpeed, 5); // Batasi kecepatan maksimum
                }
                
                mouseX = e.clientX;
                mouseY = e.clientY;
                lastMouseX = e.clientX;
                lastMouseY = e.clientY;
                lastTime = now;
                
                updateDots();
            });
            
            function updateDots() {
                const dots = document.querySelectorAll('.dot');
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;
                
                dots.forEach(dot => {
                    // Dapatkan posisi asli
                    const originalX = parseFloat(dot.dataset.originalX);
                    const originalY = parseFloat(dot.dataset.originalY);
                    
                    // Hitung posisi pixel asli
                    const dotX = originalX * windowWidth / 100;
                    const dotY = originalY * windowHeight / 100;
                    
                    // Hitung jarak dari mouse
                    const dx = mouseX - dotX;
                    const dy = mouseY - dotY;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    // Area pengaruh mouse
                    const influenceRadius = 200 + (mouseSpeed * 50); // Radius bertambah dengan kecepatan mouse
                    
                    if (distance < influenceRadius) {
                        // Hitung kekuatan dorongan berdasarkan jarak dan kecepatan mouse
                        const force = (1 - distance / influenceRadius) * (1 + mouseSpeed * 0.5);
                        const pushDistance = force * 40; // Jarak maksimum dorongan
                        
                        // Hitung sudut dorongan (menjauh dari mouse)
                        const angle = Math.atan2(dy, dx);
                        
                        // Hitung posisi baru
                        const newX = dotX - Math.cos(angle) * pushDistance;
                        const newY = dotY - Math.sin(angle) * pushDistance;
                        
                        // Konversi kembali ke persentase
                        const newXPercent = (newX / windowWidth) * 100;
                        const newYPercent = (newY / windowHeight) * 100;
                        
                        // Terapkan posisi baru dengan transition
                        dot.style.left = `${newXPercent}%`;
                        dot.style.top = `${newYPercent}%`;
                    } else {
                        // Kembali ke posisi asli dengan transition yang lebih halus
                        const currentX = parseFloat(dot.style.left);
                        const currentY = parseFloat(dot.style.top);
                        
                        // Interpolasi ke posisi asli
                        const speed = 0.1;
                        const newX = currentX + (originalX - currentX) * speed;
                        const newY = currentY + (originalY - currentY) * speed;
                        
                        dot.style.left = `${newX}%`;
                        dot.style.top = `${newY%`;
                    }
                });
                
                requestAnimationFrame(updateDots);
            }
            
            // Mulai animasi
            updateDots();
            
            // Reset posisi jika window di-resize
            window.addEventListener('resize', function() {
                const dots = document.querySelectorAll('.dot');
                dots.forEach(dot => {
                    dot.style.left = dot.dataset.originalX;
                    dot.style.top = dot.dataset.originalY;
                });
            });
        });
    </script>
</body>
</html>