<?php
include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['type']) && $_GET['type'] == 'statistik') {
            // Statistik buku populer
            $result = $conn->query("
                SELECT b.judul, COUNT(dp.id_buku) as jumlah_peminjaman
                FROM buku b
                JOIN detail_peminjaman dp ON b.id_buku = dp.id_buku
                GROUP BY b.id_buku
                ORDER BY jumlah_peminjaman DESC
                LIMIT 10
            ");
            $statistik = [];
            while ($row = $result->fetch_assoc()) {
                $statistik[] = $row;
            }
            response($statistik);
        } else {
            // Laporan peminjaman
            $bulan = isset($_GET['bulan']) ? $conn->real_escape_string($_GET['bulan']) : date('m');
            $tahun = isset($_GET['tahun']) ? $conn->real_escape_string($_GET['tahun']) : date('Y');
            
            $result = $conn->query("
                SELECT p.nomor_peminjaman, a.nama, p.tanggal_pinjam, p.tanggal_kembali_rencana,
                       COUNT(dp.id_buku) as jumlah_buku, SUM(dp.denda) as total_denda
                FROM peminjaman p
                JOIN anggota a ON p.id_anggota = a.id_anggota
                JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
                WHERE MONTH(p.tanggal_pinjam) = $bulan AND YEAR(p.tanggal_pinjam) = $tahun
                GROUP BY p.id_peminjaman
            ");
            $laporan = [];
            while ($row = $result->fetch_assoc()) {
                $laporan[] = $row;
            }
            response($laporan);
        }
        break;
}
?>