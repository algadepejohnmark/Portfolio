<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';
$error = '';
$education = null;
$formValues = [
    'school' => '',
    'course' => '',
    'start_year' => '',
    'end_year' => '',
    'description' => '',
];
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            if ($action === 'delete' && $id) {
                $stmt = $pdo->prepare('DELETE FROM education WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Education record not found.');
                }
                $message = 'Education record deleted successfully.';
            } elseif ($action === 'save') {
                $formValues = [
                    'school' => trim($_POST['school'] ?? ''),
                    'course' => trim($_POST['course'] ?? ''),
                    'start_year' => trim($_POST['start_year'] ?? ''),
                    'end_year' => trim($_POST['end_year'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                ];

                if ($formValues['school'] === '' || mb_strlen($formValues['school']) > 200) {
                    throw new RuntimeException('School is required and must be no more than 200 characters.');
                }
                if (mb_strlen($formValues['course']) > 200) {
                    throw new RuntimeException('Course or degree must be no more than 200 characters.');
                }
                foreach (['start_year' => 'Start year', 'end_year' => 'End year'] as $field => $label) {
                    if ($formValues[$field] !== '' && (!preg_match('/\A\d{4}\z/', $formValues[$field]) || (int) $formValues[$field] < 1900 || (int) $formValues[$field] > (int) date('Y') + 10)) {
                        throw new RuntimeException($label . ' must be a valid four-digit year.');
                    }
                }
                if ($formValues['start_year'] !== '' && $formValues['end_year'] !== '' && (int) $formValues['end_year'] < (int) $formValues['start_year']) {
                    throw new RuntimeException('End year cannot be earlier than start year.');
                }

                $startYear = $formValues['start_year'] !== '' ? $formValues['start_year'] : null;
                $endYear = $formValues['end_year'] !== '' ? $formValues['end_year'] : null;
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE education SET school = ?, course = ?, start_year = ?, end_year = ?, description = ? WHERE id = ?');
                    $stmt->execute([$formValues['school'], $formValues['course'] ?: null, $startYear, $endYear, $formValues['description'] ?: null, $id]);
                    if ($stmt->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT id FROM education WHERE id = ?');
                        $check->execute([$id]);
                        if (!$check->fetchColumn()) {
                            throw new RuntimeException('Education record not found.');
                        }
                    }
                    $message = 'Education record updated successfully.';
                } elseif (!empty($_POST['id'])) {
                    throw new RuntimeException('Invalid education record.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO education (school, course, start_year, end_year, description) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$formValues['school'], $formValues['course'] ?: null, $startYear, $endYear, $formValues['description'] ?: null]);
                    $message = 'Education record added successfully.';
                }

                $formValues = ['school' => '', 'course' => '', 'start_year' => '', 'end_year' => '', 'description' => ''];
            } else {
                throw new RuntimeException('Invalid education action.');
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Education management error: ' . $exception->getMessage());
            $error = 'The education record could not be saved. Please try again.';
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM education WHERE id = ?');
        $stmt->execute([$editId]);
        $education = $stmt->fetch() ?: null;
        if ($education) {
            $formValues = [
                'school' => $education['school'],
                'course' => $education['course'] ?? '',
                'start_year' => $education['start_year'] ?? '',
                'end_year' => $education['end_year'] ?? '',
                'description' => $education['description'] ?? '',
            ];
        }
    }
}

$stmt = $pdo->query('SELECT id, school, course, start_year, end_year, description FROM education ORDER BY start_year DESC, id DESC');
$records = $stmt->fetchAll();
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Education | Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f5f2ed; color: #2d211e; font-family: Arial, sans-serif; }
        main { max-width: 1100px; margin: 0 auto; }
        .topbar, .panel { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(45, 33, 30, .07); }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        h1, h2 { margin-top: 0; }
        a { color: #5d4037; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-weight: 700; margin: 0 0 6px; }
        input, textarea { width: 100%; padding: 10px 12px; margin-bottom: 16px; border: 1px solid #cfc6c1; border-radius: 6px; font: inherit; }
        textarea { min-height: 100px; resize: vertical; }
        button, .button { display: inline-block; border: 0; border-radius: 6px; padding: 10px 15px; background: #3e2723; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .secondary { background: #76645d; }
        .danger { background: #a52a2a; }
        .notice { padding: 12px 14px; background: #e9f5eb; border-radius: 6px; }
        .error { padding: 12px 14px; color: #8e1e1e; background: #fdecec; border-radius: 6px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td { text-align: left; vertical-align: top; padding: 12px; border-bottom: 1px solid #eee7e3; }
        th { background: #faf8f6; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        @media (max-width: 650px) { body { padding: 14px; } .topbar { align-items: flex-start; flex-direction: column; } .panel { padding: 18px; } .grid { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
<main>
    <header class="topbar">
        <div><h1>Manage Education</h1><a href="dashboard.php">Back to Dashboard</a></div>
    </header>
    <section class="panel" aria-labelledby="form-title">
        <h2 id="form-title"><?= $education ? 'Edit Education' : 'Add Education' ?></h2>
        <?php if ($message !== ''): ?><p class="notice" role="status"><?= $escape($message) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($education['id'] ?? 0) ?>">
            <div class="grid">
                <div><label for="school">School</label><input id="school" name="school" maxlength="200" required value="<?= $escape($formValues['school']) ?>"></div>
                <div><label for="course">Course or degree</label><input id="course" name="course" maxlength="200" value="<?= $escape($formValues['course']) ?>"></div>
            </div>
            <div class="grid">
                <div><label for="start_year">Start year</label><input type="number" id="start_year" name="start_year" min="1900" max="<?= (int) date('Y') + 10 ?>" step="1" value="<?= $escape($formValues['start_year']) ?>"></div>
                <div><label for="end_year">End year (leave blank if current)</label><input type="number" id="end_year" name="end_year" min="1900" max="<?= (int) date('Y') + 10 ?>" step="1" value="<?= $escape($formValues['end_year']) ?>"></div>
            </div>
            <label for="description">Description</label><textarea id="description" name="description"><?= $escape($formValues['description']) ?></textarea>
            <button type="submit">Save Education</button>
            <?php if ($education): ?> <a class="button secondary" href="education.php">Cancel</a><?php endif; ?>
        </form>
    </section>
    <section class="panel" aria-labelledby="saved-title">
        <h2 id="saved-title">Saved Education</h2>
        <?php if (!$records): ?><p>No education records have been added yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>School</th><th>Course or degree</th><th>Years</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody><?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= $escape($record['school']) ?></td>
                        <td><?= $escape($record['course']) ?></td>
                        <td><?= $escape($record['start_year']) ?><?= $record['end_year'] ? ' – ' . $escape($record['end_year']) : ($record['start_year'] ? ' – Present' : '') ?></td>
                        <td><?= nl2br($escape($record['description'])) ?></td>
                        <td><div class="actions"><a class="button secondary" href="education.php?edit=<?= (int) $record['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this education record?')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $record['id'] ?>"><button type="submit" class="danger">Delete</button></form></div></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
