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

/*
 * Pastikan error MySQL selalu dilempar sebagai exception,
 * sehingga pesan error asli tampil di form (bukan "bool given").
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================
   FUNGSI BANTU
========================= */

function e($nilai): string
{
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

function cari_kolom(array $kolom, array $kandidat): ?string
{
    foreach ($kandidat as $nama) {
        if (isset($kolom[$nama])) {
            return $nama;
        }
    }
    return null;
}

$username   = $_SESSION['username'] ?? 'Administrator';
$id_petugas = (int)($_SESSION['id_petugas'] ?? $_SESSION['user_id'] ?? 0);
$error      = "";

$daftar_bulan = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

/* Token CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   SIAPKAN TABEL PEMBAYARAN
========================= */

$siswa_list = [];
$spp_list   = [];
$kolom      = [];   // nama_kolom => tipe
$map        = [];

try {

    /* Buat tabel jika belum ada (tidak mengubah tabel yang sudah ada) */
    mysqli_query(
        $koneksi,
        "CREATE TABLE IF NOT EXISTS pembayaran (
            id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
            id_petugas    INT NOT NULL DEFAULT 0,
            id_siswa      INT NOT NULL,
            id_spp        INT NOT NULL,
            tanggal_bayar DATE NOT NULL,
            bulan_dibayar VARCHAR(20) NOT NULL,
            tahun_dibayar INT NOT NULL,
            jumlah_bayar  INT NOT NULL
        )"
    );

    /* Baca kolom tabel pembayaran yang sebenarnya */
    $rs = mysqli_query($koneksi, "SHOW COLUMNS FROM pembayaran");

    while ($r = mysqli_fetch_assoc($rs)) {
        $kolom[strtolower($r['Field'])] = strtolower($r['Type']);
    }

    /* Cocokkan kolom dengan beberapa kemungkinan nama */
    $map = [
        'petugas'    => cari_kolom($kolom, ['id_petugas', 'id_user', 'user_id']),
        'siswa_id'   => cari_kolom($kolom, ['id_siswa']),
        'siswa_nisn' => cari_kolom($kolom, ['nisn']),
        'spp'        => cari_kolom($kolom, ['id_spp']),
        'tanggal'    => cari_kolom($kolom, ['tanggal_bayar', 'tgl_bayar', 'tanggal']),
        'bulan'      => cari_kolom($kolom, ['bulan_dibayar', 'bulan_bayar', 'bulan']),
        'tahun'      => cari_kolom($kolom, ['tahun_dibayar', 'tahun_bayar', 'tahun']),
        'jumlah'     => cari_kolom($kolom, ['jumlah_bayar', 'jumlah', 'total_bayar', 'nominal']),
    ];

    if (
        ($map['siswa_id'] === null && $map['siswa_nisn'] === null) ||
        $map['tanggal'] === null ||
        $map['jumlah'] === null
    ) {
        $error = "Struktur tabel pembayaran tidak cocok dengan form. "
               . "Kolom yang ada: " . implode(", ", array_keys($kolom)) . ".";
    }

    /* Data dropdown */
    $q_siswa = mysqli_query(
        $koneksi,
        "SELECT id_siswa, nisn, nama_siswa, kelas FROM siswa ORDER BY nama_siswa ASC"
    );

    while ($row = mysqli_fetch_assoc($q_siswa)) {
        $siswa_list[] = $row;
    }

    $q_spp = mysqli_query(
        $koneksi,
        "SELECT id_spp, tahun, nominal FROM spp ORDER BY tahun DESC"
    );

    while ($row = mysqli_fetch_assoc($q_spp)) {
        $spp_list[] = $row;
    }

} catch (Throwable $ex) {

    $error = "Gagal menyiapkan data: " . $ex->getMessage();
}

/* =========================
   PROSES SIMPAN
========================= */

if ($error === "" && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['simpan'])) {

    $id_siswa      = (int)($_POST['id_siswa'] ?? 0);
    $id_spp        = (int)($_POST['id_spp'] ?? 0);
    $bulan         = trim($_POST['bulan_dibayar'] ?? '');
    $tahun_dibayar = trim($_POST['tahun_dibayar'] ?? '');
    $tanggal_bayar = trim($_POST['tanggal_bayar'] ?? '');
    $jumlah_bayar  = trim($_POST['jumlah_bayar'] ?? '');
    $token         = $_POST['csrf_token'] ?? '';

    $tgl_valid = DateTime::createFromFormat('Y-m-d', $tanggal_bayar);

    if (!hash_equals($_SESSION['csrf_token'], $token)) {

        $error = "Sesi tidak valid. Silakan muat ulang halaman.";

    } elseif ($id_siswa <= 0 || $id_spp <= 0) {

        $error = "Siswa dan data SPP wajib dipilih.";

    } elseif (!in_array($bulan, $daftar_bulan, true)) {

        $error = "Bulan pembayaran tidak valid.";

    } elseif (!ctype_digit($tahun_dibayar) || strlen($tahun_dibayar) !== 4) {

        $error = "Tahun dibayar harus berupa 4 angka.";

    } elseif (!$tgl_valid || $tgl_valid->format('Y-m-d') !== $tanggal_bayar) {

        $error = "Tanggal bayar tidak valid.";

    } elseif (!is_numeric($jumlah_bayar) || (float)$jumlah_bayar <= 0) {

        $error = "Jumlah bayar harus lebih dari 0.";

    } else {

        $tahun_int  = (int)$tahun_dibayar;
        $jumlah_int = (int)round((float)$jumlah_bayar);

        try {

            /* Ambil NISN siswa (sekaligus memastikan siswa ada) */
            $st = mysqli_prepare($koneksi, "SELECT nisn FROM siswa WHERE id_siswa = ? LIMIT 1");
            mysqli_stmt_bind_param($st, "i", $id_siswa);
            mysqli_stmt_execute($st);
            mysqli_stmt_bind_result($st, $nisn_siswa);
            $siswa_ada = mysqli_stmt_fetch($st) === true;
            mysqli_stmt_close($st);

            /* Pastikan data SPP ada */
            $st = mysqli_prepare($koneksi, "SELECT 1 FROM spp WHERE id_spp = ? LIMIT 1");
            mysqli_stmt_bind_param($st, "i", $id_spp);
            mysqli_stmt_execute($st);
            mysqli_stmt_store_result($st);
            $spp_ada = mysqli_stmt_num_rows($st) > 0;
            mysqli_stmt_close($st);

            if (!$siswa_ada || !$spp_ada) {

                $error = "Data siswa atau SPP tidak ditemukan.";

            } else {

                /* Nilai bulan: angka jika kolom bertipe INT, selain itu nama bulan */
                $bulan_angka = array_search($bulan, $daftar_bulan, true) + 1;
                $bulan_int   = $map['bulan'] !== null && strpos($kolom[$map['bulan']], 'int') !== false;

                /* Susun kolom yang akan diisi: [kolom, tipe bind, nilai] */
                $isi = [];

                if ($map['petugas'] !== null) {
                    $isi[] = [$map['petugas'], 'i', $id_petugas];
                }

                if ($map['siswa_id'] !== null) {
                    $isi[] = [$map['siswa_id'], 'i', $id_siswa];
                } else {
                    $isi[] = [$map['siswa_nisn'], 's', (string)$nisn_siswa];
                }

                if ($map['spp'] !== null) {
                    $isi[] = [$map['spp'], 'i', $id_spp];
                }

                $isi[] = [$map['tanggal'], 's', $tanggal_bayar];

                if ($map['bulan'] !== null) {
                    $isi[] = $bulan_int
                        ? [$map['bulan'], 'i', $bulan_angka]
                        : [$map['bulan'], 's', $bulan];
                }

                if ($map['tahun'] !== null) {
                    $isi[] = [$map['tahun'], 'i', $tahun_int];
                }

                $isi[] = [$map['jumlah'], 'i', $jumlah_int];

                /* Cegah pembayaran ganda (siswa + bulan + tahun yang sama) */
                $sudah_bayar = false;

                if ($map['bulan'] !== null && $map['tahun'] !== null) {

                    $kolom_siswa = $map['siswa_id'] ?? $map['siswa_nisn'];
                    $tipe_siswa  = $map['siswa_id'] !== null ? 'i' : 's';
                    $nilai_siswa = $map['siswa_id'] !== null ? $id_siswa : (string)$nisn_siswa;
                    $nilai_bulan = $bulan_int ? $bulan_angka : $bulan;
                    $tipe_bulan  = $bulan_int ? 'i' : 's';

                    $st = mysqli_prepare(
                        $koneksi,
                        "SELECT 1 FROM pembayaran
                         WHERE `$kolom_siswa` = ? AND `{$map['bulan']}` = ? AND `{$map['tahun']}` = ?
                         LIMIT 1"
                    );
                    mysqli_stmt_bind_param($st, $tipe_siswa . $tipe_bulan . 'i', $nilai_siswa, $nilai_bulan, $tahun_int);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_store_result($st);
                    $sudah_bayar = mysqli_stmt_num_rows($st) > 0;
                    mysqli_stmt_close($st);
                }

                if ($sudah_bayar) {

                    $error = "Siswa ini sudah membayar SPP bulan $bulan $tahun_int.";

                } else {

                    /* Simpan pembayaran */
                    $nama_kolom  = implode(", ", array_map(fn($i) => "`" . $i[0] . "`", $isi));
                    $placeholder = implode(", ", array_fill(0, count($isi), "?"));
                    $tipe        = implode("", array_column($isi, 1));
                    $nilai       = array_column($isi, 2);

                    $stmt = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO pembayaran ($nama_kolom) VALUES ($placeholder)"
                    );
                    mysqli_stmt_bind_param($stmt, $tipe, ...$nilai);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    unset($_SESSION['csrf_token']);

                    header("Location: pembayaran.php");
                    exit;
                }
            }

        } catch (Throwable $ex) {

            $error = "Data gagal disimpan: " . $ex->getMessage();
        }
    }
}

/* Nilai default form */
$val_siswa   = (int)($_POST['id_siswa'] ?? 0);
$val_spp     = (int)($_POST['id_spp'] ?? 0);
$val_bulan   = $_POST['bulan_dibayar'] ?? $daftar_bulan[(int)date('n') - 1];
$val_tahun   = $_POST['tahun_dibayar'] ?? date('Y');
$val_tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
$val_jumlah  = $_POST['jumlah_bayar'] ?? '';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Pembayaran - SPP Digital</title>

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

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #334155;
        }

        input,
        select {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            background: white;
        }

        input:focus,
        select:focus {
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

            .row {
                grid-template-columns: 1fr;
                gap: 0;
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
        <a href="spp.php">💰 &nbsp; Data SPP</a>
        <a href="pembayaran.php" class="active">💳 &nbsp; Pembayaran</a>
        <a href="laporan.php">📊 &nbsp; Laporan</a>
        <a href="logout.php">🚪 &nbsp; Logout</a>
    </div>

</div>

<!-- CONTENT -->

<div class="content">

    <div class="header">

        <h1>Tambah Pembayaran</h1>

        <div class="user">
            Login sebagai:
            <strong><?= e($username) ?></strong>
        </div>

    </div>

    <div class="card">

        <h2>Form Pembayaran SPP</h2>

        <?php if ($error !== ""): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <!-- SISWA -->
            <div class="form-group">

                <label for="id_siswa">Siswa</label>

                <select id="id_siswa" name="id_siswa" required>

                    <option value="">-- Pilih Siswa --</option>

                    <?php foreach ($siswa_list as $s): ?>
                        <option value="<?= (int)$s['id_siswa'] ?>"
                            <?= $val_siswa === (int)$s['id_siswa'] ? 'selected' : '' ?>>
                            <?= e($s['nisn'] ?? '') ?> - <?= e($s['nama_siswa'] ?? '') ?>
                            (<?= e($s['kelas'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>

                </select>

                <?php if (empty($siswa_list)): ?>
                    <div class="info">Belum ada data siswa. Tambahkan siswa terlebih dahulu.</div>
                <?php endif; ?>

            </div>

            <!-- SPP -->
            <div class="form-group">

                <label for="id_spp">Data SPP</label>

                <select id="id_spp" name="id_spp" required>

                    <option value="">-- Pilih Tahun SPP --</option>

                    <?php foreach ($spp_list as $p): ?>
                        <option value="<?= (int)$p['id_spp'] ?>"
                            data-nominal="<?= (int)$p['nominal'] ?>"
                            <?= $val_spp === (int)$p['id_spp'] ? 'selected' : '' ?>>
                            Tahun <?= e($p['tahun']) ?> - Rp <?= number_format((float)$p['nominal'], 0, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <?php if (empty($spp_list)): ?>
                    <div class="info">Belum ada data SPP. Tambahkan data SPP terlebih dahulu.</div>
                <?php endif; ?>

            </div>

            <!-- BULAN & TAHUN -->
            <div class="row">

                <div class="form-group">

                    <label for="bulan_dibayar">Bulan Dibayar</label>

                    <select id="bulan_dibayar" name="bulan_dibayar" required>
                        <?php foreach ($daftar_bulan as $b): ?>
                            <option value="<?= e($b) ?>" <?= $val_bulan === $b ? 'selected' : '' ?>>
                                <?= e($b) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <div class="form-group">

                    <label for="tahun_dibayar">Tahun Dibayar</label>

                    <input
                        type="number"
                        id="tahun_dibayar"
                        name="tahun_dibayar"
                        min="2000"
                        max="2100"
                        value="<?= e($val_tahun) ?>"
                        required
                    >

                </div>

            </div>

            <!-- TANGGAL & JUMLAH -->
            <div class="row">

                <div class="form-group">

                    <label for="tanggal_bayar">Tanggal Bayar</label>

                    <input
                        type="date"
                        id="tanggal_bayar"
                        name="tanggal_bayar"
                        value="<?= e($val_tanggal) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="jumlah_bayar">Jumlah Bayar (Rp)</label>

                    <input
                        type="number"
                        id="jumlah_bayar"
                        name="jumlah_bayar"
                        placeholder="Otomatis sesuai nominal SPP"
                        min="1"
                        value="<?= e($val_jumlah) ?>"
                        required
                    >

                </div>

            </div>

            <div class="info">
                Jumlah bayar terisi otomatis dari nominal SPP yang dipilih, dan masih bisa diubah.
            </div>

            <div class="buttons">

                <button type="submit" name="simpan" class="btn-simpan">
                    💾 Simpan Pembayaran
                </button>

                <a href="pembayaran.php" class="btn-kembali">↩ Kembali</a>

            </div>

        </form>

    </div>

</div>

<script>

    /* Isi jumlah bayar otomatis saat memilih SPP */
    var selectSpp = document.getElementById('id_spp');
    var inputJumlah = document.getElementById('jumlah_bayar');

    selectSpp.addEventListener('change', function () {
        var opt = selectSpp.options[selectSpp.selectedIndex];
        var nominal = opt ? opt.getAttribute('data-nominal') : '';
        if (nominal) {
            inputJumlah.value = nominal;
        }
    });

    /* Isi otomatis juga saat halaman dibuka dan jumlah masih kosong */
    if (selectSpp.value && !inputJumlah.value) {
        selectSpp.dispatchEvent(new Event('change'));
    }

</script>

</body>

</html>