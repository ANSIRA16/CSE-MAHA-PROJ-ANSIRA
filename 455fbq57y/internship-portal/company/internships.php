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

$errors = [];
$successMessage = "";
$internships = [];
$companyId = null;

// Find the company profile.
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

// Delete an internship only when it belongs to this company.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_internship"])) {
    $internshipId = filter_input(
        INPUT_POST,
        "internship_id",
        FILTER_VALIDATE_INT
    );

    if (!$internshipId || $internshipId < 1) {
        $errors[] = "Invalid internship selected.";
    } elseif ($companyId !== null) {
        try {
            // Do not delete internships that already have applications.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM applications
                 WHERE internship_id = :internship_id"
            );

            $stmt->execute([
                ":internship_id" => $internshipId
            ]);

            $applicationCount = (int) $stmt->fetchColumn();

            if ($applicationCount > 0) {
                $errors[] = "This internship has applications and cannot be deleted.";
            } else {
                $stmt = $pdo->prepare(
                    "DELETE FROM internships
                     WHERE id = :internship_id
                     AND company_id = :company_id"
                );

                $stmt->execute([
                    ":internship_id" => $internshipId,
                    ":company_id" => $companyId
                ]);

                if ($stmt->rowCount() > 0) {
                    header("Location: internships.php?deleted=1");
                    exit();
                }

                $errors[] = "Internship not found or you do not have permission to delete it.";
            }
        } catch (PDOException $e) {
            error_log("InternOrbit internship delete: " . $e->getMessage());
            $errors[] = "Unable to delete this internship.";
        }
    }
}

// Show a success message after a redirect.
if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $successMessage = "Internship deleted successfully.";
}

// Load this company's internships.
if ($companyId !== null) {
    try {
        $stmt = $pdo->prepare(
            "SELECT
                i.id,
                i.title,
                i.description,
                i.skills,
                i.location,
                i.created_at,
                (
                    SELECT COUNT(*)
                    FROM applications a
                    WHERE a.internship_id = i.id
                ) AS application_count
             FROM internships i
             WHERE i.company_id = :company_id
             ORDER BY i.id DESC"
        );

        $stmt->execute([
            ":company_id" => $companyId
        ]);

        $internships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("InternOrbit internships list: " . $e->getMessage());
        $errors[] = "Unable to load your internships.";
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Internships | InternOrbit</title>

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
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
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

        .primary-link {
            min-height: 42px;
            flex-shrink: 0;
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
            transition: transform 0.2s ease;
        }

        .primary-link:hover {
            transform: translateY(-2px);
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

        .summary-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 21px 23px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: var(--surface);
        }

        .summary-card p {
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 12px;
        }

        .summary-card h2 {
            font-size: 27px;
        }

        .summary-icon {
            width: 49px;
            height: 49px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 14px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
            font-size: 23px;
        }

        .internship-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .internship-card {
            min-width: 0;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            transition:
                transform 0.2s ease,
                border-color 0.2s ease;
        }

        .internship-card:hover {
            transform: translateY(-3px);
            border-color: rgba(192, 132, 252, 0.30);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 17px;
        }

        .internship-icon {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border: 1px solid rgba(192, 132, 252, 0.20);
            border-radius: 13px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
            font-size: 19px;
        }

        .internship-heading {
            min-width: 0;
            flex: 1;
        }

        .internship-heading h2 {
            overflow-wrap: anywhere;
            margin-bottom: 7px;
            font-size: 15px;
            line-height: 1.5;
        }

        .internship-heading p {
            color: var(--muted);
            font-size: 11px;
        }

        .card-description {
            display: -webkit-box;
            overflow: hidden;
            margin-bottom: 16px;
            color: #c6bdd5;
            font-size: 12px;
            line-height: 1.8;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 17px;
        }

        .meta-chip {
            padding: 6px 9px;
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            color: #d4c9e3;
            font-size: 10px;
        }

        .skills-text {
            margin-bottom: 18px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .skills-text strong {
            color: #e9ddf7;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .application-count {
            color: var(--muted);
            font-size: 11px;
        }

        .application-count strong {
            color: var(--purple-light);
        }

        .delete-form {
            margin: 0;
        }

        .delete-button {
            min-height: 34px;
            padding: 8px 11px;
            border: 1px solid rgba(248, 113, 113, 0.20);
            border-radius: 9px;
            background: rgba(239, 68, 68, 0.07);
            color: var(--red);
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            transition: background 0.2s ease;
        }

        .delete-button:hover {
            background: rgba(239, 68, 68, 0.15);
        }

        .empty-state {
            padding: 60px 22px;
            border: 1px dashed rgba(192, 132, 252, 0.25);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.025);
            text-align: center;
        }

        .empty-icon {
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
            margin: 0 auto 18px;
            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 18px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
            font-size: 26px;
        }

        .empty-state h2 {
            margin-bottom: 9px;
            font-size: 19px;
        }

        .empty-state p {
            max-width: 440px;
            margin: 0 auto 21px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
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

            .main-area {
                padding: 22px 16px 30px;
            }

            .topbar {
                margin-bottom: 24px;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .summary-card {
                padding: 18px;
            }

            .internship-card {
                padding: 17px;
            }

            .card-footer {
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

            <a href="internships.php" class="nav-link active">
                <span class="nav-icon">&#128196;</span>
                <span>My Internships</span>
            </a>

            <a href="applicants.php" class="nav-link">
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
                Company workspace / <span>My Internships</span>
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

            <div>
                <h1>
                    My <span>internships</span>
                </h1>

                <p>
                    Manage the opportunities your company has published.
                </p>
            </div>

            <a href="add_internship.php" class="primary-link">
                <span>&#10010;</span>
                Post Internship
            </a>

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

        <section class="summary-card">

            <div>
                <p>Total internships posted by your company</p>
                <h2><?= number_format(count($internships)) ?></h2>
            </div>

            <div class="summary-icon">
                &#128188;
            </div>

        </section>

        <?php if (!empty($internships)): ?>

            <section class="internship-grid">

                <?php foreach ($internships as $internship): ?>

                    <article class="internship-card">

                        <div class="card-top">

                            <div class="internship-icon">
                                &#128188;
                            </div>

                            <div class="internship-heading">

                                <h2>
                                    <?= escapeOutput($internship["title"]) ?>
                                </h2>

                                <p>
                                    Posted on
                                    <?= escapeOutput(formatDate($internship["created_at"] ?? "")) ?>
                                </p>

                            </div>

                        </div>

                        <p class="card-description">
                            <?= escapeOutput($internship["description"] ?? "") ?>
                        </p>

                        <div class="card-meta">

                            <span class="meta-chip">
                                &#128205;
                                <?= escapeOutput($internship["location"] ?: "Location not specified") ?>
                            </span>

                            <span class="meta-chip">
                                &#128101;
                                <?= number_format((int) $internship["application_count"]) ?>
                                applications
                            </span>

                        </div>

                        <p class="skills-text">
                            <strong>Required skills:</strong>
                            <?= escapeOutput($internship["skills"] ?: "Not specified") ?>
                        </p>

                        <div class="card-footer">

                            <div class="application-count">
                                Applications received:
                                <strong>
                                    <?= number_format((int) $internship["application_count"]) ?>
                                </strong>
                            </div>

                            <?php if ((int) $internship["application_count"] === 0): ?>

                                <form
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="return confirm('Are you sure you want to delete this internship? This action cannot be undone.');"
                                >

                                    <input
                                        type="hidden"
                                        name="internship_id"
                                        value="<?= (int) $internship["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_internship"
                                        value="1"
                                        class="delete-button"
                                    >
                                        Delete Internship
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <section class="empty-state">

                <div class="empty-icon">
                    &#128188;
                </div>

                <h2>No internships posted yet</h2>

                <p>
                    Your company has not published any internships.
                    Create your first listing to help students discover opportunities.
                </p>

                <a href="add_internship.php" class="primary-link">
                    <span>&#10010;</span>
                    Post Your First Internship
                </a>

            </section>

        <?php endif; ?>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Connecting companies with talented students.
        </footer>

    </main>

</div>

</body>
</html>