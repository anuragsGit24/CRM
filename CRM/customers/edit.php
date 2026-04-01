<?php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../auth/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('/auth/login.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$customerId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$customerId) {
    echo 'Unauthorized access';
    exit;
}

$errors = [];
$successMessage = '';

$projects = [];
$projectStmt = $conn->prepare("SELECT id, name FROM projects WHERE status = 'active' ORDER BY name ASC");
if ($projectStmt) {
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    while ($row = $projectResult->fetch_assoc()) {
        $projects[] = $row;
    }
    $projectStmt->close();
}

$selectSql = "SELECT id, name, contact, alternate_contact, flat_type, location, visited_date, project_id
              FROM customers
              WHERE id = ? AND user_id = ?
              LIMIT 1";
$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
    echo 'Unable to load customer details.';
    exit;
}

$selectStmt->bind_param('ii', $customerId, $userId);
$selectStmt->execute();
$customer = $selectStmt->get_result()->fetch_assoc();
$selectStmt->close();

if (!$customer) {
    echo 'Unauthorized access';
    exit;
}

$name = $customer['name'];
$contact = $customer['contact'];
$alternateContact = $customer['alternate_contact'];
$flatType = $customer['flat_type'];
$location = $customer['location'];
$visitedDate = $customer['visited_date'];
$projectId = (int) $customer['project_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $alternateContact = trim($_POST['alternate_contact'] ?? '');
    $flatType = trim($_POST['flat_type'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $visitedDate = trim($_POST['visited_date'] ?? '');
    $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

    if ($name === '' || $contact === '' || $flatType === '' || $location === '' || $visitedDate === '' || !$projectId) {
        $errors[] = 'Please fill all required fields.';
    }

    if (!$errors) {
        $projectCheck = $conn->prepare("SELECT id FROM projects WHERE id = ? AND status = 'active' LIMIT 1");
        if (!$projectCheck) {
            $errors[] = 'Unable to validate project.';
        } else {
            $projectCheck->bind_param('i', $projectId);
            $projectCheck->execute();
            $validProject = $projectCheck->get_result()->fetch_assoc();
            $projectCheck->close();

            if (!$validProject) {
                $errors[] = 'Please select a valid active project.';
            }
        }
    }

    if (!$errors) {
        $updateSql = "UPDATE customers
                      SET name = ?, contact = ?, alternate_contact = ?, flat_type = ?, location = ?, visited_date = ?, project_id = ?
                      WHERE id = ? AND user_id = ?";
        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {
            $errors[] = 'Unable to update customer right now.';
        } else {
            $updateStmt->bind_param(
                'ssssssiii',
                $name,
                $contact,
                $alternateContact,
                $flatType,
                $location,
                $visitedDate,
                $projectId,
                $customerId,
                $userId
            );

            if ($updateStmt->execute()) {
                if ($updateStmt->affected_rows >= 0) {
                    $successMessage = 'Customer updated successfully.';
                }
            } else {
                $errors[] = 'Failed to update customer. Please try again.';
            }

            $updateStmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f7faf7;--card:#fff;--ink:#1f2933;--muted:#52606d;--line:#d9e2ec;--brand:#2f9e44;--brand-2:#0b7285;--danger:#d64545;--shadow:0 18px 45px rgba(16,42,67,.12)}*{box-sizing:border-box}
        body{margin:0;font-family:'Manrope',sans-serif;color:var(--ink);background:radial-gradient(circle at right top,rgba(11,114,133,.13),transparent 45%),var(--bg);min-height:100vh;padding:24px}
        .wrap{width:min(860px,100%);margin:0 auto;background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:26px}
        h1{margin:0 0 6px;font-size:28px}.sub{margin:0 0 18px;color:var(--muted)}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
        label{display:block;font-size:13px;font-weight:700;margin:0 0 6px;color:#334e68}
        input,select{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:10px;font:inherit}
        .full{grid-column:1/-1}.actions{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}
        .btn{border:none;border-radius:10px;padding:11px 16px;font:700 14px/1 'Manrope',sans-serif;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
        .btn-primary{color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2))}
        .btn-muted{color:#334e68;background:#edf2f7}
        .msg{margin:0 0 14px;padding:11px 12px;border-radius:10px;font-size:14px}
        .msg-ok{background:#e6ffed;color:#1f7a36;border:1px solid #b7ebc6}.msg-err{background:#fff5f5;color:#9b2c2c;border:1px solid #fed7d7}
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/crm-theme.css')); ?>">
</head>
<body>
    <main class="wrap">
        <h1>Edit Customer</h1>
        <p class="sub">Update your customer information securely.</p>

        <?php if ($successMessage): ?>
            <p class="msg msg-ok"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="msg msg-err">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="grid">
                <div>
                    <label for="name">Customer Name</label>
                    <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                <div>
                    <label for="contact">Contact Number</label>
                    <input id="contact" name="contact" type="text" value="<?php echo htmlspecialchars($contact); ?>" required>
                </div>
                <div>
                    <label for="alternate_contact">Alternate Contact</label>
                    <input id="alternate_contact" name="alternate_contact" type="text" value="<?php echo htmlspecialchars($alternateContact); ?>">
                </div>
                <div>
                    <label for="flat_type">Flat Type</label>
                    <input id="flat_type" name="flat_type" type="text" value="<?php echo htmlspecialchars($flatType); ?>" required>
                </div>
                <div>
                    <label for="location">Location</label>
                    <input id="location" name="location" type="text" value="<?php echo htmlspecialchars($location); ?>" required>
                </div>
                <div>
                    <label for="visited_date">Visited Date</label>
                    <input id="visited_date" name="visited_date" type="date" value="<?php echo htmlspecialchars($visitedDate); ?>" required>
                </div>
                <div class="full">
                    <label for="project_id">Project</label>
                    <select id="project_id" name="project_id" required>
                        <option value="">Select active project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo (int) $project['id']; ?>" <?php echo ((int)$project['id'] === (int)$projectId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Update Customer</button>
                <a class="btn btn-muted" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">Back to List</a>
            </div>
        </form>
    </main>
</body>
</html>
