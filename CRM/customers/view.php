<?php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

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

$sql = "SELECT c.id, c.name, c.contact, c.alternate_contact, c.flat_type, c.location, c.visited_date, p.name AS project_name
        FROM customers c
        INNER JOIN projects p ON c.project_id = p.id
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo 'Unable to load customer details.';
    exit;
}

$stmt->bind_param('ii', $customerId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (!$customer) {
    echo 'Unauthorized access';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f7faf7; --card:#fff; --ink:#1f2933; --muted:#52606d; --line:#d9e2ec; --brand:#2f9e44; --brand-2:#0b7285; --shadow:0 18px 45px rgba(16,42,67,.12);}*{box-sizing:border-box}
        body{margin:0;font-family:'Manrope',sans-serif;color:var(--ink);background:radial-gradient(circle at left top,rgba(47,158,68,.13),transparent 45%),var(--bg);min-height:100vh;padding:24px}
        .layout{width:min(980px,100%);margin:0 auto;display:grid;gap:20px}.topbar,.card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
        .topbar{padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.title{margin:0;font-size:24px;font-weight:800}.subtitle{margin:4px 0 0;color:var(--muted);font-size:14px}
        .btn-link{text-decoration:none;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));padding:10px 14px;border-radius:10px;font-weight:700}
        .card{padding:20px;display:grid;gap:12px}.row{display:grid;grid-template-columns:180px 1fr;gap:10px;border-bottom:1px solid #edf2f7;padding-bottom:10px}.row:last-child{border-bottom:none}
        .label{font-weight:800;color:#243b53}.value{color:var(--ink)}
        @media (max-width:760px){.row{grid-template-columns:1fr}.topbar{flex-direction:column;align-items:flex-start}}
    </style>
</head>
<body>
<div class="layout">
    <header class="topbar">
        <div>
            <h1 class="title">Customer Details</h1>
            <p class="subtitle">Read-only view for your customer record.</p>
        </div>
        <a class="btn-link" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">Back to List</a>
    </header>

    <section class="card">
        <div class="row"><div class="label">Name</div><div class="value"><?php echo htmlspecialchars($customer['name']); ?></div></div>
        <div class="row"><div class="label">Contact</div><div class="value"><?php echo htmlspecialchars($customer['contact']); ?></div></div>
        <div class="row"><div class="label">Alternate Contact</div><div class="value"><?php echo htmlspecialchars($customer['alternate_contact']); ?></div></div>
        <div class="row"><div class="label">Flat Type</div><div class="value"><?php echo htmlspecialchars($customer['flat_type']); ?></div></div>
        <div class="row"><div class="label">Location</div><div class="value"><?php echo htmlspecialchars($customer['location']); ?></div></div>
        <div class="row"><div class="label">Visited Date</div><div class="value"><?php echo htmlspecialchars($customer['visited_date']); ?></div></div>
        <div class="row"><div class="label">Project Name</div><div class="value"><?php echo htmlspecialchars($customer['project_name']); ?></div></div>
    </section>
</div>
</body>
</html>
