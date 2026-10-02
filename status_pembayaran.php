<?php

/*
|--------------------------------------------------------------------------
| STATUS PEMBAYARAN SPP (LUNAS / BELUM LUNAS)
|--------------------------------------------------------------------------
| - Nominal SPP dianggap tarif PER BULAN.
| - Siswa dinyatakan LUNAS jika 12 bulan pada tahun SPP terpilih sudah dibayar.
| - Kolom tabel pembayaran dicocokkan otomatis (id_siswa/nisn, id_spp, bulan, dll).
*/

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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================
   FUNGSI BANTU
========================= */

function e($nilai): string
{
    return htmlspecialchars((string)($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return "Rp " . number_format((float)$angka, 0, ',', '.');
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

/* Ubah nilai bulan (nama / angka / singkatan) menjadi 1-12 */
function normalisasi_bulan($nilai, array $daftar): ?int
{
    $nilai = trim((string)$nilai);

    if ($nilai === '') {
        return null;
    }

    if (ctype_digit($nilai)) {
        $n = (int)$nilai;
        return ($n >= 1 && $n <= 12) ? $n : null;
    }

    foreach ($daftar as $i => $nama) {
        if (strcasecmp($nama, $nilai) === 0) {
            return $i + 1;
        }
    }

    foreach ($daftar as $i => $nama) {
        if (strncasecmp($nama, $nilai, 3) === 0) {
            return $i + 1;
        }
    }

    return null;
}

$username = $_SESSION['username'] ?? 'Administrator';

$daftar_bulan = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

$error      = "";
$spp_list   = [];
$siswa_list = [];
$kelas_list = [];
$baris      = [];   // data akhir yang ditampilkan
$ringkasan  = ['total' => 0, 'lunas' => 0, 'belum' => 0, 'tunggakan' => 0, 'terbayar' => 0];

/* =========================
   FILTER
========================= */

$f_spp    = filter_input(INPUT_GET, 'spp', FILTER_VALIDATE_INT) ?: 0;
$f_kelas  = trim($_GET['kelas'] ?? '');
$f_status = $_GET['status'] ?? 'semua';
$f_cari   = trim($_GET['q'] ?? '');

if (!in_array($f_status, ['semua', 'lunas', 'belum'], true)) {
    $f_status = 'semua';
}

$spp_aktif = null;

try {

    /* ---- Data SPP ---- */
    $q = mysqli_query($koneksi, "SELECT id_spp, tahun, nominal FROM spp ORDER BY tahun DESC");

    while ($r = mysqli_fetch_assoc($q)) {
        $spp_list[] = $r;
    }

    foreach ($spp_list as $s) {
        if ((int)$s['id_spp'] === $f_spp) {
            $spp_aktif = $s;
        }
    }

    if ($spp_aktif === null && !empty($spp_list)) {
        $spp_aktif = $spp_list[0];          // default: tahun SPP terbaru
        $f_spp     = (int)$spp_aktif['id_spp'];
    }

    /* ---- Data siswa ---- */
    $q = mysqli_query(
        $koneksi,
        "SELECT id_siswa, nisn, nama_siswa, kelas FROM siswa ORDER BY kelas ASC, nama_siswa ASC"
    );

    while ($r = mysqli_fetch_assoc($q)) {
        $siswa_list[] = $r;
        $kls = trim((string)($r['kelas'] ?? ''));
        if ($kls !== '') {
            $kelas_list[$kls] = $kls;
        }
    }

    ksort($kelas_list);

    /* ---- Struktur tabel pembayaran ---- */
    $pembayaran_ada = true;
    $kolom          = [];

    try {
        $rs = mysqli_query($koneksi, "SHOW COLUMNS FROM pembayaran");
        while ($r = mysqli_fetch_assoc($rs)) {
            $kolom[strtolower($r['Field'])] = strtolower($r['Type']);
        }
    } catch (Throwable $ex) {
        $pembayaran_ada = false;
        $error = "Tabel pembayaran belum ada. Semua siswa dianggap belum membayar.";
    }

    $map = [
        'siswa_id'   => cari_kolom($kolom, ['id_siswa']),
        'siswa_nisn' => cari_kolom($kolom, ['nisn']),
        'spp'        => cari_kolom($kolom, ['id_spp']),
        'bulan'      => cari_kolom($kolom, ['bulan_dibayar', 'bulan_bayar', 'bulan']),
        'tahun'      => cari_kolom($kolom, ['tahun_dibayar', 'tahun_bayar', 'tahun']),
        'jumlah'     => cari_kolom($kolom, ['jumlah_bayar', 'jumlah', 'total_bayar', 'nominal']),
    ];

    $pakai_id = $map['siswa_id'] !== null;

    if ($pembayaran_ada && $map['siswa_id'] === null && $map['siswa_nisn'] === null) {
        $pembayaran_ada = false;
        $error = "Tabel pembayaran tidak memiliki kolom id_siswa atau nisn. "
               . "Kolom yang ada: " . implode(", ", array_keys($kolom)) . ".";
    }

    /* ---- Ambil data pembayaran untuk SPP terpilih ---- */
    $bayar = [];   // key siswa => ['bulan' => [1=>true,...], 'jumlah' => total, 'baris' => n]

    if ($pembayaran_ada && $spp_aktif !== null) {

        $kolom_siswa  = $pakai_id ? $map['siswa_id'] : $map['siswa_nisn'];
        $sel_bulan    = $map['bulan']  !== null ? "`{$map['bulan']}`"  : "NULL";
        $sel_jumlah   = $map['jumlah'] !== null ? "`{$map['jumlah']}`" : "0";

        $sql = "SELECT `$kolom_siswa` AS s, $sel_bulan AS b, $sel_jumlah AS j FROM pembayaran";

        if ($map['spp'] !== null) {

            $sql .= " WHERE `{$map['spp']}` = ?";
            $st = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($st, "i", $spp_aktif['id_spp']);

        } elseif ($map['tahun'] !== null) {

            $sql .= " WHERE `{$map['tahun']}` = ?";
            $st = mysqli_prepare($koneksi, $sql);
            $tahun_spp = (int)$spp_aktif['tahun'];
            mysqli_stmt_bind_param($st, "i", $tahun_spp);

        } else {

            $st = mysqli_prepare($koneksi, $sql);
        }

        mysqli_stmt_execute($st);
        mysqli_stmt_bind_result($st, $r_siswa, $r_bulan, $r_jumlah);

        while (mysqli_stmt_fetch($st)) {

            $key = (string)$r_siswa;

            if (!isset($bayar[$key])) {
                $bayar[$key] = ['bulan' => [], 'jumlah' => 0, 'baris' => 0];
            }

            $bayar[$key]['jumlah'] += (float)$r_jumlah;
            $bayar[$key]['baris']++;

            $b = normalisasi_bulan($r_bulan, $daftar_bulan);

            if ($b !== null) {
                $bayar[$key]['bulan'][$b] = true;
            }
        }

        mysqli_stmt_close($st);
    }

    /* ---- Hitung status tiap siswa ---- */
    $nominal      = $spp_aktif ? (float)$spp_aktif['nominal'] : 0;
    $punya_bulan  = $map['bulan'] !== null;

    foreach ($siswa_list as $sw) {

        /* Filter kelas & pencarian */
        if ($f_kelas !== '' && trim((string)$sw['kelas']) !== $f_kelas) {
            continue;
        }

        if ($f_cari !== '') {
            $cari_di = strtolower(($sw['nama_siswa'] ?? '') . ' ' . ($sw['nisn'] ?? ''));
            if (strpos($cari_di, strtolower($f_cari)) === false) {
                continue;
            }
        }

        $key  = (string)($pakai_id ? $sw['id_siswa'] : $sw['nisn']);
        $data = $bayar[$key] ?? ['bulan' => [], 'jumlah' => 0, 'baris' => 0];

        if ($punya_bulan) {
            $terbayar = count($data['bulan']);
        } else {
            $terbayar = min(12, $data['baris']);
        }

        $belum_bulan = [];

        if ($punya_bulan) {
            for ($i = 1; $i <= 12; $i++) {
                if (!isset($data['bulan'][$i])) {
                    $belum_bulan[] = $i;
                }
            }
        }

        $tunggakan = max(0, 12 - $terbayar) * $nominal;
        $lunas     = $terbayar >= 12;

        $ringkasan['total']++;
        $ringkasan['terbayar']  += $data['jumlah'];

        if ($lunas) {
            $ringkasan['lunas']++;
        } else {
            $ringkasan['belum']++;
            $ringkasan['tunggakan'] += $tunggakan;
        }

        /* Filter status dilakukan terakhir agar ringkasan tetap lengkap */
        if ($f_status === 'lunas' && !$lunas) {
            continue;
        }

        if ($f_status === 'belum' && $lunas) {
            continue;
        }

        $baris[] = [
            'nisn'      => $sw['nisn'] ?? '',
            'nama'      => $sw['nama_siswa'] ?? '',
            'kelas'     => $sw['kelas'] ?? '',
            'terbayar'  => $terbayar,
            'belum'     => $belum_bulan,
            'jumlah'    => $data['jumlah'],
            'tunggakan' => $tunggakan,
            'lunas'     => $lunas,
        ];
    }

} catch (Throwable $ex) {

    $error = "Terjadi kesalahan: " . $ex->getMessage();
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Status Pembayaran - SPP Digital</title>

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

        /* RINGKASAN */

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #2563eb;
        }

        .stat span {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .stat strong {
            font-size: 22px;
            color: #172554;
        }

        .stat.hijau {
            border-left-color: #16a34a;
        }

        .stat.merah {
            border-left-color: #dc2626;
        }

        .stat.kuning {
            border-left-color: #f59e0b;
        }

        /* CARD */

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .card h2 {
            margin: 0 0 18px;
            color: #172554;
        }

        /* FILTER */

        .filter {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: flex-end;
        }

        .filter label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            color: #334155;
            margin-bottom: 6px;
        }

        .filter select,
        .filter input {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            min-width: 150px;
        }

        .filter select:focus,
        .filter input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .btn {
            display: inline-block;
            padding: 11px 18px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-cari {
            background: #2563eb;
            color: white;
        }

        .btn-cari:hover {
            background: #1d4ed8;
        }

        .btn-reset {
            background: #64748b;
            color: white;
        }

        .btn-reset:hover {
            background: #475569;
        }

        /* TABLE */

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th {
            background: #172554;
            color: white;
            padding: 13px 12px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 14px;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* PROGRESS */

        .progress {
            width: 110px;
            height: 8px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 6px;
        }

        .progress div {
            height: 100%;
            background: #2563eb;
        }

        .progress div.penuh {
            background: #16a34a;
        }

        /* BADGE */

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge.lunas {
            background: #dcfce7;
            color: #166534;
        }

        .badge.belum {
            background: #fee2e2;
            color: #991b1b;
        }

        .chip {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            margin: 2px 2px 2px 0;
        }

        .tunggakan {
            color: #dc2626;
            font-weight: bold;
        }

        .btn-bayar {
            display: inline-block;
            padding: 6px 12px;
            background: #16a34a;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-bayar:hover {
            background: #15803d;
        }

        /* ALERT & EMPTY */

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        .catatan {
            margin-top: 15px;
            color: #64748b;
            font-size: 13px;
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
        <a href="spp.php">💰 &nbsp; Data SPP</a>
        <a href="pembayaran.php">💳 &nbsp; Pembayaran</a>
        <a href="status_pembayaran.php" class="active">✅ &nbsp; Status Pembayaran</a>
        <a href="laporan.php">📊 &nbsp; Laporan</a>
        <a href="logout.php">🚪 &nbsp; Logout</a>
    </div>

</div>

<!-- CONTENT -->

<div class="content">

    <div class="header">

        <h1>Status Pembayaran</h1>

        <div class="user">
            Login sebagai:
            <strong><?= e($username) ?></strong>
        </div>

    </div>

    <?php if ($error !== ""): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- RINGKASAN -->

    <div class="stats">

        <div class="stat">
            <span>Total Siswa</span>
            <strong><?= (int)$ringkasan['total'] ?></strong>
        </div>

        <div class="stat hijau">
            <span>Sudah Lunas</span>
            <strong><?= (int)$ringkasan['lunas'] ?></strong>
        </div>

        <div class="stat merah">
            <span>Belum Lunas</span>
            <strong><?= (int)$ringkasan['belum'] ?></strong>
        </div>

        <div class="stat kuning">
            <span>Total Tunggakan</span>
            <strong><?= rupiah($ringkasan['tunggakan']) ?></strong>
        </div>

        <div class="stat hijau">
            <span>Total Terbayar</span>
            <strong><?= rupiah($ringkasan['terbayar']) ?></strong>
        </div>

    </div>

    <!-- TABEL -->

    <div class="card">

        <h2>
            Daftar Status Siswa
            <?php if ($spp_aktif): ?>
                &ndash; SPP <?= e($spp_aktif['tahun']) ?>
            <?php endif; ?>
        </h2>

        <form method="GET" class="filter">

            <div>
                <label for="spp">Tahun SPP</label>
                <select id="spp" name="spp">
                    <?php foreach ($spp_list as $s): ?>
                        <option value="<?= (int)$s['id_spp'] ?>"
                            <?= $f_spp === (int)$s['id_spp'] ? 'selected' : '' ?>>
                            <?= e($s['tahun']) ?> (<?= rupiah($s['nominal']) ?>/bulan)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="kelas">Kelas</label>
                <select id="kelas" name="kelas">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelas_list as $k): ?>
                        <option value="<?= e($k) ?>" <?= $f_kelas === $k ? 'selected' : '' ?>>
                            <?= e($k) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="semua" <?= $f_status === 'semua' ? 'selected' : '' ?>>Semua</option>
                    <option value="lunas" <?= $f_status === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                    <option value="belum" <?= $f_status === 'belum' ? 'selected' : '' ?>>Belum Lunas</option>
                </select>
            </div>

            <div>
                <label for="q">Cari Nama / NISN</label>
                <input type="text" id="q" name="q" placeholder="Ketik di sini..." value="<?= e($f_cari) ?>">
            </div>

            <div>
                <button type="submit" class="btn btn-cari">🔍 Tampilkan</button>
                <a href="status_pembayaran.php" class="btn btn-reset">Reset</a>
            </div>

        </form>

        <div class="table-container">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>NISN</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Bulan Terbayar</th>
                        <th>Belum Dibayar</th>
                        <th>Total Dibayar</th>
                        <th>Tunggakan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($spp_list)): ?>

                    <tr>
                        <td colspan="9" class="empty">Belum ada data SPP. Tambahkan data SPP terlebih dahulu.</td>
                    </tr>

                <?php elseif (empty($baris)): ?>

                    <tr>
                        <td colspan="9" class="empty">Tidak ada data siswa yang cocok dengan filter.</td>
                    </tr>

                <?php else: ?>

                    <?php $no = 1; foreach ($baris as $b): ?>

                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= e($b['nisn']) ?></td>
                        <td><?= e($b['nama']) ?></td>
                        <td><?= e($b['kelas']) ?></td>

                        <td>
                            <strong><?= (int)$b['terbayar'] ?> / 12</strong>
                            <div class="progress">
                                <div class="<?= $b['lunas'] ? 'penuh' : '' ?>"
                                     style="width: <?= min(100, round($b['terbayar'] / 12 * 100)) ?>%"></div>
                            </div>
                        </td>

                        <td>
                            <?php if ($b['lunas']): ?>
                                -
                            <?php elseif (!empty($b['belum'])): ?>
                                <?php foreach ($b['belum'] as $bulan_ke): ?>
                                    <span class="chip"><?= e(substr($daftar_bulan[$bulan_ke - 1], 0, 3)) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?= 12 - (int)$b['terbayar'] ?> bulan
                            <?php endif; ?>
                        </td>

                        <td><?= rupiah($b['jumlah']) ?></td>

                        <td class="<?= $b['lunas'] ? '' : 'tunggakan' ?>">
                            <?= $b['lunas'] ? '-' : rupiah($b['tunggakan']) ?>
                        </td>

                        <td>
                            <?php if ($b['lunas']): ?>
                                <span class="badge lunas">✔ Lunas</span>
                            <?php else: ?>
                                <span class="badge belum">Belum Lunas</span><br><br>
                                <a href="pembayaran_tambah.php" class="btn-bayar">+ Bayar</a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="catatan">
            Catatan: nominal SPP dihitung per bulan. Siswa dinyatakan lunas jika 12 bulan
            pada tahun SPP terpilih sudah dibayar.
        </div>

    </div>

</div>

</body>

</html>