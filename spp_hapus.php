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

/* Error MySQL dilempar sebagai exception agar bisa ditangkap dengan benar */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* Langsung kembali ke halaman daftar SPP (tanpa pesan) */
function kembali_langsung(): void
{
    header("Location: spp.php");
    exit;
}

/* Tampilkan pesan (untuk kondisi gagal) lalu kembali ke daftar SPP */
function kembali_dengan_pesan(string $pesan): void
{
    echo "<script>
        alert(" . json_encode($pesan, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'spp.php';
    </script>";
    exit;
}

/* =========================
   VALIDASI ID
========================= */

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    kembali_dengan_pesan("ID data SPP tidak valid.");
}

try {

    /* Pastikan data SPP ada */
    $cek = mysqli_prepare($koneksi, "SELECT tahun FROM spp WHERE id_spp = ? LIMIT 1");
    mysqli_stmt_bind_param($cek, "i", $id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_bind_result($cek, $tahun_spp);
    $ada = mysqli_stmt_fetch($cek) === true;
    mysqli_stmt_close($cek);

    if (!$ada) {
        kembali_dengan_pesan("Data SPP tidak ditemukan.");
    }

    /* Cegah hapus jika sudah dipakai di tabel pembayaran */
    $jumlah_dipakai = 0;

    try {

        $pakai = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM pembayaran WHERE id_spp = ?");
        mysqli_stmt_bind_param($pakai, "i", $id);
        mysqli_stmt_execute($pakai);
        mysqli_stmt_bind_result($pakai, $jumlah_dipakai);
        mysqli_stmt_fetch($pakai);
        mysqli_stmt_close($pakai);

    } catch (Throwable $ex) {
        /* Tabel pembayaran belum ada / kolom id_spp tidak ada: lewati pengecekan */
        $jumlah_dipakai = 0;
    }

    if ($jumlah_dipakai > 0) {
        kembali_dengan_pesan(
            "SPP tahun " . $tahun_spp . " tidak bisa dihapus karena sudah dipakai di " .
            $jumlah_dipakai . " data pembayaran."
        );
    }

    /* Hapus data */
    $stmt = mysqli_prepare($koneksi, "DELETE FROM spp WHERE id_spp = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $terhapus = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($terhapus > 0) {
        /* Berhasil: langsung kembali ke tampilan spp.php */
        kembali_langsung();
    }

    kembali_dengan_pesan("Data SPP gagal dihapus.");

} catch (Throwable $ex) {

    kembali_dengan_pesan("Terjadi kesalahan: " . $ex->getMessage());
}