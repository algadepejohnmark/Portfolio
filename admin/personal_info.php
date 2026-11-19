
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';
$csrfToken = getCsrfToken();

$stmt = $pdo->query("SELECT * FROM personal_info ORDER BY id DESC LIMIT 1");
$personal = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Your session token expired. Please reload the page and try again.';
    } else {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($full_name === '') {
        $message = 'Full name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
    } else {

        if ($personal) {
            $stmt = $pdo->prepare(
                "UPDATE personal_info
                 SET full_name = ?, email = ?, phone = ?, address = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $full_name,
                $email,
                $phone,
                $address,
                $personal['id']
            ]);

            $message = 'Personal information updated successfully!';

        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO personal_info
                 (full_name, email, phone, address)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $full_name,
                $email,
                $phone,
                $address
            ]);

            $message = 'Personal information saved successfully!';
        }

        $stmt = $pdo->query("SELECT * FROM personal_info ORDER BY id DESC LIMIT 1");
        $personal = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Personal Information</title>
</head>

<body>

    <h2>Manage Personal Information</h2>

    <p>
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>

   
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <label>Full Name</label><br>
    <input
        type="text"
        name="full_name"
        value="<?= htmlspecialchars($personal['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        required
    ><br><br>

    <label>Email</label><br>
    <input
        type="email"
        name="email"
        value="<?= htmlspecialchars($personal['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    ><br><br>

    <label>Phone Number</label><br>
    <input
        type="text"
        name="phone"
        value="<?= htmlspecialchars($personal['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    ><br><br>

    <label>Address</label><br>
    <input
        type="text"
        name="address"
        value="<?= htmlspecialchars($personal['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    ><br><br>

    <button type="submit">Save Information</button>

</form>


    <br>
    <a href="dashboard.php">Back to Dashboard</a>

</body>
</html>
