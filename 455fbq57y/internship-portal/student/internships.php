<?php

session_start();

require_once __DIR__ . "/../config/database.php";

// Protect the student page.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Student"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];

function escapeOutput($value)
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$internships = [];
$studentName = $_SESSION["name"] ?? "Student";
$initial = strtoupper(substr(trim($studentName), 0, 1));

if ($initial === "") {
    $initial = "S";
}

$errorMessage = "";
$search = trim($_GET["search"] ?? "");

try {
    // Fetch the logged-in student's database profile.
    $studentQuery = $pdo->prepare(
        "SELECT id
         FROM students
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $studentQuery->execute([
        ":user_id" => $userId
    ]);

    $studentId = $studentQuery->fetchColumn();

    // Create a student profile if one does not exist.
    if (!$studentId) {
        $createStudent = $pdo->prepare(
            "INSERT INTO students (user_id, skills, resume)
             VALUES (:user_id, '', NULL)"
        );

        $createStudent->execute([
            ":user_id" => $userId
        ]);

        $studentId = (int) $pdo->lastInsertId();
    }

    // Load internships and identify those already applied for.
    $sql = "
        SELECT
            i.id,
            i.title,
            i.description,
            i.skills,
            i.location,
            i.created_at,
            c.company_name,
            CASE
                WHEN a.id IS NOT NULL THEN 1
                ELSE 0
            END AS already_applied
        FROM internships i
        LEFT JOIN companies c
            ON c.id = i.company_id
        LEFT JOIN applications a
            ON a.internship_id = i.id
            AND a.student_id = :student_id
        WHERE 1 = 1
    ";

    $params = [
        ":student_id" => (int) $studentId
    ];

    // Optional keyword search.
    if ($search !== "") {
        $sql .= "
            AND (
                i.title LIKE :search_title
                OR i.description LIKE :search_description
                OR i.skills LIKE :search_skills
                OR i.location LIKE :search_location
                OR c.company_name LIKE :search_company
            )
        ";

        $keyword = "%" . $search . "%";

        $params[":search_title"] = $keyword;
        $params[":search_description"] = $keyword;
        $params[":search_skills"] = $keyword;
        $params[":search_location"] = $keyword;
        $params[":search_company"] = $keyword;
    }

    $sql .= " ORDER BY i.created_at DESC, i.id DESC";

    $internshipQuery = $pdo->prepare($sql);
    $internshipQuery->execute($params);

    $internships = $internshipQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        "InternOrbit internships loading error: " .
        $e->getMessage()
    );

    $errorMessage =
        "We could not load internships right now. Please try again later.";
}

$totalInternships = count($internships);

$availableInternships = 0;
$appliedInternships = 0;

foreach ($internships as $internship) {
    if ((int) $internship["already_applied"] === 1) {
        $appliedInternships++;
    } else {
        $availableInternships++;
    }
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

    <title>Explore Internships | InternOrbit</title>

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
            margin-bottom: 25px;
        }

        .intro h2 {
            margin-bottom: 8px;
            font-size: 22px;
            letter-spacing: -0.4px;
        }

        .intro p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* Statistics */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 15px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-icon {
            width: 37px;
            height: 37px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.15);
            border-radius: 11px;
            background: rgba(168, 85, 247, 0.11);
            color: var(--purple-light);
            font-size: 17px;
        }

        .stat-value {
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -0.6px;
        }

        /* Search */

        .search-panel {
            padding: 20px;
            margin-bottom: 27px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: var(--surface);
        }

        .search-panel h3 {
            margin-bottom: 7px;
            font-size: 15px;
        }

        .search-panel p {
            margin-bottom: 17px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .search-form {
            display: flex;
            gap: 11px;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .search-input-wrap svg {
            position: absolute;
            top: 50%;
            left: 15px;
            width: 18px;
            height: 18px;
            transform: translateY(-50%);
            color: #9589a9;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            min-height: 47px;
            padding: 12px 15px 12px 43px;
            border: 1px solid rgba(255, 255, 255, 0.11);
            border-radius: 11px;
            outline: none;
            background: rgba(10, 7, 19, 0.55);
            color: var(--white);
            font-size: 12px;
        }

        .search-input::placeholder {
            color: #817791;
        }

        .search-input:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.10);
        }

        .search-btn {
            min-height: 47px;
            padding: 12px 21px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
        }

        .clear-search {
            display: inline-block;
            margin-top: 13px;
            color: var(--purple-light);
            font-size: 12px;
        }

        /* Listings */

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            font-size: 19px;
            letter-spacing: -0.4px;
        }

        .results-count {
            padding: 7px 11px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.035);
            color: var(--muted);
            font-size: 11px;
        }

        .internship-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .internship-card {
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            transition:
                transform 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .internship-card:hover {
            transform: translateY(-3px);
            border-color: rgba(192, 132, 252, 0.28);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 17px;
        }

        .company-logo {
            width: 49px;
            height: 49px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.20);
            border-radius: 14px;
            background: linear-gradient(
                135deg,
                rgba(168, 85, 247, 0.22),
                rgba(124, 58, 237, 0.12)
            );
            color: var(--purple-light);
            font-size: 19px;
            font-weight: 800;
        }

        .company-details {
            flex: 1;
            min-width: 0;
        }

        .company-details h3 {
            overflow-wrap: anywhere;
            margin-bottom: 6px;
            font-size: 16px;
            line-height: 1.5;
        }

        .company-name {
            overflow-wrap: anywhere;
            color: var(--muted);
            font-size: 11px;
        }

        .applied-badge {
            flex-shrink: 0;
            padding: 6px 9px;
            border: 1px solid rgba(74, 222, 128, 0.20);
            border-radius: 8px;
            background: rgba(34, 197, 94, 0.08);
            color: var(--green);
            font-size: 10px;
            font-weight: 700;
        }

        .location-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            color: var(--muted);
            font-size: 12px;
        }

        .location-row svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            color: var(--purple-light);
        }

        .description {
            display: -webkit-box;
            overflow: hidden;
            margin-bottom: 17px;
            color: #c5bdd3;
            font-size: 12px;
            line-height: 1.8;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
        }

        .skills-heading {
            margin-bottom: 10px;
            color: #e9e1f5;
            font-size: 11px;
            font-weight: 700;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-bottom: 20px;
        }

        .skill-tag {
            padding: 6px 9px;
            border: 1px solid rgba(192, 132, 252, 0.17);
            border-radius: 7px;
            background: rgba(168, 85, 247, 0.08);
            color: var(--purple-light);
            font-size: 10px;
        }

        .no-skills {
            color: #9589a9;
            font-size: 11px;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .posted-date {
            color: #9589a9;
            font-size: 10px;
            line-height: 1.7;
        }

        .apply-btn {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(124, 58, 237, 0.25);
        }

        .apply-btn svg {
            width: 15px;
            height: 15px;
        }

        .apply-btn:disabled {
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            color: var(--muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Empty and error states */

        .empty-state {
            grid-column: 1 / -1;
            padding: 55px 25px;
            border: 1px dashed rgba(192, 132, 252, 0.22);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.025);
            text-align: center;
        }

        .empty-icon {
            width: 65px;
            height: 65px;
            display: grid;
            place-items: center;
            margin: 0 auto 19px;
            border: 1px solid rgba(192, 132, 252, 0.17);
            border-radius: 20px;
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
        }

        .empty-icon svg {
            width: 29px;
            height: 29px;
        }

        .empty-state h3 {
            margin-bottom: 9px;
            font-size: 18px;
        }

        .empty-state p {
            max-width: 420px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .error-message {
            padding: 16px;
            margin-bottom: 22px;
            border: 1px solid rgba(248, 113, 113, 0.22);
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.08);
            color: var(--red);
            font-size: 12px;
            line-height: 1.8;
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

        /* Responsive */

        @media (max-width: 1100px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .internship-grid {
                grid-template-columns: 1fr;
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
                margin-bottom: 23px;
                padding-bottom: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-top {
                margin-bottom: 10px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-btn {
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

            .search-panel,
            .internship-card {
                padding: 17px;
            }

            .card-top {
                flex-wrap: wrap;
            }

            .card-footer {
                align-items: flex-start;
                flex-direction: column;
            }

            .apply-btn {
                width: 100%;
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

            <!-- Dashboard with SVG icon -->
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

            <a href="internships.php" class="nav-link active">
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
                        <rect x="3" y="7" width="18" height="14" rx="2"></rect>
                        <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <path d="M3 12h18"></path>
                        <path d="M10 12v2h4v-2"></path>
                    </svg>
                </span>
                <span>Explore Internships</span>
            </a>

            <a href="applications.php" class="nav-link">
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
                        <rect x="5" y="4" width="14" height="17" rx="2"></rect>
                        <path d="M9 4.5h6"></path>
                        <path d="M9 10h6"></path>
                        <path d="M9 14h6"></path>
                        <path d="M9 18h3"></path>
                    </svg>
                </span>
                <span>My Applications</span>
            </a>

            <a href="profile.php" class="nav-link">
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
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 21a8 8 0 0 1 16 0"></path>
                    </svg>
                </span>
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

            <div>
                <div class="breadcrumb">
                    Workspace / <span>Explore Internships</span>
                </div>

                <h1 class="page-title">
                    Explore Internships
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

            <h2>Find your next opportunity</h2>

            <p>
                Discover internships, explore new career paths, and
                take the next step toward your professional future.
            </p>

        </section>

        <?php if ($errorMessage !== ""): ?>

            <div class="error-message" role="alert">
                <?= escapeOutput($errorMessage) ?>
            </div>

        <?php else: ?>

            <!-- Statistics -->

            <section class="stats-grid">

                <div class="stat-card">

                    <div class="stat-top">
                        <span class="stat-label">
                            Total internships
                        </span>

                        <span class="stat-icon">
                            &#128188;
                        </span>
                    </div>

                    <div class="stat-value">
                        <?= $totalInternships ?>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <span class="stat-label">
                            Available to apply
                        </span>

                        <span class="stat-icon">
                            &#10003;
                        </span>
                    </div>

                    <div class="stat-value">
                        <?= $availableInternships ?>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-top">
                        <span class="stat-label">
                            Already applied
                        </span>

                        <span class="stat-icon">
                            &#128203;
                        </span>
                    </div>

                    <div class="stat-value">
                        <?= $appliedInternships ?>
                    </div>

                </div>

            </section>

            <!-- Search -->

            <section class="search-panel">

                <h3>Search opportunities</h3>

                <p>
                    Search by internship title, company, location,
                    description, or required skills.
                </p>

                <form
                    method="GET"
                    action="internships.php"
                    class="search-form"
                >

                    <div class="search-input-wrap">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-4-4"></path>
                        </svg>

                        <input
                            type="search"
                            name="search"
                            class="search-input"
                            placeholder="Try Java, React, Chennai..."
                            value="<?= escapeOutput($search) ?>"
                        >

                    </div>

                    <button type="submit" class="search-btn">
                        Search internships
                    </button>

                </form>

                <?php if ($search !== ""): ?>
                    <a
                        href="internships.php"
                        class="clear-search"
                    >
                        Clear search
                    </a>
                <?php endif; ?>

            </section>

            <!-- Internship listings -->

            <section>

                <div class="section-heading">

                    <h2>
                        <?= $search !== ""
                            ? "Search results"
                            : "Latest opportunities" ?>
                    </h2>

                    <span class="results-count">
                        <?= $totalInternships ?>
                        <?= $totalInternships === 1
                            ? "internship"
                            : "internships" ?>
                    </span>

                </div>

                <div class="internship-grid">

                    <?php if (empty($internships)): ?>

                        <div class="empty-state">

                            <div class="empty-icon">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <rect x="3" y="7" width="18" height="14" rx="2"></rect>
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <path d="M3 12h18"></path>
                                </svg>
                            </div>

                            <h3>
                                <?= $search !== ""
                                    ? "No matching internships"
                                    : "No internships available yet" ?>
                            </h3>

                            <p>
                                <?= $search !== ""
                                    ? "Try another keyword or clear your search to see all available internships."
                                    : "New opportunities will appear here when companies publish internships. Please check back later." ?>
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach ($internships as $internship): ?>

                            <?php
                            $companyName =
                                trim($internship["company_name"] ?? "");

                            if ($companyName === "") {
                                $companyName = "Company";
                            }

                            $companyInitial = strtoupper(
                                substr($companyName, 0, 1)
                            );

                            $skillItems = array_values(
                                array_unique(
                                    array_filter(
                                        array_map(
                                            "trim",
                                            explode(
                                                ",",
                                                $internship["skills"] ?? ""
                                            )
                                        )
                                    )
                                )
                            );

                            $isApplied =
                                (int) $internship["already_applied"] === 1;

                            $postedDate = "";

                            if (!empty($internship["created_at"])) {
                                $timestamp = strtotime(
                                    $internship["created_at"]
                                );

                                if ($timestamp !== false) {
                                    $postedDate = date(
                                        "d M Y",
                                        $timestamp
                                    );
                                }
                            }
                            ?>

                            <article class="internship-card">

                                <div class="card-top">

                                    <div class="company-logo">
                                        <?= escapeOutput($companyInitial) ?>
                                    </div>

                                    <div class="company-details">

                                        <h3>
                                            <?= escapeOutput(
                                                $internship["title"]
                                            ) ?>
                                        </h3>

                                        <div class="company-name">
                                            <?= escapeOutput($companyName) ?>
                                        </div>

                                    </div>

                                    <?php if ($isApplied): ?>

                                        <span class="applied-badge">
                                            Applied
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="location-row">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"></path>
                                        <circle cx="12" cy="10" r="2.5"></circle>
                                    </svg>

                                    <span>
                                        <?= escapeOutput(
                                            $internship["location"]
                                                ?: "Location not specified"
                                        ) ?>
                                    </span>

                                </div>

                                <p class="description">
                                    <?= escapeOutput(
                                        $internship["description"]
                                    ) ?>
                                </p>

                                <div class="skills-heading">
                                    Required skills
                                </div>

                                <div class="skills-list">

                                    <?php if (empty($skillItems)): ?>

                                        <span class="no-skills">
                                            Skills not specified
                                        </span>

                                    <?php else: ?>

                                        <?php foreach (
                                            array_slice($skillItems, 0, 8)
                                            as $skill
                                        ): ?>

                                            <span class="skill-tag">
                                                <?= escapeOutput($skill) ?>
                                            </span>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </div>

                                <div class="card-footer">

                                    <div class="posted-date">

                                        <?php if ($postedDate !== ""): ?>

                                            Posted on
                                            <?= escapeOutput($postedDate) ?>

                                        <?php else: ?>

                                            Internship opportunity

                                        <?php endif; ?>

                                    </div>

                                    <?php if ($isApplied): ?>

                                        <button
                                            type="button"
                                            class="apply-btn"
                                            disabled
                                        >
                                            Already applied
                                        </button>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            action="apply.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="internship_id"
                                                value="<?= (int) $internship["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="apply-btn"
                                            >
                                                Apply now

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M5 12h14"></path>
                                                    <path d="m13 6 6 6-6 6"></path>
                                                </svg>
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </section>

        <?php endif; ?>

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

</body>
</html>