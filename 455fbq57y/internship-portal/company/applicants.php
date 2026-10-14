<?php

session_start();

require_once __DIR__ . "/../config/database.php";

function escapeOutput($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Allow only logged-in companies.
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Company"
) {
    header("Location: ../login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];
$companyName = $_SESSION["name"] ?? "Company";

$companyId = null;
$applicants = [];
$errors = [];
$successMessage = "";

// Find the logged-in company's profile.
try {
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

    if ($company) {
        $companyId = (int) $company["id"];
        $companyName = $company["company_name"] ?: $companyName;
    } else {
        $errors[] = "Your company profile could not be found.";
    }
} catch (PDOException $e) {
    error_log("InternOrbit company lookup: " . $e->getMessage());
    $errors[] = "Unable to load your company profile.";
}

// Update application status.
$allowedStatuses = [
    "Applied",
    "Under Review",
    "Shortlisted",
    "Interview",
    "Selected",
    "Rejected"
];

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {
    $applicationId = filter_input(
        INPUT_POST,
        "application_id",
        FILTER_VALIDATE_INT
    );

    $newStatus = trim($_POST["status"] ?? "");

    if (!$applicationId || $applicationId < 1) {
        $errors[] = "Invalid application selected.";
    } elseif (!in_array($newStatus, $allowedStatuses, true)) {
        $errors[] = "Please select a valid application status.";
    } elseif ($companyId !== null) {
        try {
            // Update only applications for internships owned by this company.
            $stmt = $pdo->prepare(
                "UPDATE applications a
                 INNER JOIN internships i
                    ON i.id = a.internship_id
                 SET a.status = :status
                 WHERE a.id = :application_id
                   AND i.company_id = :company_id"
            );

            $stmt->execute([
                ":status" => $newStatus,
                ":application_id" => $applicationId,
                ":company_id" => $companyId
            ]);

            if ($stmt->rowCount() > 0) {
                header("Location: applicants.php?updated=1");
                exit();
            }

            // A zero row count can mean the status was already the same.
            $stmt = $pdo->prepare(
                "SELECT a.id
                 FROM applications a
                 INNER JOIN internships i
                    ON i.id = a.internship_id
                 WHERE a.id = :application_id
                   AND i.company_id = :company_id
                   AND a.status = :status
                 LIMIT 1"
            );

            $stmt->execute([
                ":application_id" => $applicationId,
                ":company_id" => $companyId,
                ":status" => $newStatus
            ]);

            if ($stmt->fetch()) {
                header("Location: applicants.php?updated=1");
                exit();
            }

            $errors[] = "Application not found or you do not have permission to update it.";
        } catch (PDOException $e) {
            error_log("InternOrbit status update: " . $e->getMessage());
            $errors[] = "Unable to update the application status.";
        }
    }
}

// Show success message after redirect.
if (isset($_GET["updated"]) && $_GET["updated"] === "1") {
    $successMessage = "Application status updated successfully.";
}

// Load applicants for internships owned by this company.
if ($companyId !== null) {
    try {
        $stmt = $pdo->prepare(
            "SELECT
                a.id AS application_id,
                a.status,
                a.message,
                a.applied_at,
                i.id AS internship_id,
                i.title AS internship_title,
                s.id AS student_id,
                s.skills,
                s.resume,
                u.name AS student_name,
                u.email AS student_email
             FROM applications a
             INNER JOIN internships i
                ON i.id = a.internship_id
             INNER JOIN students s
                ON s.id = a.student_id
             INNER JOIN users u
                ON u.id = s.user_id
             WHERE i.company_id = :company_id
             ORDER BY a.applied_at DESC, a.id DESC"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("InternOrbit applicants list: " . $e->getMessage());
        $errors[] = "Unable to load applicants. Please check the database structure.";
    }
}

$firstName = preg_split('/\s+/', trim($companyName))[0] ?? "Company";

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
    switch ($status) {
        case "Selected":
            return "status-selected";

        case "Rejected":
            return "status-rejected";

        case "Shortlisted":
        case "Interview":
            return "status-shortlisted";

        case "Under Review":
            return "status-review";

        default:
            return "status-applied";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Applicants | InternOrbit</title>

    <style>
        :root {
            --purple: #a855f7;
            --purple-light: #c084fc;
            --background: #10091f;
            --surface: rgba(255, 255, 255, 0.055);
            --border: rgba(255, 255, 255, 0.10);
            --white: #ffffff;
            --muted: #b9b2c9;
            --red: #fca5a5;
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
            background: linear-gradient(135deg, #c084fc, #7c3aed);
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
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

        .logout-link {
            margin-top: 12px;
            color: var(--red);
        }

        .logout-link:hover {
            color: var(--white);
            background: rgba(239, 68, 68, 0.10);
            border-color: rgba(248, 113, 113, 0.18);
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

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin-bottom: 9px;
            font-size: clamp(25px, 3vw, 34px);
            letter-spacing: -0.8px;
        }

        .page-heading h1 span {
            color: var(--purple-light);
        }

        .page-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .alert {
            margin-bottom: 22px;
            padding: 14px 16px;
            border: 1px solid transparent;
            border-radius: 12px;
            font-size: 12px;
            line-height: 1.8;
        }

        .alert-success {
            border-color: rgba(74, 222, 128, 0.22);
            background: rgba(34, 197, 94, 0.08);
            color: var(--green);
        }

        .alert-error {
            border-color: rgba(248, 113, 113, 0.22);
            background: rgba(239, 68, 68, 0.08);
            color: var(--red);
        }

        .alert ul {
            padding-left: 18px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
        }

        .summary-card p {
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 12px;
        }

        .summary-card strong {
            font-size: 28px;
            letter-spacing: -0.7px;
        }

        .table-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
        }

        .table-heading {
            padding: 22px;
            border-bottom: 1px solid var(--border);
        }

        .table-heading h2 {
            margin-bottom: 6px;
            font-size: 16px;
        }

        .table-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: rgba(255, 255, 255, 0.025);
        }

        th {
            padding: 14px 17px;
            color: #a99bbd;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 17px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            color: #e8e0f1;
            font-size: 12px;
            vertical-align: top;
        }

        tbody tr:hover {
            background: rgba(168, 85, 247, 0.035);
        }

        .student-name {
            margin-bottom: 5px;
            font-weight: 700;
        }

        .student-email {
            color: var(--muted);
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        .internship-name {
            max-width: 210px;
            font-weight: 700;
            line-height: 1.6;
        }

        .skills {
            max-width: 220px;
            color: var(--muted);
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border: 1px solid transparent;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-applied {
            border-color: rgba(251, 191, 36, 0.17);
            background: rgba(251, 191, 36, 0.09);
            color: #fcd34d;
        }

        .status-review {
            border-color: rgba(192, 132, 252, 0.20);
            background: rgba(168, 85, 247, 0.10);
            color: var(--purple-light);
        }

        .status-shortlisted {
            border-color: rgba(96, 165, 250, 0.20);
            background: rgba(59, 130, 246, 0.10);
            color: #93c5fd;
        }

        .status-selected {
            border-color: rgba(74, 222, 128, 0.20);
            background: rgba(34, 197, 94, 0.09);
            color: var(--green);
        }

        .status-rejected {
            border-color: rgba(248, 113, 113, 0.20);
            background: rgba(239, 68, 68, 0.09);
            color: var(--red);
        }

        .status-form {
            min-width: 145px;
            display: grid;
            gap: 8px;
        }

        .status-select {
            width: 100%;
            min-height: 36px;
            padding: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            outline: none;
            background: #1b102b;
            color: var(--white);
            font-size: 11px;
        }

        .status-select:focus {
            border-color: var(--purple);
        }

        .update-button {
            min-height: 34px;
            padding: 8px 10px;
            border: 0;
            border-radius: 8px;
            background: linear-gradient(110deg, #9333ea, #7c3aed);
            color: var(--white);
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
        }

        .update-button:hover {
            background: linear-gradient(110deg, #a855f7, #8b5cf6);
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin: 0 auto 17px;
            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 18px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
            font-size: 25px;
        }

        .empty-state h2 {
            margin-bottom: 9px;
            font-size: 18px;
        }

        .empty-state p {
            max-width: 420px;
            margin: 0 auto 20px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .primary-link {
            min-height: 41px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 15px;
            border-radius: 10px;
            background: linear-gradient(110deg, #9333ea, #7c3aed);
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
        }

        .footer {
            padding-top: 30px;
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

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .topbar {
                margin-bottom: 24px;
            }

            .table-card {
                border-radius: 14px;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .table-heading {
                padding: 18px;
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
                    <circle cx="24" cy="24" r="15" stroke="white" stroke-width="2.2"/>
                    <ellipse
                        cx="24"
                        cy="24"
                        rx="21"
                        ry="8.5"
                        transform="rotate(-35 24 24)"
                        stroke="white"
                        stroke-width="2.2"
                    />
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

            <a href="dashboard.php" class="nav-link">
                <span class="nav-icon">&#9638;</span>
                <span>Dashboard</span>
            </a>

            <a href="add_internship.php" class="nav-link">
                <span class="nav-icon">&#10010;</span>
                <span>Post Internship</span>
            </a>

            <a href="internships.php" class="nav-link">
                <span class="nav-icon">&#128196;</span>
                <span>My Internships</span>
            </a>

            <a href="applicants.php" class="nav-link active">
                <span class="nav-icon">&#128101;</span>
                <span>Applicants</span>
            </a>

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
                        <?= escapeOutput($companyName) ?>
                    </div>

                    <div class="profile-role">
                        Company account
                    </div>
                </div>

            </div>

        </div>

    </aside>

    <!-- Main content -->

    <main class="main-area">

        <header class="topbar">

            <div class="breadcrumb">
                Company workspace / <span>Applicants</span>
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

        <section class="page-heading">

            <h1>
                Manage <span>applicants</span>
            </h1>

            <p>
                Review student applications and update their progress.
            </p>

        </section>

        <?php if ($successMessage !== ""): ?>

            <div class="alert alert-success" role="status">
                <?= escapeOutput($successMessage) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($errors)): ?>

            <div class="alert alert-error" role="alert">
                <strong>Please note:</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeOutput($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <section class="summary-grid">

            <article class="summary-card">
                <p>Total applications</p>
                <strong><?= number_format(count($applicants)) ?></strong>
            </article>

            <article class="summary-card">
                <p>Shortlisted</p>
                <strong>
                    <?= number_format(
                        count(
                            array_filter(
                                $applicants,
                                fn($applicant) =>
                                    ($applicant["status"] ?? "") === "Shortlisted"
                            )
                        )
                    ) ?>
                </strong>
            </article>

            <article class="summary-card">
                <p>Selected</p>
                <strong>
                    <?= number_format(
                        count(
                            array_filter(
                                $applicants,
                                fn($applicant) =>
                                    ($applicant["status"] ?? "") === "Selected"
                            )
                        )
                    ) ?>
                </strong>
            </article>

        </section>

        <section class="table-card">

            <div class="table-heading">
                <h2>Student applications</h2>
                <p>
                    Only applications submitted to your company's internships are displayed.
                </p>
            </div>

            <?php if (!empty($applicants)): ?>

                <div class="table-wrap">

                    <table>

                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Internship</th>
                                <th>Skills</th>
                                <th>Applied on</th>
                                <th>Status</th>
                                <th>Update status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($applicants as $applicant): ?>

                                <tr>

                                    <td>
                                        <div class="student-name">
                                            <?= escapeOutput($applicant["student_name"] ?? "Student") ?>
                                        </div>

                                        <div class="student-email">
                                            <?= escapeOutput($applicant["student_email"] ?? "") ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="internship-name">
                                            <?= escapeOutput($applicant["internship_title"] ?? "Internship") ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="skills">
                                            <?= escapeOutput($applicant["skills"] ?: "Not provided") ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= escapeOutput(
                                            formatDate($applicant["applied_at"] ?? "")
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status <?= escapeOutput(
                                            statusClass($applicant["status"] ?? "Applied")
                                        ) ?>">
                                            <?= escapeOutput($applicant["status"] ?? "Applied") ?>
                                        </span>
                                    </td>

                                    <td>

                                        <form
                                            method="POST"
                                            class="status-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="application_id"
                                                value="<?= (int) $applicant["application_id"] ?>"
                                            >

                                            <select
                                                name="status"
                                                class="status-select"
                                                aria-label="Update application status"
                                                required
                                            >

                                                <?php foreach ($allowedStatuses as $status): ?>

                                                    <option
                                                        value="<?= escapeOutput($status) ?>"
                                                        <?= ($applicant["status"] ?? "Applied") === $status ? "selected" : "" ?>
                                                    >
                                                        <?= escapeOutput($status) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <button
                                                type="submit"
                                                name="update_status"
                                                value="1"
                                                class="update-button"
                                            >
                                                Update Status
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        &#128101;
                    </div>

                    <h2>No applications yet</h2>

                    <p>
                        When students apply for your internships,
                        their information and application status will appear here.
                    </p>

                    <a href="internships.php" class="primary-link">
                        View My Internships
                    </a>

                </div>

            <?php endif; ?>

        </section>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Connecting companies with talented students.
        </footer>

    </main>

</div>

</body>
</html>