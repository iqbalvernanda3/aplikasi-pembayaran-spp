<?php

session_start();

/* =========================
   CEK LOGIN
========================= */

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

/* =========================
   KONEKSI DATABASE
========================= */

$lokasi_koneksi = [
    __DIR__ . '/config/koneksi.php',
    __DIR__ . '/koneksi.php',
];

foreach ($lokasi_koneksi as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    die("Koneksi database tidak ditemukan. Cek file config/koneksi.php (variabel harus bernama \$koneksi).");
}

/* Error MySQL dilempar sebagai exception agar bisa ditangkap */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Tampilkan pesan lalu kembali ke halaman pembayaran.php */
function kembali(string $pesan): void
{
    echo "<script>
        alert(" . json_encode($pesan, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'pembayaran.php';
    </script>";
    exit;
}

/* =========================
   VALIDASI ID
========================= */

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $id = filter_input(INPUT_GET, 'id_pembayaran', FILTER_VALIDATE_INT);
}

if (!$id || $id <= 0) {
    kembali("ID pembayaran tidak valid.");
}

try {

    /* Cari nama kolom primary key tabel pembayaran (default: id_pembayaran) */
    $kolom_id = "id_pembayaran";

    $keys = mysqli_query($koneksi, "SHOW KEYS FROM pembayaran WHERE Key_name = 'PRIMARY'");
    $key  = mysqli_fetch_assoc($keys);

    if ($key && !empty($key['Column_name'])) {
        $kolom_id = $key['Column_name'];
    }

    /* Pastikan data pembayaran ada */
    $cek = mysqli_prepare($koneksi, "SELECT 1 FROM pembayaran WHERE `$kolom_id` = ? LIMIT 1");
    mysqli_stmt_bind_param($cek, "i", $id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);
    $ada = mysqli_stmt_num_rows($cek) > 0;
    mysqli_stmt_close($cek);

    if (!$ada) {
        kembali("Data pembayaran tidak ditemukan.");
    }

    /* Hapus data */
    $stmt = mysqli_prepare($koneksi, "DELETE FROM pembayaran WHERE `$kolom_id` = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $terhapus = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($terhapus > 0) {
        kembali("Data pembayaran berhasil dihapus.");
    }

    kembali("Data pembayaran gagal dihapus.");

} catch (Throwable $ex) {

    kembali("Terjadi kesalahan: " . $ex->getMessage());
}