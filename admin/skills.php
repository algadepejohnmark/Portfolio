
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';

// Add a new skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill'])) {

    $skill_name = trim($_POST['skill_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $proficiency = trim($_POST['proficiency'] ?? '');

    if ($skill_name === '') {
        $message = 'Skill name is required.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO skills (skill_name, category, proficiency)
             VALUES (?, ?, ?)"
        );

        $stmt->execute([
            $skill_name,
            $category,
            $proficiency
        ]);

        $message = 'Skill added successfully!';
    }
}

// Retrieve all skills
$stmt = $pdo->query("SELECT * FROM skills ORDER BY id DESC");
$skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Skills</title>
</head>
<body>

<h2>Manage Skills</h2>

<p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

<form method="POST">

    <label>Skill Name</label><br>
    <input type="text" name="skill_name" required><br><br>

    <label>Category</label><br>
    <input type="text" name="category"><br><br>

    <label>Proficiency</label><br>
    <select name="proficiency">
        <option value="">Select Level</option>
        <option value="Beginner">Beginner</option>
        <option value="Intermediate">Intermediate</option>
        <option value="Advanced">Advanced</option>
        <option value="Expert">Expert</option>
    </select><br><br>

    <button type="submit" name="add_skill">Add Skill</button>

</form>

<hr>

<h3>Saved Skills</h3>

<?php if (count($skills) > 0): ?>

    <table border="1" cellpadding="10">
        <tr>
            <th>Skill Name</th>
            <th>Category</th>
            <th>Proficiency</th>
        </tr>

        <?php foreach ($skills as $skill): ?>
        <tr>
            <td><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($skill['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($skill['proficiency'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>

    </table>

<?php else: ?>

    <p>No skills added yet.</p>

<?php endif; ?>

<br>
<a href="dashboard.php">Back to Dashboard</a>

</body>
</html>
