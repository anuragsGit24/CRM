<?php
// Load reusable authentication middleware and URL helper.
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/database.php';

// Middleware call: allow normal users only.
require_user();

$displayName = $_SESSION['username'] ?? 'User';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$recentCustomers = [];

$recentSql = "SELECT c.id, c.name, c.contact, c.visited_date, p.name AS project_name
              FROM customers c
              INNER JOIN projects p ON c.project_id = p.id
              WHERE c.user_id = ?
              ORDER BY c.id DESC
              LIMIT 5";
$recentStmt = $conn->prepare($recentSql);
if ($recentStmt) {
    $recentStmt->bind_param('i', $userId);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    while ($row = $recentResult->fetch_assoc()) {
        $recentCustomers[] = $row;
    }
    $recentStmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f7faf7;
            --card: #ffffff;
            --ink: #1f2933;
            --muted: #52606d;
            --line: #d9e2ec;
            --brand: #2f9e44;
            --brand-2: #0b7285;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at left top, rgba(47, 158, 68, 0.13), transparent 45%), var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        .layout {
            width: min(1320px, 100%);
            margin: 0 auto;
            padding: 24px;
            display: grid;
            gap: 20px;
        }

        .topbar {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }

        .subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .btn {
            text-decoration: none;
            color: #fff;
            background: linear-gradient(120deg, var(--brand), var(--brand-2));
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
        }

        .workspace {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
        }

        .panel h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }

        .panel p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 14px;
        }

        .hero-actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            text-decoration: none;
            border: 1px solid var(--line);
            color: #334e68;
            background: #f8fafc;
            border-radius: 9px;
            padding: 8px 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .action-btn.primary {
            background: #e6ffed;
            border-color: #b7ebc6;
            color: #1f7a36;
        }

        .action-btn.dark {
            background: #102a43;
            border-color: #102a43;
            color: #fff;
        }

        .mini-grid {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .mini-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
            background: #f9fcff;
        }

        .mini-card h3 {
            margin: 0 0 6px;
            font-size: 16px;
        }

        .mini-card p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .list {
            margin: 14px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }

        .list li {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
        }

        .list strong {
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .list span {
            color: var(--muted);
            font-size: 13px;
        }

        .side-actions {
            margin-top: 14px;
            display: grid;
            gap: 8px;
        }

        .recent-panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
        }

        .recent-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .recent-head h2 {
            margin: 0;
            font-size: 22px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
            background: #fff;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            letter-spacing: .2px;
            color: var(--muted);
            padding: 12px 14px;
            background: #f8fbfe;
            border-bottom: 1px solid var(--line);
        }

        tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f9fcff;
        }

        .table-action {
            text-decoration: none;
            border: 1px solid var(--line);
            color: #334e68;
            background: #f8fafc;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }

        .empty {
            margin: 0;
            padding: 12px;
            font-size: 14px;
            color: var(--muted);
            border: 1px dashed var(--line);
            border-radius: 10px;
            background: #f8fbfe;
        }

        @media (max-width: 980px) {
            .workspace { grid-template-columns: 1fr; }
            .mini-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/crm-theme.css')); ?>">
</head>
<body>
    <div class="layout">
        <header class="topbar">
            <div>
                <h1 class="title">User Dashboard</h1>
                <p class="subtitle">Welcome, <?php echo htmlspecialchars($displayName); ?>. You are logged in as user.</p>
            </div>
            <a class="btn" href="<?php echo htmlspecialchars(app_url('/auth/logout.php')); ?>">Logout</a>
        </header>

        <section class="workspace">
            <article class="panel">
                <h2>Customer Operations</h2>
                <p>Run your complete customer workflow from here without opening multiple pages manually.</p>

                <div class="hero-actions">
                    <a class="action-btn primary" href="<?php echo htmlspecialchars(app_url('/customers/create.php')); ?>">Create Customer</a>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">View All Customers</a>
                    <a class="action-btn dark" href="<?php echo htmlspecialchars(app_url('/customers/index.php?q=&project=all')); ?>">Search and Filter</a>
                </div>

                <div class="mini-grid">
                    <article class="mini-card">
                        <h3>New Lead Entry</h3>
                        <p>Add customer details including project, contact, and visit date in one step.</p>
                    </article>
                    <article class="mini-card">
                        <h3>Fast Record Access</h3>
                        <p>Use list actions to open View, Edit, or Delete quickly during follow-ups.</p>
                    </article>
                    <article class="mini-card">
                        <h3>Project-wise Tracking</h3>
                        <p>Use project filter to isolate leads by project and reduce manual searching.</p>
                    </article>
                    <article class="mini-card">
                        <h3>Daily Activity Flow</h3>
                        <p>Keep visit records updated so your next calls and site plans stay organized.</p>
                    </article>
                </div>
            </article>

            <aside class="panel">
                <h2>Quick Guide</h2>
                <p>Simple steps to keep your daily CRM workflow easy and consistent.</p>

                <ul class="list">
                    <li>
                        <strong>Step 1: Create Customer</strong>
                        <span>Add lead details and assign an active project.</span>
                    </li>
                    <li>
                        <strong>Step 2: Manage from List</strong>
                        <span>View full profile, edit fields, or remove invalid entries.</span>
                    </li>
                    <li>
                        <strong>Step 3: Filter Quickly</strong>
                        <span>Use quick search and project filter to find records instantly.</span>
                    </li>
                </ul>

                <div class="side-actions">
                    <a class="action-btn primary" href="<?php echo htmlspecialchars(app_url('/customers/create.php')); ?>">Add New Lead</a>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">Open Customer Registry</a>
                </div>
            </aside>
        </section>

        <section class="recent-panel">
            <div class="recent-head">
                <h2>Recent Customers</h2>
                <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">View Full List</a>
            </div>

            <?php if (count($recentCustomers) === 0): ?>
                <p class="empty">No customers added yet. Start with Add New Lead to see your recent records here.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Project</th>
                                <th>Visited Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCustomers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['contact']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['visited_date']); ?></td>
                                    <td>
                                        <a class="table-action" href="<?php echo htmlspecialchars(app_url('/customers/view.php?id=' . (int) $customer['id'])); ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
