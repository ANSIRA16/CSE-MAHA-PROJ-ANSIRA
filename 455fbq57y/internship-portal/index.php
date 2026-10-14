
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["name"] ?? "";
$userRole = $_SESSION["role"] ?? "";

$dashboardUrl = "login.php";

if ($isLoggedIn && $userRole === "Student") {
    $dashboardUrl = "student/dashboard.php";
} elseif ($isLoggedIn && $userRole === "Company") {
    $dashboardUrl = "company/dashboard.php";
}

function escapeOutput($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
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

    <meta
        name="description"
        content="InternOrbit connects students with internship opportunities and helps companies discover emerging talent."
    >

    <title>InternOrbit | Your Future Starts Here</title>

    <style>
        /* =========================
           GLOBAL STYLES
        ========================= */

        :root {
            --purple: #7C3AED;
            --purple-dark: #5B21B6;
            --purple-light: #A78BFA;
            --lavender: #F5F3FF;
            --black: #0B0B0F;
            --white: #FFFFFF;
            --gray: #6B7280;
            --border: #E5E7EB;
            --radius: 18px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            font-family: Inter, Arial, Helvetica, sans-serif;
            background: var(--white);
            color: var(--black);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        .container {
            width: min(1160px, 90%);
            margin: auto;
        }

        .section {
            padding: 100px 0;
        }

        .section-heading {
            max-width: 690px;
            margin: 0 auto 55px;
            text-align: center;
        }

        .eyebrow {
            display: inline-block;
            color: var(--purple);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 15px;
        }

        .section-heading h2 {
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.2;
            letter-spacing: -1.5px;
            margin-bottom: 18px;
        }

        .section-heading h2 span {
            color: var(--purple);
        }

        .section-heading p {
            color: var(--gray);
            font-size: 16px;
        }

        /* =========================
           BRAND LOGO
        ========================= */

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -1px;
            white-space: nowrap;
        }

        .brand-icon {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                #7C3AED,
                #5B21B6
            );
            box-shadow: 0 6px 18px rgba(124, 58, 237, 0.3);
            transition: transform 0.3s ease;
        }

        .brand:hover .brand-icon {
            transform: rotate(-8deg) scale(1.05);
        }

        .brand-name {
            color: var(--purple);
            letter-spacing: -1.2px;
        }

        /* =========================
           BUTTONS
        ========================= */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 22px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            background: var(--purple);
            color: var(--white);
            box-shadow: 0 8px 22px rgba(124, 58, 237, 0.2);
        }

        .btn-primary:hover {
            background: var(--purple-dark);
            box-shadow: 0 12px 28px rgba(124, 58, 237, 0.3);
        }

        .btn-outline {
            background: var(--white);
            color: var(--black);
            border-color: var(--border);
        }

        .btn-outline:hover {
            color: var(--purple);
            border-color: var(--purple);
        }

        /* =========================
           NAVIGATION
        ========================= */

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
        }

        .nav-inner {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links a {
            color: #4B5563;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .nav-links a:hover {
            color: var(--purple);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-text {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
            color: var(--gray);
        }

        .menu-toggle {
            display: none;
            background: var(--lavender);
            border: 0;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 23px;
            cursor: pointer;
            color: var(--purple);
        }

        /* =========================
           HERO SECTION
        ========================= */

        .hero {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(
                    circle at 90% 10%,
                    rgba(167, 139, 250, 0.18),
                    transparent 30%
                ),
                linear-gradient(
                    180deg,
                    #FFFFFF 0%,
                    #FAF8FF 100%
                );
        }

        .hero-inner {
            min-height: 650px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            align-items: center;
            gap: 55px;
            padding-top: 65px;
            padding-bottom: 75px;
        }

        .hero-content {
            animation: fadeUp 0.8s ease both;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 8px 13px;
            border: 1px solid #DDD6FE;
            background: #EDE9FE;
            color: #5B21B6;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 25px;
        }

        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--purple);
            animation: pulse 1.8s infinite;
        }

        .hero h1 {
            font-size: clamp(42px, 5.3vw, 66px);
            line-height: 1.08;
            letter-spacing: -3px;
            font-weight: 900;
            margin-bottom: 25px;
        }

        .hero h1 span {
            color: var(--purple);
            display: inline-block;
        }

        .hero-description {
            max-width: 550px;
            color: var(--gray);
            font-size: 17px;
            line-height: 1.9;
            margin-bottom: 30px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 13px;
            margin-bottom: 32px;
        }

        .hero-highlights {
            display: flex;
            flex-wrap: wrap;
            gap: 15px 22px;
        }

        .highlight {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #4B5563;
        }

        .check-icon {
            display: grid;
            place-items: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #EDE9FE;
            color: var(--purple);
            font-size: 12px;
            font-weight: 900;
        }

        /* =========================
           HERO DASHBOARD PREVIEW
        ========================= */

        .hero-visual {
            position: relative;
            padding: 20px;
            animation: fadeUp 1s ease 0.15s both;
        }

        .visual-glow {
            position: absolute;
            width: 300px;
            height: 300px;
            right: 10%;
            top: 10%;
            background: rgba(124, 58, 237, 0.18);
            filter: blur(70px);
            border-radius: 50%;
            pointer-events: none;
        }

        .dashboard-preview {
            position: relative;
            z-index: 1;
            padding: 28px;
            background: var(--white);
            border: 1px solid #E9E2FF;
            border-radius: 24px;
            box-shadow: 0 35px 90px rgba(42, 21, 83, 0.13);
            transform: rotate(1deg);
            transition: transform 0.4s ease;
        }

        .dashboard-preview:hover {
            transform: rotate(0deg) translateY(-5px);
        }

        .preview-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 28px;
        }

        .preview-label {
            display: block;
            color: var(--gray);
            font-size: 10px;
            letter-spacing: 1.6px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .preview-top h2 {
            font-size: 23px;
            letter-spacing: -0.8px;
        }

        .sparkle {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            background: var(--lavender);
            color: var(--purple);
            border-radius: 13px;
            font-size: 23px;
        }

        .preview-profile {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 51px;
            height: 51px;
            flex-shrink: 0;
            background: linear-gradient(
                135deg,
                var(--purple),
                #A78BFA
            );
            color: var(--white);
            font-size: 21px;
            font-weight: 800;
            border-radius: 15px;
        }

        .preview-profile h3 {
            font-size: 15px;
            margin-bottom: 3px;
        }

        .preview-profile p {
            color: var(--gray);
            font-size: 12px;
        }

        .progress-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .progress-heading span {
            color: var(--gray);
        }

        .progress-heading strong {
            color: var(--purple);
        }

        .progress-track {
            height: 8px;
            background: #EDE9FE;
            border-radius: 100px;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .progress-fill {
            width: 75%;
            height: 100%;
            background: linear-gradient(
                90deg,
                var(--purple),
                var(--purple-light)
            );
            border-radius: inherit;
            animation: fillProgress 1.4s ease 0.5s both;
        }

        .opportunity-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 17px;
            background: var(--lavender);
            border: 1px solid #EDE9FE;
            border-radius: 15px;
        }

        .opportunity-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            background: var(--white);
            color: var(--purple);
            border-radius: 13px;
            font-size: 23px;
        }

        .opportunity-text {
            flex: 1;
        }

        .opportunity-text h3 {
            font-size: 13px;
            margin-bottom: 4px;
        }

        .opportunity-text p {
            font-size: 11px;
            color: var(--gray);
        }

        .opportunity-arrow {
            font-size: 22px;
            color: var(--purple);
        }

        .preview-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            font-size: 11px;
            color: var(--gray);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22C55E;
        }

        .floating-card {
            position: absolute;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 17px;
            background: var(--white);
            border: 1px solid #E9E2FF;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(42, 21, 83, 0.12);
            font-size: 12px;
            font-weight: 800;
            animation: float 4s ease-in-out infinite;
        }

        .floating-top {
            top: 0;
            right: -5px;
        }

        .floating-bottom {
            bottom: 0;
            left: -5px;
            animation-delay: 1s;
        }

        .floating-icon {
            color: var(--purple);
            font-size: 21px;
        }

        /* =========================
           FEATURES
        ========================= */

        .features {
            background: var(--white);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .feature-card {
            padding: 29px 24px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: #C4B5FD;
            box-shadow: 0 20px 45px rgba(76, 29, 149, 0.09);
        }

        .feature-icon {
            display: grid;
            place-items: center;
            width: 53px;
            height: 53px;
            margin-bottom: 23px;
            background: var(--lavender);
            color: var(--purple);
            border-radius: 15px;
            font-size: 26px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--purple);
            color: var(--white);
        }

        .feature-card h3 {
            font-size: 17px;
            margin-bottom: 12px;
            letter-spacing: -0.4px;
        }

        .feature-card p {
            color: var(--gray);
            font-size: 13px;
            line-height: 1.9;
        }

        /* =========================
           HOW IT WORKS
        ========================= */

        .how-it-works {
            background: #F9F7FF;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .step-card {
            padding: 35px 30px;
            background: var(--white);
            border: 1px solid #E9E2FF;
            border-radius: var(--radius);
            transition: transform 0.3s ease;
        }

        .step-card:hover {
            transform: translateY(-6px);
        }

        .step-number {
            display: inline-block;
            margin-bottom: 24px;
            color: var(--purple);
            font-size: 34px;
            font-weight: 900;
            letter-spacing: -1px;
        }

        .step-card h3 {
            font-size: 19px;
            margin-bottom: 12px;
        }

        .step-card p {
            font-size: 14px;
            color: var(--gray);
            line-height: 1.9;
        }

        /* =========================
           CALL TO ACTION
        ========================= */

        .cta-section {
            padding: 30px 0 100px;
            background: #F9F7FF;
        }

        .cta-card {
            position: relative;
            overflow: hidden;
            padding: 70px 30px;
            text-align: center;
            color: var(--white);
            background: var(--black);
            border-radius: 28px;
        }

        .cta-card::before,
        .cta-card::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(124, 58, 237, 0.35);
            filter: blur(70px);
            border-radius: 50%;
        }

        .cta-card::before {
            left: -100px;
            top: -150px;
        }

        .cta-card::after {
            right: -100px;
            bottom: -180px;
        }

        .cta-content {
            position: relative;
            z-index: 1;
            max-width: 680px;
            margin: auto;
        }

        .cta-content .eyebrow {
            color: #C4B5FD;
        }

        .cta-content h2 {
            font-size: clamp(30px, 4vw, 45px);
            line-height: 1.2;
            letter-spacing: -1.5px;
            margin-bottom: 18px;
        }

        .cta-content p {
            max-width: 550px;
            margin: 0 auto 28px;
            color: #D1D5DB;
            font-size: 15px;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            background: var(--black);
            color: var(--white);
        }

        .footer-main {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 50px;
            padding-top: 60px;
            padding-bottom: 50px;
        }

        .footer .brand {
            margin-bottom: 20px;
        }

        .footer .brand-name {
            color: var(--white);
        }

        .footer-description {
            max-width: 370px;
            color: #9CA3AF;
            font-size: 14px;
            line-height: 1.9;
        }

        .footer-links h3 {
            font-size: 14px;
            margin-bottom: 18px;
        }

        .footer-links-list {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .footer-links-list a {
            color: #9CA3AF;
            font-size: 13px;
            transition: color 0.2s ease;
        }

        .footer-links-list a:hover {
            color: var(--purple-light);
        }

        .footer-bottom {
            border-top: 1px solid #27272A;
            padding: 22px 0;
            color: #9CA3AF;
            font-size: 12px;
            text-align: center;
        }

        /* =========================
           ANIMATIONS
        ========================= */

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(25px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes pulse {
            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.35);
            }

            50% {
                box-shadow: 0 0 0 6px rgba(124, 58, 237, 0);
            }
        }

        @keyframes fillProgress {
            from {
                width: 0;
            }

            to {
                width: 75%;
            }
        }

        /* =========================
           RESPONSIVE DESIGN
        ========================= */

        @media (max-width: 1050px) {
            .nav-links {
                gap: 17px;
            }

            .hero-inner {
                gap: 25px;
            }

            .hero h1 {
                font-size: clamp(40px, 5vw, 56px);
            }

            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 760px) {
            .nav-inner {
                min-height: 70px;
                flex-wrap: wrap;
                padding-top: 12px;
                padding-bottom: 12px;
            }

            .brand {
                font-size: 22px;
            }

            .menu-toggle {
                display: block;
                margin-left: auto;
            }

            .nav-links {
                display: none;
                order: 3;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 8px 0;
            }

            .nav-links.active {
                display: flex;
                animation: fadeUp 0.25s ease;
            }

            .nav-links a {
                padding: 12px 5px;
                border-bottom: 1px solid var(--border);
            }

            .nav-actions {
                order: 4;
                width: 100%;
                flex-wrap: wrap;
                padding-top: 5px;
            }

            .welcome-text {
                max-width: 100%;
                margin-right: auto;
            }

            .nav-actions .btn {
                padding: 10px 15px;
            }

            .hero-inner {
                grid-template-columns: 1fr;
                gap: 45px;
                padding-top: 65px;
                padding-bottom: 65px;
            }

            .hero h1 {
                font-size: clamp(42px, 10vw, 58px);
                letter-spacing: -2px;
            }

            .hero-description {
                font-size: 15px;
            }

            .hero-visual {
                max-width: 550px;
                width: 100%;
                margin: auto;
            }

            .dashboard-preview {
                padding: 22px;
            }

            .section {
                padding: 75px 0;
            }

            .section-heading {
                margin-bottom: 38px;
            }

            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .feature-card {
                padding: 23px 18px;
            }

            .steps-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .step-card {
                padding: 28px;
            }

            .cta-section {
                padding-bottom: 75px;
            }

            .cta-card {
                padding: 55px 22px;
            }

            .footer-main {
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }
        }

        @media (max-width: 480px) {
            .container {
                width: 91%;
            }

            .hero-inner {
                padding-top: 45px;
            }

            .hero h1 {
                font-size: 40px;
            }

            .hero-actions {
                flex-direction: column;
            }

            .hero-actions .btn {
                width: 100%;
            }

            .hero-highlights {
                flex-direction: column;
                gap: 12px;
            }

            .hero-visual {
                padding: 12px 5px;
            }

            .dashboard-preview {
                padding: 18px;
                border-radius: 19px;
            }

            .preview-top h2 {
                font-size: 20px;
            }

            .floating-card {
                padding: 10px 12px;
                font-size: 10px;
            }

            .floating-top {
                right: -3px;
            }

            .floating-bottom {
                left: -3px;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .feature-card {
                padding: 26px;
            }

            .footer-main {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .section-heading h2 {
                letter-spacing: -1px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

    <!-- NAVIGATION -->

    <header class="navbar">

        <div class="container nav-inner">

            <a href="index.php" class="brand">

                <span class="brand-icon">

                    <svg
                        viewBox="0 0 40 40"
                        width="30"
                        height="30"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <circle
                            cx="20"
                            cy="20"
                            r="14"
                            stroke="white"
                            stroke-width="2.5"
                        />

                        <ellipse
                            cx="20"
                            cy="20"
                            rx="6"
                            ry="14"
                            stroke="white"
                            stroke-width="2"
                            transform="rotate(45 20 20)"
                        />

                        <circle
                            cx="20"
                            cy="20"
                            r="3"
                            fill="white"
                        />
                    </svg>

                </span>

                <span class="brand-name">InternOrbit</span>

            </a>

            <button
                type="button"
                class="menu-toggle"
                id="menuToggle"
                aria-label="Toggle navigation"
                aria-expanded="false"
                aria-controls="navLinks"
            >
                ☰
            </button>

            <nav class="nav-links" id="navLinks">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
            </nav>

            <div class="nav-actions">

                <?php if ($isLoggedIn): ?>

                    <span class="welcome-text">
                        Hi, <?= escapeOutput($userName) ?>
                    </span>

                    <a
                        href="<?= escapeOutput($dashboardUrl) ?>"
                        class="btn btn-primary"
                    >
                        Dashboard →
                    </a>

                    <a href="logout.php" class="btn btn-outline">
                        Logout
                    </a>

                <?php else: ?>

                    <a href="login.php" class="btn btn-outline">
                        Login
                    </a>

                    <a href="register.php" class="btn btn-primary">
                        Get Started →
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </header>

    <main>

        <!-- HERO SECTION -->

        <section class="hero" id="home">

            <div class="container hero-inner">

                <div class="hero-content">

                    <div class="hero-badge">
                        <span class="badge-dot"></span>
                        YOUR NEXT OPPORTUNITY STARTS HERE
                    </div>

                    <h1>
                        Your Talent.
                        <br>
                        Your Future.
                        <br>
                        <span>Your Orbit.</span>
                    </h1>

                    <p class="hero-description">
                        Discover internships, showcase your skills,
                        and connect with companies ready to help
                        you take the next step in your career.
                    </p>

                    <div class="hero-actions">

                        <?php if ($isLoggedIn): ?>

                            <a
                                href="<?= escapeOutput($dashboardUrl) ?>"
                                class="btn btn-primary"
                            >
                                Go to Dashboard →
                            </a>

                        <?php else: ?>

                            <a
                                href="register.php"
                                class="btn btn-primary"
                            >
                                Explore Your Future →
                            </a>

                            <a
                                href="#how-it-works"
                                class="btn btn-outline"
                            >
                                Discover How It Works
                            </a>

                        <?php endif; ?>

                    </div>

                    <div class="hero-highlights">

                        <div class="highlight">
                            <span class="check-icon">✓</span>
                            Discover Opportunities
                        </div>

                        <div class="highlight">
                            <span class="check-icon">✓</span>
                            Track Applications
                        </div>

                        <div class="highlight">
                            <span class="check-icon">✓</span>
                            Connect With Companies
                        </div>

                    </div>

                </div>

                <!-- DASHBOARD PREVIEW -->

                <div class="hero-visual">

                    <div class="visual-glow"></div>

                    <div class="dashboard-preview">

                        <div class="preview-top">

                            <div>
                                <span class="preview-label">
                                    YOUR CAREER SPACE
                                </span>

                                <h2>Career Dashboard</h2>
                            </div>

                            <div class="sparkle">✦</div>

                        </div>

                        <div class="preview-profile">

                            <div class="avatar">I</div>

                            <div>
                                <h3>Your Student Profile</h3>
                                <p>
                                    Ready to explore new opportunities
                                </p>
                            </div>

                        </div>

                        <div class="progress-heading">
                            <span>Profile completion</span>
                            <strong>75%</strong>
                        </div>

                        <div class="progress-track">
                            <div class="progress-fill"></div>
                        </div>

                        <div class="opportunity-card">

                            <div class="opportunity-icon">✧</div>

                            <div class="opportunity-text">
                                <h3>Explore Internships</h3>
                                <p>Your next experience awaits.</p>
                            </div>

                            <span class="opportunity-arrow">→</span>

                        </div>

                        <div class="preview-footer">
                            <span class="status-dot"></span>
                            Learn. Apply. Grow.
                        </div>

                    </div>

                    <div class="floating-card floating-top">
                        <span class="floating-icon">✦</span>
                        New Possibilities
                    </div>

                    <div class="floating-card floating-bottom">
                        <span class="floating-icon">↗</span>
                        Build Your Career
                    </div>

                </div>

            </div>

        </section>

        <!-- FEATURES -->

        <section class="section features" id="features">

            <div class="container">

                <div class="section-heading">

                    <span class="eyebrow">WHY INTERNORBIT?</span>

                    <h2>
                        Everything You Need to
                        <span>Move Forward</span>
                    </h2>

                    <p>
                        One platform to bring student talent
                        and internship opportunities together.
                    </p>

                </div>

                <div class="feature-grid">

                    <article class="feature-card">

                        <div class="feature-icon">⌕</div>

                        <h3>Discover Internships</h3>

                        <p>
                            Browse available opportunities and
                            find roles that match your interests
                            and career goals.
                        </p>

                    </article>

                    <article class="feature-card">

                        <div class="feature-icon">◎</div>

                        <h3>Showcase Your Skills</h3>

                        <p>
                            Create your profile, highlight
                            your skills, and upload your resume
                            for company review.
                        </p>

                    </article>

                    <article class="feature-card">

                        <div class="feature-icon">↗</div>

                        <h3>Track Applications</h3>

                        <p>
                            Follow application updates from
                            submission and review through
                            interviews and final decisions.
                        </p>

                    </article>

                    <article class="feature-card">

                        <div class="feature-icon">◇</div>

                        <h3>Company Workspace</h3>

                        <p>
                            Publish internship openings,
                            review applicants, and manage
                            candidate decisions.
                        </p>

                    </article>

                </div>

            </div>

        </section>

        <!-- HOW IT WORKS -->

        <section
            class="section how-it-works"
            id="how-it-works"
        >

            <div class="container">

                <div class="section-heading">

                    <span class="eyebrow">YOUR JOURNEY</span>

                    <h2>
                        Three Steps to
                        <span>Get Started</span>
                    </h2>

                    <p>
                        Begin your internship journey with a
                        simple and organized process.
                    </p>

                </div>

                <div class="steps-grid">

                    <article class="step-card">

                        <span class="step-number">01</span>

                        <h3>Create Your Account</h3>

                        <p>
                            Register as a student or company
                            to access the features designed
                            for your role.
                        </p>

                    </article>

                    <article class="step-card">

                        <span class="step-number">02</span>

                        <h3>Find Your Opportunity</h3>

                        <p>
                            Discover internships as a student
                            or publish openings as a company.
                        </p>

                    </article>

                    <article class="step-card">

                        <span class="step-number">03</span>

                        <h3>Apply and Grow</h3>

                        <p>
                            Submit applications, follow their
                            status, and take your next career step.
                        </p>

                    </article>

                </div>

            </div>

        </section>

        <!-- CALL TO ACTION -->

        <section class="cta-section">

            <div class="container">

                <div class="cta-card">

                    <div class="cta-content">

                        <span class="eyebrow">
                            YOUR NEXT CHAPTER
                        </span>

                        <h2>
                            Find Your Place in the Professional World.
                        </h2>

                        <p>
                            Your next opportunity could be one
                            application away. Start your journey
                            with InternOrbit.
                        </p>

                        <?php if ($isLoggedIn): ?>

                            <a
                                href="<?= escapeOutput($dashboardUrl) ?>"
                                class="btn btn-primary"
                            >
                                Open Your Dashboard →
                            </a>

                        <?php else: ?>

                            <a
                                href="register.php"
                                class="btn btn-primary"
                            >
                                Join InternOrbit →
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>

    </main>

    <!-- FOOTER -->

    <footer class="footer">

        <div class="container footer-main">

            <div>

                <a href="index.php" class="brand">

                    <span class="brand-icon">

                        <svg
                            viewBox="0 0 40 40"
                            width="30"
                            height="30"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <circle
                                cx="20"
                                cy="20"
                                r="14"
                                stroke="white"
                                stroke-width="2.5"
                            />

                            <ellipse
                                cx="20"
                                cy="20"
                                rx="6"
                                ry="14"
                                stroke="white"
                                stroke-width="2"
                                transform="rotate(45 20 20)"
                            />

                            <circle
                                cx="20"
                                cy="20"
                                r="3"
                                fill="white"
                            />
                        </svg>

                    </span>

                    <span class="brand-name">InternOrbit</span>

                </a>

                <p class="footer-description">
                    Connecting student talent with internship
                    opportunities. Learn new skills, gain experience,
                    and build your future with InternOrbit.
                </p>

            </div>

            <div class="footer-links">

                <h3>Explore InternOrbit</h3>

                <div class="footer-links-list">
                    <a href="#home">Home</a>
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How It Works</a>
                    <a href="login.php">Login</a>
                    <a href="register.php">Create Account</a>
                </div>

            </div>

        </div>

        <div class="footer-bottom">

            <div class="container">
                &copy; <?= date("Y") ?> InternOrbit.
                Your Future Starts Here.
            </div>

        </div>

    </footer>

    <!-- MOBILE NAVIGATION -->

    <script>
        const menuToggle = document.getElementById("menuToggle");
        const navLinks = document.getElementById("navLinks");

        menuToggle.addEventListener("click", function () {
            const isOpen = navLinks.classList.toggle("active");

            menuToggle.setAttribute(
                "aria-expanded",
                String(isOpen)
            );

            menuToggle.textContent = isOpen ? "✕" : "☰";
        });

        navLinks.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                navLinks.classList.remove("active");

                menuToggle.setAttribute(
                    "aria-expanded",
                    "false"
                );

                menuToggle.textContent = "☰";
            });
        });
    </script>

</body>

</html>
