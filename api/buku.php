<?php
include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = $conn->real_escape_string($_GET['id']);
            $result = $conn->query("
                SELECT b.*, k.nama_kategori, p.nama_penerbit 
                FROM buku b 
                LEFT JOIN kategori k ON b.id_kategori = k.id_kategori 
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit 
                WHERE b.id_buku = $id
            ");
            $buku = $result->fetch_assoc();
            response($buku ?: ["error" => "Buku tidak ditemukan"], $buku ? 200 : 404);
        } else if (isset($_GET['search'])) {
            $search = $conn->real_escape_string($_GET['search']);
            $result = $conn->query("
                SELECT b.*, k.nama_kategori, p.nama_penerbit 
                FROM buku b 
                LEFT JOIN kategori k ON b.id_kategori = k.id_kategori 
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit 
                WHERE b.judul LIKE '%$search%' OR b.isbn LIKE '%$search%'
            ");
            $buku = [];
            while ($row = $result->fetch_assoc()) {
                $buku[] = $row;
            }
            response($buku);
        } else {
            $result = $conn->query("
                SELECT b.*, k.nama_kategori, p.nama_penerbit 
                FROM buku b 
                LEFT JOIN kategori k ON b.id_kategori = k.id_kategori 
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit 
                ORDER BY b.id_buku DESC
            ");
            $buku = [];
            while ($row = $result->fetch_assoc()) {
                $buku[] = $row;
            }
            response($buku);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $isbn = $conn->real_escape_string($data['isbn']);
        $judul = $conn->real_escape_string($data['judul']);
        $id_kategori = $conn->real_escape_string($data['id_kategori']);
        $id_penerbit = $conn->real_escape_string($data['id_penerbit']);
        $tahun_terbit = $conn->real_escape_string($data['tahun_terbit']);
        $stok = $conn->real_escape_string($data['stok']);
        $rak_lokasi = $conn->real_escape_string($data['rak_lokasi']);
        
        $query = "INSERT INTO buku (isbn, judul, id_kategori, id_penerbit, tahun_terbit, stok, rak_lokasi) 
                  VALUES ('$isbn', '$judul', $id_kategori, $id_penerbit, $tahun_terbit, $stok, '$rak_lokasi')";
        
        if ($conn->query($query)) {
            response(["message" => "Buku berhasil ditambahkan", "id" => $conn->insert_id]);
        } else {
            response(["error" => "Gagal menambah buku: " . $conn->error], 500);
        }
        break;
}
?>