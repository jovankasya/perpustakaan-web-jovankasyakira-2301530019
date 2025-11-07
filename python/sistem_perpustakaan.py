import mysql.connector
from mysql.connector import Error
import datetime
import hashlib
from typing import List, Dict, Optional

class SistemPerpustakaan:
    def __init__(self):
        self.connection = None
        self.connect_db()
        
    def connect_db(self):
        """Membuat koneksi ke database"""
        try:
            self.connection = mysql.connector.connect(
                host='localhost',
                user='root',          # ← GANTI JIKA PERLU
                password='',          # ← GANTI JIKA ADA PASSWORD
                database='perpustakaan_db'
            )
            print("✅ Koneksi database berhasil!")
        except Error as e:
            print(f"❌ Error: {e}")
            print("💡 Pastikan:")
            print("   - MySQL sedang berjalan")
            print("   - Database 'perpustakaan_db' sudah dibuat")
            print("   - Username/password sudah benar")
    
    def hash_password(self, password: str) -> str:
        """Hash password untuk keamanan"""
        return hashlib.sha256(password.encode()).hexdigest()
    
    # CRUD Operations untuk Anggota
    def tambah_anggota(self, data: Dict):
        """Menambah anggota baru"""
        try:
            cursor = self.connection.cursor()
            query = """
            INSERT INTO anggota (nomor_anggota, nama, alamat, telepon, email, tanggal_daftar)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
            values = (
                data['nomor_anggota'],
                data['nama'],
                data['alamat'],
                data['telepon'],
                data['email'],
                datetime.date.today()
            )
            cursor.execute(query, values)
            self.connection.commit()
            print("✅ Anggota berhasil ditambahkan!")
        except Error as e:
            print(f"❌ Error: {e}")
    
    def daftar_anggota(self) -> List[Dict]:
        """Mendapatkan daftar semua anggota"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            query = "SELECT * FROM anggota"
            cursor.execute(query)
            return cursor.fetchall()
        except Error as e:
            print(f"❌ Error: {e}")
            return []
    
    # CRUD Operations untuk Buku
    def tambah_buku(self, data: Dict):
        """Menambah buku baru"""
        try:
            cursor = self.connection.cursor()
            query = """
            INSERT INTO buku (isbn, judul, id_kategori, id_penerbit, tahun_terbit, stok, rak_lokasi)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            values = (
                data['isbn'],
                data['judul'],
                data['id_kategori'],
                data['id_penerbit'],
                data['tahun_terbit'],
                data['stok'],
                data['rak_lokasi']
            )
            cursor.execute(query, values)
            self.connection.commit()
            print("✅ Buku berhasil ditambahkan!")
        except Error as e:
            print(f"❌ Error: {e}")
    
    def cari_buku(self, keyword: str) -> List[Dict]:
        """Mencari buku berdasarkan judul atau ISBN"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            query = """
            SELECT b.*, k.nama_kategori, p.nama_penerbit 
            FROM buku b
            JOIN kategori k ON b.id_kategori = k.id_kategori
            JOIN penerbit p ON b.id_penerbit = p.id_penerbit
            WHERE b.judul LIKE %s OR b.isbn LIKE %s
            """
            cursor.execute(query, (f'%{keyword}%', f'%{keyword}%'))
            return cursor.fetchall()
        except Error as e:
            print(f"❌ Error: {e}")
            return []
    
    # Operasi Peminjaman
    def pinjam_buku(self, id_anggota: int, buku_list: List[Dict]):
        """Memproses peminjaman buku"""
        try:
            cursor = self.connection.cursor()
            
            # Generate nomor peminjaman
            nomor_peminjaman = f"PMJ{datetime.datetime.now().strftime('%Y%m%d%H%M%S')}"
            
            # Hitung tanggal kembali (14 hari dari sekarang)
            tanggal_pinjam = datetime.date.today()
            tanggal_kembali = tanggal_pinjam + datetime.timedelta(days=14)
            
            # Insert data peminjaman
            query_peminjaman = """
            INSERT INTO peminjaman (nomor_peminjaman, id_anggota, tanggal_pinjam, tanggal_kembali_rencana)
            VALUES (%s, %s, %s, %s)
            """
            cursor.execute(query_peminjaman, (nomor_peminjaman, id_anggota, tanggal_pinjam, tanggal_kembali))
            id_peminjaman = cursor.lastrowid
            
            # Insert detail peminjaman
            query_detail = """
            INSERT INTO detail_peminjaman (id_peminjaman, id_buku, jumlah)
            VALUES (%s, %s, %s)
            """
            for buku in buku_list:
                cursor.execute(query_detail, (id_peminjaman, buku['id_buku'], buku['jumlah']))
                
                # Update stok buku
                query_update_stok = "UPDATE buku SET stok = stok - %s WHERE id_buku = %s"
                cursor.execute(query_update_stok, (buku['jumlah'], buku['id_buku']))
            
            self.connection.commit()
            print(f"✅ Peminjaman berhasil! Nomor: {nomor_peminjaman}")
            return nomor_peminjaman
            
        except Error as e:
            self.connection.rollback()
            print(f"❌ Error: {e}")
            return None
    
    # Operasi Pengembalian
    def kembalikan_buku(self, nomor_peminjaman: str):
        """Memproses pengembalian buku"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            
            # Cari data peminjaman
            query_peminjaman = """
            SELECT * FROM peminjaman WHERE nomor_peminjaman = %s AND status = 'Dipinjam'
            """
            cursor.execute(query_peminjaman, (nomor_peminjaman,))
            peminjaman = cursor.fetchone()
            
            if not peminjaman:
                print("❌ Peminjaman tidak ditemukan atau sudah dikembalikan!")
                return False
            
            # Hitung denda jika terlambat
            tanggal_kembali = datetime.date.today()
            tanggal_rencana = peminjaman['tanggal_kembali_rencana']
            terlambat_hari = max(0, (tanggal_kembali - tanggal_rencana).days)
            denda_per_hari = 2000  # Rp 2.000 per hari
            total_denda = terlambat_hari * denda_per_hari
            
            # Update status peminjaman
            query_update = """
            UPDATE peminjaman 
            SET status = 'Dikembalikan', tanggal_kembali_aktual = %s 
            WHERE id_peminjaman = %s
            """
            cursor.execute(query_update, (tanggal_kembali, peminjaman['id_peminjaman']))
            
            # Kembalikan stok buku
            query_detail = "SELECT * FROM detail_peminjaman WHERE id_peminjaman = %s"
            cursor.execute(query_detail, (peminjaman['id_peminjaman'],))
            details = cursor.fetchall()
            
            for detail in details:
                query_update_stok = "UPDATE buku SET stok = stok + %s WHERE id_buku = %s"
                cursor.execute(query_update_stok, (detail['jumlah'], detail['id_buku']))
                
                # Update denda di detail
                query_update_denda = """
                UPDATE detail_peminjaman SET denda = %s 
                WHERE id_detail = %s
                """
                cursor.execute(query_update_denda, (total_denda, detail['id_detail']))
            
            # Insert ke tabel pengembalian
            query_pengembalian = """
            INSERT INTO pengembalian (id_peminjaman, tanggal_pengembalian, total_denda)
            VALUES (%s, %s, %s)
            """
            cursor.execute(query_pengembalian, (peminjaman['id_peminjaman'], tanggal_kembali, total_denda))
            
            self.connection.commit()
            
            if terlambat_hari > 0:
                print(f"✅ Buku berhasil dikembalikan! Denda: Rp {total_denda:,} ({terlambat_hari} hari terlambat)")
            else:
                print("✅ Buku berhasil dikembalikan tepat waktu!")
            
            return True
            
        except Error as e:
            self.connection.rollback()
            print(f"❌ Error: {e}")
            return False
    
    # Laporan dan Statistik
    def laporan_peminjaman(self, bulan: int, tahun: int) -> List[Dict]:
        """Generate laporan peminjaman per bulan"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            query = """
            SELECT p.nomor_peminjaman, a.nama, p.tanggal_pinjam, p.tanggal_kembali_rencana,
                   COUNT(dp.id_buku) as jumlah_buku, SUM(dp.denda) as total_denda
            FROM peminjaman p
            JOIN anggota a ON p.id_anggota = a.id_anggota
            JOIN detail_peminjaman dp ON p.id_peminjaman = dp.id_peminjaman
            WHERE MONTH(p.tanggal_pinjam) = %s AND YEAR(p.tanggal_pinjam) = %s
            GROUP BY p.id_peminjaman
            """
            cursor.execute(query, (bulan, tahun))
            return cursor.fetchall()
        except Error as e:
            print(f"❌ Error: {e}")
            return []
    
    def statistik_populer_buku(self) -> List[Dict]:
        """Statistik buku paling sering dipinjam"""
        try:
            cursor = self.connection.cursor(dictionary=True)
            query = """
            SELECT b.judul, COUNT(dp.id_buku) as jumlah_peminjaman
            FROM buku b
            JOIN detail_peminjaman dp ON b.id_buku = dp.id_buku
            GROUP BY b.id_buku
            ORDER BY jumlah_peminjaman DESC
            LIMIT 10
            """
            cursor.execute(query)
            return cursor.fetchall()
        except Error as e:
            print(f"❌ Error: {e}")
            return []

# Contoh Penggunaan Aplikasi
def main():
    perpustakaan = SistemPerpustakaan()
    
    while True:
        print("\n" + "="*50)
        print("📚 SISTEM MANAJEMEN PERPUSTAKAAN")
        print("="*50)
        print("1. 📝 Tambah Anggota")
        print("2. 👥 Daftar Anggota")
        print("3. 📖 Tambah Buku")
        print("4. 🔍 Cari Buku")
        print("5. 📥 Peminjaman Buku")
        print("6. 📤 Pengembalian Buku")
        print("7. 📊 Laporan Peminjaman")
        print("8. 📈 Statistik Buku Populer")
        print("0. 🚪 Keluar")
        print("-"*50)
        
        pilihan = input("Pilih menu (0-8): ")
        
        if pilihan == '1':
            print("\n➕ TAMBAH ANGGOTA BARU")
            data_anggota = {
                'nomor_anggota': input("Nomor Anggota: "),
                'nama': input("Nama: "),
                'alamat': input("Alamat: "),
                'telepon': input("Telepon: "),
                'email': input("Email: ")
            }
            perpustakaan.tambah_anggota(data_anggota)
            
        elif pilihan == '2':
            print("\n👥 DAFTAR SEMUA ANGGOTA")
            anggota_list = perpustakaan.daftar_anggota()
            if anggota_list:
                for anggota in anggota_list:
                    print(f"  {anggota['nomor_anggota']} - {anggota['nama']} - {anggota['status']}")
            else:
                print("  ❌ Tidak ada data anggota")
                
        elif pilihan == '3':
            print("\n📖 TAMBAH BUKU BARU")
            data_buku = {
                'isbn': input("ISBN: "),
                'judul': input("Judul: "),
                'id_kategori': int(input("ID Kategori: ")),
                'id_penerbit': int(input("ID Penerbit: ")),
                'tahun_terbit': int(input("Tahun Terbit: ")),
                'stok': int(input("Stok: ")),
                'rak_lokasi': input("Lokasi Rak: ")
            }
            perpustakaan.tambah_buku(data_buku)
            
        elif pilihan == '4':
            print("\n🔍 PENCARIAN BUKU")
            keyword = input("Masukkan judul atau ISBN: ")
            hasil = perpustakaan.cari_buku(keyword)
            if hasil:
                for buku in hasil:
                    print(f"  {buku['isbn']} - {buku['judul']} - Stok: {buku['stok']}")
            else:
                print("  ❌ Buku tidak ditemukan")
                
        elif pilihan == '5':
            print("\n📥 PEMINJAMAN BUKU")
            id_anggota = int(input("ID Anggota: "))
            jumlah_buku = int(input("Jumlah buku yang dipinjam: "))
            buku_list = []
            for i in range(jumlah_buku):
                print(f"  Buku ke-{i+1}:")
                id_buku = int(input("    ID Buku: "))
                jumlah = int(input("    Jumlah: "))
                buku_list.append({'id_buku': id_buku, 'jumlah': jumlah})
            
            perpustakaan.pinjam_buku(id_anggota, buku_list)
            
        elif pilihan == '6':
            print("\n📤 PENGEMBALIAN BUKU")
            nomor_peminjaman = input("Nomor Peminjaman: ")
            perpustakaan.kembalikan_buku(nomor_peminjaman)
            
        elif pilihan == '7':
            print("\n📊 LAPORAN PEMINJAMAN")
            bulan = int(input("Bulan (1-12): "))
            tahun = int(input("Tahun: "))
            laporan = perpustakaan.laporan_peminjaman(bulan, tahun)
            if laporan:
                for item in laporan:
                    print(f"  {item['nomor_peminjaman']} - {item['nama']} - {item['jumlah_buku']} buku")
            else:
                print("  ❌ Tidak ada data laporan")
                
        elif pilihan == '8':
            print("\n📈 STATISTIK BUKU POPULER")
            statistik = perpustakaan.statistik_populer_buku()
            if statistik:
                for i, buku in enumerate(statistik, 1):
                    print(f"  {i}. {buku['judul']} - {buku['jumlah_peminjaman']}x dipinjam")
            else:
                print("  ❌ Tidak ada data statistik")
                
        elif pilihan == '0':
            print("\n👋 Terima kasih telah menggunakan sistem!")
            break
        else:
            print("❌ Pilihan tidak valid!")

if __name__ == "__main__":
    main()