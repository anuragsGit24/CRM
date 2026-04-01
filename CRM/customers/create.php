<?php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('/auth/login.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$errorMessage = '';
$successMessage = '';
$projects = [];

$formData = [
    'name' => '',
    'contact' => '',
    'alternate_contact' => '',
    'flat_type' => '',
    'location' => '',
    'visited_date' => '',
    'project_id' => '',
];

// Load active projects for dropdown.
$projectSql = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name ASC";
$projectResult = $conn->query($projectSql);
if ($projectResult) {
    while ($row = $projectResult->fetch_assoc()) {
        $projects[] = $row;
    }
} else {
    $errorMessage = 'Unable to load active projects.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Invalid request token. Please refresh and try again.';
    }

    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['contact'] = trim($_POST['contact'] ?? '');
    $formData['alternate_contact'] = trim($_POST['alternate_contact'] ?? '');
    $formData['flat_type'] = trim($_POST['flat_type'] ?? '');
    $formData['location'] = trim($_POST['location'] ?? '');
    $formData['visited_date'] = trim($_POST['visited_date'] ?? '');
    $formData['project_id'] = trim($_POST['project_id'] ?? '');

    $projectId = filter_var($formData['project_id'], FILTER_VALIDATE_INT);

    if ($errorMessage === '' && (
        $formData['name'] === '' ||
        $formData['contact'] === '' ||
        $formData['flat_type'] === '' ||
        $formData['location'] === '' ||
        $formData['visited_date'] === '' ||
        !$projectId
    )) {
        $errorMessage = 'Please fill all required fields.';
    } elseif ($errorMessage === '') {
        $insertSql = 'INSERT INTO customers (name, contact, alternate_contact, flat_type, location, visited_date, project_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($insertSql);

        if (!$stmt) {
            $errorMessage = 'Unable to prepare database query.';
        } else {
            $stmt->bind_param(
                'ssssssii',
                $formData['name'],
                $formData['contact'],
                $formData['alternate_contact'],
                $formData['flat_type'],
                $formData['location'],
                $formData['visited_date'],
                $projectId,
                $userId
            );

            if ($stmt->execute()) {
                $successMessage = 'Customer created successfully.';
                $formData = [
                    'name' => '',
                    'contact' => '',
                    'alternate_contact' => '',
                    'flat_type' => '',
                    'location' => '',
                    'visited_date' => '',
                    'project_id' => '',
                ];
            } else {
                $errorMessage = 'Failed to create customer.';
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Customer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f7faf7; --card:#fff; --ink:#1f2933; --muted:#52606d; --line:#d9e2ec; --brand:#2f9e44; --brand-2:#0b7285; --danger-bg:#ffe3e3; --danger-text:#c92a2a; --success-bg:#d3f9d8; --success-text:#2b8a3e; --shadow:0 18px 45px rgba(16,42,67,.12);}*{box-sizing:border-box}
        body{margin:0;font-family:'Manrope',sans-serif;color:var(--ink);background:radial-gradient(circle at left top,rgba(47,158,68,.13),transparent 45%),var(--bg);min-height:100vh;padding:24px}
        .layout{width:min(980px,100%);margin:0 auto;display:grid;gap:20px}.topbar{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;box-shadow:var(--shadow)}
        .title{margin:0;font-size:24px;font-weight:800}.subtitle{margin:4px 0 0;color:var(--muted);font-size:14px}.btn-link{text-decoration:none;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));padding:10px 14px;border-radius:10px;font-weight:700}
        .card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:20px}.messages{display:grid;gap:10px;margin-bottom:14px}.msg{margin:0;padding:10px 12px;border-radius:10px;font-size:14px;font-weight:700}.msg.error{background:var(--danger-bg);color:var(--danger-text);border:1px solid #ffc9c9}.msg.success{background:var(--success-bg);color:var(--success-text);border:1px solid #b2f2bb}
        form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1 / -1}label{font-size:14px;font-weight:700;color:#243b53}
        input,select{width:100%;border:1px solid var(--line);border-radius:12px;padding:12px 14px;font-size:15px;outline:none;background:#fff;font-family:inherit}input:focus,select:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(11,114,133,.14)}
        .submit{grid-column:1 / -1;border:none;border-radius:12px;padding:13px 16px;font-size:15px;font-weight:800;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));cursor:pointer;box-shadow:0 10px 24px rgba(11,114,133,.28)}
        @media (max-width:760px){form{grid-template-columns:1fr}.topbar{flex-direction:column;align-items:flex-start}}
    </style>
</head>
<body>
<div class="layout">
    <header class="topbar">
        <div>
            <h1 class="title">Create Customer</h1>
            <p class="subtitle">Add a customer linked to one active project.</p>
        </div>
        <a class="btn-link" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">Back to List</a>
    </header>

    <section class="card">
        <?php if ($errorMessage !== '' || $successMessage !== ''): ?>
            <div class="messages">
                <?php if ($errorMessage !== ''): ?><p class="msg error"><?php echo htmlspecialchars($errorMessage); ?></p><?php endif; ?>
                <?php if ($successMessage !== ''): ?><p class="msg success"><?php echo htmlspecialchars($successMessage); ?></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div class="field"><label for="name">Name *</label><input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($formData['name']); ?>"></div>
            <div class="field"><label for="contact">Contact *</label><input id="contact" name="contact" type="text" required value="<?php echo htmlspecialchars($formData['contact']); ?>"></div>
            <div class="field"><label for="alternate_contact">Alternate Contact</label><input id="alternate_contact" name="alternate_contact" type="text" value="<?php echo htmlspecialchars($formData['alternate_contact']); ?>"></div>
            <div class="field"><label for="flat_type">Flat Type *</label><input id="flat_type" name="flat_type" type="text" required value="<?php echo htmlspecialchars($formData['flat_type']); ?>"></div>
            <div class="field"><label for="location">Location *</label><input id="location" name="location" type="text" required value="<?php echo htmlspecialchars($formData['location']); ?>"></div>
            <div class="field"><label for="visited_date">Visited Date *</label><input id="visited_date" name="visited_date" type="date" required value="<?php echo htmlspecialchars($formData['visited_date']); ?>"></div>
            <div class="field full">
                <label for="project_id">Project (Active) *</label>
                <select id="project_id" name="project_id" required>
                    <option value="">Select active project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo (int) $project['id']; ?>" <?php echo ((string)$project['id'] === (string)$formData['project_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="submit" type="submit">Create Customer</button>
        </form>
    </section>
</div>
</body>
</html>
