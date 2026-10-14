<?php

session_start();

require_once __DIR__ . "/../config/database.php";

function escapeOutput($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Allow students only.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Student"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];
$studentName = $_SESSION["name"] ?? "Student";
$firstName = preg_split('/\s+/', trim($studentName))[0] ?? "Student";

// Get the internship ID from the URL.
$internshipId = filter_input(
    INPUT_GET,
    "internship_id",
    FILTER_VALIDATE_INT
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $internshipId = filter_input(
        INPUT_POST,
        "internship_id",
        FILTER_VALIDATE_INT
    );
}

$internship = null;
$studentId = null;
$alreadyApplied = false;
$error = "";
$success = "";
$message = "";

$csrfToken = $_SESSION["csrf_token"] ?? bin2hex(random_bytes(32));

$_SESSION["csrf_token"] = $csrfToken;

try {
    // Find the student's profile ID.
    $stmt = $pdo->prepare(
        "SELECT id
         FROM students
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        ":user_id" => $userId
    ]);

    $studentId = $stmt->fetchColumn();

    if (!$studentId) {
        $error = "Your student profile was not found. Please complete your profile first.";
    }

    // Load the selected internship and company details.
    if ($internshipId && $internshipId > 0) {
        $stmt = $pdo->prepare(
            "SELECT
                i.id,
                i.company_id,
                i.title,
                i.description,
                i.skills,
                i.location,
                i.created_at,
                c.company_name
             FROM internships i
             LEFT JOIN companies c
                ON i.company_id = c.id
             WHERE i.id = :internship_id
             LIMIT 1"
        );

        $stmt->execute([
            ":internship_id" => $internshipId
        ]);

        $internship = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$internship) {
            $error = "This internship could not be found. It may have been removed.";
        }
    } else {
        $error = "Please select a valid internship before applying.";
    }

    // Check whether an application already exists.
    if ($studentId && $internship) {
        $stmt = $pdo->prepare(
            "SELECT id, status
             FROM applications
             WHERE student_id = :student_id
               AND internship_id = :internship_id
             LIMIT 1"
        );

        $stmt->execute([
            ":student_id" => (int) $studentId,
            ":internship_id" => (int) $internship["id"]
        ]);

        $existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingApplication) {
            $alreadyApplied = true;
        }
    }

    // Process the application form.
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        $error === "" &&
        $studentId &&
        $internship
    ) {
        $submittedToken = $_POST["csrf_token"] ?? "";

        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            $error = "Your session has expired. Please refresh the page and try again.";
        } elseif ($alreadyApplied) {
            $error = "You have already applied for this internship.";
        } else {
            $message = trim($_POST["message"] ?? "");

            if (strlen($message) > 5000) {
                $error = "Your application message must be no longer than 5000 characters.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Use the student profile ID, not the users table ID.
                    $stmt = $pdo->prepare(
                        "INSERT INTO applications
                            (
                                internship_id,
                                student_id,
                                status,
                                message
                            )
                         VALUES
                            (
                                :internship_id,
                                :student_id,
                                'Applied',
                                :message
                            )"
                    );

                    $stmt->execute([
                        ":internship_id" => (int) $internship["id"],
                        ":student_id" => (int) $studentId,
                        ":message" => $message !== "" ? $message : null
                    ]);

                    $pdo->commit();

                    // Prevent duplicate submissions when refreshing.
                    header(
                        "Location: apply.php?internship_id=" .
                        (int) $internship["id"] .
                        "&applied=1"
                    );

                    exit();

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // Handle duplicate applications safely.
                    if ($e->getCode() === "23000") {
                        $alreadyApplied = true;
                        $error = "You have already applied for this internship.";
                    } else {
                        error_log(
                            "InternOrbit application error: " .
                            $e->getMessage()
                        );

                        $error = "We couldn't submit your application. Please try again.";
                    }
                }
            }
        }
    }

    // Show success only after redirecting from a successful submission.
    if (
        isset($_GET["applied"]) &&
        $_GET["applied"] === "1" &&
        $internship
    ) {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM applications
             WHERE student_id = :student_id
               AND internship_id = :internship_id
             LIMIT 1"
        );

        $stmt->execute([
            ":student_id" => (int) $studentId,
            ":internship_id" => (int) $internship["id"]
        ]);

        if ($stmt->fetchColumn()) {
            $success = "Your application has been submitted successfully!";
            $alreadyApplied = true;
        }
    }

} catch (PDOException $e) {
    error_log(
        "InternOrbit apply page error: " . $e->getMessage()
    );

    $error = "We couldn't load this page. Please try again later.";
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

    <title>Apply for Internship | InternOrbit</title>

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
        textarea {
            font: inherit;
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

            transition: 0.2s ease;
        }

        .nav-icon {
            width: 23px;
            flex-shrink: 0;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            text-align: center;
            font-size: 17px;
        }

        .nav-icon svg {
            display: block;
            width: 20px;
            height: 20px;
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

            background: rgba(168, 85, 247, 0.15);
            color: var(--purple-light);
            font-weight: 800;
        }

        .profile-text {
            min-width: 0;
        }

        .profile-name {
            overflow: hidden;
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

        /* Main content */

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
            margin-bottom: 30px;
        }

        .breadcrumb {
            color: var(--muted);
            font-size: 12px;
        }

        .breadcrumb span {
            color: var(--purple-light);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-date {
            color: var(--muted);
            font-size: 12px;
        }

        .top-avatar {
            width: 41px;
            height: 41px;

            display: grid;
            place-items: center;

            border: 1px solid rgba(192, 132, 252, 0.30);
            border-radius: 50%;

            background: rgba(168, 85, 247, 0.15);
            color: var(--purple-light);
            font-weight: 800;
        }

        /* Page heading */

        .page-heading {
            margin-bottom: 25px;
        }

        .page-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            margin-bottom: 14px;
            padding: 7px 11px;

            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 30px;

            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);

            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .page-heading h1 {
            margin-bottom: 10px;
            font-size: clamp(27px, 3vw, 36px);
            letter-spacing: -1px;
        }

        .page-heading h1 span {
            color: var(--purple-light);
        }

        .page-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* Main layout */

        .application-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.8fr);
            align-items: start;
            gap: 22px;
        }

        .panel {
            min-width: 0;
            padding: 25px;

            border: 1px solid var(--border);
            border-radius: 18px;

            background: var(--surface);

            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .panel-heading {
            margin-bottom: 22px;
        }

        .panel-heading h2 {
            margin-bottom: 8px;
            font-size: 18px;
        }

        .panel-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        /* Internship information */

        .internship-symbol {
            width: 58px;
            height: 58px;

            display: grid;
            place-items: center;

            margin-bottom: 20px;

            border: 1px solid rgba(192, 132, 252, 0.23);
            border-radius: 17px;

            background: linear-gradient(
                135deg,
                rgba(168, 85, 247, 0.22),
                rgba(124, 58, 237, 0.10)
            );

            color: var(--purple-light);
            font-size: 25px;
        }

        .internship-title {
            margin-bottom: 9px;
            font-size: 23px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .company-name {
            margin-bottom: 20px;
            color: var(--purple-light);
            font-size: 13px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;

            padding: 15px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
        }

        .info-icon {
            width: 35px;
            height: 35px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border-radius: 11px;
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
        }

        .info-content {
            min-width: 0;
        }

        .info-label {
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .info-value {
            color: #f2eafa;
            font-size: 12px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 7px;
        }

        .skill-chip {
            padding: 7px 9px;

            border: 1px solid rgba(192, 132, 252, 0.16);
            border-radius: 8px;

            background: rgba(168, 85, 247, 0.08);
            color: #dfc3ff;

            font-size: 10px;
        }

        .internship-description {
            color: #c8bfd5;
            font-size: 12px;
            line-height: 1.9;
            overflow-wrap: anywhere;
        }

        /* Application form */

        .form-group {
            margin-bottom: 19px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;

            color: #eee6f8;
            font-size: 12px;
            font-weight: 700;
        }

        .form-group textarea {
            width: 100%;
            min-height: 175px;
            padding: 14px;

            resize: vertical;

            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 11px;
            outline: none;

            background: rgba(255, 255, 255, 0.045);
            color: var(--white);

            font-size: 12px;
            line-height: 1.8;

            transition: border-color 0.2s ease;
        }

        .form-group textarea::placeholder {
            color: #827990;
        }

        .form-group textarea:focus {
            border-color: var(--purple-light);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.10);
        }

        .field-hint {
            display: flex;
            justify-content: space-between;
            gap: 10px;

            margin-top: 8px;

            color: #9589a9;
            font-size: 10px;
        }

        .submit-button {
            width: 100%;
            min-height: 48px;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            padding: 13px 17px;

            border: none;
            border-radius: 11px;

            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );

            color: var(--white);
            cursor: pointer;

            font-size: 12px;
            font-weight: 800;

            box-shadow: 0 8px 22px rgba(124, 58, 237, 0.18);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(124, 58, 237, 0.28);
        }

        .submit-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-note {
            margin-top: 15px;
            color: #9589a9;
            font-size: 10px;
            line-height: 1.8;
            text-align: center;
        }

        /* Messages */

        .message {
            padding: 14px 16px;
            margin-bottom: 20px;

            border-radius: 11px;

            font-size: 12px;
            line-height: 1.8;
        }

        .message-error {
            border: 1px solid rgba(248, 113, 113, 0.25);
            background: rgba(239, 68, 68, 0.08);
            color: #fca5a5;
        }

        .message-success {
            border: 1px solid rgba(74, 222, 128, 0.22);
            background: rgba(34, 197, 94, 0.08);
            color: var(--green);
        }

        .already-applied {
            padding: 25px 20px;
            border: 1px solid rgba(74, 222, 128, 0.18);
            border-radius: 14px;
            background: rgba(34, 197, 94, 0.05);
            text-align: center;
        }

        .success-icon {
            width: 52px;
            height: 52px;

            display: grid;
            place-items: center;

            margin: 0 auto 15px;

            border-radius: 50%;
            background: rgba(34, 197, 94, 0.12);
            color: var(--green);

            font-size: 23px;
        }

        .already-applied h3 {
            margin-bottom: 9px;
            font-size: 16px;
        }

        .already-applied p {
            margin-bottom: 18px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .primary-link,
        .secondary-link {
            min-height: 41px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 11px 15px;
            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;
        }

        .primary-link {
            background: linear-gradient(110deg, #9333ea, #7c3aed);
            color: var(--white);
        }

        .secondary-link {
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            margin-top: 24px;

            color: var(--muted);
            font-size: 12px;

            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--purple-light);
        }

        .footer {
            padding-top: 35px;
            color: #817791;
            font-size: 11px;
            text-align: center;
        }

        /* Tablet */

        @media (max-width: 1100px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .application-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile navigation */

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
                margin-bottom: 24px;
            }

            .panel {
                padding: 20px;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .page-heading h1 {
                font-size: 28px;
            }

            .internship-title {
                font-size: 20px;
            }

            .panel {
                padding: 17px;
                border-radius: 15px;
            }

            .form-group textarea {
                min-height: 150px;
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

        <div class="nav-label">
            Student workspace
        </div>

        <nav class="sidebar-nav">

            <!-- Updated Dashboard SVG icon -->
            <a href="dashboard.php" class="nav-link">
                <span class="nav-icon" aria-hidden="true">
                    <svg
                        viewBox="0 0 24 24"
                        width="20"
                        height="20"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <rect
                            x="3"
                            y="3"
                            width="7"
                            height="7"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <rect
                            x="14"
                            y="3"
                            width="7"
                            height="7"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <rect
                            x="3"
                            y="14"
                            width="7"
                            height="7"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <rect
                            x="14"
                            y="14"
                            width="7"
                            height="7"
                            rx="1.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />
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

            <a href="profile.php" class="nav-link">
                <span class="nav-icon">&#128100;</span>
                <span>My Profile</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-profile">

                <div class="avatar">
                    <?= escapeOutput(
                        strtoupper(substr($firstName, 0, 1))
                    ) ?>
                </div>

                <div class="profile-text">

                    <div class="profile-name">
                        <?= escapeOutput($studentName) ?>
                    </div>

                    <div class="profile-role">
                        Student account
                    </div>

                </div>

            </div>

        </div>

    </aside>

    <!-- Main content -->

    <main class="main-area">

        <header class="topbar">

            <div class="breadcrumb">
                Workspace /
                <a href="internships.php">Explore Internships</a>
                / <span>Apply</span>
            </div>

            <div class="topbar-right">

                <div class="topbar-date">
                    <?= date("D, d M Y") ?>
                </div>

                <div class="top-avatar">
                    <?= escapeOutput(
                        strtoupper(substr($firstName, 0, 1))
                    ) ?>
                </div>

            </div>

        </header>

        <section class="page-heading">

            <div class="page-tag">
                &#10024; Take the next step
            </div>

            <h1>
                Your next chapter
                <span>starts here.</span>
            </h1>

            <p>
                Hi <?= escapeOutput($firstName) ?>, review the opportunity
                and submit your application when you're ready.
            </p>

        </section>

        <?php if ($error !== ""): ?>

            <div class="message message-error" role="alert">
                <?= escapeOutput($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="message message-success" role="status">
                &#10003;
                <?= escapeOutput($success) ?>
            </div>

        <?php endif; ?>

        <?php if ($internship): ?>

            <div class="application-layout">

                <!-- Internship details -->

                <section class="panel">

                    <div class="panel-heading">
                        <h2>Internship details</h2>

                        <p>
                            Review the opportunity before submitting
                            your application.
                        </p>
                    </div>

                    <div class="internship-symbol">
                        &#128188;
                    </div>

                    <h2 class="internship-title">
                        <?= escapeOutput($internship["title"]) ?>
                    </h2>

                    <div class="company-name">
                        <?= escapeOutput(
                            $internship["company_name"] ??
                            "Company information unavailable"
                        ) ?>
                    </div>

                    <div class="info-row">

                        <div class="info-icon">
                            &#128205;
                        </div>

                        <div class="info-content">

                            <div class="info-label">
                                Location
                            </div>

                            <div class="info-value">
                                <?= escapeOutput(
                                    $internship["location"] ??
                                    "Not specified"
                                ) ?>
                            </div>

                        </div>

                    </div>

                    <div class="info-row">

                        <div class="info-icon">
                            &#128221;
                        </div>

                        <div class="info-content">

                            <div class="info-label">
                                About this internship
                            </div>

                            <div class="internship-description">
                                <?= nl2br(
                                    escapeOutput(
                                        $internship["description"] ??
                                        "No description has been provided."
                                    )
                                ) ?>
                            </div>

                        </div>

                    </div>

                    <div class="info-row">

                        <div class="info-icon">
                            &#10024;
                        </div>

                        <div class="info-content">

                            <div class="info-label">
                                Required skills
                            </div>

                            <?php
                            $skills = trim(
                                (string) ($internship["skills"] ?? "")
                            );

                            $skillList = array_filter(
                                array_map(
                                    "trim",
                                    explode(",", $skills)
                                )
                            );
                            ?>

                            <?php if (!empty($skillList)): ?>

                                <div class="skills-list">

                                    <?php foreach (
                                        array_slice($skillList, 0, 20)
                                        as $skill
                                    ): ?>

                                        <span class="skill-chip">
                                            <?= escapeOutput($skill) ?>
                                        </span>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <div class="info-value">
                                    No specific skills listed.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                    <a
                        href="internships.php"
                        class="back-link"
                    >
                        &#8592; Back to internships
                    </a>

                </section>

                <!-- Application form -->

                <section class="panel">

                    <div class="panel-heading">

                        <h2>Submit your application</h2>

                        <p>
                            Tell the company why you're interested
                            in this opportunity.
                        </p>

                    </div>

                    <?php if ($alreadyApplied): ?>

                        <div class="already-applied">

                            <div class="success-icon">
                                &#10003;
                            </div>

                            <h3>
                                Application already submitted
                            </h3>

                            <p>
                                You've already applied for this internship.
                                You can check the status from your applications page.
                            </p>

                            <a
                                href="applications.php"
                                class="primary-link"
                            >
                                View my applications &#8594;
                            </a>

                        </div>

                    <?php elseif ($studentId): ?>

                        <form
                            method="POST"
                            action="apply.php?internship_id=<?= (int) $internship["id"] ?>"
                            id="applicationForm"
                        >

                            <input
                                type="hidden"
                                name="internship_id"
                                value="<?= (int) $internship["id"] ?>"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escapeOutput($csrfToken) ?>"
                            >

                            <div class="form-group">

                                <label for="message">
                                    Application message
                                    <span style="color: #9589a9; font-weight: 400;">
                                        (Optional)
                                    </span>
                                </label>

                                <textarea
                                    id="message"
                                    name="message"
                                    maxlength="5000"
                                    placeholder="Introduce yourself, explain your interest in this internship, and describe the skills you can contribute..."
                                ><?= escapeOutput($message) ?></textarea>

                                <div class="field-hint">

                                    <span>
                                        Keep your message clear and professional.
                                    </span>

                                    <span id="characterCount">
                                        0 / 5000
                                    </span>

                                </div>

                            </div>

                            <button
                                type="submit"
                                class="submit-button"
                                id="submitButton"
                            >
                                Submit application
                                <span>&#8594;</span>
                            </button>

                            <p class="form-note">
                                By submitting, you confirm that your
                                application information is accurate.
                            </p>

                        </form>

                    <?php endif; ?>

                </section>

            </div>

        <?php else: ?>

            <section class="panel">

                <div class="already-applied">

                    <div class="success-icon">
                        &#128269;
                    </div>

                    <h3>
                        Internship unavailable
                    </h3>

                    <p>
                        We couldn't display this internship.
                        Return to the internship list to explore other opportunities.
                    </p>

                    <a
                        href="internships.php"
                        class="primary-link"
                    >
                        Browse internships &#8594;
                    </a>

                </div>

            </section>

        <?php endif; ?>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Discover your next opportunity.
        </footer>

    </main>

</div>

<script>
    const messageField = document.getElementById("message");
    const characterCount = document.getElementById("characterCount");
    const applicationForm = document.getElementById("applicationForm");
    const submitButton = document.getElementById("submitButton");

    if (messageField && characterCount) {
        function updateCharacterCount() {
            characterCount.textContent =
                messageField.value.length + " / 5000";
        }

        messageField.addEventListener(
            "input",
            updateCharacterCount
        );

        updateCharacterCount();
    }

    if (applicationForm && submitButton) {
        applicationForm.addEventListener("submit", function () {
            submitButton.disabled = true;
            submitButton.innerHTML = "Submitting application...";
        });
    }
</script>

</body>
</html>