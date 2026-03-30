<?php
// Load reusable authentication middleware and URL helper.
require_once __DIR__ . '/../auth/middleware.php';

// Middleware call: allow admin users only.
require_admin();

$displayName = $_SESSION['username'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --ink: #102a43;
            --muted: #486581;
            --line: #d9e2ec;
            --brand: #0b7285;
            --brand-2: #2f9e44;
            --shadow: 0 18px 45px rgba(16, 42, 67, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(circle at right top, rgba(11, 114, 133, 0.12), transparent 45%),
                radial-gradient(circle at left bottom, rgba(47, 158, 68, 0.15), transparent 40%),
                var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        .layout {
            width: min(940px, 100%);
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
            box-shadow: var(--shadow);
        }

        .title {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
        }

        .subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .btn {
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            transition: transform 120ms ease, box-shadow 120ms ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-ghost {
            color: var(--ink);
            background: #fff;
            border: 1px solid var(--line);
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 22px;
            box-shadow: var(--shadow);
            display: grid;
            gap: 16px;
        }

        .panel-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
        }

        .panel-subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .action-card {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 14px;
            padding: 16px;
            display: grid;
            gap: 10px;
        }

        .action-card h3 {
            margin: 0;
            font-size: 17px;
        }

        .action-card p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .action-btn {
            width: fit-content;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(120deg, var(--brand), var(--brand-2));
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 760px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <header class="topbar">
            <div>
                <h1 class="title">Admin Dashboard</h1>
                <p class="subtitle">Welcome, <?php echo htmlspecialchars($displayName); ?>. You are logged in as admin.</p>
            </div>
            <a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/auth/logout.php')); ?>">Logout</a>
        </header>

        <section class="panel">
            <h2 class="panel-title">User Management</h2>
            <p class="panel-subtitle">Choose one action to continue. This dashboard stays simple so admin tasks are quick and clear.</p>

            <div class="action-grid">
                <article class="action-card">
                    <h3>Create User</h3>
                    <p>Add a new admin or user account with secure password hashing and status control.</p>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/create_user.php')); ?>">Open Create User</a>
                </article>

                <article class="action-card">
                    <h3>View Users</h3>
                    <p>See all users in a table, then edit role, status, and profile details when needed.</p>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/users.php')); ?>">Open Users List</a>
                </article>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Builder Management</h2>
            <p class="panel-subtitle">Add builder records quickly from here without manually changing URLs.</p>

            <div class="action-grid">
                <article class="action-card">
                    <h3>Create Builder</h3>
                    <p>Add a new builder with contact details, address, email, and status.</p>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/create_builder.php')); ?>">Open Create Builder</a>
                </article>

                <article class="action-card">
                    <h3>View Builders</h3>
                    <p>See all builders in a table and open edit action for each record.</p>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/list_builder.php')); ?>">Open Builders List</a>
                </article>
            </div>
        </section>
    </div>
</body>
</html>
