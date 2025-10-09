-- Buat database
CREATE DATABASE absensi_ajibarang;
USE absensi_ajibarang;

-- Tabel pegawai
CREATE TABLE pegawai (
    id_pegawai INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nip VARCHAR(20) NOT NULL UNIQUE,
    jabatan VARCHAR(50) NOT NULL,
    no_whatsapp VARCHAR(15),
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Tabel absensi
CREATE TABLE absensi (
    id_absen INT AUTO_INCREMENT PRIMARY KEY,
    id_pegawai INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME,
    jam_keluar TIME,
    lokasi_lat DECIMAL(10, 8),
    lokasi_lng DECIMAL(11, 8),
    status ENUM('hadir', 'izin', 'sakit', 'dinas luar') DEFAULT 'hadir',
    FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai)
);

-- Insert data admin contoh
INSERT INTO pegawai (nama, nip, jabatan, no_whatsapp, username, password) 
VALUES ('Admin Kecamatan', '198304102006041001', 'Administrator', '08123456789', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert data pegawai contoh
INSERT INTO pegawai (nama, nip, jabatan, no_whatsapp, username, password) 
VALUES ('Budi Santoso', '198504112008041002', 'Staf', '08129876543', 'budi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Tambah kolom
ALTER TABLE pegawai ADD COLUMN jatah_cuti_tahunan INT DEFAULT 12;

-- Tabel Pengajuan Cuti
CREATE TABLE pengajuan_cuti (
    id_pengajuan INT AUTO_INCREMENT PRIMARY KEY,
    id_pegawai INT NOT NULL,
    jenis_cuti ENUM('tahunan', 'sakit') NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    link_data_dukung VARCHAR(500),
    status ENUM('pending', 'disetujui', 'ditolak') DEFAULT 'pending',
    tanggal_pengajuan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pegawai) REFERENCES pegawai(id_pegawai)
);

-- contoh update data jatah cuti
UPDATE pegawai SET jatah_cuti_tahunan = 12 WHERE id_pegawai = 1;
UPDATE pegawai SET jatah_cuti_tahunan = 12 WHERE id_pegawai = 2;

-- Update enum status di tabel absensi untuk menambahkan cuti tahunan dan cuti sakit
ALTER TABLE absensi MODIFY status ENUM('hadir', 'izin', 'sakit', 'dinas luar', 'cuti tahunan', 'cuti sakit');

-- Tambahkan kolom jumlah_hari ke tabel pengajuan_cuti
ALTER TABLE pengajuan_cuti ADD COLUMN jumlah_hari INT DEFAULT 0;

-- Update data yang sudah ada (untuk cuti tahunan hitung hari kerja, untuk cuti sakit semua hari)
UPDATE pengajuan_cuti 
SET jumlah_hari = CASE 
    WHEN jenis_cuti = 'tahunan' THEN 
        (SELECT COUNT(*) 
         FROM (
             SELECT ADDDATE(tanggal_mulai, INTERVAL n DAY) as tanggal
             FROM (
                 SELECT a.N + b.N * 10 + 1 as n
                 FROM (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                      (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
             ) numbers
             WHERE ADDDATE(tanggal_mulai, INTERVAL n DAY) <= tanggal_selesai
         ) dates
         WHERE DAYOFWEEK(dates.tanggal) NOT IN (1, 7))
    ELSE 
        DATEDIFF(tanggal_selesai, tanggal_mulai) + 1
    END;