<?php
session_start();

if (!isset($_SESSION['id_petugas'])) {
    header("Location: login.php");
    exit;
}

include "config/koneksi.php";

/* Ambil data laporan pembayaran */
$query = mysqli_query($koneksi, "
    SELECT
        pembayaran.id_pembayaran,
        siswa.nisn,
        siswa.nama_siswa,
        siswa.kelas,
        spp.tahun,
        spp.nominal,
        pembayaran.tanggal_bayar,
        pembayaran.bulan_dibayar,
        pembayaran.jumlah_bayar
    FROM pembayaran
    INNER JOIN siswa
        ON pembayaran.id_siswa = siswa.id_siswa
    INNER JOIN spp
        ON pembayaran.id_spp = spp.id_spp
    ORDER BY pembayaran.tanggal_bayar DESC
");

/* Hitung total pembayaran */
$total_query = mysqli_query($koneksi, "
    SELECT SUM(jumlah_bayar) AS total
    FROM pembayaran
");

$total_data = mysqli_fetch_assoc($total_query);
$total_pembayaran = $total_data['total'] ?? 0;

$username = $_SESSION['username'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Laporan SPP - SPP Digital</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
}

/* SIDEBAR */

.sidebar {
    position: fixed;
    width: 250px;
    height: 100vh;
    background: #172554;
    color: white;
    padding: 25px 15px;
}

.logo {
    text-align: center;
    margin-bottom: 35px;
}

.logo h2 {
    margin: 0;
}

.logo p {
    color: #bfdbfe;
    font-size: 13px;
}

.menu a {
    display: block;
    padding: 14px 18px;
    margin-bottom: 8px;
    color: white;
    text-decoration: none;
    border-radius: 8px;
}

.menu a:hover {
    background: #2563eb;
}

.menu a.active {
    background: #2563eb;
}

/* CONTENT */

.content {
    margin-left: 250px;
    padding: 30px;
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
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.card h2 {
    margin-top: 0;
    color: #172554;
}

/* TOTAL */

.total-box {
    background: linear-gradient(135deg, #2563eb, #1e3a8a);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.total-box h3 {
    margin: 0 0 10px 0;
}

.total-box .nominal {
    font-size: 30px;
    font-weight: bold;
}

/* TOMBOL */

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-print {
    background: #2563eb;
    color: white;
    margin-bottom: 20px;
}

.btn-print:hover {
    background: #1d4ed8;
}

/* TABLE */

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

table th {
    background: #172554;
    color: white;
    padding: 13px;
    text-align: left;
}

table td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

table tr:hover {
    background: #f8fafc;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

/* PRINT */

@media print {

    body {
        background: white;
    }

    .sidebar {
        display: none;
    }

    .content {
        margin-left: 0;
        padding: 10px;
    }

    .header {
        box-shadow: none;
        border: none;
    }

    .btn-print {
        display: none;
    }

    .total-box {
        background: white;
        color: black;
        border: 1px solid #ddd;
    }

    table th {
        background: #ddd !important;
        color: black;
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

        <a href="index.php">
            🏠 Dashboard
        </a>

        <a href="siswa.php">
            👨‍🎓 Data Siswa
        </a>

        <a href="spp.php">
            💰 Data SPP
        </a>

        <a href="pembayaran.php">
            💳 Pembayaran
        </a>

        <a href="laporan.php" class="active">
            📊 Laporan
        </a>

        <a href="logout.php">
            🚪 Logout
        </a>

    </div>

</div>


<!-- CONTENT -->

<div class="content">

    <!-- HEADER -->

    <div class="header">

        <h1>Laporan Pembayaran</h1>

        <div class="user">

            Login sebagai:
            <strong>
                <?php echo htmlspecialchars($username); ?>
            </strong>

        </div>

    </div>


    <!-- TOTAL PEMBAYARAN -->

    <div class="total-box">

        <h3>Total Seluruh Pembayaran</h3>

        <div class="nominal">

            Rp <?php echo number_format(
                $total_pembayaran,
                0,
                ',',
                '.'
            ); ?>

        </div>

    </div>


    <!-- LAPORAN -->

    <div class="card">

        <h2>Daftar Laporan Pembayaran SPP</h2>

        <button
            onclick="window.print()"
            class="btn btn-print">
            🖨️ Cetak Laporan
        </button>


        <div class="table-container">

            <table>

                <tr>

                    <th>No</th>

                    <th>NISN</th>

                    <th>Nama Siswa</th>

                    <th>Kelas</th>

                    <th>Tahun SPP</th>

                    <th>Tanggal Bayar</th>

                    <th>Bulan</th>

                    <th>Jumlah Bayar</th>

                </tr>


                <?php

                $no = 1;

                if (mysqli_num_rows($query) > 0) {

                    while ($data = mysqli_fetch_assoc($query)) {

                ?>

                <tr>

                    <td class="text-center">
                        <?php echo $no++; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($data['nisn']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($data['nama_siswa']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($data['kelas']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($data['tahun']); ?>
                    </td>

                    <td>
                        <?php echo date(
                            'd-m-Y',
                            strtotime($data['tanggal_bayar'])
                        ); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars(
                            $data['bulan_dibayar']
                        ); ?>
                    </td>

                    <td class="text-right">

                        Rp <?php echo number_format(
                            $data['jumlah_bayar'],
                            0,
                            ',',
                            '.'
                        ); ?>

                    </td>

                </tr>

                <?php

                    }

                } else {

                ?>

                <tr>

                    <td colspan="8" class="text-center">

                        Belum ada data pembayaran.

                    </td>

                </tr>

                <?php

                }

                ?>

            </table>

        </div>

    </div>

</div>

</body>

</html>