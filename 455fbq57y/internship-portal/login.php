<?php
session_start();

require_once __DIR__ . "/config/database.php";

function escapeOutput($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Redirect users who are already logged in.
if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    if ($_SESSION["role"] === "Student") {
        header("Location: student/dashboard.php");
        exit();
    }

    if ($_SESSION["role"] === "Company") {
        header("Location: company/dashboard.php");
        exit();
    }
}

$error = "";
$email = "";
$selectedRole = "Student";
$successMessage = "";

if (isset($_GET["registered"]) && $_GET["registered"] === "1") {
    $successMessage = "Registration successful! Please log in.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $selectedRole = $_POST["role"] ?? "Student";

    if (!in_array($selectedRole, ["Student", "Company"], true)) {
        $error = "Please select a valid account type.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password === "") {
        $error = "Please enter your password.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, email, password, role
                 FROM users
                 WHERE email = :email AND role = :role
                 LIMIT 1"
            );

            $stmt->execute([
                ":email" => $email,
                ":role" => $selectedRole
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user["password"])) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] === "Student") {
                    header("Location: student/dashboard.php");
                    exit();
                }

                if ($user["role"] === "Company") {
                    header("Location: company/dashboard.php");
                    exit();
                }

                $error = "This account has an unsupported role.";
            } else {
                $error = "Invalid email, password, or account type.";
            }
        } catch (PDOException $e) {
            error_log("InternOrbit login error: " . $e->getMessage());
            $error = "Unable to log in right now. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | InternOrbit</title>

    <meta
        name="description"
        content="Log in to InternOrbit to discover internships and manage your applications."
    >

    <style>
        :root {
            --purple: #a855f7;
            --purple-light: #c084fc;
            --purple-dark: #7c3aed;
            --white: #ffffff;
            --muted: #b9b2c9;
            --background: #10091f;
            --danger: #fca5a5;
            --success: #86efac;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            padding: 30px 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--white);

            background:
                radial-gradient(
                    circle at 12% 15%,
                    rgba(124, 58, 237, 0.30),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(168, 85, 247, 0.20),
                    transparent 35%
                ),
                var(--background);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* Background glow */

        .orb {
            position: fixed;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(147, 51, 234, 0.20);
            filter: blur(75px);
            pointer-events: none;
            z-index: 0;
        }

        .orb-one {
            top: -90px;
            left: -70px;
        }

        .orb-two {
            right: -90px;
            bottom: -100px;
        }

        /* Main container */

        .page-wrapper {
            width: 100%;
            max-width: 1050px;
            position: relative;
            z-index: 1;
            animation: appear 0.7s ease both;
        }

        .auth-card {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            align-items: center;
            gap: 46px;
            padding: 43px;

            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 24px;

            background: rgba(255, 255, 255, 0.075);

            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);

            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.30);
        }

        /* Left introduction panel */

        .intro-panel {
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        /* Brand: always on its own row */

        .brand {
            display: flex;
            width: fit-content;
            max-width: 100%;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            margin-bottom: 22px;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 15px;

            background: linear-gradient(
                135deg,
                #c084fc,
                #7c3aed
            );

            box-shadow: 0 8px 25px rgba(124, 58, 237, 0.35);
        }

        .brand-logo svg {
            width: 35px;
            height: 35px;
            display: block;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.8px;
            white-space: nowrap;
        }

        .brand-name span {
            color: var(--purple-light);
        }

        /* Career badge: below the logo */

        .eyebrow {
            display: inline-flex;
            width: fit-content;
            max-width: 100%;
            align-items: center;
            gap: 8px;

            margin-bottom: 24px;
            padding: 9px 13px;

            border: 1px solid rgba(192, 132, 252, 0.25);
            border-radius: 30px;

            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);

            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            flex-shrink: 0;

            border-radius: 50%;
            background: #c084fc;
            box-shadow: 0 0 10px #c084fc;
        }

        .intro-panel h1 {
            max-width: 390px;
            margin-bottom: 17px;

            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.16;
            letter-spacing: -1.5px;
        }

        .intro-panel h1 span {
            display: block;
            color: var(--purple-light);
        }

        .intro-description {
            max-width: 360px;
            margin-bottom: 28px;

            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        /* Feature list */

        .feature-list {
            display: grid;
            gap: 17px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;

            color: #e9e3f3;
            font-size: 13px;
            line-height: 1.5;
        }

        .feature-icon {
            width: 37px;
            height: 37px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 11px;

            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
            font-size: 16px;
        }

        /* Right login panel */

        .form-panel {
            min-width: 0;
            padding: 30px;

            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;

            background: rgba(10, 5, 20, 0.30);
        }

        .form-heading {
            margin-bottom: 28px;
        }

        .form-heading h2 {
            margin-bottom: 9px;
            font-size: 27px;
            letter-spacing: -0.6px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        /* Feedback messages */

        .message {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        .message-error {
            border: 1px solid rgba(248, 113, 113, 0.30);
            background: rgba(239, 68, 68, 0.10);
            color: var(--danger);
        }

        .message-success {
            border: 1px solid rgba(74, 222, 128, 0.25);
            background: rgba(34, 197, 94, 0.10);
            color: var(--success);
        }

        /* Form controls */

        .form-group {
            min-width: 0;
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;

            color: #f1ebfa;
            font-size: 13px;
            font-weight: 600;
        }

        .input-wrapper {
            position: relative;
            width: 100%;
        }

        .form-control {
            display: block;
            width: 100%;
            min-height: 50px;
            padding: 13px 14px;

            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 11px;
            outline: none;

            background: rgba(255, 255, 255, 0.055);
            color: var(--white);

            font-family: inherit;
            font-size: 13px;

            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .form-control::placeholder {
            color: #827990;
        }

        .form-control:focus {
            border-color: var(--purple-light);
            background: rgba(168, 85, 247, 0.07);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.12);
        }

        /* Account type dropdown */

        select.form-control {
            cursor: pointer;
            color: #ffffff;
            background-color: #241832;
            color-scheme: dark;
        }

        select.form-control option {
            background-color: #241832;
            color: #ffffff;
        }

        select.form-control option:checked {
            background-color: #7c3aed;
            color: #ffffff;
        }

        /* Password visibility */

        .password-input {
            padding-right: 70px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 13px;
            transform: translateY(-50%);

            border: none;
            background: transparent;

            color: var(--purple-light);
            cursor: pointer;

            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
        }

        .toggle-password:hover {
            color: var(--white);
        }

        /* Remember and forgot password */

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;

            margin: 2px 0 24px;
            font-size: 12px;
        }

        .remember-option {
            display: flex;
            align-items: center;
            gap: 8px;

            color: var(--muted);
            cursor: pointer;
        }

        .remember-option input {
            accent-color: var(--purple);
        }

        .forgot-link {
            color: var(--purple-light);
            white-space: nowrap;
        }

        .forgot-link:hover {
            color: var(--white);
        }

        /* Login button */

        .login-button {
            width: 100%;
            min-height: 51px;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            border: none;
            border-radius: 11px;

            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );

            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.25);

            color: var(--white);
            cursor: pointer;

            font-family: inherit;
            font-size: 14px;
            font-weight: 700;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(124, 58, 237, 0.38);
        }

        .login-button:active {
            transform: translateY(0);
        }

        /* Registration link */

        .register-prompt {
            margin-top: 23px;

            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
            text-align: center;
        }

        .register-prompt a {
            color: var(--purple-light);
            font-weight: 700;
        }

        .register-prompt a:hover {
            color: var(--white);
        }

        .back-link {
            display: inline-block;
            margin-top: 22px;

            color: #c7bdd7;
            font-size: 12px;

            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--white);
        }

        /* Entrance animation */

        @keyframes appear {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tablet */

        @media (max-width: 850px) {
            .auth-card {
                grid-template-columns: 0.9fr 1.1fr;
                gap: 28px;
                padding: 28px;
            }

            .form-panel {
                padding: 24px;
            }

            .intro-panel h1 {
                font-size: 32px;
            }

            .brand-name {
                font-size: 22px;
            }
        }

        /* Mobile */

        @media (max-width: 680px) {
            body {
                padding: 22px 13px;
                align-items: flex-start;
            }

            .page-wrapper {
                max-width: 460px;
                margin: auto;
            }

            .auth-card {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 22px;
                border-radius: 20px;
            }

            .intro-panel {
                align-items: center;
                text-align: center;
            }

            .brand {
                justify-content: center;
                margin-bottom: 18px;
            }

            .brand-logo {
                width: 46px;
                height: 46px;
            }

            .brand-name {
                font-size: 23px;
            }

            .eyebrow {
                justify-content: center;
                margin-bottom: 18px;
            }

            .intro-panel h1 {
                max-width: 100%;
                margin-bottom: 12px;
                font-size: 31px;
            }

            .intro-description {
                max-width: 100%;
                margin-bottom: 0;
                font-size: 13px;
            }

            .feature-list {
                display: none;
            }

            .form-panel {
                padding: 22px 18px;
                border-radius: 16px;
            }

            .form-heading {
                margin-bottom: 23px;
                text-align: left;
            }

            .form-heading h2 {
                font-size: 25px;
            }

            .form-group {
                margin-bottom: 19px;
            }

            .back-link {
                margin-top: 17px;
            }
        }

        @media (max-width: 360px) {
            .auth-card {
                padding: 15px;
            }

            .form-panel {
                padding: 18px 13px;
            }

            .brand {
                gap: 9px;
            }

            .brand-name {
                font-size: 21px;
            }

            .form-options {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>

<body>

    <div class="orb orb-one"></div>
    <div class="orb orb-two"></div>

    <main class="page-wrapper">

        <section class="auth-card">

            <!-- Left introduction panel -->

            <div class="intro-panel">

                <!-- Brand stays on its own row -->

                <a
                    href="index.php"
                    class="brand"
                    aria-label="InternOrbit home"
                >
                    <div class="brand-logo">
                        <svg
                            viewBox="0 0 48 48"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <circle
                                cx="24"
                                cy="24"
                                r="15"
                                stroke="white"
                                stroke-width="2.2"
                            />

                            <ellipse
                                cx="24"
                                cy="24"
                                rx="21"
                                ry="8.5"
                                transform="rotate(-35 24 24)"
                                stroke="white"
                                stroke-width="2.2"
                            />

                            <circle
                                cx="36"
                                cy="10"
                                r="4"
                                fill="#FDE68A"
                            />

                            <circle
                                cx="24"
                                cy="24"
                                r="4"
                                fill="white"
                            />
                        </svg>
                    </div>

                    <div class="brand-name">
                        Intern<span>Orbit</span>
                    </div>
                </a>

                <!-- Badge is now below the brand -->

                <div class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    Your career starts here
                </div>

                <h1>
                    Welcome back to
                    <span>your future.</span>
                </h1>

                <p class="intro-description">
                    Sign in to discover internship opportunities,
                    connect with companies, and take the next step
                    in your professional journey.
                </p>

                <div class="feature-list">

                    <div class="feature">
                        <div class="feature-icon">&#10003;</div>
                        <span>
                            Discover internships that match your skills
                        </span>
                    </div>

                    <div class="feature">
                        <div class="feature-icon">&#9733;</div>
                        <span>
                            Track your applications in one place
                        </span>
                    </div>

                    <div class="feature">
                        <div class="feature-icon">&#8599;</div>
                        <span>
                            Build connections with companies
                        </span>
                    </div>

                </div>

            </div>

            <!-- Right login form -->

            <div class="form-panel">

                <div class="form-heading">
                    <h2>Sign in</h2>
                    <p>
                        Enter your account details to continue.
                    </p>
                </div>

                <?php if ($successMessage !== ""): ?>
                    <div
                        class="message message-success"
                        role="status"
                    >
                        <?= escapeOutput($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ""): ?>
                    <div
                        class="message message-error"
                        role="alert"
                    >
                        <?= escapeOutput($error) ?>
                    </div>
                <?php endif; ?>

                <form
                    method="POST"
                    action="login.php"
                    id="loginForm"
                >

                    <!-- Account type -->

                    <div class="form-group">
                        <label for="role">
                            I am signing in as
                        </label>

                        <select
                            class="form-control"
                            id="role"
                            name="role"
                            required
                        >
                            <option
                                value="Student"
                                <?= $selectedRole === "Student" ? "selected" : "" ?>
                            >
                                Student
                            </option>

                            <option
                                value="Company"
                                <?= $selectedRole === "Company" ? "selected" : "" ?>
                            >
                                Company
                            </option>
                        </select>
                    </div>

                    <!-- Email -->

                    <div class="form-group">
                        <label for="email">
                            Email address
                        </label>

                        <input
                            class="form-control"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Email Id"
                            value="<?= escapeOutput($email) ?>"
                            autocomplete="username"
                            maxlength="254"
                            required
                        >
                    </div>

                    <!-- Password -->

                    <div class="form-group">
                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrapper">
                            <input
                                class="form-control password-input"
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                class="toggle-password"
                                type="button"
                                id="togglePassword"
                                aria-controls="password"
                                aria-pressed="false"
                            >
                                Show
                            </button>
                        </div>
                    </div>

                    <!-- Remember me and forgot password -->

                    <div class="form-options">

                        <label class="remember-option">
                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                            >
                            <span>Remember me</span>
                        </label>

                        <a
                            href="#"
                            class="forgot-link"
                            id="forgotPassword"
                        >
                            Forgot password?
                        </a>

                    </div>

                    <!-- Submit -->

                    <button
                        class="login-button"
                        type="submit"
                    >
                        Login to your account
                        <span aria-hidden="true">&#8594;</span>
                    </button>

                </form>

                <p class="register-prompt">
                    Don't have an account?
                    <a href="register.php">
                        Create an account
                    </a>
                </p>

            </div>

        </section>

        <a href="index.php" class="back-link">
            &#8592; Back to Home
        </a>

    </main>

    <script>
        // Show and hide password.

        const passwordInput = document.getElementById("password");
        const togglePassword = document.getElementById("togglePassword");

        togglePassword.addEventListener("click", function () {
            const isPassword = passwordInput.type === "password";

            passwordInput.type = isPassword ? "text" : "password";

            togglePassword.textContent = isPassword ? "Hide" : "Show";

            togglePassword.setAttribute(
                "aria-pressed",
                String(isPassword)
            );
        });

        // Basic client-side validation.

        document.getElementById("loginForm").addEventListener(
            "submit",
            function (event) {
                const email = document.getElementById("email").value.trim();
                const password = passwordInput.value;

                if (!email || !password) {
                    event.preventDefault();

                    alert(
                        "Please enter your email address and password."
                    );
                }
            }
        );

        // Password recovery placeholder.

        document.getElementById("forgotPassword").addEventListener(
            "click",
            function (event) {
                event.preventDefault();

                alert(
                    "Password recovery is not configured yet. " +
                    "Please contact the portal administrator for assistance."
                );
            }
        );
    </script>

</body>
</html>