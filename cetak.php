<?php

include "config/koneksi.php";

$id = $_GET['id'];

$query = mysqli_query($koneksi, "
    SELECT
        pembayaran.*,
        siswa.nisn,
        siswa.nama_siswa,
        siswa.kelas,
        spp.tahun,
        petugas.nama_petugas
    FROM pembayaran
    JOIN siswa
        ON pembayaran.id_siswa = siswa.id_siswa
    JOIN spp
        ON pembayaran.id_spp = spp.id_spp
    JOIN petugas
        ON pembayaran.id_petugas = petugas.id_petugas
    WHERE pembayaran.id_pembayaran='$id'
");

$data = mysqli_fetch_assoc($query);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Bukti Pembayaran SPP</title>
</head>

<body onload="window.print()">

<center>

<h2>BUKTI PEMBAYARAN SPP</h2>

<hr>

<table>

<tr>
    <td>NISN</td>
    <td>:</td>
    <td><?php echo $data['nisn']; ?></td>
</tr>

<tr>
    <td>Nama Siswa</td>
    <td>:</td>
    <td><?php echo $data['nama_siswa']; ?></td>
</tr>

<tr>
    <td>Kelas</td>
    <td>:</td>
    <td><?php echo $data['kelas']; ?></td>
</tr>

<tr>
    <td>Tanggal Bayar</td>
    <td>:</td>
    <td><?php echo $data['tanggal_bayar']; ?></td>
</tr>

<tr>
    <td>Bulan</td>
    <td>:</td>
    <td><?php echo $data['bulan_dibayar']; ?></td>
</tr>

<tr>
    <td>Tahun SPP</td>
    <td>:</td>
    <td><?php echo $data['tahun']; ?></td>
</tr>

<tr>
    <td>Jumlah Bayar</td>
    <td>:</td>
    <td>
        Rp <?php echo number_format(
            $data['jumlah_bayar'],
            0,
            ',',
            '.'
        ); ?>
    </td>
</tr>

<tr>
    <td>Petugas</td>
    <td>:</td>
    <td><?php echo $data['nama_petugas']; ?></td>
</tr>

</table>

<br><br>

<p>
    Pembayaran SPP telah diterima.
</p>

<br><br>

Petugas,

<br><br><br>

<b><?php echo $data['nama_petugas']; ?></b>

</center>

</body>
</html>