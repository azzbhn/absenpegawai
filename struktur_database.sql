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