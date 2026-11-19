<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$csrfToken = getCsrfToken();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $adminColumns = $pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);
        $passwordColumn = in_array('password_hash', $adminColumns, true)
            ? 'password_hash'
            : (in_array('password', $adminColumns, true) ? 'password' : null);

        if ($passwordColumn === null) {
            $admin = false;
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, username, `' . $passwordColumn . '` AS password_hash FROM admins WHERE username = :username'
            );
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();
        }

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            unset($_SESSION['csrf_token']);

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-box {
            background: white;
            padding: 30px;
            width: 350px;
            max-width: 90%;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
        }

        input, button {
            width: 100%;
            padding: 12px;
            margin-top: 12px;
        }

        button {
            background: #3e2723;
            color: white;
            border: none;
            cursor: pointer;
        }

        .error {
            color: #b91c1c;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Administrator Login</h2>

    <?php if ($error): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="username"
               placeholder="Username" required>

        <input type="password" name="password"
               placeholder="Password" required>

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
