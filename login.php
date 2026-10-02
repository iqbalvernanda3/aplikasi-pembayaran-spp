<?php

session_start();

/*
 * Load koneksi.php
 * Mencari di folder config dulu, lalu di folder yang sama dengan login.php.
 */
$lokasi_koneksi = [
    __DIR__ . '/config/koneksi.php',
    __DIR__ . '/koneksi.php',
];

$koneksi_ditemukan = false;

foreach ($lokasi_koneksi as $file) {
    if (file_exists($file)) {
        require_once $file;
        $koneksi_ditemukan = true;
        break;
    }
}

if (!$koneksi_ditemukan) {
    die("File koneksi.php tidak ditemukan. Pastikan ada di folder config/ atau di folder yang sama dengan login.php.");
}

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    die("Variabel \$koneksi tidak ditemukan. Pastikan koneksi.php memakai nama variabel \$koneksi.");
}

$error = "";

/* Jika sudah login */
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: index.php");
    exit();
}

/* Token CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Proses login */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $token    = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION['csrf_token'], $token)) {

        $error = "Sesi tidak valid. Silakan muat ulang halaman.";

    } elseif ($username === "" || $password === "") {

        $error = "Username dan password wajib diisi.";

    } else {

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT * FROM users WHERE username = ? LIMIT 1"
        );

        if (!$stmt) {

            $error = "Database error: " . mysqli_error($koneksi);

        } else {

            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {

                $user        = mysqli_fetch_assoc($result);
                $password_db = (string)($user["password"] ?? "");

                /*
                 * Cek password:
                 * - password_hash() (disarankan)
                 * - password biasa (hash_equals agar aman dari timing attack)
                 */
                if (
                    password_verify($password, $password_db) ||
                    hash_equals($password_db, $password)
                ) {

                    session_regenerate_id(true);

                    $id = $user["id"] ?? $user["id_user"] ?? $user["id_petugas"] ?? 0;

                    $_SESSION["login"]      = true;
                    $_SESSION["user_id"]    = $id;
                    $_SESSION["id_petugas"] = $id;
                    $_SESSION["username"]   = $user["username"];
                    $_SESSION["nama"]       = $user["nama"]
                                              ?? $user["nama_user"]
                                              ?? $user["nama_petugas"]
                                              ?? $user["username"];

                    unset($_SESSION['csrf_token']);

                    mysqli_stmt_close($stmt);

                    header("Location: index.php");
                    exit();

                } else {

                    $error = "Password salah.";
                }

            } else {

                $error = "Username tidak ditemukan.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login SPP</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #2563eb, #1e40af);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.login-box {
    width: 100%;
    max-width: 380px;
    background: white;
    padding: 35px;
    border-radius: 15px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.login-box h1 {
    text-align: center;
    margin-top: 0;
    margin-bottom: 10px;
    color: #1e40af;
}

.login-box p {
    text-align: center;
    color: #777;
    margin-bottom: 30px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #333;
}

input {
    width: 100%;
    padding: 13px;
    margin-bottom: 18px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #2563eb;
}

button {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 18px;
    text-align: center;
}

.footer {
    text-align: center;
    margin-top: 25px;
    color: #888;
    font-size: 13px;
}

</style>

</head>

<body>

<div class="login-box">

    <h1>SPP Digital</h1>

    <p>Silakan login untuk melanjutkan</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            placeholder="Masukkan username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            required
            autofocus
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Masukkan password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <div class="footer">
        &copy; 2026 SPP Digital
    </div>

</div>

</body>

</html>