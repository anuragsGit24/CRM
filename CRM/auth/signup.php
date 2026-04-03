<?php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . app_url('/admin/dashboard.php'));
        exit;
    }

    header('Location: ' . app_url('/user/dashboard.php'));
    exit;
}

$errorMessage = '';
$successMessage = '';
$formData = [
    'name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'contact' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Invalid request token. Please refresh and try again.';
    }

    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['last_name'] = trim($_POST['last_name'] ?? '');
    $formData['username'] = trim($_POST['username'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['contact'] = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($errorMessage === '' && (
        $formData['name'] === '' ||
        $formData['last_name'] === '' ||
        $formData['username'] === '' ||
        $formData['email'] === '' ||
        $password === '' ||
        $confirmPassword === ''
    )) {
        $errorMessage = 'Please fill all required fields.';
    } elseif ($errorMessage === '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($errorMessage === '' && strlen($password) < 6) {
        $errorMessage = 'Password must be at least 6 characters.';
    } elseif ($errorMessage === '' && $password !== $confirmPassword) {
        $errorMessage = 'Password and confirm password do not match.';
    } elseif ($errorMessage === '') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';
        $status = 'active';

        $sql = 'INSERT INTO users (name, last_name, username, email, password, contact, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = 'Unable to prepare database query. Please try again.';
        } else {
            $stmt->bind_param(
                'ssssssss',
                $formData['name'],
                $formData['last_name'],
                $formData['username'],
                $formData['email'],
                $hashedPassword,
                $formData['contact'],
                $role,
                $status
            );

            if ($stmt->execute()) {
                header('Location: ' . app_url('/auth/login.php?registered=1'));
                exit;
            }

            if ((int) $stmt->errno === 1062) {
                $errorMessage = 'Username or email already exists. Please choose another.';
            } else {
                $errorMessage = 'Failed to create account. Please try again.';
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
    <title>CRM Sign Up</title>
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
            --success-bg: #d3f9d8;
            --success-text: #2b8a3e;
            --shadow: 0 22px 55px rgba(16, 42, 67, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 20% 20%, rgba(11, 44, 95, 0.12), transparent 40%),
                radial-gradient(circle at 80% 0%, rgba(11, 114, 133, 0.1), transparent 35%),
                linear-gradient(140deg, #f6fbff, #eef8f2);
            padding: 0;
        }

        .shell {
            width: min(100%, 100vw);
            min-height: 100vh;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-radius: 0;
            overflow: hidden;
            box-shadow: none;
            background: var(--card);
            animation: rise 500ms ease-out;
        }

        .intro {
            padding: 48px 52px;
            background: linear-gradient(135deg, #0b2c5f 0%, #0d4b8c 50%, #0b7285 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 32px;
            position: relative;
            overflow: hidden;
        }

        .intro::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 122, 0, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .intro::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .intro-bg {
            width: 100%;
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 16px 0;
            position: relative;
            z-index: 1;
        }

        .intro-bg img {
            width: 140%;
            height: 140%;
            object-fit: contain;
            filter: brightness(0.95) saturate(1.05);
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
            margin-top: 15px;
            font-size: clamp(28px, 3.4vw, 42px);
            line-height: 1.1;
            position: relative;
            z-index: 1;
            letter-spacing: -0.01em;
        }

        .intro p {
            margin: 0;
            color: rgba(255, 255, 255, 0.88);
            line-height: 1.7;
            position: relative;
            z-index: 1;
            font-size: 15px;
        }

        .intro-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            position: relative;
            z-index: 2;
        }

        .crm-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 122, 0, 0.2);
            border: 2px solid rgba(255, 122, 0, 0.4);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
            z-index: 3;
        }

        .crm-icon svg {
            width: 36px;
            height: 36px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.15));
        }

        .intro-text {
            flex: 1;
            position: relative;
            z-index: 3;
        }

        .intro > div:first-of-type {
            position: relative;
            z-index: 2;
        }

        .intro > ul {
            position: relative;
            z-index: 2;
        }

        .intro > div:first-of-type {
            position: relative;
            z-index: 2;
        }

        .intro > ul {
            position: relative;
            z-index: 2;
        }

        .steps {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .steps li {
            padding: 14px 16px;
            border: 1.5px solid rgba(255, 122, 0, 0.35);
            border-radius: 12px;
            background: rgba(255, 122, 0, 0.08);
            backdrop-filter: blur(4px);
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 200ms ease;
        }

        .steps li:before {
            content: '';
            width: 6px;
            height: 6px;
            background: #ff7a00;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .steps li:hover {
            background: rgba(255, 122, 0, 0.12);
            border-color: rgba(255, 122, 0, 0.5);
            transform: translateX(4px);
        }

        .panel {
            padding: 52px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 24px;
            background: linear-gradient(135deg, #fafbfc 0%, #f5f8fb 100%);
        }

        .panel h2 {
            margin: 0;
            font-size: 32px;
            letter-spacing: -0.015em;
            background: linear-gradient(120deg, #0b2c5f, #0b7285);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sub {
            margin: -10px 0 0;
            margin-top: 5px;
            color: var(--muted);
            font-size: 15px;
        }

        form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 8px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 600;
            color: #243b53;
            text-transform: capitalize;
        }

        input {
            width: 100%;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 15px;
            outline: none;
            background: #ffffff;
            font-family: 'Manrope', sans-serif;
            transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
        }

        input::placeholder {
            color: #9ca3af;
        }

        input:hover {
            border-color: #c3cfe5;
        }

        input:focus {
            border-color: #0b2c5f;
            box-shadow: 0 0 0 3px rgba(11, 44, 95, 0.1);
            background: #fafbfc;
        }

        .btn {
            grid-column: 1 / -1;
            margin-top: 8px;
            border: none;
            border-radius: 10px;
            padding: 14px 16px;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.015em;
            color: #fff;
            background: linear-gradient(120deg, #0b2c5f 0%, #0b7285 100%);
            cursor: pointer;
            transition: all 200ms ease;
            box-shadow: 0 8px 20px rgba(11, 44, 95, 0.25);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(11, 44, 95, 0.35);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(11, 44, 95, 0.25);
        }

        .error,
        .success {
            grid-column: 1 / -1;
            margin: 0;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .error {
            background: #ffe3e3;
            color: #c92a2a;
            border: 1px solid #ffc9c9;
        }

        .success {
            background: #d3f9d8;
            color: #2b8a3e;
            border: 1px solid #b2f2bb;
        }

        .auth-link {
            margin: 0;
            grid-column: 1 / -1;
            color: var(--muted);
            font-size: 14px;
        }

        .auth-link a {
            color: #0b2c5f;
            font-weight: 700;
            text-decoration: none;
        }

        @media (max-width: 960px) {
            .shell {
                grid-template-columns: 1fr;
                max-width: 100%;
                min-height: 100vh;
            }

            .intro {
                padding: 40px 42px;
            }

            .panel {
                padding: 42px;
            }

            .intro-header {
                gap: 12px;
            }

            .crm-icon {
                width: 56px;
                height: 56px;
            }

            .crm-icon svg {
                width: 32px;
                height: 32px;
            }

            form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .intro {
                padding: 32px 24px;
                gap: 24px;
            }

            .panel {
                padding: 28px 24px;
                gap: 18px;
            }

            .panel h2 {
                font-size: 26px;
            }

            .badge {
                font-size: 11px;
                padding: 6px 12px;
            }

            .intro h1 {
                font-size: 28px;
            }

            .steps li {
                font-size: 13px;
                padding: 12px 14px;
            }

            .crm-icon {
                width: 48px;
                height: 48px;
            }

            .crm-icon svg {
                width: 28px;
                height: 28px;
            }

            form {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/crm-theme.css')); ?>">
</head>
<body>
    <main class="shell">
        <section class="intro">
            <div>
                <div class="intro-header">
                    <div class="crm-icon">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- CRM/Building icon -->
                            <path d="M3 3h2v18H3V3z" fill="#ff7a00"/>
                            <path d="M7 5h2v16H7V5z" fill="#ff7a00"/>
                            <path d="M11 7h2v14h-2V7z" fill="#ff7a00"/>
                            <path d="M15 6h2v15h-2V6z" fill="#ff7a00"/>
                            <path d="M19 4h2v17h-2V4z" fill="#ff7a00"/>
                            <circle cx="5" cy="8" r="1.2" fill="#fff" opacity="0.8"/>
                            <circle cx="5" cy="12" r="1.2" fill="#fff" opacity="0.8"/>
                            <circle cx="5" cy="16" r="1.2" fill="#fff" opacity="0.8"/>
                            <circle cx="9" cy="9" r="1.2" fill="#fff" opacity="0.8"/>
                            <circle cx="9" cy="13" r="1.2" fill="#fff" opacity="0.8"/>
                            <circle cx="9" cy="17" r="1.2" fill="#fff" opacity="0.8"/>
                        </svg>
                    </div>
                    <div class="intro-text">
                        <span class="badge">CRM New Account</span>
                        <h1>Create Your Account</h1>
                    </div>
                </div>
                <p>Register once and start managing customer leads, projects, and follow-ups from one place.</p>
            </div>

            <div class="intro-bg">
                <img src="<?php echo htmlspecialchars(app_url('/assets/Gemini_Generated_Image_jxsdrtjxsdrtjxsd.png')); ?>" alt="CRM Analytics Dashboard">
            </div>

            <ul class="steps">
                <li>Sign up with your basic profile details</li>
                <li>Your account is created as a standard user</li>
                <li>Use your new credentials to sign in</li>
            </ul>
        </section>

        <section class="panel">
            <div>
                <h2>Sign Up</h2>
                <p class="sub">Create a new account to access your CRM workspace.</p>
            </div>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                <?php if ($errorMessage !== ''): ?>
                    <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <p class="success"><?php echo htmlspecialchars($successMessage); ?></p>
                <?php endif; ?>

                <div class="field">
                    <label for="name">First Name</label>
                    <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                </div>

                <div class="field">
                    <label for="last_name">Last Name</label>
                    <input id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars($formData['last_name']); ?>" required>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                </div>

                <div class="field full">
                    <label for="contact">Contact Number</label>
                    <input id="contact" name="contact" type="text" value="<?php echo htmlspecialchars($formData['contact']); ?>">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" minlength="6" required>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="6" required>
                </div>

                <button class="btn" type="submit">Create Account</button>

                <p class="auth-link">Already have an account? <a href="<?php echo htmlspecialchars(app_url('/auth/login.php')); ?>">Sign In</a></p>
            </form>
        </section>
    </main>
</body>
</html>
