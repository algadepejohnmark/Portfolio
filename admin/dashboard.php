
<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f2ed;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .header, .menu {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .menu a {
            display: block;
            padding: 14px;
            margin: 10px 0;
            background: #3e2723;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .menu a:hover {
            background: #5d4037;
        }

        .logout {
            color: #a52a2a;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>Administrator Dashboard</h1>

        <p>
            Welcome,
            <?= htmlspecialchars($_SESSION['admin_username'], ENT_QUOTES, 'UTF-8') ?>!
        </p>

        <p>You are successfully logged in.</p>

        <a class="logout" href="logout.php">Logout</a>
    </div>

    <div class="menu">
        <h2>Portfolio Management</h2>

        <a href="personal_info.php">Personal Information</a>
        <a href="skills.php">Manage Skills</a>
        <a href="projects.php">Manage Projects</a>
        <a href="education.php">Manage Education</a>
        <a href="experience.php">Manage Experience</a>
        <a href="achievements.php">Manage Achievements</a>
        <a href="messages.php">Contact Messages</a>
        <a href="settings.php">Website Settings</a>
    </div>

</div>

</body>
</html>
