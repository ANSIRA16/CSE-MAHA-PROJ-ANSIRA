<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Company"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];

$companyId = 0;
$companyName = $_SESSION["name"] ?? "Company";

$totalInternships = 0;
$totalApplications = 0;
$pendingApplications = 0;
$shortlistedApplications = 0;

$recentInternships = [];
$recentApplications = [];

$dashboardError = "";

function escapeOutput($value)
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatDate($date)
{
    if (empty($date)) {
        return "Date unavailable";
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date("d M Y", $timestamp)
        : "Date unavailable";
}

function statusClass($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === "selected") {
        return "status-selected";
    }

    if ($status === "rejected") {
        return "status-rejected";
    }

    if (
        in_array(
            $status,
            ["under review", "shortlisted", "interview"],
            true
        )
    ) {
        return "status-review";
    }

    return "status-applied";
}

try {
    // Find the company profile for the logged-in account.
    $stmt = $pdo->prepare(
        "SELECT id, company_name
         FROM companies
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        ":user_id" => $userId
    ]);

    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        $dashboardError =
            "Your company profile was not found. Please contact support.";
    } else {
        $companyId = (int) $company["id"];
        $companyName = $company["company_name"] ?: $companyName;

        // Count internships posted by this company.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM internships
             WHERE company_id = :company_id"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $totalInternships = (int) $stmt->fetchColumn();

        // Count applications for this company's internships.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM applications a
             INNER JOIN internships i
                ON i.id = a.internship_id
             WHERE i.company_id = :company_id"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $totalApplications = (int) $stmt->fetchColumn();

        // Count applications awaiting a final decision.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM applications a
             INNER JOIN internships i
                ON i.id = a.internship_id
             WHERE i.company_id = :company_id
             AND a.status IN (
                 'Applied',
                 'Under Review',
                 'Shortlisted',
                 'Interview'
             )"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $pendingApplications = (int) $stmt->fetchColumn();

        // Count shortlisted applicants.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM applications a
             INNER JOIN internships i
                ON i.id = a.internship_id
             WHERE i.company_id = :company_id
             AND a.status = 'Shortlisted'"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $shortlistedApplications = (int) $stmt->fetchColumn();

        // Recent internships.
        $stmt = $pdo->prepare(
            "SELECT id, title, location, created_at
             FROM internships
             WHERE company_id = :company_id
             ORDER BY id DESC
             LIMIT 5"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $recentInternships = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        // Recent applications submitted for this company's internships.
        $stmt = $pdo->prepare(
            "SELECT
                a.id,
                a.status,
                a.applied_at,
                i.title,
                s.user_id AS student_user_id,
                u.name AS student_name
             FROM applications a
             INNER JOIN internships i
                ON i.id = a.internship_id
             LEFT JOIN students s
                ON s.id = a.student_id
             LEFT JOIN users u
                ON u.id = s.user_id
             WHERE i.company_id = :company_id
             ORDER BY a.id DESC
             LIMIT 5"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $recentApplications = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
} catch (PDOException $e) {
    error_log(
        "InternOrbit company dashboard error: " . $e->getMessage()
    );

    $dashboardError =
        "Some dashboard information could not be loaded. Please check your database configuration.";
}

$nameParts = preg_split('/\s+/', trim($companyName));

$firstName = !empty($nameParts[0])
    ? $nameParts[0]
    : "Company";

$initial = strtoupper(substr(trim($companyName), 0, 1));

if ($initial === "") {
    $initial = "C";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Company Dashboard | InternOrbit</title>

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

        .app-layout {
            min-height: 100vh;
            display: flex;
        }

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
            display: grid;
            place-items: center;
        }

        .nav-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
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
            background: rgba(168, 85, 247, 0.18);
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

        .welcome-banner {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            padding: 34px;
            margin-bottom: 29px;
            border: 1px solid rgba(192, 132, 252, 0.20);
            border-radius: 22px;
            background:
                radial-gradient(
                    circle at 85% 20%,
                    rgba(192, 132, 252, 0.20),
                    transparent 35%
                ),
                linear-gradient(
                    115deg,
                    rgba(124, 58, 237, 0.22),
                    rgba(255, 255, 255, 0.035)
                );
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-tag {
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

        .welcome-content h1 {
            margin-bottom: 11px;
            font-size: clamp(25px, 3vw, 35px);
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .welcome-content h1 span {
            color: var(--purple-light);
        }

        .welcome-content p {
            max-width: 560px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .welcome-art {
            width: 115px;
            height: 115px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 26px;
            background: rgba(255, 255, 255, 0.04);
            transform: rotate(5deg);
        }

        .welcome-art svg {
            width: 72px;
            height: 72px;
            stroke: var(--purple-light);
            fill: none;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 17px;
        }

        .section-heading h2 {
            font-size: 18px;
            letter-spacing: -0.3px;
        }

        .section-heading p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .action-card {
            min-width: 0;
            min-height: 145px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            gap: 17px;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: var(--surface);
            transition: 0.2s ease;
        }

        .action-card:hover {
            transform: translateY(-3px);
            border-color: rgba(192, 132, 252, 0.35);
            background: rgba(168, 85, 247, 0.075);
        }

        .action-icon {
            width: 41px;
            height: 41px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 12px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
        }

        .action-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .action-card h3 {
            margin-bottom: 5px;
            font-size: 13px;
        }

        .action-card p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 31px;
        }

        .stat-card {
            min-width: 0;
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: var(--surface);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 19px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
        }

        .stat-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .stat-value {
            margin-bottom: 6px;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .stat-note {
            color: #9589a9;
            font-size: 11px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 19px;
        }

        .content-panel {
            min-width: 0;
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 21px;
        }

        .panel-heading h2 {
            font-size: 15px;
        }

        .panel-heading p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

        .text-link {
            flex-shrink: 0;
            color: var(--purple-light);
            font-size: 12px;
            font-weight: 700;
        }

        .internship-item,
        .application-item {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .internship-item:first-child,
        .application-item:first-child {
            padding-top: 0;
        }

        .internship-item:last-child,
        .application-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .item-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.19);
            border-radius: 12px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
        }

        .item-icon svg {
            width: 19px;
            height: 19px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .item-info {
            min-width: 0;
            flex: 1;
        }

        .item-info h3 {
            overflow-wrap: anywhere;
            margin-bottom: 6px;
            font-size: 13px;
            line-height: 1.5;
        }

        .item-info p {
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .meta-chip {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.055);
            color: #cfc4df;
            font-size: 10px;
        }

        .status {
            flex-shrink: 0;
            display: inline-block;
            padding: 6px 9px;
            border: 1px solid transparent;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-applied {
            border-color: rgba(251, 191, 36, 0.17);
            background: rgba(251, 191, 36, 0.09);
            color: #fcd34d;
        }

        .status-selected {
            border-color: rgba(74, 222, 128, 0.17);
            background: rgba(34, 197, 94, 0.09);
            color: var(--green);
        }

        .status-rejected {
            border-color: rgba(248, 113, 113, 0.17);
            background: rgba(239, 68, 68, 0.09);
            color: var(--red);
        }

        .status-review {
            border-color: rgba(192, 132, 252, 0.20);
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
        }

        .empty-state {
            padding: 26px 10px;
            text-align: center;
        }

        .empty-icon {
            width: 47px;
            height: 47px;
            display: grid;
            place-items: center;
            margin: 0 auto 13px;
            border: 1px solid rgba(192, 132, 252, 0.20);
            border-radius: 15px;
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
        }

        .empty-icon svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .empty-state h3 {
            margin-bottom: 7px;
            font-size: 13px;
        }

        .empty-state p {
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .primary-link {
            min-height: 39px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
        }

        .dashboard-alert {
            padding: 12px 15px;
            margin-bottom: 22px;
            border: 1px solid rgba(251, 191, 36, 0.20);
            border-radius: 11px;
            background: rgba(251, 191, 36, 0.07);
            color: #fcd34d;
            font-size: 12px;
            line-height: 1.7;
        }

        .footer {
            padding-top: 29px;
            color: #817791;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 1100px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions,
            .stats-grid {
                gap: 12px;
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

            .main-area {
                padding: 22px 16px 30px;
            }

            .welcome-banner {
                padding: 25px;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .welcome-banner {
                padding: 22px 19px;
            }

            .welcome-art {
                display: none;
            }

            .quick-actions,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-card {
                min-height: auto;
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                gap: 14px;
            }

            .action-card > div:last-child {
                flex: 1;
            }

            .content-panel {
                padding: 17px;
            }

            .status {
                padding: 5px 7px;
                font-size: 9px;
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

    <aside class="sidebar">

        <a href="dashboard.php" class="brand">

            <div class="brand-logo">
                <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="24" cy="24" r="15" stroke="white" stroke-width="2.2"/>
                    <ellipse cx="24" cy="24" rx="21" ry="8.5" transform="rotate(-35 24 24)" stroke="white" stroke-width="2.2"/>
                    <circle cx="36" cy="10" r="4" fill="#FDE68A"/>
                    <circle cx="24" cy="24" r="4" fill="white"/>
                </svg>
            </div>

            <div class="brand-name">
                Intern<span>Orbit</span>
            </div>

        </a>

        <div class="nav-label">Company workspace</div>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="nav-link active">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                    </svg>
                </span>
                <span>Dashboard</span>
            </a>

            <a href="add_internship.php" class="nav-link">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 8v8M8 12h8"/>
                    </svg>
                </span>
                <span>Post Internship</span>
            </a>

            <a href="internships.php" class="nav-link">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="7" width="18" height="13" rx="2"/>
                        <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>
                    </svg>
                </span>
                <span>My Internships</span>
            </a>

            <a href="applicants.php" class="nav-link">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="10" cy="7" r="4"/>
                        <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <span>Applicants</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-profile">

                <div class="avatar">
                    <?= escapeOutput($initial) ?>
                </div>

                <div class="profile-text">

                    <div class="profile-name">
                        <?= escapeOutput($companyName) ?>
                    </div>

                    <div class="profile-role">
                        Company account
                    </div>

                </div>

            </div>

        </div>

    </aside>

    <main class="main-area">

        <header class="topbar">

            <div class="breadcrumb">
                Workspace / <span>Dashboard</span>
            </div>

            <div class="topbar-right">

                <div class="topbar-date">
                    <?= date("D, d M Y") ?>
                </div>

                <div class="top-avatar">
                    <?= escapeOutput($initial) ?>
                </div>

            </div>

        </header>

        <?php if ($dashboardError !== ""): ?>

            <div class="dashboard-alert">
                <?= escapeOutput($dashboardError) ?>
            </div>

        <?php endif; ?>

        <section class="welcome-banner">

            <div class="welcome-content">

                <div class="welcome-tag">
                    <span>&#10024;</span>
                    Company workspace
                </div>

                <h1>
                    Welcome back,
                    <span><?= escapeOutput($firstName) ?>!</span>
                </h1>

                <p>
                    Manage your internship opportunities, discover
                    promising student talent, and keep track of
                    applications from one place.
                </p>

            </div>

            <div class="welcome-art">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="7" width="18" height="13" rx="2"/>
                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>
                </svg>
            </div>

        </section>

        <section>

            <div class="section-heading">
                <div>
                    <h2>Quick actions</h2>
                    <p>Manage your recruitment activities.</p>
                </div>
            </div>

            <div class="quick-actions">

                <a href="add_internship.php" class="action-card">

                    <div class="action-icon">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 8v8M8 12h8"/>
                        </svg>
                    </div>

                    <div>
                        <h3>Post an internship</h3>
                        <p>Create a new opportunity for students.</p>
                    </div>

                </a>

                <a href="internships.php" class="action-card">

                    <div class="action-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="7" width="18" height="13" rx="2"/>
                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </div>

                    <div>
                        <h3>Manage internships</h3>
                        <p>Review the opportunities you have posted.</p>
                    </div>

                </a>

                <a href="applicants.php" class="action-card">

                    <div class="action-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="10" cy="7" r="4"/>
                            <path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>

                    <div>
                        <h3>Review applicants</h3>
                        <p>View students who applied to your internships.</p>
                    </div>

                </a>

            </div>

        </section>

        <section>

            <div class="section-heading">
                <div>
                    <h2>Recruitment overview</h2>
                    <p>A summary of your company's activity.</p>
                </div>
            </div>

            <div class="stats-grid">

                <article class="stat-card">

                    <div class="stat-top">
                        <div class="stat-label">My internships</div>

                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="7" width="18" height="13" rx="2"/>
                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalInternships) ?>
                    </div>

                    <div class="stat-note">Opportunities posted by your company</div>

                </article>

                <article class="stat-card">

                    <div class="stat-top">
                        <div class="stat-label">Total applications</div>

                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M8 3h8l4 4v14H4V3h4z"/>
                                <path d="M14 3v5h6M8 13h8M8 17h8"/>
                            </svg>
                        </div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalApplications) ?>
                    </div>

                    <div class="stat-note">Applications received</div>

                </article>

                <article class="stat-card">

                    <div class="stat-top">
                        <div class="stat-label">Awaiting decision</div>

                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 7v5l3 2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($pendingApplications) ?>
                    </div>

                    <div class="stat-note">Applications to review</div>

                </article>

            </div>

        </section>

        <section class="content-grid">

            <div class="content-panel">

                <div class="panel-heading">

                    <div>
                        <h2>Recently posted internships</h2>
                        <p>Your latest opportunities.</p>
                    </div>

                    <a href="internships.php" class="text-link">
                        View all &#8594;
                    </a>

                </div>

                <?php if (!empty($recentInternships)): ?>

                    <?php foreach ($recentInternships as $internship): ?>

                        <article class="internship-item">

                            <div class="item-icon">
                                <svg viewBox="0 0 24 24">
                                    <rect x="3" y="7" width="18" height="13" rx="2"/>
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </div>

                            <div class="item-info">

                                <h3>
                                    <?= escapeOutput($internship["title"]) ?>
                                </h3>

                                <p>
                                    <?= escapeOutput(
                                        $internship["location"] ?: "Location not specified"
                                    ) ?>
                                </p>

                                <span class="meta-chip">
                                    Posted
                                    <?= escapeOutput(
                                        formatDate($internship["created_at"] ?? "")
                                    ) ?>
                                </span>

                            </div>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="7" width="18" height="13" rx="2"/>
                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </div>

                        <h3>No internships posted yet</h3>

                        <p>
                            Publish your first internship to start
                            receiving student applications.
                        </p>

                        <a href="add_internship.php" class="primary-link">
                            Post an internship
                        </a>

                    </div>

                <?php endif; ?>

            </div>

            <div class="content-panel">

                <div class="panel-heading">

                    <div>
                        <h2>Recent applications</h2>
                        <p>Latest student activity.</p>
                    </div>

                    <a href="applicants.php" class="text-link">
                        View all &#8594;
                    </a>

                </div>

                <?php if (!empty($recentApplications)): ?>

                    <?php foreach ($recentApplications as $application): ?>

                        <article class="application-item">

                            <div class="item-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="10" cy="7" r="4"/>
                                    <path d="M16 11l2 2 4-4"/>
                                </svg>
                            </div>

                            <div class="item-info">

                                <h3>
                                    <?= escapeOutput(
                                        $application["student_name"]
                                            ?: "Student"
                                    ) ?>
                                </h3>

                                <p>
                                    <?= escapeOutput(
                                        $application["title"]
                                            ?: "Internship"
                                    ) ?>
                                </p>

                                <span class="meta-chip">
                                    <?= escapeOutput(
                                        formatDate($application["applied_at"] ?? "")
                                    ) ?>
                                </span>

                            </div>

                            <span class="status <?= escapeOutput(
                                statusClass($application["status"] ?? "Applied")
                            ) ?>">
                                <?= escapeOutput(
                                    $application["status"] ?? "Applied"
                                ) ?>
                            </span>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="10" cy="7" r="4"/>
                            </svg>
                        </div>

                        <h3>No applications yet</h3>

                        <p>
                            Student applications will appear here
                            after someone applies to your internships.
                        </p>

                        <a href="internships.php" class="primary-link">
                            View my internships
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Helping companies connect with emerging talent.
        </footer>

    </main>

</div>

</body>
</html>