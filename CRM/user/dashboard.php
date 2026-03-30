<?php
// Load reusable authentication middleware and URL helper.
require_once __DIR__ . '/../auth/middleware.php';

// Middleware call: allow normal users only.
require_user();

$displayName = $_SESSION['username'] ?? 'User';
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
            width: min(1000px, 100%);
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

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
        }

        .card h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 14px;
        }
    </style>
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

        <section class="cards">
            <article class="card">
                <h3>My Customers</h3>
                <p>Track your customer leads and their visit dates in a simple workflow.</p>
            </article>
            <article class="card">
                <h3>Project Access</h3>
                <p>View project details linked with your daily customer follow-ups.</p>
            </article>
            <article class="card">
                <h3>Profile Details</h3>
                <p>Keep your user information updated for smooth CRM operations.</p>
            </article>
        </section>
    </div>
</body>
</html>
