<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Koneksi database
$host = "localhost";
$username = "root";
$password = "";
$database = "db_perpustakaan";

// Buat koneksi tanpa database dulu
$conn = new mysqli($host, $username, $password);

// Cek koneksi
if ($conn->connect_error) {
    die(json_encode(["error" => "Koneksi MySQL gagal: " . $conn->connect_error]));
}

// Buat database jika belum ada
$conn->query("CREATE DATABASE IF NOT EXISTS $database");
$conn->select_db($database);

// Buat tabel jika belum ada
function createTables($conn) {
    // Tabel Anggota
    $conn->query("CREATE TABLE IF NOT EXISTS anggota (
        id_anggota INT PRIMARY KEY AUTO_INCREMENT,
        nomor_anggota VARCHAR(20) UNIQUE,
        nama VARCHAR(100) NOT NULL,
        alamat TEXT,
        telepon VARCHAR(15),
        email VARCHAR(100),
        tanggal_daftar DATE,
        status ENUM('Aktif', 'Non-Aktif', 'Diblokir') DEFAULT 'Aktif'
    )");
    
    // Tabel Kategori
    $conn->query("CREATE TABLE IF NOT EXISTS kategori (
        id_kategori INT PRIMARY KEY AUTO_INCREMENT,
        nama_kategori VARCHAR(50) NOT NULL,
        deskripsi TEXT
    )");
    
    // Tabel Penerbit
    $conn->query("CREATE TABLE IF NOT EXISTS penerbit (
        id_penerbit INT PRIMARY KEY AUTO_INCREMENT,
        nama_penerbit VARCHAR(100) NOT NULL,
        alamat TEXT,
        telepon VARCHAR(15),
        email VARCHAR(100)
    )");
    
    // Tabel Buku
    $conn->query("CREATE TABLE IF NOT EXISTS buku (
        id_buku INT PRIMARY KEY AUTO_INCREMENT,
        isbn VARCHAR(20) UNIQUE,
        judul VARCHAR(200) NOT NULL,
        id_kategori INT,
        id_penerbit INT,
        tahun_terbit INT,
        stok INT DEFAULT 0,
        rak_lokasi VARCHAR(50)
    )");
    
    // Tabel Peminjaman
    $conn->query("CREATE TABLE IF NOT EXISTS peminjaman (
        id_peminjaman INT PRIMARY KEY AUTO_INCREMENT,
        nomor_peminjaman VARCHAR(20) UNIQUE,
        id_anggota INT,
        tanggal_pinjam DATE,
        tanggal_kembali_rencana DATE,
        tanggal_kembali_aktual DATE,
        status ENUM('Dipinjam', 'Dikembalikan', 'Terlambat') DEFAULT 'Dipinjam'
    )");
    
    // Tabel Detail Peminjaman
    $conn->query("CREATE TABLE IF NOT EXISTS detail_peminjaman (
        id_detail INT PRIMARY KEY AUTO_INCREMENT,
        id_peminjaman INT,
        id_buku INT,
        jumlah INT DEFAULT 1,
        denda DECIMAL(10,2) DEFAULT 0
    )");
    
    // Tambahkan data sample jika tabel kosong
    addSampleData($conn);
}

// Tambah data sample
function addSampleData($conn) {
    // Cek jika tabel anggota kosong
    $result = $conn->query("SELECT COUNT(*) as total FROM anggota");
    $row = $result->fetch_assoc();
    
    if ($row['total'] == 0) {
        // Data sample kategori
        $conn->query("INSERT IGNORE INTO kategori (id_kategori, nama_kategori, deskripsi) VALUES 
            (1, 'Teknologi', 'Buku tentang teknologi dan programming'),
            (2, 'Fiksi', 'Novel dan cerita fiksi'),
            (3, 'Sains', 'Buku ilmu pengetahuan')");
        
        // Data sample penerbit
        $conn->query("INSERT IGNORE INTO penerbit (id_penerbit, nama_penerbit, alamat, telepon, email) VALUES 
            (1, 'Penerbit Informatika', 'Jakarta', '021-123456', 'info@informatika.com'),
            (2, 'Gramedia Pustaka', 'Bandung', '022-654321', 'contact@gramedia.com')");
        
        // Data sample buku
        $conn->query("INSERT IGNORE INTO buku (isbn, judul, id_kategori, id_penerbit, tahun_terbit, stok, rak_lokasi) VALUES 
            ('ISBN001', 'Pemrograman Python untuk Pemula', 1, 1, 2023, 10, 'Rak A1'),
            ('ISBN002', 'Novel Romantis', 2, 2, 2022, 5, 'Rak B2')");
        
        error_log("Data sample berhasil ditambahkan");
    }
}

// Panggil fungsi create tables
createTables($conn);

function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
?>