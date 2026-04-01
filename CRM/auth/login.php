<?php
// Start session so we can store and read logged-in user information.
session_start();

// Include URL helper for reliable redirects and links.
require_once __DIR__ . '/../config/app.php';

// Include reusable CSRF helper.
require_once __DIR__ . '/csrf.php';

// Include database connection.
require_once __DIR__ . '/../config/database.php';

// If user is already logged in, send to role dashboard.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . app_url('/admin/dashboard.php'));
        exit;
    }

    header('Location: ' . app_url('/user/dashboard.php'));
    exit;
}

// Variables for showing friendly errors and keeping old form input.
$error = '';
$loginInput = '';

// Run this block only when form is submitted with POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token before processing credentials.
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    }

    // Get input and trim spaces for cleaner validation.
    $loginInput = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic empty-field checks for user experience.
    if ($error === '' && ($loginInput === '' || $password === '')) {
        $error = 'Please enter username/email and password.';
    } elseif ($error === '') {
        /*
         |--------------------------------------------------------------------
         | Secure query with prepared statement
         |--------------------------------------------------------------------
         | We allow login by username OR email.
         | Prepared statements protect against SQL injection attacks.
         */
        $sql = 'SELECT id, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = 'Something went wrong. Please try again.';
        } else {
            // Bind both placeholders with same login input.
            $stmt->bind_param('ss', $loginInput, $loginInput);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // Validate user existence first.
            if (!$user) {
                $error = 'Invalid credentials.';
            } elseif ($user['status'] !== 'active') {
                // Prevent login for inactive users.
                $error = 'Your account is inactive. Please contact admin.';
            } elseif (!password_verify($password, $user['password'])) {
                // Verify entered password against hashed password in DB.
                $error = 'Invalid credentials.';
            } else {
                // Security step: regenerate session ID after successful login.
                session_regenerate_id(true);

                // Store only needed user data in session.
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];

                // Role-based redirect.
                if ($user['role'] === 'admin') {
                    header('Location: ' . app_url('/admin/dashboard.php'));
                    exit;
                }

                header('Location: ' . app_url('/user/dashboard.php'));
                exit;
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-1: #f6fbff;
            --bg-2: #eef8f2;
            --ink: #102a43;
            --muted: #486581;
            --card: #ffffff;
            --line: #d9e2ec;
            --brand: #0b7285;
            --brand-2: #2b8a3e;
            --danger-bg: #ffe3e3;
            --danger-text: #c92a2a;
            --radius: 16px;
            --shadow: 0 22px 55px rgba(16, 42, 67, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 20% 20%, rgba(11, 114, 133, 0.14), transparent 40%),
                radial-gradient(circle at 80% 0%, rgba(43, 138, 62, 0.18), transparent 35%),
                linear-gradient(140deg, var(--bg-1), var(--bg-2));
            display: grid;
            place-items: center;
            padding: 20px;
        }

        .shell {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow);
            background: var(--card);
            animation: rise 500ms ease-out;
        }

        .intro {
            padding: 38px;
            background: linear-gradient(170deg, #0b7285, #2b8a3e);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 20px;
        }

        .badge {
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            font-weight: 700;
            letter-spacing: 0.03em;
            font-size: 12px;
            text-transform: uppercase;
        }

        .intro h1 {
            margin: 0;
            margin-top: 10px;
            font-size: clamp(28px, 3.4vw, 40px);
            line-height: 1.1;
        }

        .intro p {
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.65;
        }

        .steps {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }

        .steps li {
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(2px);
            font-size: 14px;
        }

        .panel {
            padding: 34px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 20px;
        }

        .panel h2 {
            margin: 0;
            font-size: 28px;
        }

        .sub {
            margin: -8px 0 0;
            margin-top: 10px;
            color: var(--muted);
        }

        form {
            display: grid;
            gap: 14px;
        }

        label {
            font-size: 14px;
            font-weight: 700;
            color: #243b53;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
            transition: border-color 150ms ease, box-shadow 150ms ease;
            outline: none;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(11, 114, 133, 0.14);
        }

        .btn {
            margin-top: 4px;
            border: none;
            border-radius: 12px;
            padding: 13px 16px;
            font-weight: 800;
            font-size: 15px;
            color: #fff;
            background: linear-gradient(120deg, #0b7285, #2b8a3e);
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 120ms ease;
            box-shadow: 0 10px 24px rgba(11, 114, 133, 0.28);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error {
            margin: 0;
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--danger-bg);
            color: var(--danger-text);
            border: 1px solid #ffc9c9;
            font-size: 14px;
            font-weight: 700;
        }

        .hint {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
                max-width: 560px;
            }

            .intro {
                padding: 28px;
            }

            .panel {
                padding: 28px;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="intro">
            <div>
                <span class="badge">CRM Secure Access</span>
                <h1>Welcome Back</h1>
                <p>Sign in to continue managing builders, projects, and customers in one place.</p>
            </div>

            <ul class="steps">
                <li>Login with your username or email</li>
                <li>Access is controlled by your user role</li>
                <li>Inactive accounts are blocked for safety</li>
            </ul>
        </section>

        <section class="panel">
            <div>
                <h2>Sign In</h2>
                <p class="sub">Use your CRM credentials to continue.</p>
            </div>

            <?php if ($error !== ''): ?>
                <!-- Show user-friendly error message from server validation. -->
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="field">
                    <label for="login">Username or Email</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="<?php echo htmlspecialchars($loginInput); ?>"
                        placeholder="Enter username or email"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button class="btn" type="submit">Login to Dashboard</button>
            </form>

            <p class="hint">
                For security, this form uses prepared statements and password hash verification on the server.
            </p>
        </section>
    </main>
</body>
</html>
