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

$applications = [];
$error = "";
$studentId = null;

$totalApplications = 0;
$underReview = 0;
$shortlisted = 0;
$selected = 0;

$statusCounts = [
    "Applied" => 0,
    "Under Review" => 0,
    "Shortlisted" => 0,
    "Interview" => 0,
    "Selected" => 0,
    "Rejected" => 0
];

$filter = trim($_GET["status"] ?? "");

$allowedFilters = [
    "Applied",
    "Under Review",
    "Shortlisted",
    "Interview",
    "Selected",
    "Rejected"
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = "";
}

try {
    // Find the student profile ID.
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
    } else {
        $studentId = (int) $studentId;

        // Fetch all applications for this student.
        $stmt = $pdo->prepare(
            "SELECT
                a.id AS application_id,
                a.internship_id,
                a.status,
                a.message,
                a.applied_at,
                i.title AS internship_title,
                i.description AS internship_description,
                i.skills AS internship_skills,
                i.location AS internship_location,
                c.company_name
             FROM applications a
             INNER JOIN internships i
                ON a.internship_id = i.id
             LEFT JOIN companies c
                ON i.company_id = c.id
             WHERE a.student_id = :student_id
             ORDER BY a.applied_at DESC, a.id DESC"
        );

        $stmt->execute([
            ":student_id" => $studentId
        ]);

        $allApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary statistics.
        foreach ($allApplications as $application) {
            $status = trim(
                (string) ($application["status"] ?? "Applied")
            );

            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }

            $statusCounts[$status]++;

            if ($status === "Under Review") {
                $underReview++;
            }

            if ($status === "Shortlisted") {
                $shortlisted++;
            }

            if ($status === "Selected") {
                $selected++;
            }
        }

        $totalApplications = count($allApplications);

        // Apply the selected status filter.
        if ($filter !== "") {
            $applications = array_values(
                array_filter(
                    $allApplications,
                    function ($application) use ($filter) {
                        return (
                            ($application["status"] ?? "Applied")
                            === $filter
                        );
                    }
                )
            );
        } else {
            $applications = $allApplications;
        }
    }

} catch (PDOException $e) {
    error_log(
        "InternOrbit applications page error: " . $e->getMessage()
    );

    $error = "We couldn't load your applications. Please try again later.";
}

// Return a CSS class for each application status.
function getStatusClass($status)
{
    $classes = [
        "Applied" => "status-applied",
        "Under Review" => "status-review",
        "Shortlisted" => "status-shortlisted",
        "Interview" => "status-interview",
        "Selected" => "status-selected",
        "Rejected" => "status-rejected"
    ];

    return $classes[$status] ?? "status-default";
}

// Return an icon for each status.
function getStatusIcon($status)
{
    $icons = [
        "Applied" => "&#9679;",
        "Under Review" => "&#8987;",
        "Shortlisted" => "&#9733;",
        "Interview" => "&#128197;",
        "Selected" => "&#10003;",
        "Rejected" => "&#10005;"
    ];

    return $icons[$status] ?? "&#9679;";
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

    <title>My Applications | InternOrbit</title>

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
        select {
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

        /* Statistics */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            min-width: 0;
            padding: 20px;

            border: 1px solid var(--border);
            border-radius: 16px;

            background: var(--surface);

            transition:
                transform 0.2s ease,
                border-color 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(192, 132, 252, 0.25);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 18px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border: 1px solid rgba(192, 132, 252, 0.17);
            border-radius: 12px;

            background: rgba(168, 85, 247, 0.11);
            color: var(--purple-light);
            font-size: 17px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .stat-description {
            margin-top: 7px;
            color: #9286a5;
            font-size: 10px;
            line-height: 1.6;
        }

        /* Applications panel */

        .applications-panel {
            padding: 24px;

            border: 1px solid var(--border);
            border-radius: 18px;

            background: var(--surface);

            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;

            margin-bottom: 24px;
        }

        .panel-heading h2 {
            margin-bottom: 7px;
            font-size: 18px;
        }

        .panel-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .filter-form select {
            min-height: 40px;
            max-width: 190px;
            padding: 9px 30px 9px 12px;

            border: 1px solid var(--border);
            border-radius: 10px;
            outline: none;

            background: #1a102a;
            color: var(--white);

            font-size: 11px;
        }

        .filter-form select:focus {
            border-color: var(--purple-light);
        }

        .filter-button {
            min-height: 40px;
            padding: 9px 14px;

            border: 1px solid rgba(192, 132, 252, 0.20);
            border-radius: 10px;

            background: rgba(168, 85, 247, 0.13);
            color: var(--purple-light);

            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .filter-button:hover {
            background: rgba(168, 85, 247, 0.22);
        }

        /* Application cards */

        .application-list {
            display: grid;
            gap: 14px;
        }

        .application-card {
            min-width: 0;
            padding: 20px;

            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;

            background: rgba(255, 255, 255, 0.025);

            transition:
                border-color 0.2s ease,
                background 0.2s ease;
        }

        .application-card:hover {
            border-color: rgba(192, 132, 252, 0.22);
            background: rgba(168, 85, 247, 0.035);
        }

        .application-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 17px;
        }

        .internship-info {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 0;
        }

        .internship-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;

            display: grid;
            place-items: center;

            border: 1px solid rgba(192, 132, 252, 0.20);
            border-radius: 14px;

            background: linear-gradient(
                135deg,
                rgba(168, 85, 247, 0.18),
                rgba(124, 58, 237, 0.08)
            );

            color: var(--purple-light);
            font-size: 21px;
        }

        .internship-text {
            min-width: 0;
        }

        .internship-text h3 {
            margin: 2px 0 7px;
            font-size: 15px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .company-name {
            color: var(--purple-light);
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            flex-shrink: 0;
            padding: 8px 11px;

            border: 1px solid transparent;
            border-radius: 30px;

            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-applied {
            border-color: rgba(96, 165, 250, 0.20);
            background: rgba(59, 130, 246, 0.10);
            color: #93c5fd;
        }

        .status-review {
            border-color: rgba(250, 204, 21, 0.20);
            background: rgba(234, 179, 8, 0.09);
            color: #fde68a;
        }

        .status-shortlisted {
            border-color: rgba(192, 132, 252, 0.23);
            background: rgba(168, 85, 247, 0.11);
            color: #d8b4fe;
        }

        .status-interview {
            border-color: rgba(45, 212, 191, 0.22);
            background: rgba(20, 184, 166, 0.09);
            color: #99f6e4;
        }

        .status-selected {
            border-color: rgba(74, 222, 128, 0.23);
            background: rgba(34, 197, 94, 0.09);
            color: #86efac;
        }

        .status-rejected {
            border-color: rgba(248, 113, 113, 0.22);
            background: rgba(239, 68, 68, 0.08);
            color: #fca5a5;
        }

        .status-default {
            border-color: var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
        }

        .application-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 22px;

            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 7px;

            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .meta-icon {
            color: var(--purple-light);
        }

        .application-message {
            margin-top: 14px;
            padding: 13px 14px;

            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 10px;

            background: rgba(255, 255, 255, 0.025);
        }

        .application-message strong {
            display: block;
            margin-bottom: 7px;

            color: #d8c8ea;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .application-message p {
            color: #b9b0c8;
            font-size: 11px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        /* Empty state */

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 70px;
            height: 70px;

            display: grid;
            place-items: center;

            margin: 0 auto 20px;

            border: 1px solid rgba(192, 132, 252, 0.18);
            border-radius: 22px;

            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);

            font-size: 29px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }

        .empty-state p {
            max-width: 410px;
            margin: 0 auto 22px;

            color: var(--muted);
            font-size: 12px;
            line-height: 1.9;
        }

        .primary-link {
            min-height: 43px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 12px 17px;

            border-radius: 10px;

            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );

            color: var(--white);

            font-size: 11px;
            font-weight: 800;

            box-shadow: 0 8px 22px rgba(124, 58, 237, 0.18);

            transition: transform 0.2s ease;
        }

        .primary-link:hover {
            transform: translateY(-2px);
        }

        .message-error {
            padding: 14px 16px;
            margin-bottom: 20px;

            border: 1px solid rgba(248, 113, 113, 0.25);
            border-radius: 11px;

            background: rgba(239, 68, 68, 0.08);
            color: #fca5a5;

            font-size: 12px;
            line-height: 1.8;
        }

        .footer {
            padding-top: 35px;
            color: #817791;
            font-size: 11px;
            text-align: center;
        }

        /* Responsive layout */

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1000px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .applications-panel {
                padding: 20px;
            }
        }

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
            }

            .main-area {
                padding: 22px 16px 30px;
            }

            .topbar {
                margin-bottom: 24px;
            }

            .application-top {
                flex-direction: column;
                gap: 14px;
            }

            .status-badge {
                align-self: flex-start;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .page-heading h1 {
                font-size: 28px;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }

            .panel-header {
                align-items: flex-start;
            }

            .filter-form {
                width: 100%;
            }

            .filter-form select {
                flex: 1;
                max-width: none;
                min-width: 0;
            }

            .application-card {
                padding: 15px;
            }

            .internship-info {
                gap: 11px;
            }

            .internship-icon {
                width: 42px;
                height: 42px;
            }

            .internship-text h3 {
                font-size: 14px;
            }

            .application-meta {
                gap: 8px 14px;
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

            <!-- Dashboard SVG icon -->

            <a href="dashboard.php" class="nav-link">

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        viewBox="0 0 24 24"
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

            <a href="applications.php" class="nav-link active">

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
                <span>My Applications</span>
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

        <!-- Page heading -->

        <section class="page-heading">

            <div class="page-tag">
                &#10024; Your career journey
            </div>

            <h1>
                My <span>Applications</span>
            </h1>

            <p>
                Hi <?= escapeOutput($firstName) ?>, track your internship
                applications and stay updated on your progress.
            </p>

        </section>

        <?php if ($error !== ""): ?>

            <div class="message-error" role="alert">
                <?= escapeOutput($error) ?>
            </div>

        <?php else: ?>

            <!-- Statistics -->

            <section class="stats-grid">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">
                            Total applications
                        </div>

                        <div class="stat-icon">
                            &#128203;
                        </div>

                    </div>

                    <div class="stat-value">
                        <?= $totalApplications ?>
                    </div>

                    <div class="stat-description">
                        All your submitted applications
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">
                            Under review
                        </div>

                        <div class="stat-icon">
                            &#8987;
                        </div>

                    </div>

                    <div class="stat-value">
                        <?= $underReview ?>
                    </div>

                    <div class="stat-description">
                        Waiting for company evaluation
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">
                            Shortlisted
                        </div>

                        <div class="stat-icon">
                            &#9733;
                        </div>

                    </div>

                    <div class="stat-value">
                        <?= $shortlisted ?>
                    </div>

                    <div class="stat-description">
                        Applications progressing further
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-label">
                            Selected
                        </div>

                        <div class="stat-icon">
                            &#10003;
                        </div>

                    </div>

                    <div class="stat-value">
                        <?= $selected ?>
                    </div>

                    <div class="stat-description">
                        Successful internship applications
                    </div>

                </div>

            </section>

            <!-- Application list -->

            <section class="applications-panel">

                <div class="panel-header">

                    <div class="panel-heading">

                        <h2>Application history</h2>

                        <p>
                            View the status and details of your applications.
                        </p>

                    </div>

                    <form
                        method="GET"
                        action="applications.php"
                        class="filter-form"
                    >

                        <select
                            name="status"
                            aria-label="Filter applications by status"
                        >

                            <option value="">
                                All statuses
                            </option>

                            <?php foreach ($allowedFilters as $status): ?>

                                <option
                                    value="<?= escapeOutput($status) ?>"
                                    <?= $filter === $status ? "selected" : "" ?>
                                >
                                    <?= escapeOutput($status) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button
                            type="submit"
                            class="filter-button"
                        >
                            Filter
                        </button>

                    </form>

                </div>

                <?php if (empty($applications)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            &#128203;
                        </div>

                        <?php if ($totalApplications === 0): ?>

                            <h3>No applications yet</h3>

                            <p>
                                Your internship journey starts here.
                                Explore available opportunities and submit
                                your first application.
                            </p>

                        <?php else: ?>

                            <h3>No matching applications</h3>

                            <p>
                                You don't have any applications with this
                                status. Try selecting another status or view
                                all your applications.
                            </p>

                        <?php endif; ?>

                        <?php if ($filter !== ""): ?>

                            <a
                                href="applications.php"
                                class="primary-link"
                            >
                                View all applications &#8594;
                            </a>

                        <?php else: ?>

                            <a
                                href="internships.php"
                                class="primary-link"
                            >
                                <span>&#128188;</span>
                                Explore internships
                                <span>&#8594;</span>
                            </a>

                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <div class="application-list">

                        <?php foreach ($applications as $application): ?>

                            <?php
                            $status = trim(
                                (string) (
                                    $application["status"] ?? "Applied"
                                )
                            );

                            $appliedDate = "Date unavailable";

                            if (!empty($application["applied_at"])) {
                                $timestamp = strtotime(
                                    $application["applied_at"]
                                );

                                if ($timestamp !== false) {
                                    $appliedDate = date(
                                        "d M Y",
                                        $timestamp
                                    );
                                }
                            }

                            $companyName = trim(
                                (string) (
                                    $application["company_name"] ?? ""
                                )
                            );

                            if ($companyName === "") {
                                $companyName = "Company information unavailable";
                            }

                            $location = trim(
                                (string) (
                                    $application["internship_location"] ?? ""
                                )
                            );

                            if ($location === "") {
                                $location = "Location not specified";
                            }
                            ?>

                            <article class="application-card">

                                <div class="application-top">

                                    <div class="internship-info">

                                        <div class="internship-icon">
                                            &#128188;
                                        </div>

                                        <div class="internship-text">

                                            <h3>
                                                <?= escapeOutput(
                                                    $application["internship_title"]
                                                ) ?>
                                            </h3>

                                            <div class="company-name">
                                                <?= escapeOutput($companyName) ?>
                                            </div>

                                        </div>

                                    </div>

                                    <div class="status-badge <?= escapeOutput(
                                        getStatusClass($status)
                                    ) ?>">

                                        <span>
                                            <?= getStatusIcon($status) ?>
                                        </span>

                                        <span>
                                            <?= escapeOutput($status) ?>
                                        </span>

                                    </div>

                                </div>

                                <div class="application-meta">

                                    <div class="meta-item">

                                        <span class="meta-icon">
                                            &#128197;
                                        </span>

                                        <span>
                                            Applied on
                                            <?= escapeOutput($appliedDate) ?>
                                        </span>

                                    </div>

                                    <div class="meta-item">

                                        <span class="meta-icon">
                                            &#128205;
                                        </span>

                                        <span>
                                            <?= escapeOutput($location) ?>
                                        </span>

                                    </div>

                                    <div class="meta-item">

                                        <span class="meta-icon">
                                            &#35;
                                        </span>

                                        <span>
                                            Application #<?= (int) $application["application_id"] ?>
                                        </span>

                                    </div>

                                </div>

                                <?php
                                $applicationMessage = trim(
                                    (string) (
                                        $application["message"] ?? ""
                                    )
                                );
                                ?>

                                <?php if ($applicationMessage !== ""): ?>

                                    <div class="application-message">

                                        <strong>
                                            Your application message
                                        </strong>

                                        <p>
                                            <?= nl2br(
                                                escapeOutput($applicationMessage)
                                            ) ?>
                                        </p>

                                    </div>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Discover your next opportunity.
        </footer>

    </main>

</div>

</body>
</html>