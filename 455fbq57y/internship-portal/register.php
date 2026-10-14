
<?php

session_start();

require_once __DIR__ . "/config/database.php";

$error = "";

if (isset($_SESSION["user_id"])) {

    $role = $_SESSION["role"] ?? "";

    if ($role === "Student") {
        header("Location: student/dashboard.php");
        exit();
    }

    if ($role === "Company") {
        header("Location: company/dashboard.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "Student";
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        $name === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $error = "Please fill in all required fields.";

    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = "Name must contain between 2 and 100 characters.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (!in_array($role, ["Student", "Company"], true)) {
        $error = "Please select a valid account type.";

    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";

    } else {

        try {

            if (!isset($pdo) || !($pdo instanceof PDO)) {
                throw new RuntimeException(
                    "PDO database connection is unavailable."
                );
            }

            $pdo->beginTransaction();

            $check = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $check->execute([$email]);

            if ($check->fetch()) {

                $pdo->rollBack();

                $error = "This email is already registered.";

            } else {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insertUser = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role)
                     VALUES (?, ?, ?, ?)"
                );

                $insertUser->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $role
                ]);

                $userId = (int) $pdo->lastInsertId();

                if ($role === "Student") {

                    $insertProfile = $pdo->prepare(
                        "INSERT INTO students (user_id)
                         VALUES (?)"
                    );

                    $insertProfile->execute([$userId]);

                } else {

                    $insertProfile = $pdo->prepare(
                        "INSERT INTO companies (user_id, company_name)
                         VALUES (?, ?)"
                    );

                    $insertProfile->execute([
                        $userId,
                        $name
                    ]);
                }

                $pdo->commit();

                header("Location: login.php?registered=1");
                exit();
            }

        } catch (Throwable $exception) {

            if (
                isset($pdo) &&
                $pdo instanceof PDO &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            error_log(
                "InternOrbit registration error: " .
                $exception->getMessage()
            );

            $error = "Something went wrong. Please try again.";
        }
    }
}

function escapeOutput($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="theme-color" content="#10091f">

    <title>Create Account | InternOrbit</title>

    <style>

        :root {
            --purple: #8b5cf6;
            --purple-dark: #6d28d9;
            --white: #ffffff;
            --muted: #c4bdd5;
            --glass: rgba(255, 255, 255, 0.075);
            --glass-border: rgba(255, 255, 255, 0.16);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 24px;
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--white);

            background:
                radial-gradient(
                    ellipse at 10% 10%,
                    rgba(124, 58, 237, 0.35),
                    transparent 42%
                ),
                radial-gradient(
                    ellipse at 90% 90%,
                    rgba(91, 33, 182, 0.30),
                    transparent 40%
                ),
                #10091f;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        button,
        a,
        input,
        select {
            -webkit-tap-highlight-color: transparent;
        }

        /* BACKGROUND DECORATIONS */

        .background-orb {
            position: fixed;
            z-index: -1;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.20);
            filter: blur(75px);
            pointer-events: none;
        }

        .orb-one {
            top: -120px;
            left: -100px;
        }

        .orb-two {
            right: -120px;
            bottom: -100px;
        }

        .page-shell {
            width: 100%;
            max-width: 1060px;
            margin: auto;
        }

        /* HORIZONTAL GLASS CARD */

        .auth-card {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            align-items: center;
            gap: 46px;

            width: 100%;
            padding: 43px;

            border: 1px solid var(--glass-border);
            border-radius: 22px;

            background: var(--glass);

            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);

            box-shadow:
                0 28px 90px rgba(0, 0, 0, 0.30),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);

            animation: cardAppear 0.65s ease both;
        }

        /* BRAND INSIDE THE CARD */

        .brand {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 11px;
            margin-bottom: 35px;

            font-size: 25px;
            font-weight: 900;
            letter-spacing: -1.2px;
        }

        .brand-icon {
            display: grid;
            place-items: center;

            width: 46px;
            height: 46px;
            flex-shrink: 0;

            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 13px;

            background: linear-gradient(
                135deg,
                #a78bfa,
                #6d28d9
            );

            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.3);

            transition: transform 0.3s ease;
        }

        .brand:hover .brand-icon {
            transform: rotate(-8deg) scale(1.04);
        }

        .brand-name {
            color: #c4b5fd;
        }

        /* LEFT INTRODUCTION */

        .intro-panel {
            min-width: 0;
            padding: 5px 0;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            margin-bottom: 24px;
            padding: 9px 12px;

            border: 1px solid rgba(167, 139, 250, 0.3);
            border-radius: 7px;

            background: rgba(139, 92, 246, 0.12);
            color: #d8caff;

            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.1px;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #a78bfa;
            box-shadow: 0 0 12px #8b5cf6;
        }

        .intro-panel h1 {
            max-width: 390px;
            margin-bottom: 20px;

            font-size: clamp(32px, 4vw, 49px);
            font-weight: 900;
            line-height: 1.12;
            letter-spacing: -1.9px;
        }

        .gradient-text {
            color: #b9a0ff;
        }

        .intro-description {
            max-width: 350px;
            margin-bottom: 30px;

            color: var(--muted);
            font-size: 14px;
            line-height: 1.9;
        }

        .feature-list {
            display: grid;
            gap: 17px;
            list-style: none;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;

            color: #e9e2f7;
            font-size: 13px;
            line-height: 1.6;
        }

        .feature-icon {
            display: grid;
            place-items: center;

            width: 35px;
            height: 35px;
            flex-shrink: 0;

            border: 1px solid rgba(167, 139, 250, 0.25);
            border-radius: 9px;

            background: rgba(139, 92, 246, 0.12);
            color: #c4b5fd;
            font-size: 16px;
        }

        .intro-bottom {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-top: 36px;
            padding-top: 23px;

            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .orbit-symbol {
            display: grid;
            place-items: center;

            width: 42px;
            height: 42px;
            flex-shrink: 0;

            border: 1px solid rgba(167, 139, 250, 0.25);
            border-radius: 50%;

            color: #c4b5fd;
            font-size: 22px;
        }

        .intro-bottom strong {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .intro-bottom span {
            color: var(--muted);
            font-size: 11px;
        }

        /* RIGHT FORM PANEL */

        .form-panel {
            min-width: 0;
            padding: 28px;

            border: 1px solid rgba(255, 255, 255, 0.11);
            border-radius: 15px;

            background: rgba(10, 5, 24, 0.25);
        }

        .form-heading {
            margin-bottom: 25px;
        }

        .form-heading h2 {
            margin-bottom: 9px;
            font-size: 25px;
            letter-spacing: -0.8px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        /* FORM FIELDS */

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;

            color: #f0ebfa;
            font-size: 12px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        .form-control {
            display: block;
            width: 100%;
            min-width: 0;
            min-height: 46px;
            padding: 12px 13px;

            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            outline: none;

            background: rgba(9, 5, 20, 0.36);
            color: var(--white);

            font-size: 14px;

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .form-control::placeholder {
            color: #9288a8;
            opacity: 1;
        }

        .form-control:focus {
            border-color: var(--purple);
            background: rgba(9, 5, 20, 0.55);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        select.form-control {
            cursor: pointer;
        }

        select.form-control option {
            color: white;
            background: #211535;
        }

        .password-input {
            padding-right: 65px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);

            padding: 6px 0 6px 8px;
            border: none;

            background: transparent;
            color: #c4b5fd;

            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .password-toggle:hover {
            color: white;
        }

        .form-hint {
            margin-top: 7px;
            color: #a69bbd;
            font-size: 10px;
            line-height: 1.6;
        }

        /* ERROR MESSAGE */

        .message {
            padding: 12px 13px;
            margin-bottom: 18px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .error-message {
            border: 1px solid rgba(248, 113, 113, 0.3);
            background: rgba(127, 29, 29, 0.23);
            color: #fecaca;
        }

        /* SUBMIT BUTTON */

        .submit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            width: 100%;
            min-height: 49px;
            margin-top: 6px;
            padding: 13px 18px;

            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;

            background: linear-gradient(
                110deg,
                #8b5cf6,
                #6d28d9
            );

            color: white;
            font-size: 13px;
            font-weight: 800;

            cursor: pointer;

            box-shadow: 0 9px 25px rgba(109, 40, 217, 0.23);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                filter 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.12);
            box-shadow: 0 13px 30px rgba(109, 40, 217, 0.35);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;

            padding: 12px;
            margin-top: 15px;

            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;

            background: rgba(255, 255, 255, 0.035);
            color: #bdb2d2;

            font-size: 10px;
            line-height: 1.8;
        }

        .security-icon {
            flex-shrink: 0;
            color: #c4b5fd;
            font-size: 14px;
        }

        .form-footer {
            margin-top: 21px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
            text-align: center;
        }

        .form-footer a,
        .back-home {
            color: #c4b5fd;
            font-weight: 700;
            transition: color 0.2s ease;
        }

        .form-footer a:hover,
        .back-home:hover {
            color: white;
        }

        .back-home {
            display: block;
            margin-top: 22px;
            text-align: center;
            font-size: 12px;
        }

        /* ANIMATION */

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* TABLET */

        @media (max-width: 900px) {
            .auth-card {
                gap: 28px;
                padding: 30px;
            }

            .form-panel {
                padding: 23px;
            }

            .brand {
                font-size: 23px;
                margin-bottom: 27px;
            }

            .brand-icon {
                width: 42px;
                height: 42px;
            }

            .intro-panel h1 {
                font-size: 35px;
            }
        }

        /* MOBILE */

        @media (max-width: 680px) {
            body {
                align-items: flex-start;
                padding: 23px 14px;
            }

            .page-shell {
                max-width: 460px;
            }

            .auth-card {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 21px;
                border-radius: 16px;
            }

            .intro-panel {
                padding: 5px 0;
            }

            .brand {
                justify-content: center;
                margin-bottom: 25px;
                font-size: 24px;
            }

            .brand-icon {
                width: 42px;
                height: 42px;
            }

            .eyebrow {
                margin-bottom: 16px;
                font-size: 9px;
            }

            .intro-panel h1 {
                max-width: none;
                margin-bottom: 12px;
                font-size: 34px;
            }

            .intro-description {
                max-width: none;
                margin-bottom: 19px;
                font-size: 13px;
            }

            .feature-list {
                gap: 11px;
            }

            .feature-item {
                font-size: 12px;
            }

            .feature-icon {
                width: 31px;
                height: 31px;
            }

            .intro-bottom {
                margin-top: 21px;
                padding-top: 16px;
            }

            .form-panel {
                padding: 20px 16px;
            }

            .form-heading {
                margin-bottom: 20px;
            }

            .form-heading h2 {
                font-size: 23px;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .form-control {
                min-height: 47px;
                font-size: 16px;
            }

            .submit-btn {
                min-height: 48px;
            }

            .back-home {
                margin-top: 18px;
            }
        }

        /* VERY SMALL MOBILE */

        @media (max-width: 360px) {
            body {
                padding: 18px 10px;
            }

            .auth-card {
                padding: 15px;
                gap: 21px;
            }

            .intro-panel h1 {
                font-size: 29px;
            }

            .form-panel {
                padding: 17px 12px;
            }

            .brand {
                font-size: 22px;
            }
        }

        /* SHORT DESKTOP SCREENS */

        @media (min-width: 681px) and (max-height: 760px) {
            body {
                padding-top: 20px;
                padding-bottom: 20px;
            }

            .auth-card {
                padding: 25px;
            }

            .brand {
                margin-bottom: 20px;
            }

            .intro-panel h1 {
                font-size: 36px;
            }

            .intro-description {
                margin-bottom: 20px;
            }

            .intro-bottom {
                margin-top: 22px;
                padding-top: 15px;
            }

            .form-panel {
                padding: 20px;
            }

            .form-heading {
                margin-bottom: 17px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .back-home {
                margin-top: 14px;
            }
        }

        /* ACCESSIBILITY */

        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible {
            outline: 2px solid #c4b5fd;
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation: none !important;
                transition: none !important;
            }
        }

    </style>

</head>

<body>

    <div class="background-orb orb-one"></div>
    <div class="background-orb orb-two"></div>

    <main class="page-shell">

        <!-- HORIZONTAL GLASS CARD -->

        <section class="auth-card">

            <!-- LEFT INTRODUCTION -->

            <div class="intro-panel">

                <!-- BRAND IS NOW INSIDE THE CARD -->

                <a href="index.php" class="brand">

                    <span class="brand-icon">

                        <svg
                            viewBox="0 0 40 40"
                            width="29"
                            height="29"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >

                            <circle
                                cx="20"
                                cy="20"
                                r="14"
                                stroke="white"
                                stroke-width="2.5"
                            />

                            <ellipse
                                cx="20"
                                cy="20"
                                rx="6"
                                ry="14"
                                stroke="white"
                                stroke-width="2"
                                transform="rotate(45 20 20)"
                            />

                            <circle
                                cx="20"
                                cy="20"
                                r="3"
                                fill="white"
                            />

                        </svg>

                    </span>

                    <span class="brand-name">InternOrbit</span>

                </a>

                <div class="eyebrow">

                    <span class="eyebrow-dot"></span>

                    YOUR NEXT OPPORTUNITY STARTS HERE

                </div>

                <h1>
                    Your Future.
                    <br>
                    Your <span class="gradient-text">Orbit.</span>
                </h1>

                <p class="intro-description">
                    Discover internships, showcase your skills,
                    and take the next step in your professional journey.
                    Your next opportunity starts here.
                </p>

                <ul class="feature-list">

                    <li class="feature-item">

                        <span class="feature-icon" aria-hidden="true">
                            ↗
                        </span>

                        <span>
                            Discover exciting internship opportunities
                        </span>

                    </li>

                    <li class="feature-item">

                        <span class="feature-icon" aria-hidden="true">
                            ◈
                        </span>

                        <span>
                            Build your profile and showcase your skills
                        </span>

                    </li>

                    <li class="feature-item">

                        <span class="feature-icon" aria-hidden="true">
                            ✓
                        </span>

                        <span>
                            Track applications and selection progress
                        </span>

                    </li>

                </ul>

                <div class="intro-bottom">

                    <div class="orbit-symbol" aria-hidden="true">
                        ◎
                    </div>

                    <div>

                        <strong>Find your direction.</strong>

                        <span>Make your next move count.</span>

                    </div>

                </div>

            </div>

            <!-- RIGHT REGISTRATION FORM -->

            <div class="form-panel">

                <div class="form-heading">

                    <h2>Create Account</h2>

                    <p>
                        Join InternOrbit and get started in a few steps.
                    </p>

                </div>

                <?php if ($error !== ""): ?>

                    <div
                        class="message error-message"
                        role="alert"
                    >
                        <?= escapeOutput($error) ?>
                    </div>

                <?php endif; ?>

                <form
                    action="register.php"
                    method="POST"
                    id="registerForm"
                >

                    <div class="form-group">

                        <label for="name">
                            Full Name / Company Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            placeholder="Enter your name"
                            value="<?= escapeOutput($_POST["name"] ?? "") ?>"
                            minlength="2"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="Email Id"
                            value="<?= escapeOutput($_POST["email"] ?? "") ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="role">
                            Register As
                        </label>

                        <select
                            id="role"
                            name="role"
                            class="form-control"
                            required
                        >

                            <option
                                value="Student"
                                <?= ($_POST["role"] ?? "Student") === "Student"
                                    ? "selected"
                                    : "" ?>
                            >
                                Student
                            </option>

                            <option
                                value="Company"
                                <?= ($_POST["role"] ?? "") === "Company"
                                    ? "selected"
                                    : "" ?>
                            >
                                Company
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrap">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control password-input"
                                placeholder="At least 8 characters"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="password"
                                aria-label="Show password"
                            >
                                Show
                            </button>

                        </div>

                        <p class="form-hint">
                            Use at least 8 characters.
                        </p>

                    </div>

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <div class="input-wrap">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-control password-input"
                                placeholder="Re-enter your password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="confirm_password"
                                aria-label="Show confirmation password"
                            >
                                Show
                            </button>

                        </div>

                    </div>

                    <button
                        type="submit"
                        class="submit-btn"
                    >

                        Create Account

                        <span aria-hidden="true">→</span>

                    </button>

                </form>

                <div class="security-note">

                    <span class="security-icon" aria-hidden="true">
                        ◈
                    </span>

                    <p>
                        Your password is hashed before storage.
                        Keep your account credentials private.
                    </p>

                </div>

                <div class="form-footer">

                    Already have an account?
                    <a href="login.php">Login here</a>

                </div>

            </div>

        </section>

        <a href="index.php" class="back-home">
            ← Back to Home
        </a>

    </main>

    <script>

        // Show or hide password.

        document.querySelectorAll(".password-toggle").forEach(
            function (button) {

                button.addEventListener("click", function () {

                    const input = document.getElementById(
                        button.dataset.target
                    );

                    const show = input.type === "password";

                    input.type = show ? "text" : "password";

                    button.textContent = show ? "Hide" : "Show";

                    button.setAttribute(
                        "aria-label",
                        show ? "Hide password" : "Show password"
                    );

                });

            }
        );

        // Validate password confirmation.

        document.getElementById("registerForm").addEventListener(
            "submit",
            function (event) {

                const password = document.getElementById(
                    "password"
                ).value;

                const confirmPassword = document.getElementById(
                    "confirm_password"
                ).value;

                if (password !== confirmPassword) {

                    event.preventDefault();

                    alert("Your passwords do not match.");

                    document.getElementById(
                        "confirm_password"
                    ).focus();

                }

            }
        );

    </script>

</body>

</html>
