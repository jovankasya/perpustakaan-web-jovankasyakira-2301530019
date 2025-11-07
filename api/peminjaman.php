<?php
include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = $conn->real_escape_string($_GET['id']);
            $result = $conn->query("
                SELECT p.*, a.nama as nama_anggota 
                FROM peminjaman p 
                JOIN anggota a ON p.id_anggota = a.id_anggota 
                WHERE p.id_peminjaman = $id
            ");
            $peminjaman = $result->fetch_assoc();
            
            $detail_result = $conn->query("
                SELECT dp.*, b.judul 
                FROM detail_peminjaman dp 
                JOIN buku b ON dp.id_buku = b.id_buku 
                WHERE dp.id_peminjaman = $id
            ");
            $detail = [];
            while ($row = $detail_result->fetch_assoc()) {
                $detail[] = $row;
            }
            $peminjaman['detail_buku'] = $detail;
            
            response($peminjaman ?: ["error" => "Peminjaman tidak ditemukan"], $peminjaman ? 200 : 404);
        } else {
            $result = $conn->query("
                SELECT p.*, a.nama as nama_anggota 
                FROM peminjaman p 
                JOIN anggota a ON p.id_anggota = a.id_anggota 
                ORDER BY p.id_peminjaman DESC
            ");
            $peminjaman = [];
            while ($row = $result->fetch_assoc()) {
                $peminjaman[] = $row;
            }
            response($peminjaman);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        $nomor_peminjaman = "PMJ" . date('YmdHis');
        $id_anggota = $conn->real_escape_string($data['id_anggota']);
        $buku_list = $data['buku_list'];
        
        $conn->begin_transaction();
        
        try {
            $query_peminjaman = "INSERT INTO peminjaman (nomor_peminjaman, id_anggota, tanggal_pinjam, tanggal_kembali_rencana) 
                                VALUES ('$nomor_peminjaman', $id_anggota, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY))";
            $conn->query($query_peminjaman);
            $id_peminjaman = $conn->insert_id;
            
            foreach ($buku_list as $buku) {
                $id_buku = $conn->real_escape_string($buku['id_buku']);
                $jumlah = $conn->real_escape_string($buku['jumlah']);
                
                $query_detail = "INSERT INTO detail_peminjaman (id_peminjaman, id_buku, jumlah) 
                               VALUES ($id_peminjaman, $id_buku, $jumlah)";
                $conn->query($query_detail);
                
                $query_stok = "UPDATE buku SET stok = stok - $jumlah WHERE id_buku = $id_buku";
                $conn->query($query_stok);
            }
            
            $conn->commit();
            response(["message" => "Peminjaman berhasil", "nomor_peminjaman" => $nomor_peminjaman]);
            
        } catch (Exception $e) {
            $conn->rollback();
            response(["error" => "Gagal memproses peminjaman: " . $e->getMessage()], 500);
        }
        break;
}
?>