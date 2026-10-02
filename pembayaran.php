<?php
session_start();

if (!isset($_SESSION['id_petugas'])) {
    header("Location: login.php");
    exit;
}

include "config/koneksi.php";

/*
|--------------------------------------------------------------------------
| Ambil data pembayaran
|--------------------------------------------------------------------------
| Menggabungkan:
| - pembayaran
| - siswa
| - petugas
| - spp
|--------------------------------------------------------------------------
*/

$query = mysqli_query($koneksi, "
    SELECT
        pembayaran.*,
        siswa.nisn,
        siswa.nama_siswa,
        siswa.kelas,
        petugas.nama_petugas,
        spp.tahun,
        spp.nominal
    FROM pembayaran
    LEFT JOIN siswa
        ON pembayaran.id_siswa = siswa.id_siswa
    LEFT JOIN petugas
        ON pembayaran.id_petugas = petugas.id_petugas
    LEFT JOIN spp
        ON pembayaran.id_spp = spp.id_spp
    ORDER BY pembayaran.tanggal_bayar DESC
");

if (!$query) {
    die("Query pembayaran gagal: " . mysqli_error($koneksi));
}

$no = 1;
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pembayaran - SPP Digital</title>

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

        .menu .active {
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
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .card-header h2 {
            margin: 0;
            color: #172554;
        }

        /* BUTTON */

        .btn-tambah {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-tambah:hover {
            background: #1d4ed8;
        }

        .btn-hapus {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 7px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-hapus:hover {
            background: #b91c1c;
        }

        /* TABLE */

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #172554;
            color: white;
            padding: 14px 12px;
            text-align: left;
        }

        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .nominal {
            font-weight: bold;
            color: #2563eb;
        }

        .kosong {
            text-align: center;
            padding: 35px;
            color: #64748b;
        }

        /* RESPONSIVE */

        @media (max-width: 768px) {

            .sidebar {
                width: 200px;
            }

            .content {
                margin-left: 200px;
                padding: 20px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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

        <a href="pembayaran.php" class="active">
            💳 Pembayaran
        </a>

        <a href="laporan.php">
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

        <h1>Pembayaran</h1>

        <div class="user">

            Login sebagai:

            <strong>
                <?php
                echo htmlspecialchars(
                    $_SESSION['username'] ?? 'Admin'
                );
                ?>
            </strong>

        </div>

    </div>


    <!-- CARD -->

    <div class="card">

        <div class="card-header">

            <h2>Daftar Pembayaran SPP</h2>

            <a
                href="pembayaran_tambah.php"
                class="btn-tambah"
            >
                + Tambah Pembayaran
            </a>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Tanggal</th>

                        <th>NISN</th>

                        <th>Nama Siswa</th>

                        <th>Kelas</th>

                        <th>Tahun SPP</th>

                        <th>Bulan</th>

                        <th>Jumlah Bayar</th>

                        <th>Petugas</th>

                        <th>Aksi</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (mysqli_num_rows($query) > 0) { ?>

                    <?php while ($data = mysqli_fetch_assoc($query)) { ?>

                        <tr>

                            <td>
                                <?php echo $no++; ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    'd-m-Y',
                                    strtotime($data['tanggal_bayar'])
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $data['nisn'] ?? '-'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $data['nama_siswa'] ?? '-'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $data['kelas'] ?? '-'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $data['tahun'] ?? '-'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $data['bulan_dibayar'] ?? '-'
                                );
                                ?>
                            </td>

                            <td class="nominal">

                                Rp
                                <?php
                                echo number_format(
                                    $data['jumlah_bayar'] ?? 0,
                                    0,
                                    ',',
                                    '.'
                                );
                                ?>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $data['nama_petugas'] ?? '-'
                                );
                                ?>
                            </td>

                            <td>

                                <?php if (isset($data['id_pembayaran'])) { ?>

                                    <a
                                        href="pembayaran_hapus.php?id=<?php echo $data['id_pembayaran']; ?>"
                                        class="btn-hapus"
                                        onclick="return confirm('Apakah yakin ingin menghapus pembayaran ini?')"
                                    >
                                        Hapus
                                    </a>

                                <?php } ?>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>

                        <td
                            colspan="10"
                            class="kosong"
                        >
                            Belum ada data pembayaran.
                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>