<?php

session_start();

require_once __DIR__ . "/../config/database.php";

// Protect the student profile page.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Student"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];

$message = "";
$messageType = "";

function escapeOutput($value)
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$student = [];
$profileQuery = null;

try {
    // Load the logged-in student's profile.
    $profileQuery = $pdo->prepare(
        "SELECT
            u.id AS user_id,
            u.name,
            u.email,
            u.role,
            u.created_at,
            s.id AS student_id,
            s.skills,
            s.resume
         FROM users u
         LEFT JOIN students s
            ON s.user_id = u.id
         WHERE u.id = :user_id
         LIMIT 1"
    );

    $profileQuery->execute([
        ":user_id" => $userId
    ]);

    $student = $profileQuery->fetch(PDO::FETCH_ASSOC);

    if (!$student || $student["role"] !== "Student") {
        header("Location: ../login.php");
        exit();
    }

    // Create a student profile if it does not exist.
    if (empty($student["student_id"])) {
        $createProfile = $pdo->prepare(
            "INSERT INTO students (user_id, skills, resume)
             VALUES (:user_id, '', NULL)"
        );

        $createProfile->execute([
            ":user_id" => $userId
        ]);

        header("Location: profile.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("InternOrbit profile loading error: " . $e->getMessage());

    die("Unable to load your profile. Please try again later.");
}

// Save profile changes.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $skills = trim($_POST["skills"] ?? "");

    if ($name === "") {
        $message = "Please enter your full name.";
        $messageType = "error";
    } elseif (strlen($name) > 100) {
        $message = "Your name cannot exceed 100 characters.";
        $messageType = "error";
    } elseif (strlen($skills) > 10000) {
        $message = "Your skills description is too long.";
        $messageType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            $updateUser = $pdo->prepare(
                "UPDATE users
                 SET name = :name
                 WHERE id = :user_id
                 AND role = 'Student'"
            );

            $updateUser->execute([
                ":name" => $name,
                ":user_id" => $userId
            ]);

            $updateStudent = $pdo->prepare(
                "UPDATE students
                 SET skills = :skills
                 WHERE user_id = :user_id"
            );

            $updateStudent->execute([
                ":skills" => $skills,
                ":user_id" => $userId
            ]);

            $pdo->commit();

            // Update the current session name.
            $_SESSION["name"] = $name;

            // Reload the saved profile.
            $profileQuery->execute([
                ":user_id" => $userId
            ]);

            $student = $profileQuery->fetch(PDO::FETCH_ASSOC);

            $message = "Profile updated successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("InternOrbit profile update error: " . $e->getMessage());

            $message = "Unable to update your profile. Please try again.";
            $messageType = "error";
        }
    }
}

$name = $student["name"] ?? "";
$email = $student["email"] ?? "";
$skills = $student["skills"] ?? "";
$resume = $student["resume"] ?? "";
$createdAt = $student["created_at"] ?? null;

$nameParts = preg_split('/\s+/', trim($name));
$firstName = !empty($nameParts[0]) ? $nameParts[0] : "Student";

$initial = strtoupper(substr(trim($name), 0, 1));

if ($initial === "") {
    $initial = "S";
}

$skillList = array_values(
    array_unique(
        array_filter(
            array_map("trim", explode(",", $skills))
        )
    )
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile | InternOrbit</title>

    <style>
        :root {
            --purple: #a855f7;
            --purple-light: #c084fc;
            --purple-dark: #7c3aed;
            --background: #10091f;
            --surface: rgba(255, 255, 255, 0.055);
            --border: rgba(255, 255, 255, 0.10);
            --white: #ffffff;
            --muted: #b9b2c9;
            --green: #86efac;
            --red: #fca5a5;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--white);
            background:
                radial-gradient(
                    circle at 10% 0%,
                    rgba(124, 58, 237, 0.22),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 95% 95%,
                    rgba(168, 85, 247, 0.12),
                    transparent 30%
                ),
                var(--background);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        textarea {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        .app-layout {
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            min-height: 100vh;
            padding: 28px 18px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            background: rgba(13, 7, 25, 0.78);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 8px;
            margin-bottom: 40px;
        }

        .brand-logo {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.20);
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                #c084fc,
                #7c3aed
            );
            box-shadow: 0 7px 20px rgba(124, 58, 237, 0.30);
        }

        .brand-logo svg {
            width: 31px;
            height: 31px;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .brand-name span {
            color: var(--purple-light);
        }

        .nav-label {
            padding: 0 12px;
            margin-bottom: 13px;
            color: #817791;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            display: grid;
            gap: 7px;
        }

        .nav-link {
            min-height: 46px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            border: 1px solid transparent;
            border-radius: 11px;
            color: var(--muted);
            font-size: 13px;
            transition:
                color 0.2s ease,
                background 0.2s ease,
                border-color 0.2s ease;
        }

        .nav-icon {
            width: 23px;
            height: 23px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .nav-icon svg {
            display: block;
        }

        .nav-link:hover {
            background: rgba(168, 85, 247, 0.08);
            color: var(--white);
        }

        .nav-link.active {
            border-color: rgba(192, 132, 252, 0.16);
            background: linear-gradient(
                100deg,
                rgba(147, 51, 234, 0.24),
                rgba(124, 58, 237, 0.10)
            );
            color: var(--purple-light);
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 25px;
        }

        .sidebar-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 9px;
            border-top: 1px solid var(--border);
        }

        .avatar {
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.30);
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                rgba(168, 85, 247, 0.30),
                rgba(124, 58, 237, 0.14)
            );
            color: var(--purple-light);
            font-size: 14px;
            font-weight: 800;
        }

        .profile-text {
            min-width: 0;
        }

        .profile-name {
            overflow: hidden;
            color: #f4effb;
            font-size: 12px;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-role {
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
        }

        /* Main area */

        .main-area {
            flex: 1;
            min-width: 0;
            padding: 30px 34px 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding-bottom: 24px;
            margin-bottom: 27px;
            border-bottom: 1px solid var(--border);
        }

        .breadcrumb {
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 12px;
        }

        .breadcrumb span {
            color: var(--purple-light);
        }

        .page-title {
            font-size: 29px;
            line-height: 1.3;
            letter-spacing: -0.8px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-badge {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: rgba(255, 255, 255, 0.035);
            color: var(--muted);
            font-size: 11px;
        }

        .top-avatar {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.30);
            border-radius: 14px;
            background: linear-gradient(
                135deg,
                #a855f7,
                #7c3aed
            );
            color: var(--white);
            font-size: 17px;
            font-weight: 800;
        }

        /* Intro */

        .intro {
            margin-bottom: 29px;
        }

        .intro h2 {
            margin-bottom: 8px;
            font-size: 21px;
            letter-spacing: -0.4px;
        }

        .intro p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* Profile layout */

        .profile-grid {
            display: grid;
            grid-template-columns: minmax(270px, 0.75fr) minmax(0, 1.65fr);
            align-items: start;
            gap: 24px;
        }

        .panel {
            min-width: 0;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 19px;
            background: var(--surface);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.10);
        }

        /* Profile identity card */

        .identity {
            text-align: center;
        }

        .large-avatar {
            width: 100px;
            height: 100px;
            display: grid;
            place-items: center;
            margin: 5px auto 19px;
            border: 1px solid rgba(255, 255, 255, 0.17);
            border-radius: 27px;
            background: linear-gradient(
                135deg,
                #c084fc,
                #7c3aed
            );
            box-shadow: 0 14px 32px rgba(124, 58, 237, 0.25);
            color: var(--white);
            font-size: 37px;
            font-weight: 800;
        }

        .identity h2 {
            overflow-wrap: anywhere;
            margin-bottom: 8px;
            font-size: 20px;
        }

        .identity .email {
            overflow-wrap: anywhere;
            margin-bottom: 17px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 13px;
            border: 1px solid rgba(168, 85, 247, 0.23);
            border-radius: 30px;
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.4px;
        }

        .identity-divider {
            height: 1px;
            margin: 25px 0 14px;
            background: var(--border);
        }

        .identity-detail {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            text-align: left;
        }

        .identity-detail span {
            color: var(--muted);
            font-size: 12px;
        }

        .identity-detail strong {
            overflow-wrap: anywhere;
            color: var(--white);
            font-size: 12px;
            text-align: right;
        }

        .resume-box {
            margin-top: 14px;
            padding: 17px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.025);
        }

        .resume-box h3 {
            margin-bottom: 10px;
            font-size: 13px;
        }

        .resume-box p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .resume-link {
            display: inline-block;
            margin-top: 8px;
            overflow-wrap: anywhere;
            color: var(--purple-light);
            font-size: 12px;
        }

        /* Edit form */

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 24px;
        }

        .panel-heading h2 {
            margin-bottom: 7px;
            font-size: 17px;
        }

        .panel-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .edit-mark {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.12);
            border-radius: 13px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
            font-size: 20px;
        }

        .alert {
            padding: 13px 15px;
            margin-bottom: 21px;
            border: 1px solid transparent;
            border-radius: 11px;
            font-size: 12px;
            line-height: 1.7;
        }

        .alert.success {
            border-color: rgba(74, 222, 128, 0.22);
            background: rgba(34, 197, 94, 0.08);
            color: var(--green);
        }

        .alert.error {
            border-color: rgba(248, 113, 113, 0.22);
            background: rgba(239, 68, 68, 0.08);
            color: var(--red);
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #f4effb;
            font-size: 12px;
            font-weight: 700;
        }

        .form-group label small {
            color: #9589a9;
            font-size: 10px;
            font-weight: 400;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.11);
            border-radius: 12px;
            outline: none;
            background: rgba(10, 7, 19, 0.55);
            color: var(--white);
            font-size: 13px;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .form-group input {
            min-height: 48px;
            padding: 13px 15px;
        }

        .form-group textarea {
            min-height: 145px;
            padding: 15px;
            line-height: 1.7;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.10);
        }

        .form-group input[readonly] {
            color: #a99fbd;
            cursor: not-allowed;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #817791;
        }

        .field-hint {
            margin-top: 8px;
            color: #9589a9;
            font-size: 10px;
            line-height: 1.7;
        }

        .skills-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .skill-tag {
            padding: 7px 10px;
            border: 1px solid rgba(192, 132, 252, 0.18);
            border-radius: 8px;
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
            font-size: 11px;
        }

        .skills-empty {
            color: #817791;
            font-size: 11px;
        }

        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding-top: 21px;
            border-top: 1px solid var(--border);
        }

        .form-footer p {
            max-width: 280px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .save-btn {
            min-height: 44px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 19px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.18);
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(124, 58, 237, 0.28);
        }

        .save-btn:focus-visible,
        .nav-link:focus-visible {
            outline: 2px solid var(--purple-light);
            outline-offset: 3px;
        }

        /* Footer */

        .footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 29px;
            color: #817791;
            font-size: 11px;
        }

        /* Tablet */

        @media (max-width: 1100px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .profile-grid {
                grid-template-columns: minmax(230px, 0.8fr) minmax(0, 1.4fr);
                gap: 16px;
            }

            .panel {
                padding: 20px;
            }

            .form-footer {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        /* Mobile */

        @media (max-width: 760px) {
            .app-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                padding: 14px 16px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .brand {
                margin-bottom: 16px;
            }

            .sidebar .nav-label,
            .sidebar-bottom {
                display: none;
            }

            .sidebar-nav {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                padding-bottom: 3px;
            }

            .nav-link {
                min-height: 40px;
                flex-shrink: 0;
                padding: 9px 12px;
                font-size: 12px;
            }

            .nav-icon {
                width: auto;
                font-size: 15px;
            }

            .main-area {
                padding: 22px 16px 30px;
            }

            .topbar {
                margin-bottom: 23px;
                padding-bottom: 20px;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .identity {
                max-width: none;
            }

            .form-footer {
                align-items: stretch;
            }

            .save-btn {
                width: 100%;
            }
        }

        @media (max-width: 520px) {
            .page-title {
                font-size: 25px;
            }

            .topbar-badge {
                display: none;
            }

            .top-avatar {
                width: 39px;
                height: 39px;
            }

            .intro h2 {
                font-size: 19px;
            }

            .panel {
                padding: 18px;
            }

            .panel-heading h2 {
                font-size: 15px;
            }

            .large-avatar {
                width: 86px;
                height: 86px;
                border-radius: 23px;
                font-size: 32px;
            }

            .footer {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<div class="app-layout">

    <!-- Sidebar -->

    <aside class="sidebar">

        <a href="dashboard.php" class="brand">

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

        <div class="nav-label">Student workspace</div>

        <nav class="sidebar-nav">

            <!-- Dashboard link with updated SVG icon -->
            <a href="dashboard.php" class="nav-link">
                <span class="nav-icon">
                    <svg
                        width="19"
                        height="19"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <rect x="3" y="3" width="8" height="8" rx="2"></rect>
                        <rect x="13" y="3" width="8" height="5" rx="2"></rect>
                        <rect x="13" y="10" width="8" height="11" rx="2"></rect>
                        <rect x="3" y="13" width="8" height="8" rx="2"></rect>
                    </svg>
                </span>
                <span>Dashboard</span>
            </a>

            <a href="internships.php" class="nav-link">
                <span class="nav-icon">&#128188;</span>
                <span>Explore Internships</span>
            </a>

            <a href="applications.php" class="nav-link">
                <span class="nav-icon">&#128203;</span>
                <span>My Applications</span>
            </a>

            <a href="profile.php" class="nav-link active">
                <span class="nav-icon">&#128100;</span>
                <span>My Profile</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-profile">

                <div class="avatar">
                    <?= escapeOutput($initial) ?>
                </div>

                <div class="profile-text">

                    <div class="profile-name">
                        <?= escapeOutput($name) ?>
                    </div>

                    <div class="profile-role">
                        Student account
                    </div>

                </div>

            </div>

        </div>

    </aside>

    <!-- Main profile page -->

    <main class="main-area">

        <header class="topbar">

            <div>
                <div class="breadcrumb">
                    Workspace / <span>My Profile</span>
                </div>

                <h1 class="page-title">
                    My Profile
                </h1>
            </div>

            <div class="topbar-right">

                <div class="topbar-badge">
                    Student Workspace
                </div>

                <div class="top-avatar">
                    <?= escapeOutput($initial) ?>
                </div>

            </div>

        </header>

        <!-- Introduction -->

        <section class="intro">

            <h2>Your professional identity</h2>

            <p>
                Manage your personal information and technical skills
                to make your internship applications stand out.
            </p>

        </section>

        <!-- Profile content -->

        <section class="profile-grid">

            <!-- Profile summary -->

            <div class="panel identity">

                <div class="large-avatar">
                    <?= escapeOutput($initial) ?>
                </div>

                <h2>
                    <?= escapeOutput($name) ?>
                </h2>

                <p class="email">
                    <?= escapeOutput($email) ?>
                </p>

                <span class="role-badge">
                    STUDENT ACCOUNT
                </span>

                <div class="identity-divider"></div>

                <div class="identity-detail">
                    <span>Account type</span>
                    <strong>Student</strong>
                </div>

                <div class="identity-detail">
                    <span>Member since</span>

                    <strong>
                        <?php
                        if ($createdAt) {
                            echo escapeOutput(
                                date("M Y", strtotime($createdAt))
                            );
                        } else {
                            echo "Unavailable";
                        }
                        ?>
                    </strong>
                </div>

                <div class="identity-detail">
                    <span>Skills added</span>

                    <strong>
                        <?= count($skillList) ?>
                    </strong>
                </div>

                <div class="resume-box">

                    <h3>Resume status</h3>

                    <?php if (!empty($resume)): ?>

                        <p>
                            A resume file path is saved in your profile.
                        </p>

                        <span class="resume-link">
                            <?= escapeOutput(basename($resume)) ?>
                        </span>

                    <?php else: ?>

                        <p>
                            No resume has been saved yet.
                            Resume upload can be added after confirming
                            your file storage configuration.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <!-- Edit profile form -->

            <div class="panel">

                <div class="panel-heading">

                    <div>
                        <h2>Edit your information</h2>

                        <p>
                            Keep your profile accurate and your skills
                            relevant to the roles you want.
                        </p>
                    </div>

                    <div class="edit-mark">
                        &#9998;
                    </div>

                </div>

                <?php if ($message !== ""): ?>

                    <div
                        class="alert <?= $messageType === "success"
                            ? "success"
                            : "error" ?>"
                        role="status"
                    >
                        <?= escapeOutput($message) ?>
                    </div>

                <?php endif; ?>

                <form method="POST" action="profile.php">

                    <div class="form-group">

                        <label for="name">
                            Full name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="100"
                            value="<?= escapeOutput($name) ?>"
                            placeholder="Enter your full name"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="email">
                            Email address
                            <small>· Cannot be changed here</small>
                        </label>

                        <input
                            type="email"
                            id="email"
                            value="<?= escapeOutput($email) ?>"
                            readonly
                        >

                        <p class="field-hint">
                            Your email is connected to your login account.
                        </p>

                    </div>

                    <div class="form-group">

                        <label for="skills">
                            Technical skills
                        </label>

                        <textarea
                            id="skills"
                            name="skills"
                            maxlength="10000"
                            placeholder="Example: HTML, CSS, JavaScript, PHP, MySQL, Java"
                        ><?= escapeOutput($skills) ?></textarea>

                        <p class="field-hint">
                            Separate each skill with a comma.
                            Example: HTML, CSS, JavaScript, PHP
                        </p>

                        <div
                            class="skills-preview"
                            id="skillsPreview"
                        ></div>

                    </div>

                    <div class="form-footer">

                        <p>
                            Your changes will be saved to your
                            student profile.
                        </p>

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            <span>&#10003;</span>
                            Save Changes
                        </button>

                    </div>

                </form>

            </div>

        </section>

        <footer class="footer">

            <span>
                &copy; <?= date("Y") ?> InternOrbit
            </span>

            <span>
                Build your future, one opportunity at a time.
            </span>

        </footer>

    </main>

</div>

<script>
    const skillsInput = document.getElementById("skills");
    const skillsPreview = document.getElementById("skillsPreview");

    function renderSkills() {
        skillsPreview.replaceChildren();

        const skills = skillsInput.value
            .split(",")
            .map(skill => skill.trim())
            .filter(skill => skill.length > 0);

        const uniqueSkills = [...new Set(skills)];

        if (uniqueSkills.length === 0) {
            const emptyMessage = document.createElement("span");

            emptyMessage.className = "skills-empty";
            emptyMessage.textContent =
                "Your skills preview will appear here.";

            skillsPreview.appendChild(emptyMessage);
            return;
        }

        uniqueSkills.forEach(skill => {
            const tag = document.createElement("span");

            tag.className = "skill-tag";
            tag.textContent = skill;

            skillsPreview.appendChild(tag);
        });
    }

    skillsInput.addEventListener("input", renderSkills);

    renderSkills();
</script>

</body>
</html>