<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';
$error = '';
$experience = null;
$formValues = ['position' => '', 'company' => '', 'start_date' => '', 'end_date' => '', 'description' => ''];
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

function validExperienceDate(string $value): bool
{
    if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)) {
        return false;
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            if ($action === 'delete' && $id) {
                $stmt = $pdo->prepare('DELETE FROM experience WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Experience record not found.');
                }
                $message = 'Experience record deleted successfully.';
            } elseif ($action === 'save') {
                $formValues = [
                    'position' => trim($_POST['position'] ?? ''),
                    'company' => trim($_POST['company'] ?? ''),
                    'start_date' => trim($_POST['start_date'] ?? ''),
                    'end_date' => trim($_POST['end_date'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                ];
                if ($formValues['position'] === '' || mb_strlen($formValues['position']) > 150) {
                    throw new RuntimeException('Position is required and must be no more than 150 characters.');
                }
                if (mb_strlen($formValues['company']) > 150) {
                    throw new RuntimeException('Organization must be no more than 150 characters.');
                }
                foreach (['start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label) {
                    if ($formValues[$field] !== '' && !validExperienceDate($formValues[$field])) {
                        throw new RuntimeException($label . ' must be a valid date.');
                    }
                }
                if ($formValues['start_date'] !== '' && $formValues['end_date'] !== '' && $formValues['end_date'] < $formValues['start_date']) {
                    throw new RuntimeException('End date cannot be earlier than start date.');
                }

                $startDate = $formValues['start_date'] !== '' ? $formValues['start_date'] : null;
                $endDate = $formValues['end_date'] !== '' ? $formValues['end_date'] : null;
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE experience SET position = ?, company = ?, start_date = ?, end_date = ?, description = ? WHERE id = ?');
                    $stmt->execute([$formValues['position'], $formValues['company'] ?: null, $startDate, $endDate, $formValues['description'] ?: null, $id]);
                    if ($stmt->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT id FROM experience WHERE id = ?');
                        $check->execute([$id]);
                        if (!$check->fetchColumn()) {
                            throw new RuntimeException('Experience record not found.');
                        }
                    }
                    $message = 'Experience record updated successfully.';
                } elseif (!empty($_POST['id'])) {
                    throw new RuntimeException('Invalid experience record.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO experience (position, company, start_date, end_date, description) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$formValues['position'], $formValues['company'] ?: null, $startDate, $endDate, $formValues['description'] ?: null]);
                    $message = 'Experience record added successfully.';
                }
                $formValues = ['position' => '', 'company' => '', 'start_date' => '', 'end_date' => '', 'description' => ''];
            } else {
                throw new RuntimeException('Invalid experience action.');
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Experience management error: ' . $exception->getMessage());
            $error = 'The experience record could not be saved. Please try again.';
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM experience WHERE id = ?');
        $stmt->execute([$editId]);
        $experience = $stmt->fetch() ?: null;
        if ($experience) {
            $formValues = [
                'position' => $experience['position'],
                'company' => $experience['company'] ?? '',
                'start_date' => $experience['start_date'] ?? '',
                'end_date' => $experience['end_date'] ?? '',
                'description' => $experience['description'] ?? '',
            ];
        }
    }
}

$stmt = $pdo->query('SELECT id, position, company, start_date, end_date, description FROM experience ORDER BY start_date DESC, id DESC');
$records = $stmt->fetchAll();
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Experience | Admin</title>
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
    <header class="topbar"><div><h1>Manage Experience</h1><a href="dashboard.php">Back to Dashboard</a></div></header>
    <section class="panel" aria-labelledby="form-title">
        <h2 id="form-title"><?= $experience ? 'Edit Experience' : 'Add Experience' ?></h2>
        <?php if ($message !== ''): ?><p class="notice" role="status"><?= $escape($message) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($experience['id'] ?? 0) ?>">
            <div class="grid">
                <div><label for="position">Position or role</label><input id="position" name="position" maxlength="150" required value="<?= $escape($formValues['position']) ?>"></div>
                <div><label for="company">Organization</label><input id="company" name="company" maxlength="150" value="<?= $escape($formValues['company']) ?>"></div>
            </div>
            <div class="grid">
                <div><label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" value="<?= $escape($formValues['start_date']) ?>"></div>
                <div><label for="end_date">End date (leave blank if current)</label><input type="date" id="end_date" name="end_date" value="<?= $escape($formValues['end_date']) ?>"></div>
            </div>
            <label for="description">Description</label><textarea id="description" name="description"><?= $escape($formValues['description']) ?></textarea>
            <button type="submit">Save Experience</button>
            <?php if ($experience): ?> <a class="button secondary" href="experience.php">Cancel</a><?php endif; ?>
        </form>
    </section>
    <section class="panel" aria-labelledby="saved-title">
        <h2 id="saved-title">Saved Experience</h2>
        <?php if (!$records): ?><p>No experience records have been added yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Position</th><th>Organization</th><th>Dates</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody><?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= $escape($record['position']) ?></td>
                        <td><?= $escape($record['company']) ?></td>
                        <td><?= $record['start_date'] ? $escape(date('M Y', strtotime($record['start_date']))) : '' ?><?= $record['end_date'] ? ' – ' . $escape(date('M Y', strtotime($record['end_date']))) : ($record['start_date'] ? ' – Present' : '') ?></td>
                        <td><?= nl2br($escape($record['description'])) ?></td>
                        <td><div class="actions"><a class="button secondary" href="experience.php?edit=<?= (int) $record['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this experience record?')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $record['id'] ?>"><button type="submit" class="danger">Delete</button></form></div></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
