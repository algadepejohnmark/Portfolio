
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';
$csrfToken = getCsrfToken();

// Add a new skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $message = 'Your session token expired. Please reload the page and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '') && isset($_POST['add_skill'])) {

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
// Update an existing skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '') && isset($_POST['update_skill'])) {

    $id = (int) $_POST['id'];
    $skill_name = trim($_POST['skill_name']);
    $category = trim($_POST['category']);
    $proficiency = trim($_POST['proficiency']);

    if ($id > 0 && $skill_name !== '') {
        $stmt = $pdo->prepare(
            "UPDATE skills
             SET skill_name = ?, category = ?, proficiency = ?
             WHERE id = ?"
        );

        $stmt->execute([$skill_name, $category, $proficiency, $id]);
        $message = 'Skill updated successfully!';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '') && isset($_POST['delete_skill'])) {

    $id = (int) $_POST['id'];

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM skills WHERE id = ?");
        $stmt->execute([$id]);

        $message = 'Skill deleted successfully!';
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
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

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
            <th>Actions</th>
        </tr>

        <?php foreach ($skills as $skill): ?>
        <tr>
            <td><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($skill['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($skill['proficiency'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="id"
           value="<?= (int) $skill['id'] ?>">

    <input type="text" name="skill_name"
           value="<?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>"
           required>

    <input type="text" name="category"
           value="<?= htmlspecialchars($skill['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <select name="proficiency">
        <?php foreach (['Beginner', 'Intermediate', 'Advanced', 'Expert'] as $level): ?>
            <option value="<?= $level ?>"
                <?= $skill['proficiency'] === $level ? 'selected' : '' ?>>
                <?= $level ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" name="update_skill">Save Changes</button>
</form>

    <form method="POST"
          onsubmit="return confirm('Are you sure you want to delete this skill?');">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <input type="hidden" name="id"
               value="<?= (int) $skill['id'] ?>">

        <button type="submit" name="delete_skill">
            Delete
        </button>
    </form>
</td>
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
