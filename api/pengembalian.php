<?php
include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $nomor_peminjaman = $conn->real_escape_string($data['nomor_peminjaman']);
        
        $conn->begin_transaction();
        
        try {
            $result = $conn->query("
                SELECT * FROM peminjaman 
                WHERE nomor_peminjaman = '$nomor_peminjaman' AND status = 'Dipinjam'
            ");
            $peminjaman = $result->fetch_assoc();
            
            if (!$peminjaman) {
                throw new Exception("Peminjaman tidak ditemukan atau sudah dikembalikan");
            }
            
            $tanggal_kembali = date('Y-m-d');
            $tanggal_rencana = $peminjaman['tanggal_kembali_rencana'];
            $terlambat_hari = max(0, (strtotime($tanggal_kembali) - strtotime($tanggal_rencana)) / (60 * 60 * 24));
            $denda_per_hari = 2000;
            $total_denda = $terlambat_hari * $denda_per_hari;
            
            $conn->query("
                UPDATE peminjaman 
                SET status = 'Dikembalikan', tanggal_kembali_aktual = '$tanggal_kembali' 
                WHERE id_peminjaman = {$peminjaman['id_peminjaman']}
            ");
            
            $detail_result = $conn->query("
                SELECT * FROM detail_peminjaman 
                WHERE id_peminjaman = {$peminjaman['id_peminjaman']}
            ");
            
            while ($detail = $detail_result->fetch_assoc()) {
                $conn->query("
                    UPDATE buku SET stok = stok + {$detail['jumlah']} 
                    WHERE id_buku = {$detail['id_buku']}
                ");
                
                $conn->query("
                    UPDATE detail_peminjaman SET denda = $total_denda 
                    WHERE id_detail = {$detail['id_detail']}
                ");
            }
            
            $conn->query("
                INSERT INTO pengembalian (id_peminjaman, tanggal_pengembalian, total_denda) 
                VALUES ({$peminjaman['id_peminjaman']}, '$tanggal_kembali', $total_denda)
            ");
            
            $conn->commit();
            
            $response = ["message" => "Buku berhasil dikembalikan"];
            if ($total_denda > 0) {
                $response["denda"] = $total_denda;
                $response["keterangan"] = "Terlambat $terlambat_hari hari";
            }
            
            response($response);
            
        } catch (Exception $e) {
            $conn->rollback();
            response(["error" => $e->getMessage()], 500);
        }
        break;
}
?>