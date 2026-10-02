<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

/* =========================
   AMBIL DATA SISWA
========================= */

$query = mysqli_query($koneksi, "SELECT * FROM siswa ORDER BY id_siswa DESC");

if (!$query) {
    die("Query data siswa gagal: " . mysqli_error($koneksi));
}

$username = $_SESSION['username'] ?? 'Administrator';

/* Helper untuk menampilkan data dengan aman */
function e($nilai): string
{
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Siswa - SPP Digital</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #172554;
            color: white;
            padding: 25px 15px;
            overflow-y: auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo h2 {
            margin: 0;
            font-size: 22px;
        }

        .logo p {
            color: #bfdbfe;
            font-size: 13px;
            margin-top: 8px;
        }

        .menu a {
            display: block;
            padding: 14px 18px;
            margin-bottom: 8px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
        }

        .menu a:hover,
        .menu a.active {
            background: #2563eb;
        }

        /* CONTENT */

        .content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        /* HEADER */

        .header {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .header h1 {
            margin: 0;
            color: #172554;
        }

        .user {
            color: #555;
        }

        .user strong {
            color: #2563eb;
        }

        /* CARD */

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h2 {
            margin: 0;
            color: #172554;
        }

        /* BUTTON */

        .btn-tambah {
            display: inline-block;
            padding: 11px 18px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .btn-tambah:hover {
            background: #1d4ed8;
        }

        /* TABLE */

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th {
            background: #172554;
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 14px;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* ACTION */

        .btn-edit,
        .btn-hapus {
            display: inline-block;
            padding: 7px 12px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn-edit {
            background: #f59e0b;
        }

        .btn-hapus {
            background: #dc2626;
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-hapus:hover {
            background: #b91c1c;
        }

        /* EMPTY DATA */

        .empty {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        /* RESPONSIVE */

        @media (max-width: 700px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .content {
                margin-left: 0;
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

    </style>

</head>

<body>

<!-- SIDEBAR -->

<div class="sidebar">

    <div class="logo">
        <h2>SPP DIGITAL</h2>
        <p>Sistem Pembayaran SPP</p>
    </div>

    <div class="menu">
        <a href="index.php">🏠 &nbsp; Dashboard</a>
        <a href="siswa.php" class="active">👨‍🎓 &nbsp; Data Siswa</a>
        <a href="spp.php">💰 &nbsp; Data SPP</a>
        <a href="pembayaran.php">💳 &nbsp; Pembayaran</a>
        <a href="laporan.php">📊 &nbsp; Laporan</a>
        <a href="logout.php">🚪 &nbsp; Logout</a>
    </div>

</div>

<!-- CONTENT -->

<div class="content">

    <div class="header">

        <h1>Data Siswa</h1>

        <div class="user">
            Login sebagai:
            <strong><?= e($username) ?></strong>
        </div>

    </div>

    <div class="card">

        <div class="card-header">

            <h2>Daftar Siswa</h2>

            <a href="siswa_tambah.php" class="btn-tambah">+ Tambah Siswa</a>

        </div>

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Alamat</th>
                        <th>No HP</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php

                $no = 1;

                if (mysqli_num_rows($query) > 0) {

                    while ($siswa = mysqli_fetch_assoc($query)) {

                        $id = (int)($siswa['id_siswa'] ?? 0);

                ?>

                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= e($siswa['nisn'] ?? '') ?></td>
                        <td><?= e($siswa['nama_siswa'] ?? $siswa['nama'] ?? '') ?></td>
                        <td><?= e($siswa['kelas'] ?? $siswa['id_kelas'] ?? '') ?></td>
                        <td><?= e($siswa['alamat'] ?? '') ?></td>
                        <td><?= e($siswa['no_hp'] ?? $siswa['no_telp'] ?? '') ?></td>
                        <td>
                            <a href="siswa_edit.php?id=<?= $id ?>" class="btn-edit">Edit</a>

                            <a href="siswa_hapus.php?id=<?= $id ?>"
                               class="btn-hapus"
                               onclick="return confirm('Apakah kamu yakin ingin menghapus data siswa ini?')">
                                Hapus
                            </a>
                        </td>
                    </tr>

                <?php

                    }

                } else {

                ?>

                    <tr>
                        <td colspan="7" class="empty">Belum ada data siswa.</td>
                    </tr>

                <?php

                }

                ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>