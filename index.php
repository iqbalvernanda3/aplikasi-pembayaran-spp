<?php

session_start();

/* Proteksi: wajib login */
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}

/* Koneksi database */
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
    die("Koneksi database tidak ditemukan. Cek file config/koneksi.php.");
}

/*
 * Helper: ambil satu angka dari query.
 * Jika tabel/kolom tidak ada, kembalikan 0 (tidak menampilkan error).
 */
function ambil_angka(mysqli $koneksi, string $sql): float
{
    try {
        $hasil = mysqli_query($koneksi, $sql);
        if ($hasil) {
            $row = mysqli_fetch_row($hasil);
            return (float)($row[0] ?? 0);
        }
    } catch (Throwable $e) {
        // abaikan
    }
    return 0;
}

$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Petugas';

/* Statistik (sesuaikan nama tabel jika berbeda) */
$total_siswa      = (int)ambil_angka($koneksi, "SELECT COUNT(*) FROM siswa");
$total_spp        = (int)ambil_angka($koneksi, "SELECT COUNT(*) FROM spp");
$total_pembayaran = (int)ambil_angka($koneksi, "SELECT COUNT(*) FROM pembayaran");

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard - SPP Digital</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.navbar {
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.navbar h1 {
    margin: 0;
    font-size: 22px;
}

.navbar .user {
    font-size: 14px;
}

.navbar .user a {
    color: white;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    margin-left: 10px;
}

.navbar .user a:hover {
    background: rgba(255, 255, 255, 0.35);
}

.container {
    max-width: 1100px;
    margin: 30px auto;
    padding: 0 20px;
}

.welcome {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
    margin-bottom: 25px;
}

.welcome h2 {
    margin: 0 0 6px;
    color: #1e40af;
}

.welcome p {
    margin: 0;
    color: #64748b;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
    border-left: 5px solid #2563eb;
}

.card span {
    display: block;
    color: #64748b;
    font-size: 14px;
    margin-bottom: 8px;
}

.card strong {
    font-size: 30px;
    color: #1e40af;
}

h3.judul {
    margin: 0 0 15px;
}

.menu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.menu a {
    display: block;
    background: #2563eb;
    color: white;
    text-align: center;
    padding: 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
}

.menu a:hover {
    background: #1d4ed8;
}

.footer {
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    margin: 40px 0 20px;
}

</style>

</head>

<body>

<div class="navbar">

    <h1>SPP Digital</h1>

    <div class="user">
        Halo, <b><?= htmlspecialchars($nama) ?></b>
        <a href="logout.php">Logout</a>
    </div>

</div>

<div class="container">

    <div class="welcome">
        <h2>Selamat datang, <?= htmlspecialchars($nama) ?>!</h2>
        <p>Kelola data siswa, SPP, pembayaran, dan laporan dari satu tempat.</p>
    </div>

    <div class="cards">

        <div class="card">
            <span>Total Siswa</span>
            <strong><?= $total_siswa ?></strong>
        </div>

        <div class="card">
            <span>Data SPP</span>
            <strong><?= $total_spp ?></strong>
        </div>

        <div class="card">
            <span>Total Transaksi Pembayaran</span>
            <strong><?= $total_pembayaran ?></strong>
        </div>

    </div>

    <h3 class="judul">Menu Utama</h3>

    <div class="menu">
        <a href="siswa.php">Data Siswa</a>
        <a href="spp.php">Data SPP</a>
        <a href="pembayaran.php">Pembayaran</a>
        <a href="laporan.php">Laporan</a>
    </div>

    <div class="footer">
        &copy; 2026 SPP Digital
    </div>

</div>

</body>

</html>