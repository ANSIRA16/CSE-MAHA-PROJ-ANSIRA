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

$title = "";
$description = "";
$skills = "";
$location = "";

// Get company ID from the companies table.
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

    if (!$company) {
        $errors[] = "Your company profile was not found. Please complete your company registration first.";
    } else {
        $companyId = (int) $company["id"];
        $companyName = $company["company_name"] ?: $companyName;
    }
} catch (PDOException $e) {
    error_log("InternOrbit company lookup: " . $e->getMessage());
    $errors[] = "Unable to load your company profile.";
}

// Handle internship form submission.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $skills = trim($_POST["skills"] ?? "");
    $location = trim($_POST["location"] ?? "");

    if ($title === "") {
        $errors[] = "Please enter an internship title.";
    } elseif (mb_strlen($title) > 150) {
        $errors[] = "The internship title must be 150 characters or fewer.";
    }

    if ($description === "") {
        $errors[] = "Please enter an internship description.";
    }

    if ($skills === "") {
        $errors[] = "Please enter the required skills.";
    }

    if ($location === "") {
        $errors[] = "Please enter the internship location.";
    } elseif (mb_strlen($location) > 150) {
        $errors[] = "The location must be 150 characters or fewer.";
    }

    if (empty($errors) && isset($companyId)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO internships
                    (company_id, title, description, skills, location, created_at)
                 VALUES
                    (:company_id, :title, :description, :skills, :location, NOW())"
            );

            $stmt->execute([
                ":company_id" => $companyId,
                ":title" => $title,
                ":description" => $description,
                ":skills" => $skills,
                ":location" => $location
            ]);

            $successMessage = "Your internship has been posted successfully!";

            $title = "";
            $description = "";
            $skills = "";
            $location = "";
        } catch (PDOException $e) {
            error_log("InternOrbit internship insert: " . $e->getMessage());
            $errors[] = "Unable to post the internship. Please check your database structure and try again.";
        }
    }
}

$nameParts = preg_split('/\s+/', trim($companyName));
$firstName = $nameParts[0] ?? "Company";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post Internship | InternOrbit</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
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

        .form-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(230px, 0.7fr);
            align-items: start;
            gap: 22px;
        }

        .form-card,
        .info-card {
            min-width: 0;
            padding: 27px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--surface);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.10);
        }

        .card-heading {
            margin-bottom: 24px;
        }

        .card-heading h2 {
            margin-bottom: 7px;
            font-size: 18px;
        }

        .card-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #eee7f8;
            font-size: 12px;
            font-weight: 700;
        }

        .required {
            color: var(--purple-light);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 11px;
            outline: none;
            background: rgba(8, 5, 17, 0.45);
            color: var(--white);
            font-family: inherit;
            font-size: 13px;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .form-group input {
            min-height: 46px;
            padding: 12px 14px;
        }

        .form-group textarea {
            min-height: 145px;
            padding: 13px 14px;
            resize: vertical;
            line-height: 1.7;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #80748f;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.12);
        }

        .field-help {
            margin-top: 7px;
            color: #9589a9;
            font-size: 11px;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 27px;
        }

        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 17px;
            border: 1px solid transparent;
            border-radius: 11px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            transition:
                transform 0.2s ease,
                background 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(
                110deg,
                #9333ea,
                #7c3aed
            );
            color: var(--white);
            box-shadow: 0 8px 22px rgba(124, 58, 237, 0.20);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(
                110deg,
                #a855f7,
                #8b5cf6
            );
        }

        .btn-secondary {
            border-color: var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--muted);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--white);
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

        .info-card {
            padding: 23px;
        }

        .info-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            margin-bottom: 17px;
            border: 1px solid rgba(192, 132, 252, 0.22);
            border-radius: 14px;
            background: rgba(168, 85, 247, 0.12);
            color: var(--purple-light);
        }

        .info-card h2 {
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-card > p {
            margin-bottom: 22px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .tip-list {
            display: grid;
            gap: 17px;
        }

        .tip-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
        }

        .tip-check {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(168, 85, 247, 0.13);
            color: var(--purple-light);
            font-size: 12px;
        }

        .tip-item h3 {
            margin-bottom: 5px;
            font-size: 12px;
        }

        .tip-item p {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .footer {
            padding-top: 30px;
            color: #817791;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 1050px) {
            .sidebar {
                width: 220px;
            }

            .main-area {
                padding: 25px 22px 35px;
            }

            .form-layout {
                grid-template-columns: 1fr;
            }

            .info-card {
                order: 2;
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

            .form-card,
            .info-card {
                padding: 21px;
            }
        }

        @media (max-width: 520px) {
            .topbar-date {
                display: none;
            }

            .page-heading h1 {
                font-size: 27px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
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

    <!-- Company sidebar -->

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

            <a href="add_internship.php" class="nav-link active">
                <span class="nav-icon">&#10010;</span>
                <span>Post Internship</span>
            </a>

            <a href="internships.php" class="nav-link">
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
                Company workspace / <span>Post Internship</span>
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
                Post a new <span>internship</span>
            </h1>

            <p>
                Share an opportunity with students and find the right talent for your team.
            </p>

        </section>

        <?php if ($successMessage !== ""): ?>

            <div class="alert alert-success" role="status">
                <?= escapeOutput($successMessage) ?>
                <br>
                You can view your posted opportunities from My Internships.
            </div>

        <?php endif; ?>

        <?php if (!empty($errors)): ?>

            <div class="alert alert-error" role="alert">
                <strong>Please check the following:</strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeOutput($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <section class="form-layout">

            <div class="form-card">

                <div class="card-heading">
                    <h2>Internship details</h2>
                    <p>Enter accurate information so students understand the opportunity.</p>
                </div>

                <form method="POST" action="" autocomplete="off">

                    <div class="form-group">
                        <label for="title">
                            Internship title <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="150"
                            placeholder="e.g. Frontend Developer Intern"
                            value="<?= escapeOutput($title) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">
                            Internship description <span class="required">*</span>
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            placeholder="Describe the responsibilities, learning opportunities, and expectations..."
                            required
                        ><?= escapeOutput($description) ?></textarea>

                        <p class="field-help">
                            Include responsibilities, the work students will do, and what they can learn.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="skills">
                            Required skills <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="skills"
                            name="skills"
                            placeholder="e.g. HTML, CSS, JavaScript, React"
                            value="<?= escapeOutput($skills) ?>"
                            required
                        >

                        <p class="field-help">
                            Separate skills with commas to make requirements easy to read.
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="location">
                            Internship location <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="location"
                            name="location"
                            maxlength="150"
                            placeholder="e.g. Chennai, Tamil Nadu or Remote"
                            value="<?= escapeOutput($location) ?>"
                            required
                        >
                    </div>

                    <div class="form-actions">

                        <button type="submit" class="btn btn-primary">
                            <span>&#10010;</span>
                            Publish Internship
                        </button>

                        <button type="reset" class="btn btn-secondary">
                            Clear Form
                        </button>

                    </div>

                </form>

            </div>

            <aside class="info-card">

                <div class="info-icon">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="23"
                        height="23"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 16v-4"/>
                        <path d="M12 8h.01"/>
                    </svg>
                </div>

                <h2>Tips for a great listing</h2>

                <p>
                    A clear internship listing helps students decide whether the opportunity is right for them.
                </p>

                <div class="tip-list">

                    <div class="tip-item">
                        <div class="tip-check">&#10003;</div>

                        <div>
                            <h3>Choose a clear title</h3>
                            <p>Use a role name students can easily understand.</p>
                        </div>
                    </div>

                    <div class="tip-item">
                        <div class="tip-check">&#10003;</div>

                        <div>
                            <h3>Explain the work</h3>
                            <p>Describe responsibilities and what students can expect to learn.</p>
                        </div>
                    </div>

                    <div class="tip-item">
                        <div class="tip-check">&#10003;</div>

                        <div>
                            <h3>List relevant skills</h3>
                            <p>Include the main technical or professional skills required.</p>
                        </div>
                    </div>

                    <div class="tip-item">
                        <div class="tip-check">&#10003;</div>

                        <div>
                            <h3>Specify the location</h3>
                            <p>Mention the city or clearly state if the role is remote.</p>
                        </div>
                    </div>

                </div>

            </aside>

        </section>

        <footer class="footer">
            &copy; <?= date("Y") ?> InternOrbit.
            Connecting students with career opportunities.
        </footer>

    </main>

</div>

</body>
</html>