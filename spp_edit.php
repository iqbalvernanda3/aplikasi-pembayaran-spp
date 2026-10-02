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
   VALIDASI ID
========================= */

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: spp.php");
    exit;
}

/* =========================
   AMBIL DATA SPP
========================= */

try {

    $stmt = mysqli_prepare($koneksi, "SELECT id_spp, tahun, nominal FROM spp WHERE id_spp = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $spp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

} catch (Throwable $ex) {

    die("Gagal mengambil data SPP: " . e($ex->getMessage()));
}

if (!$spp) {
    echo "<script>alert('Data SPP tidak ditemukan.'); window.location.href='spp.php';</script>";
    exit;
}

/* =========================
   PROSES UPDATE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update'])) {

    $tahun   = trim($_POST['tahun'] ?? '');
    $nominal = trim($_POST['nominal'] ?? '');
    $token   = $_POST['csrf_token'] ?? '';

    /* Tampilkan kembali input user jika validasi gagal */
    $spp['tahun']   = $tahun;
    $spp['nominal'] = $nominal;

    if (!hash_equals($_SESSION['csrf_token'], $token)) {

        $error = "Sesi tidak valid. Silakan muat ulang halaman.";

    } elseif ($tahun === "" || $nominal === "") {

        $error = "Tahun dan nominal SPP wajib diisi.";

    } elseif (!ctype_digit($tahun) || strlen($tahun) !== 4) {

        $error = "Tahun harus berupa 4 angka.";

    } elseif (!is_numeric($nominal) || (float)$nominal <= 0) {

        $error = "Nominal SPP harus lebih dari 0.";

    } else {

        $tahun_int   = (int)$tahun;
        $nominal_int = (int)round((float)$nominal);

        try {

            /* Cek tahun sudah dipakai data SPP lain */
            $cek = mysqli_prepare(
                $koneksi,
                "SELECT 1 FROM spp WHERE tahun = ? AND id_spp <> ? LIMIT 1"
            );
            mysqli_stmt_bind_param($cek, "ii", $tahun_int, $id);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);
            $sudah_ada = mysqli_stmt_num_rows($cek) > 0;
            mysqli_stmt_close($cek);

            if ($sudah_ada) {

                $error = "Data SPP tahun $tahun sudah tersedia.";

            } else {

                $upd = mysqli_prepare(
                    $koneksi,
                    "UPDATE spp SET tahun = ?, nominal = ? WHERE id_spp = ?"
                );
                mysqli_stmt_bind_param($upd, "iii", $tahun_int, $nominal_int, $id);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                unset($_SESSION['csrf_token']);

                header("Location: spp.php");
                exit;
            }

        } catch (Throwable $ex) {

            $error = "Data gagal diperbarui: " . $ex->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit SPP - SPP Digital</title>

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
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            max-width: 800px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #172554;
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

        .btn-update {
            background: #f59e0b;
            color: white;
        }

        .btn-update:hover {
            background: #d97706;
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
        <a href="siswa.php">👨‍🎓 &nbsp; Data Siswa</a>
        <a href="spp.php" class="active">💰 &nbsp; Data SPP</a>
        <a href="pembayaran.php">💳 &nbsp; Pembayaran</a>
        <a href="laporan.php">📊 &nbsp; Laporan</a>
        <a href="logout.php">🚪 &nbsp; Logout</a>
    </div>

</div>

<!-- CONTENT -->

<div class="content">

    <div class="header">

        <h1>Edit Data SPP</h1>

        <div class="user">
            Login sebagai:
            <strong><?= e($username) ?></strong>
        </div>

    </div>

    <div class="card">

        <h2>Form Edit SPP</h2>

        <?php if ($error !== ""): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <div class="form-group">

                <label for="tahun">Tahun SPP</label>

                <input
                    type="number"
                    id="tahun"
                    name="tahun"
                    placeholder="Contoh: 2026"
                    min="2000"
                    max="2100"
                    value="<?= e($spp['tahun']) ?>"
                    required
                    autofocus
                >

                <div class="info">Masukkan tahun SPP, contoh: 2026</div>

            </div>

            <div class="form-group">

                <label for="nominal">Nominal SPP</label>

                <input
                    type="number"
                    id="nominal"
                    name="nominal"
                    placeholder="Contoh: 150000"
                    min="1"
                    value="<?= e($spp['nominal']) ?>"
                    required
                >

                <div class="info">Masukkan nominal SPP tanpa tanda titik atau koma.</div>

            </div>

            <div class="buttons">

                <button type="submit" name="update" class="btn-update">
                    💾 Update SPP
                </button>

                <a href="spp.php" class="btn-kembali">↩ Kembali</a>

            </div>

        </form>

    </div>

</div>

</body>

</html>