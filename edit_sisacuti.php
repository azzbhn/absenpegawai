<?php
require_once 'config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['jabatan'] != 'Administrator') {
    header('Location: index.php');
    exit;
}

$current_year = date('Y');
$success = '';
$error = '';

// Ambil data sisa cuti yang sudah ada
$stmt = $pdo->query('
    SELECT sct.*, p.nama, p.nip 
    FROM sisa_cuti_tahunan sct 
    JOIN pegawai p ON sct.id_pegawai = p.id_pegawai 
    ORDER BY sct.tahun DESC, p.nama ASC
');
$data_sisa_cuti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses edit sisa cuti
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_sisa_cuti'])) {
    $id_sisa_cuti = $_POST['id_sisa_cuti'];
    $sisa_cuti_baru = $_POST['sisa_cuti_baru'];
    
    // Validasi
    if ($sisa_cuti_baru < 0 || $sisa_cuti_baru > 30) {
        $error = 'Sisa cuti harus antara 0-30 hari!';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE sisa_cuti_tahunan SET sisa_cuti = ? WHERE id_sisa_cuti = ?');
            $stmt->execute([$sisa_cuti_baru, $id_sisa_cuti]);
            
            $success = 'Data sisa cuti berhasil diperbarui!';
            
            // Refresh data
            $stmt = $pdo->query('
                SELECT sct.*, p.nama, p.nip 
                FROM sisa_cuti_tahunan sct 
                JOIN pegawai p ON sct.id_pegawai = p.id_pegawai 
                ORDER BY sct.tahun DESC, p.nama ASC
            ');
            $data_sisa_cuti = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sisa Cuti Tahunan - Kecamatan Ajibarang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
      <?php include 'components/header.php'; ?>
    <?php include 'components/navigation.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Edit Sisa Cuti Tahunan</h2>
            <p class="text-gray-600">Edit data sisa cuti tahunan yang sudah tersimpan.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (empty($data_sisa_cuti)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
                <i data-feather="calendar" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                <p class="text-gray-600 font-semibold">Belum ada data sisa cuti</p>
                <p class="text-gray-500 text-sm mt-2">Silakan input data sisa cuti terlebih dahulu melalui menu Input Sisa Cuti Tahunan</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">No.</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Nama Pegawai</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">NIP</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tahun</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Sisa Cuti</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Terakhir Diupdate</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $no = 1; ?>
                            <?php foreach ($data_sisa_cuti as $data): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= $no++ ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($data['nama']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($data['nip']) ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= 
                                            $data['tahun'] == $current_year ? 'bg-green-100 text-green-800' : 
                                            ($data['tahun'] == $current_year - 1 ? 'bg-yellow-100 text-yellow-800' : 
                                            'bg-gray-100 text-gray-800')
                                        ?>">
                                            <?= $data['tahun'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <span class="font-bold <?= 
                                            $data['sisa_cuti'] > 0 ? 'text-green-600' : 'text-red-600'
                                        ?>">
                                            <?= $data['sisa_cuti'] ?> hari
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($data['updated_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button onclick="openEditModal(<?= $data['id_sisa_cuti'] ?>, '<?= htmlspecialchars($data['nama']) ?>', <?= $data['sisa_cuti'] ?>, <?= $data['tahun'] ?>)" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 flex items-center space-x-2">
                                            <i data-feather="edit" class="w-4 h-4"></i>
                                            <span>Edit</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Edit -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-bold text-gray-800 mb-4" id="modalTitle">Edit Sisa Cuti</h3>
                
                <form id="editForm" method="POST" class="space-y-4">
                    <input type="hidden" id="edit_id_sisa_cuti" name="id_sisa_cuti">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pegawai</label>
                        <input type="text" id="edit_nama_pegawai" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
                        <input type="text" id="edit_tahun" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" readonly>
                    </div>
                    
                    <div>
                        <label for="edit_sisa_cuti" class="block text-sm font-medium text-gray-700 mb-2">Sisa Cuti Baru (hari)</label>
                        <input type="number" id="edit_sisa_cuti" name="sisa_cuti_baru" 
                               min="0" max="30" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#F9B000]">
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeEditModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                            Batal
                        </button>
                        <button type="submit" 
                                class="bg-[#F9B000] hover:bg-[#e6a000] text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
        
        function openEditModal(id, nama, sisa_cuti, tahun) {
            document.getElementById('edit_id_sisa_cuti').value = id;
            document.getElementById('edit_nama_pegawai').value = nama;
            document.getElementById('edit_tahun').value = tahun;
            document.getElementById('edit_sisa_cuti').value = sisa_cuti;
            document.getElementById('modalTitle').textContent = `Edit Sisa Cuti - ${nama} (${tahun})`;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const sisaCuti = document.getElementById('edit_sisa_cuti').value;
            
            if (sisaCuti < 0 || sisaCuti > 30) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Nilai tidak valid',
                    text: 'Sisa cuti harus antara 0-30 hari!',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
</body>
</html>