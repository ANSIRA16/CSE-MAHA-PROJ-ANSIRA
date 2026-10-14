<?php

session_start();

require_once __DIR__ . "/../config/database.php";

function escapeOutput($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Protect the student dashboard.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Student"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];
$studentName = $_SESSION["name"] ?? "Student";
$studentEmail = $_SESSION["email"] ?? "";

$totalInternships = 0;
$totalApplications = 0;
$pendingApplications = 0;
$approvedApplications = 0;
$recentInternships = [];
$recentApplications = [];
$dashboardError = "";

// Load dashboard statistics and recent activity.
try {
    // Count available internships.
    try {
        $stmt = $pdo->query(
            "SELECT COUNT(*)
             FROM internships
             WHERE status = 'Active'"
        );

        $totalInternships = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("InternOrbit internship count: " . $e->getMessage());
    }

    // Count applications submitted by this student.
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM applications
             WHERE student_id = :student_id"
        );

        $stmt->execute([
            ":student_id" => $userId
        ]);

        $totalApplications = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("InternOrbit application count: " . $e->getMessage());
    }

    // Count applications awaiting a decision.
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM applications
             WHERE student_id = :student_id
             AND status = 'Pending'"
        );

        $stmt->execute([
            ":student_id" => $userId
        ]);

        $pendingApplications = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("InternOrbit pending count: " . $e->getMessage());
    }

    // Count approved applications.
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM applications
             WHERE student_id = :student_id
             AND status = 'Approved'"
        );

        $stmt->execute([
            ":student_id" => $userId
        ]);

        $approvedApplications = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("InternOrbit approved count: " . $e->getMessage());
    }

    // Load recently posted internships.
    try {
        $stmt = $pdo->query(
            "SELECT
                i.id,
                i.title,
                i.location,
                i.stipend,
                i.created_at
             FROM internships i
             WHERE i.status = 'Active'
             ORDER BY i.id DESC
             LIMIT 5"
        );

        $recentInternships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("InternOrbit recent internships: " . $e->getMessage());
    }

    // Load the student's recent applications.
    try {
        $stmt = $pdo->prepare(
            "SELECT
                a.id,
                a.status,
                a.applied_at,
                i.title
             FROM applications a
             LEFT JOIN internships i ON a.internship_id = i.id
             WHERE a.student_id = :student_id
             ORDER BY a.id DESC
             LIMIT 5"
        );

        $stmt->execute([
            ":student_id" => $userId
        ]);

        $recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("InternOrbit recent applications: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("InternOrbit dashboard error: " . $e->getMessage());
    $dashboardError = "Some dashboard information could not be loaded.";
}

$firstName = preg_split('/\s+/', trim($studentName))[0] ?? "Student";

function formatDate($date)
{
    if (empty($date)) {
        return "Date unavailable";
    }

    $timestamp = strtotime($date);

    return $timestamp ? date("d M Y", $timestamp) : "Date unavailable";
}

function statusClass($status)
{
    $status = strtolower(trim((string) $status));

    if (in_array($status, ["approved", "accepted", "selected"], true)) {
        return "status-approved";
    }

    if (in_array($status, ["rejected", "declined"], true)) {
        return "status-rejected";
    }

    if (in_array($status, ["reviewing", "shortlisted"], true)) {
        return "status-reviewing";
    }

    return "status-pending";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard | InternOrbit</title>

    <meta
        name="description"
        content="Manage your internship applications and discover opportunities with InternOrbit."
    >

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
        input {
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
            flex-shrink: 0;
            text-align: center;
            font-size: 17px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link:hover {
            background: rgba(168, 85, 247, 0.08);
            color: var(--white);
        }

        /* Logout button styling */
        .logout-link {
            margin-top: 12px;
            color: #fca5a5;
        }

        .logout-link:hover {
            color: #ffffff;
            background: rgba(239, 68, 68, 0.10);
            border-color: rgba(248, 113, 113, 0.18);
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

        /* Welcome section */

        .welcome-banner {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            padding: 34px;
            margin-bottom: 27px;
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
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.12);
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
            max-width: 510px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .welcome-art {
            width: 125px;
            height: 125px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.13);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.04);
            transform: rotate(5deg);
        }

        .welcome-art svg {
            width: 94px;
            height: 94px;
        }

        /* Quick action */

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

        .text-link {
            color: var(--purple-light);
            font-size: 12px;
            font-weight: 700;
        }

        .text-link:hover {
            color: var(--white);
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
            transition:
                transform 0.2s ease,
                border-color 0.2s ease,
                background 0.2s ease;
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
            font-size: 19px;
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

        /* Statistics */

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
            font-size: 16px;
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

        /* Content panels */

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
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

        .internship-list,
        .application-list {
            display: grid;
        }

        .internship-item {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .internship-item:first-child {
            padding-top: 0;
        }

        .internship-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .company-avatar {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.19);
            border-radius: 12px;
            background: linear-gradient(
                135deg,
                rgba(168, 85, 247, 0.18),
                rgba(124, 58, 237, 0.08)
            );
            color: var(--purple-light);
            font-size: 16px;
            font-weight: 800;
        }

        .internship-info {
            min-width: 0;
            flex: 1;
        }

        .internship-info h3 {
            overflow-wrap: anywhere;
            margin-bottom: 6px;
            font-size: 13px;
            line-height: 1.5;
        }

        .internship-info p {
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .internship-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .meta-chip {
            padding: 5px 8px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.055);
            color: #cfc4df;
            font-size: 10px;
        }

        .application-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        }

        .application-item:first-child {
            padding-top: 0;
        }

        .application-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .application-info {
            min-width: 0;
        }

        .application-info h3 {
            overflow-wrap: anywhere;
            margin-bottom: 6px;
            font-size: 12px;
            line-height: 1.5;
        }

        .application-info p {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
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

        .status-pending {
            border-color: rgba(251, 191, 36, 0.17);
            background: rgba(251, 191, 36, 0.09);
            color: #fcd34d;
        }

        .status-approved {
            border-color: rgba(74, 222, 128, 0.17);
            background: rgba(34, 197, 94, 0.09);
            color: var(--green);
        }

        .status-rejected {
            border-color: rgba(248, 113, 113, 0.17);
            background: rgba(239, 68, 68, 0.09);
            color: var(--red);
        }

        .status-reviewing {
            border-color: rgba(192, 132, 252, 0.20);
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
        }

        /* Empty state */

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
            font-size: 20px;
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
            transition: transform 0.2s ease;
        }

        .primary-link:hover {
            transform: translateY(-2px);
        }

        .dashboard-alert {
            margin-bottom: 22px;
            padding: 12px 15px;
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

        /* Tablet */

        @media (max-width: 1100px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .auth-card {
                gap: 25px;
            }

            .quick-actions,
            .stats-grid {
                gap: 12px;
            }

            .action-card,
            .stat-card {
                padding: 16px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Small screens */

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
                margin-bottom: 22px;
            }

            .welcome-banner {
                padding: 25px;
            }

            .welcome-art {
                width: 90px;
                height: 90px;
                border-radius: 21px;
            }

            .welcome-art svg {
                width: 70px;
                height: 70px;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .welcome-banner {
                padding: 22px 19px;
            }

            .welcome-content h1 {
                font-size: 27px;
            }

            .welcome-art {
                display: none;
            }

            .quick-actions {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .action-card {
                min-height: auto;
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                gap: 14px;
                padding: 15px;
            }

            .action-card > div:last-child {
                flex: 1;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-top {
                margin-bottom: 12px;
            }

            .stat-value {
                font-size: 27px;
            }

            .content-panel {
                padding: 17px;
            }

            .section-heading h2 {
                font-size: 16px;
            }

            .section-heading {
                align-items: flex-start;
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

            <a href="dashboard.php" class="nav-link active">
                <span class="nav-icon">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
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

            <!-- Logout button -->
            <a href="../logout.php" class="nav-link logout-link">
                <span class="nav-icon">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </span>
                <span>Logout</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-profile">

                <div class="avatar">
                    <?= escapeOutput(strtoupper(substr($firstName, 0, 1))) ?>
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

    <!-- Main dashboard -->

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
                    <?= escapeOutput(strtoupper(substr($firstName, 0, 1))) ?>
                </div>

            </div>

        </header>

        <?php if ($dashboardError !== ""): ?>
            <div class="dashboard-alert">
                <?= escapeOutput($dashboardError) ?>
            </div>
        <?php endif; ?>

        <!-- Welcome banner -->

        <section class="welcome-banner">

            <div class="welcome-content">

                <div class="welcome-tag">
                    <span>&#10024;</span>
                    Your career journey
                </div>

                <h1>
                    Welcome back,
                    <span><?= escapeOutput($firstName) ?>!</span>
                </h1>

                <p>
                    Your next opportunity could be one click away.
                    Explore internships, manage your applications,
                    and take another step toward your career goals.
                </p>

            </div>

            <div class="welcome-art">

                <svg
                    viewBox="0 0 120 120"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <circle
                        cx="60"
                        cy="60"
                        r="36"
                        stroke="#C084FC"
                        stroke-width="2"
                    />

                    <ellipse
                        cx="60"
                        cy="60"
                        rx="53"
                        ry="22"
                        transform="rotate(-35 60 60)"
                        stroke="#A855F7"
                        stroke-width="2"
                    />

                    <circle
                        cx="60"
                        cy="60"
                        r="10"
                        fill="#C084FC"
                    />

                    <circle
                        cx="91"
                        cy="28"
                        r="7"
                        fill="#FDE68A"
                    />

                    <circle
                        cx="27"
                        cy="77"
                        r="5"
                        fill="#A855F7"
                    />
                </svg>

            </div>

        </section>

        <!-- Quick actions -->

        <section>

            <div class="section-heading">
                <div>
                    <h2>Quick actions</h2>
                    <p>Everything you need to get started.</p>
                </div>
            </div>

            <div class="quick-actions">

                <a href="internships.php" class="action-card">

                    <div class="action-icon">&#128269;</div>

                    <div>
                        <h3>Explore internships</h3>
                        <p>Find opportunities that match your goals.</p>
                    </div>

                </a>

                <a href="applications.php" class="action-card">

                    <div class="action-icon">&#128203;</div>

                    <div>
                        <h3>Track applications</h3>
                        <p>Check the status of your applications.</p>
                    </div>

                </a>

                <a href="profile.php" class="action-card">

                    <div class="action-icon">&#9998;</div>

                    <div>
                        <h3>Update your profile</h3>
                        <p>Keep your student details up to date.</p>
                    </div>

                </a>

            </div>

        </section>

        <!-- Statistics -->

        <section>

            <div class="section-heading">
                <div>
                    <h2>Your overview</h2>
                    <p>A snapshot of your internship journey.</p>
                </div>
            </div>

            <div class="stats-grid">

                <article class="stat-card">

                    <div class="stat-top">
                        <div class="stat-label">
                            Available internships
                        </div>

                        <div class="stat-icon">&#128188;</div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalInternships) ?>
                    </div>

                    <div class="stat-note">
                        Opportunities to explore
                    </div>

                </article>

                <article class="stat-card">

                    <div class="stat-top">
                        <div class="stat-label">
                            Applications submitted
                        </div>

                        <div class="stat-icon">&#128203;</div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalApplications) ?>
                    </div>

                    <div class="stat-note">
                        Your application history
                    </div>

                </article>

                <article class="stat-card">

                    <div class="stat-top">
                        <div class="stat-label">
                            Awaiting decision
                        </div>

                        <div class="stat-icon">&#9203;</div>
                    </div>

                    <div class="stat-value">
                        <?= number_format($pendingApplications) ?>
                    </div>

                    <div class="stat-note">
                        Applications marked pending
                    </div>

                </article>

            </div>

        </section>

        <!-- Recent activity -->

        <section class="content-grid">

            <!-- Recent internships -->

            <div class="content-panel">

                <div class="panel-heading">

                    <div>
                        <h2>Recently added internships</h2>
                        <p>Explore the latest opportunities.</p>
                    </div>

                    <a href="internships.php" class="text-link">
                        View all &#8594;
                    </a>

                </div>

                <?php if (!empty($recentInternships)): ?>

                    <div class="internship-list">

                        <?php foreach ($recentInternships as $internship): ?>

                            <article class="internship-item">

                                <div class="company-avatar">
                                    <?= escapeOutput(
                                        strtoupper(
                                            substr(
                                                trim($internship["title"] ?? "I"),
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>
                                </div>

                                <div class="internship-info">

                                    <h3>
                                        <?= escapeOutput($internship["title"] ?? "Internship") ?>
                                    </h3>

                                    <p>
                                        <?= escapeOutput($internship["location"] ?? "Location not specified") ?>
                                    </p>

                                    <div class="internship-meta">

                                        <?php if (
                                            isset($internship["stipend"]) &&
                                            $internship["stipend"] !== ""
                                        ): ?>
                                            <span class="meta-chip">
                                                Stipend:
                                                <?= escapeOutput($internship["stipend"]) ?>
                                            </span>
                                        <?php endif; ?>

                                        <span class="meta-chip">
                                            Posted
                                            <?= escapeOutput(formatDate($internship["created_at"] ?? "")) ?>
                                        </span>

                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-icon">&#128188;</div>

                        <h3>No internships to display yet</h3>

                        <p>
                            When active internships are available,
                            you will find them here.
                        </p>

                        <a href="internships.php" class="primary-link">
                            Explore internships &#8594;
                        </a>

                    </div>

                <?php endif; ?>

            </div>

            <!-- Recent applications -->

            <div class="content-panel">

                <div class="panel-heading">

                    <div>
                        <h2>Recent applications</h2>
                        <p>Keep an eye on your progress.</p>
                    </div>

                    <a href="applications.php" class="text-link">
                        View all &#8594;
                    </a>

                </div>

                <?php if (!empty($recentApplications)): ?>

                    <div class="application-list">

                        <?php foreach ($recentApplications as $application): ?>

                            <article class="application-item">

                                <div class="application-info">

                                    <h3>
                                        <?= escapeOutput(
                                            $application["title"] ??
                                            "Internship application"
                                        ) ?>
                                    </h3>

                                    <p>
                                        Applied:
                                        <?= escapeOutput(
                                            formatDate(
                                                $application["applied_at"] ?? ""
                                            )
                                        ) ?>
                                    </p>

                                </div>

                                <span class="status <?= escapeOutput(
                                    statusClass($application["status"] ?? "Pending")
                                ) ?>">
                                    <?= escapeOutput(
                                        $application["status"] ?? "Pending"
                                    ) ?>
                                </span>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div class="empty-icon">&#128203;</div>

                        <h3>No applications yet</h3>

                        <p>
                            Once you apply for an internship,
                            your recent activity will appear here.
                        </p>

                        <a href="internships.php" class="primary-link">
                            Find an internship &#8594;
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Built to help students discover their next opportunity.
        </footer>

    </main>

</div>

</body>
</html>