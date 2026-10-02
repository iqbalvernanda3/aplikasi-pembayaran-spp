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

/* Helper untuk menampilkan data dengan aman */
function e($nilai): string
{
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['username'] ?? 'Administrator';
$error    = "";

/* Token CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   PROSES SIMPAN
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['simpan'])) {

    $nisn   = trim($_POST['nisn'] ?? '');
    $nama   = trim($_POST['nama'] ?? '');
    $kelas  = trim($_POST['kelas'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp  = trim($_POST['no_hp'] ?? '');
    $token  = $_POST['csrf_token'] ?? '';

    /* Validasi */

    if (!hash_equals($_SESSION['csrf_token'], $token)) {

        $error = "Sesi tidak valid. Silakan muat ulang halaman.";

    } elseif ($nisn === '' || $nama === '' || $kelas === '') {

        $error = "NISN, Nama Siswa, dan Kelas wajib diisi.";

    } elseif (!ctype_digit($nisn) || strlen($nisn) < 5 || strlen($nisn) > 20) {

        $error = "NISN harus berupa angka (5 sampai 20 digit).";

    } elseif ($no_hp !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $no_hp)) {

        $error = "No HP tidak valid. Gunakan angka, contoh: 08123456789.";

    } else {

        try {

            /* Cek apakah NISN sudah ada */

            $cek = mysqli_prepare($koneksi, "SELECT 1 FROM siswa WHERE nisn = ? LIMIT 1");
            mysqli_stmt_bind_param($cek, "s", $nisn);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);
            $sudah_ada = mysqli_stmt_num_rows($cek) > 0;
            mysqli_stmt_close($cek);

            if ($sudah_ada) {

                $error = "NISN tersebut sudah terdaftar.";

            } else {

                /* Simpan data */

                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO siswa (nisn, nama_siswa, kelas, alamat, no_hp)
                     VALUES (?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param($stmt, "sssss", $nisn, $nama, $kelas, $alamat, $no_hp);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                unset($_SESSION['csrf_token']);

                header("Location: siswa.php");
                exit;
            }

        } catch (Throwable $ex) {

            $error = "Data siswa gagal disimpan: " . $ex->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Siswa - SPP Digital</title>

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

        /* FORM CARD */

        .card {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #172554;
        }

        /* FORM */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #334155;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* BUTTON */

        .button-area {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
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
        }

        .btn-kembali:hover {
            background: #475569;
        }

        /* ERROR */

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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

            .card {
                max-width: 100%;
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

        <h1>Tambah Data Siswa</h1>

        <div class="user">
            Login sebagai:
            <strong><?= e($username) ?></strong>
        </div>

    </div>

    <div class="card">

        <h2>Form Data Siswa</h2>

        <?php if ($error !== ""): ?>
            <div class="alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <!-- NISN -->
            <div class="form-group">
                <label for="nisn">NISN *</label>
                <input
                    type="text"
                    id="nisn"
                    name="nisn"
                    placeholder="Masukkan NISN"
                    inputmode="numeric"
                    maxlength="20"
                    value="<?= e($_POST['nisn'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <!-- NAMA -->
            <div class="form-group">
                <label for="nama">Nama Siswa *</label>
                <input
                    type="text"
                    id="nama"
                    name="nama"
                    placeholder="Masukkan nama siswa"
                    maxlength="100"
                    value="<?= e($_POST['nama'] ?? '') ?>"
                    required
                >
            </div>

            <!-- KELAS -->
            <div class="form-group">
                <label for="kelas">Kelas *</label>
                <input
                    type="text"
                    id="kelas"
                    name="kelas"
                    placeholder="Contoh: X IPA 1"
                    maxlength="50"
                    value="<?= e($_POST['kelas'] ?? '') ?>"
                    required
                >
            </div>

            <!-- ALAMAT -->
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea
                    id="alamat"
                    name="alamat"
                    placeholder="Masukkan alamat siswa"
                ><?= e($_POST['alamat'] ?? '') ?></textarea>
            </div>

            <!-- NO HP -->
            <div class="form-group">
                <label for="no_hp">No HP</label>
                <input
                    type="text"
                    id="no_hp"
                    name="no_hp"
                    placeholder="Contoh: 08123456789"
                    inputmode="tel"
                    maxlength="20"
                    value="<?= e($_POST['no_hp'] ?? '') ?>"
                >
            </div>

            <!-- BUTTON -->
            <div class="button-area">

                <button type="submit" name="simpan" class="btn btn-simpan">
                    💾 Simpan Data
                </button>

                <a href="siswa.php" class="btn btn-kembali">← Kembali</a>

            </div>

        </form>

    </div>

</div>

</body>

</html>