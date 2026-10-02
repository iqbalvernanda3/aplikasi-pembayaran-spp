<?php
session_start();

if (!isset($_SESSION['id_petugas'])) {
    header("Location: login.php");
    exit;
}

include "config/koneksi.php";

$error = "";

// Proses simpan
if (isset($_POST['simpan'])) {

    $tahun = trim($_POST['tahun']);
    $nominal = trim($_POST['nominal']);

    // Validasi
    if ($tahun == "" || $nominal == "") {

        $error = "Tahun dan nominal SPP wajib diisi.";

    } elseif (!is_numeric($tahun) || strlen($tahun) != 4) {

        $error = "Tahun harus berupa 4 angka.";

    } elseif (!is_numeric($nominal) || $nominal <= 0) {

        $error = "Nominal SPP harus lebih dari 0.";

    } else {

        // Cek apakah tahun sudah ada
        $cek = mysqli_query(
            $koneksi,
            "SELECT * FROM spp WHERE tahun='$tahun'"
        );

        if (mysqli_num_rows($cek) > 0) {

            $error = "Data SPP tahun $tahun sudah tersedia.";

        } else {

            // Simpan data
            $query = mysqli_query(
                $koneksi,
                "INSERT INTO spp (tahun, nominal)
                 VALUES ('$tahun', '$nominal')"
            );

            if ($query) {

                header("Location: spp.php");
                exit;

            } else {

                $error = "Data gagal disimpan: " . mysqli_error($koneksi);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah SPP - SPP Digital</title>

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

        /* FORM CARD */

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 800px;
        }

        .card h2 {
            margin-top: 0;
            color: #172554;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .info {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }

        /* BUTTON */

        .buttons {
            margin-top: 25px;
        }

        button,
        .btn-kembali {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-simpan {
            background: #2563eb;
            color: white;
        }

        .btn-simpan:hover {
            background: #1d4ed8;
        }

        .btn-kembali {
            background: #64748b;
            color: white;
            margin-left: 8px;
        }

        .btn-kembali:hover {
            background: #475569;
        }

        /* ERROR */

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
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

    <div class="header">

        <h1>Tambah Data SPP</h1>

        <div class="user">

            Login sebagai:
            <strong>
                <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
            </strong>

        </div>

    </div>


    <div class="card">

        <h2>Form Tambah SPP</h2>


        <?php if ($error != "") { ?>

            <div class="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php } ?>


        <form method="POST">

            <div class="form-group">

                <label for="tahun">
                    Tahun SPP
                </label>

                <input
                    type="number"
                    id="tahun"
                    name="tahun"
                    placeholder="Contoh: 2026"
                    min="2000"
                    max="2100"
                    value="<?php
                        echo isset($_POST['tahun'])
                            ? htmlspecialchars($_POST['tahun'])
                            : '';
                    ?>"
                    required
                >

                <div class="info">
                    Masukkan tahun SPP, contoh: 2026
                </div>

            </div>


            <div class="form-group">

                <label for="nominal">
                    Nominal SPP
                </label>

                <input
                    type="number"
                    id="nominal"
                    name="nominal"
                    placeholder="Contoh: 150000"
                    min="1"
                    value="<?php
                        echo isset($_POST['nominal'])
                            ? htmlspecialchars($_POST['nominal'])
                            : '';
                    ?>"
                    required
                >

                <div class="info">
                    Masukkan nominal SPP tanpa tanda titik atau koma.
                </div>

            </div>


            <div class="buttons">

                <button
                    type="submit"
                    name="simpan"
                    class="btn-simpan"
                >
                    💾 Simpan SPP
                </button>

                <a
                    href="spp.php"
                    class="btn-kembali"
                >
                    ↩ Kembali
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>