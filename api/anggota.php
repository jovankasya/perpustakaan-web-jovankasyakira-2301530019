<?php
include 'config.php';

// Fungsi untuk log error
function logError($message) {
    error_log("ANGGOTA API ERROR: " . $message);
}

$method = $_SERVER['REQUEST_METHOD'];

// Debug info
error_log("Anggota API Accessed: " . $method);

switch ($method) {
    case 'GET':
        try {
            if (isset($_GET['id'])) {
                // Get anggota by ID
                $id = $conn->real_escape_string($_GET['id']);
                error_log("Getting anggota with ID: " . $id);
                
                $result = $conn->query("SELECT * FROM anggota WHERE id_anggota = '$id'");
                
                if ($result === false) {
                    throw new Exception("Query failed: " . $conn->error);
                }
                
                if ($result->num_rows > 0) {
                    $anggota = $result->fetch_assoc();
                    response($anggota);
                } else {
                    response(["error" => "Anggota tidak ditemukan"], 404);
                }
            } else {
                // Get all anggota
                error_log("Getting all anggota");
                $result = $conn->query("SELECT * FROM anggota ORDER BY id_anggota DESC");
                
                if ($result === false) {
                    throw new Exception("Query failed: " . $conn->error);
                }
                
                $anggota = [];
                while ($row = $result->fetch_assoc()) {
                    $anggota[] = $row;
                }
                response($anggota);
            }
        } catch (Exception $e) {
            logError($e->getMessage());
            response(["error" => "Terjadi kesalahan: " . $e->getMessage()], 500);
        }
        break;

    case 'POST':
        try {
            $input = file_get_contents("php://input");
            error_log("POST Data: " . $input);
            
            $data = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Data JSON tidak valid: " . json_last_error_msg());
            }
            
            // Validasi data
            $nomor_anggota = $conn->real_escape_string($data['nomor_anggota'] ?? '');
            $nama = $conn->real_escape_string($data['nama'] ?? '');
            $alamat = $conn->real_escape_string($data['alamat'] ?? '');
            $telepon = $conn->real_escape_string($data['telepon'] ?? '');
            $email = $conn->real_escape_string($data['email'] ?? '');
            
            if (empty($nomor_anggota) || empty($nama)) {
                throw new Exception("Nomor anggota dan nama wajib diisi");
            }
            
            error_log("Inserting anggota: $nomor_anggota - $nama");
            
            $query = "INSERT INTO anggota (nomor_anggota, nama, alamat, telepon, email, tanggal_daftar) 
                      VALUES ('$nomor_anggota', '$nama', '$alamat', '$telepon', '$email', CURDATE())";
            
            error_log("SQL Query: " . $query);
            
            if ($conn->query($query)) {
                $response = [
                    "message" => "Anggota berhasil ditambahkan", 
                    "id" => $conn->insert_id,
                    "nomor_anggota" => $nomor_anggota
                ];
                error_log("Success: " . json_encode($response));
                response($response);
            } else {
                throw new Exception("Gagal menambah anggota: " . $conn->error);
            }
        } catch (Exception $e) {
            logError($e->getMessage());
            response(["error" => $e->getMessage()], 500);
        }
        break;

    case 'PUT':
        try {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            $id = $conn->real_escape_string($data['id_anggota'] ?? '');
            $nama = $conn->real_escape_string($data['nama'] ?? '');
            $alamat = $conn->real_escape_string($data['alamat'] ?? '');
            $telepon = $conn->real_escape_string($data['telepon'] ?? '');
            $email = $conn->real_escape_string($data['email'] ?? '');
            $status = $conn->real_escape_string($data['status'] ?? 'Aktif');
            
            $query = "UPDATE anggota SET 
                      nama='$nama', 
                      alamat='$alamat', 
                      telepon='$telepon', 
                      email='$email', 
                      status='$status' 
                      WHERE id_anggota='$id'";
            
            if ($conn->query($query)) {
                response(["message" => "Anggota berhasil diupdate"]);
            } else {
                throw new Exception("Gagal update anggota: " . $conn->error);
            }
        } catch (Exception $e) {
            logError($e->getMessage());
            response(["error" => $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        try {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            $id = $conn->real_escape_string($data['id_anggota'] ?? '');
            
            $query = "DELETE FROM anggota WHERE id_anggota='$id'";
            
            if ($conn->query($query)) {
                response(["message" => "Anggota berhasil dihapus"]);
            } else {
                throw new Exception("Gagal hapus anggota: " . $conn->error);
            }
        } catch (Exception $e) {
            logError($e->getMessage());
            response(["error" => $e->getMessage()], 500);
        }
        break;

    default:
        response(["error" => "Method tidak didukung"], 405);
        break;
}
?>