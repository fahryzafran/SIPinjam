<?php

require_once "../config/database.php";
require_once "../config/app.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    header("Location: " . BASE_URL);
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, nama, email, password_hash, role, nim_nip
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'nama' => $user['nama'],
                'email' => $user['email'],
                'role' => $user['role'],
                'nim_nip' => $user['nim_nip']
            ];

            switch ($user['role']) {
                case 'mahasiswa':
                    header("Location: " . BASE_URL . "/mahasiswa/dashboard.php");
                    break;

                case 'dosen':
                    header("Location: " . BASE_URL . "/dosen/dashboard.php");
                    break;

                case 'adminlab':
                    header("Location: " . BASE_URL . "/adminlab/dashboard.php");
                    break;

                case 'superadmin':
                    header("Location: " . BASE_URL . "/superadmin/dashboard.php");
                    break;

                default:
                    session_destroy();
                    $error = "Role pengguna tidak valid.";
            }

            if ($error === "") {
                exit;
            }

        } else {
            $error = "Email atau password salah.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars(APP_NAME) ?></title>
</head>

<body>

    <h1><?= htmlspecialchars(APP_NAME) ?></h1>

    <h2>Login</h2>

    <?php if ($error !== ""): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">

        <div>
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                required
            >
        </div>

        <br>

        <div>
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                required
            >
        </div>

        <br>

        <button type="submit">Login</button>

    </form>

</body>
</html>