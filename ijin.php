<?php
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kendali Izin - Absensi Kecamatan Ajibarang</title>
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .construction-card {
            transition: all 0.3s ease;
        }
        .animate-bounce-slow {
            animation: bounce 3s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(-5%); animation-timing-function: cubic-bezier(0.8, 0, 1, 1); }
            50% { transform: translateY(0); animation-timing-function: cubic-bezier(0, 0, 0.2, 1); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-12">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 border-l-8 border-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                        <i data-feather="info" class="text-yellow-600 w-8 h-8"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Kendali Izin Pegawai</h2>
                        <p class="text-gray-600">Fitur permohonan dan riwayat izin operasional</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-xl p-12 text-center construction-card">
                <div class="mb-8 flex justify-center">
                    <div class="relative">
                        <i data-feather="tool" class="w-24 h-24 text-gray-200 absolute -top-4 -left-4"></i>
                        <i data-feather="settings" class="w-32 h-32 text-yellow-500 animate-spin-slow" style="animation-duration: 10s;"></i>
                    </div>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-800 mb-4">Halaman Sedang Dikembangkan</h1>
                <p class="text-gray-500 text-lg mb-8 max-w-md mx-auto">
                    Mohon maaf, fitur <strong>Kendali Izin</strong> saat ini sedang dalam tahap pengerjaan oleh tim IT untuk memberikan pengalaman terbaik bagi Anda. Untuk mengajukan cuti silahkan hubungi <strong>Pengelola Kepegawaian</strong>.
                </p>

                <div class="flex flex-wrap justify-center gap-4">
                    <a href="dashboard.php" class="inline-flex items-center px-6 py-3 bg-gray-800 text-white font-semibold rounded-xl hover:bg-gray-700 transition shadow-lg">
                        <i data-feather="arrow-left" class="w-5 h-5 mr-2"></i>
                        Kembali ke Dashboard
                    </a>
                    <a href="cuti.php" class="inline-flex items-center px-6 py-3 bg-yellow-500 text-white font-semibold rounded-xl hover:bg-yellow-600 transition shadow-lg">
                        <i data-feather="calendar" class="w-5 h-5 mr-2"></i>
                        Lihat Data Cuti
                    </a>
                </div>

                <div class="mt-12 pt-8 border-t border-gray-100">
                    <div class="flex items-center justify-center space-x-2 text-sm text-gray-400">
                        <span class="flex h-2 w-2 rounded-full bg-green-500"></span>
                        <span>Estimasi selesai: Segera</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>

    <script>
        // Initialize Feather Icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    </script>
</body>
</html>