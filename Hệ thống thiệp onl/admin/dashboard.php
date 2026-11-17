<?php

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once "../config/database.php";

/*KIỂM TRA ADMIN */
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

$full_name = $_SESSION["full_name"] ?? "Quản trị viên";

/*THỐNG KÊ HỆ THỐNG */
$customer_count = 0;
$template_count = 0;
$wedding_count = 0;
$visit_count = 0;

/* Khách hàng */
$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'"
);

if ($result) {
    $customer_count = (int)$result->fetch_assoc()["total"];
}

/* Mẫu thiệp */
$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM templates
    WHERE status = 'active'"
);

if ($result) {
    $template_count = (int)$result->fetch_assoc()["total"];
}

/* Thiệp cưới */
$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM weddings"
);

if ($result) {
    $wedding_count = (int)$result->fetch_assoc()["total"];
}

/* Lượt truy cập */
$result = $conn->query(
    "SELECT COALESCE(SUM(visit_count), 0) AS total
    FROM invitation_links"
);

if ($result) {
    $visit_count = (int)$result->fetch_assoc()["total"];
}

/* THIỆP GẦN ĐÂY */
$recent_weddings = [];

$sql = "
    SELECT
        w.id,
        w.groom_name,
        w.bride_name,
        w.wedding_date,
        w.wedding_code,
        w.template_id,
        w.created_at,
        t.template_name,
        t.folder
    FROM weddings w
    LEFT JOIN templates t
        ON w.template_id = t.id
    ORDER BY w.id DESC
    LIMIT 4
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recent_weddings[] = $row;
    }

}

/* THÔNG BÁO */
$recent_customer_count = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
);

if ($result) {
    $recent_customer_count =
        (int)$result->fetch_assoc()["total"];
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title> Dashboard - thyu_nhun Weddings </title>

<!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet" >

<!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        /* RESET */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --dark: #3D0B12;
            --dark-soft: #4A101A;
            --wine: #8F1D2C;
            --wine-dark: #65131F;
            --wine-light: #b86c6c;
            --cream: #FFF8F6;
            --white: #ffffff;
            --champagne: #D4A73D;
            --text: #32191C;
            --text-light: #7E6669;
            --border: #ECD8D5;
            --green: #6ca582;
            --shadow:
                0 5px 25px rgba(48, 31, 29, .06);
        }

        html {
            scroll-behavior: smooth;
        }

    body {
        font-family: "Be Vietnam Pro", Arial, sans-serif;
        background: var(--cream);
        color: var(--text);
        min-height: 100vh;
    }

    /* LAYOUT */

    .admin-layout {
        display: flex;
        min-height: 100vh;
    }

    /* SIDEBAR */
        .sidebar {
            width: 245px;
            min-height: 100vh;
            background:
                linear-gradient(
                    180deg,
                    #3D0B12 0%,
                    #251c1e 65%,
                    #1d1719 100%
                );
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* họa tiết trang trí */
        .sidebar::after {
            content: "";
            position: absolute;
            bottom: -35px;
            left: -15px;
            width: 180px;
            height: 180px;
            border:
                1px solid
                rgba(201,164,106,.25);
            border-radius:
                70% 30% 65% 35%;
            transform:
                rotate(-30deg);
            pointer-events: none;
        }

        .brand {
            padding: 28px 20px 24px;
            text-align: center;
            border-bottom:
                1px solid
                rgba(255,255,255,.08);
        }

        .brand-mark {
            width: 55px;
            height: 55px;
            margin: auto;
            border:
                1px solid
                var(--champagne);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--champagne);
            font-family: "Playfair Display", serif;
            font-size: 23px;
            position: relative;
        }

        .brand-mark::before {
            content: "♡";
            position: absolute;
            font-size: 22px;
        }

        .brand-name {
            margin-top: 12px;
            color: var(--champagne);
            font-family: "Playfair Display", serif;
            font-size: 18px;
            letter-spacing: 1.5px;
        }

        .brand-sub {
            margin-top: 2px;
            color: rgba(255,255,255,.6);
            font-size: 8px;
            letter-spacing: 3px;
        }

        .menu {
            padding: 20px 12px;
            flex: 1;
        }

        .menu-title {
            padding: 8px 14px;
            color: rgba(255,255,255,.35);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 14px;
            margin: 4px 0;
            border-radius: 8px;
            color: rgba(255,255,255,.82);
            text-decoration: none;
            font-size: 13px;
            transition: .25s;
        }

        .menu-item i {
            width: 18px;
            text-align: center;
            font-size: 14px;
        }

        .menu-item:hover {
            background: rgba(255,255,255,.08);
            color: white;
        }


        .menu-item.active {
            background:
                linear-gradient(
                    135deg,
                    #a75858,
                    #8e4848
                );
            color: white;
            box-shadow:
                0 5px 15px
                rgba(155,79,79,.25);
        }

        .menu-divider {
            height: 1px;
            background:
                rgba(255,255,255,.08);
            margin: 15px 10px;
        }

        .logout {
            margin: 0 12px 20px;
        }

    /* MAIN */
        .main {
            margin-left: 245px;
            width: calc(100% - 245px);
            min-height: 100vh;
        }

        /*  TOPBAR */
        .topbar {
            height: 70px;
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 13px;
            font-size: 14px;
            font-weight: 600;
        }

        .page-title i {
            color: var(--wine);
        }

        .top-right {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .notification {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-light);
            position: relative;
            cursor: pointer;
        }

        .notification:hover {
            background: #f7f1ee;
            color: var(--wine);
        }

        .notification-badge {
            position: absolute;
            top: 1px;
            right: 0;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #c94c4c;
            color: white;
            font-size: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-text {
            text-align: right;
        }

        .user-text strong {
            display: block;
            font-size: 12px;
            font-weight: 600;
        }

        .user-text span {
            color: var(--text-light);
            font-size: 10px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background:
                linear-gradient(
                    135deg,
                    #e8cfc5,
                    #b98678
                );
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 15px;
        }

        /*  CONTENT */
        .content {
            padding: 32px;
            max-width: 1500px;
            margin: auto;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .welcome h1 span {
            color: var(--wine);
        }

        .welcome p {
            color: var(--text-light);
            font-size: 13px;
        }

        /* STAT CARDS*/
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: white;
            border: 1px solid rgba(235,226,221,.8);
            border-radius: 13px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition:
                transform .25s,
                box-shadow .25s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow:
                0 12px 30px
                rgba(48,31,29,.09);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-icon {
            width: 47px;
            height: 47px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .icon-red {
            background: #f8e4e4;
            color: #bf5c5c;
        }

        .icon-gold {
            background: #f8ecd8;
            color: #bd9458;
        }

        .icon-purple {
            background: #eee8f4;
            color: #9675aa;
        }

        .icon-green {
            background: #e5f3eb;
            color: #69a47f;
        }

        .stat-title {
            font-size: 12px;
            font-weight: 600;
            margin-top: 13px;
        }

        .stat-number {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 600;
            margin-top: 2px;
        }

        .stat-desc {
            color: var(--text-light);
            font-size: 10px;
            margin-top: 2px;
        }

        .stat-link {
            display: inline-block;
            margin-top: 16px;
            color: var(--wine);
            text-decoration: none;
            font-size: 10px;
        }

        .stat-link:hover {
            text-decoration: underline;
        }

        /* LOWER CONTENT */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 20px;
        }

        .panel {
            background: white;
            border: 1px solid
                rgba(235,226,221,.8);
            border-radius: 13px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .panel-header h2 {
            font-size: 15px;
            font-weight: 600;
        }

        .panel-header a {
            color: var(--wine);
            text-decoration: none;
            font-size: 10px;
        }

        .panel-header a:hover {
            text-decoration: underline;
        }

        /* RECENT WEDDING */
        .wedding-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid #f0ebe8;
        }

        .wedding-item:last-child {
            border-bottom: none;
        }


        .wedding-thumb {
            width: 82px;
            height: 105px;
            border-radius: 8px;
            overflow: hidden;
            background: #f3ece8;
            flex-shrink: 0;
            border: 1px solid #eadfd9;
            position: relative;
        }

        .wedding-thumb iframe {
            width: 100%;
            height: 100%;
            border: none;
            pointer-events: none;
        }

        .wedding-thumb img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            object-position: center;
        }

        .thumb-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b48b83;
            font-size: 25px;
            background:
                linear-gradient(
                    145deg,
                    #f7eee9,
                    #eee0da
                );
        }

        .wedding-info {
            flex: 1;
            min-width: 0;
        }

        .wedding-info h3 {
            font-size: 13px;
            font-weight: 600;
            color: #32191C;
            margin-bottom: 7px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wedding-info p {
            font-size: 10px;
            color: #7E6669;
            margin: 5px 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 20px;
            background: #e7f3ea;
            color: #58906d;
            font-size: 9px;
        }

        .wedding-actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .action-btn {
            padding: 7px 11px;
            border-radius: 6px;
            border: 1px solid #eadfd9;
            background: white;
            color: var(--wine);
            text-decoration: none;
            font-size: 9px;
        }

        .action-btn.primary {
            background: var(--wine);
            border-color: var(--wine);
            color: white;
        }

        /* NOTIFICATIONS */
        .notice {
            display: flex;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #f0ebe8;
        }

        .notice:last-child {
            border-bottom: none;
        }

        .notice-icon {
            width: 31px;
            height: 31px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6e8e8;
            color: var(--wine);
            font-size: 11px;
        }

        .notice-content {
            flex: 1;
        }

        .notice-content p {
            font-size: 10px;
            line-height: 1.5;
        }

        .notice-time {
            color: #aaa;
            font-size: 8px;
            margin-top: 3px;    
        }

        .empty {
            text-align: center;
            padding: 35px 10px;
            color: var(--text-light);
            font-size: 12px;
        }

        /*RESPONSIVE */
        @media (max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .brand-name,
            .brand-sub,
            .menu-title,
            .menu-item span {
                display: none;
            }

            .brand {
                padding: 18px 5px;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                font-size: 17px;
            }

            .menu-item {
                justify-content: center;
                padding: 13px 5px;
            }

            .menu-item i {
                margin: 0;
            }

            .main {
                margin-left: 70px;
                width: calc(100% - 70px);
            }

            .topbar {
                padding: 0 15px;
            }

            .page-title {
                font-size: 12px;
            }

            .user-text {
                display: none;
            }

            .content {
                padding: 20px 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .welcome h1 {
                font-size: 24px;
            }
        }

    @media (max-width: 480px) {
        .topbar {
            height: 62px;
        }

        .stats {
            gap: 12px;
        }

        .stat-card {
            padding: 16px;
        }

        .dashboard-grid {
            gap: 14px;
        }

        .panel {
            padding: 15px;
        }

        .wedding-item {
            align-items: flex-start;
        }

        .wedding-actions {
            flex-wrap: wrap;
        }

    }

    
/* ==========================================================
   ADMIN — VIETNAMESE RED + GOLD FINAL COLOR SYNC
   Shared visual identity with Customer system
========================================================== */
:root{
    --vn-red:#8F1D2C;
    --vn-red-dark:#65131F;
    --vn-red-deep:#3D0B12;
    --vn-red-accent:#B52A3A;
    --vn-red-soft:#FCE8E7;
    --vn-red-light:#F3C7C5;
    --vn-bg:#FFF8F6;
    --vn-card:#FFFFFF;
    --vn-text:#32191C;
    --vn-muted:#7E6669;
    --vn-border:#ECD8D5;
    --vn-gold:#D4A73D;
    --vn-gold-light:#F2D58A;
}

::selection{
    background:var(--vn-red-soft);
    color:var(--vn-red-dark);
}

/* Admin navigation */
.sidebar{
    background:linear-gradient(180deg,var(--vn-red-deep) 0%,var(--vn-red-dark) 100%) !important;
}
.brand-mark,
.brand-icon,
.brand-symbol{
    background:linear-gradient(145deg,var(--vn-red-accent),var(--vn-red-dark)) !important;
    border:1px solid rgba(242,213,138,.38) !important;
    color:var(--vn-gold-light) !important;
}
.menu-item.active,
.menu-item:hover{
    background:linear-gradient(90deg,rgba(181,42,58,.92),rgba(143,29,44,.74)) !important;
    color:#fff !important;
}
.menu-item.active i,
.menu-item:hover i{
    color:var(--vn-gold-light) !important;
}
.menu-title{
    color:rgba(242,213,138,.62) !important;
}

/* Shared buttons and interactive elements */
.btn-primary,
.primary-btn,
.save-btn,
.submit-btn,
.add-btn,
.action-btn.primary{
    background:linear-gradient(135deg,var(--vn-red),var(--vn-red-dark)) !important;
    border-color:var(--vn-red) !important;
    color:#fff !important;
}
.btn-primary:hover,
.primary-btn:hover,
.save-btn:hover,
.submit-btn:hover,
.add-btn:hover,
.action-btn.primary:hover{
    background:linear-gradient(135deg,var(--vn-red-accent),var(--vn-red)) !important;
}
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible{
    outline:2px solid var(--vn-red) !important;
    outline-offset:2px;
}

/* Cards, forms, tables */
.card,
.panel,
.stat-card,
.table-card,
.content-card,
.form-card,
.settings-card{
    border-color:var(--vn-border) !important;
}
input:focus,
select:focus,
textarea:focus{
    border-color:var(--vn-red) !important;
    box-shadow:0 0 0 3px rgba(143,29,44,.10) !important;
}
.badge,
.status-badge,
.chip{
    border-color:rgba(143,29,44,.18);
}
a:hover{
    color:var(--vn-red);
}

/* Gold accent used sparingly for premium admin identity */
.brand-sub,
.brand-area span,
.logo-subtitle{
    color:var(--vn-gold-light) !important;
}

</style>

</head>

<body>

    <div class="admin-layout">

        <!--  SIDEBAR -->
        <aside class="sidebar">

            <div class="brand">
                <div class="brand-mark"> ♡ </div>
                <div class="brand-name"> thyu_nhun</div>
                <div class="brand-sub"> WEDDINGS</div>
            </div>

            <nav class="menu">
                <div class="menu-title"> Menu chính  </div>

                <a href="dashboard.php"
                    class="menu-item active">
                    <i class="fa-solid fa-house"></i>
                    <span>Trang chủ</span>
                </a>

                <a href="customers.php" class="menu-item">
                    <i class="fa-solid fa-users"></i>
                    <span>  Quản lý khách hàng </span>
                </a>

                <a href="templates.php" class="menu-item" >
                    <i class="fa-solid fa-palette"></i>
                    <span> Mẫu thiệp </span>
                </a>

                <a href="weddings.php" class="menu-item">
                    <i class="fa-regular fa-envelope"></i>
                    <span>Thiệp cưới của tôi </span>
                </a>

                <a href="statistics.php" class="menu-item">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Thống kê </span>
                </a>

                <a href="settings.php" class="menu-item">
                    <i class="fa-solid fa-gear"></i>
                    <span> Cài đặt </span>
                </a>

                <div class="menu-divider"></div>

                <a href="../auth/logout.php" class="menu-item logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Đăng xuất </span>
                </a>

            </nav>

        </aside>

<!-- MAIN-->
        <main class="main">

            <!-- TOPBAR -->
            <header class="topbar">

                <div class="page-title">
                    <i class="fa-solid fa-bars"></i>
                    <span>Trang chủ</span>
                </div>

                <div class="top-right">

                    <div class="notification">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($recent_customer_count > 0): ?>
                            <span class="notification-badge">
                                <?= $recent_customer_count ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="user-area">
                        <div class="user-text">
                            <strong> Xin chào, <?= htmlspecialchars($full_name) ?> </strong>
                            <span> Quản trị viên </span>
                        </div>

                        <div class="avatar">
                            <i class="fa-solid fa-user"></i>
                        </div>

                    </div>

                </div>

            </header>

            <!-- CONTENT -->
            <div class="content">

                <!-- WELCOME -->
                <section class="welcome">
                    <h1> Xin chào,
                        <span>
                            <?= htmlspecialchars($full_name) ?>
                        </span>
                        👋
                    </h1>

                    <p>
                        Chúc bạn một ngày làm việc hiệu quả
                        và tạo nên những thiệp cưới thật đẹp!
                    </p>
                </section>

                <!-- STATS -->
                <section class="stats">

                    <!-- CUSTOMER -->
                    <div class="stat-card">

                        <div class="stat-top">
                            <div class="stat-icon icon-red">
                                <i class="fa-solid fa-users"></i>
                            </div>
                        </div>

                        <div class="stat-title"> Khách hàng </div>

                        <div class="stat-number">
                            <?= number_format($customer_count) ?>
                        </div>

                        <div class="stat-desc"> Tổng khách hàng </div>

                        <a href="customers.php" class="stat-link">
                            Xem chi tiết →
                        </a>

                    </div>

                    <!-- TEMPLATE -->
                    <div class="stat-card">

                        <div class="stat-top">

                            <div class="stat-icon icon-gold">
                                <i class="fa-solid fa-palette"></i>
                            </div>

                        </div>

                        <div class="stat-title"> Mẫu thiệp </div>

                        <div class="stat-number">
                            <?= number_format($template_count) ?>
                        </div>

                        <div class="stat-desc"> Đang có sẵn </div>

                        <a href="templates.php" class="stat-link"> Xem chi tiết → </a>

                    </div>

                    <!-- WEDDING -->
                    <div class="stat-card">

                        <div class="stat-top">

                            <div class="stat-icon icon-purple">
                                <i class="fa-regular fa-envelope"></i>
                            </div>

                        </div>

                        <div class="stat-title">Thiệp đã tạo</div>

                        <div class="stat-number">
                            <?= number_format($wedding_count) ?>
                        </div>

                        <div class="stat-desc"> Thiệp của khách hàng</div>

                        <a href="weddings.php" class="stat-link"> Xem chi tiết →</a>

                    </div>

                    <!-- VISITS -->
                    <div class="stat-card">

                        <div class="stat-top">

                            <div class="stat-icon icon-green">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>

                        </div>

                        <div class="stat-title"> Lượt truy cập</div>

                        <div class="stat-number">
                            <?= number_format($visit_count) ?>
                        </div>

                        <div class="stat-desc"> Lượt xem thiệp </div>

                        <a href="statistics.php" class="stat-link"> Xem chi tiết →</a>

                    </div>

                </section>

                <!-- LOWER GRID -->
                <section class="dashboard-grid">

                    <!-- RECENT WEDDINGS -->
                    <div class="panel">

                        <div class="panel-header">
                            <h2>Thiệp gần đây</h2>
                            <a href="weddings.php">
                                Xem tất cả →</a>

                        </div>

                        <?php if (!empty($recent_weddings)): ?>
                        <?php foreach (
                            $recent_weddings
                            as $wedding
                        ): ?>

                        <div class="wedding-item">

                            <div class="wedding-thumb">
                                <?php if (!empty($wedding["folder"])): ?>

                                    <iframe
                                        src="../<?= htmlspecialchars($wedding["folder"]) ?>/"
                                        loading="lazy"
                                        title="Preview thiệp"
                                    ></iframe>

                                <?php else: ?>

                                    <div class="thumb-placeholder">
                                        <i class="fa-regular fa-image"></i>
                                    </div>

                                <?php endif; ?>
                            </div>

                            <div class="wedding-info">

                                <h3>
                                    <?= htmlspecialchars(
                                        trim(($wedding["groom_name"] ?? "") . " & " . ($wedding["bride_name"] ?? ""))
                                    ) ?>
                                </h3>

                                <p>
                                    <i class="fa-regular fa-calendar"></i>
                                    <?php if (!empty($wedding["wedding_date"])): ?>
                                        <?= date("d/m/Y", strtotime($wedding["wedding_date"])) ?>
                                    <?php else: ?>
                                        Chưa có ngày cưới
                                    <?php endif; ?>
                                </p>

                                <p>
                                    <i class="fa-solid fa-palette"></i>
                                    <?= htmlspecialchars($wedding["template_name"] ?? "Chưa chọn mẫu") ?>
                                </p>

                                <span class="status-badge">
                                    <i class="fa-solid fa-circle"></i>
                                    Đã tạo
                                </span>

                                <div class="wedding-actions">

                                    <?php if (!empty($wedding["wedding_code"])): ?>
                                        <a
                                            href="../invite.php?code=<?= urlencode($wedding["wedding_code"]) ?>"
                                            target="_blank"
                                            class="action-btn primary"
                                        >
                                            <i class="fa-regular fa-eye"></i> Xem
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        href="weddings.php"
                                        class="action-btn"
                                    >
                                        Chi tiết
                                    </a>

                                </div>

                            </div>

                        </div>

                        <?php endforeach; ?>
                        <?php else: ?>

                        <div class="empty">

                            <i
                                class="fa-regular fa-envelope"
                                style="
                                    font-size:28px;
                                    margin-bottom:10px;
                                    display:block;
                                "
                            ></i>

                            Chưa có thiệp cưới nào.

                        </div>

                        <?php endif; ?>

                    </div>

                    <!-- NOTIFICATIONS-->
                    <div class="panel">

                        <div class="panel-header">
                            <h2>Thông báo</h2>
                        </div>

                        <?php if (
                            $recent_customer_count > 0
                        ): ?>

                        <div class="notice">

                            <div class="notice-icon">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>

                            <div class="notice-content">

                                <p>
                                    Bạn có
                                    <strong>
                                        <?= $recent_customer_count ?>
                                    </strong>
                                    khách hàng mới hôm nay.
                                </p>

                                <div class="notice-time">
                                    Trong 24 giờ qua
                                </div>

                            </div>

                        </div>

                        <?php endif; ?>

                        <div class="notice">

                            <div class="notice-icon">
                                <i class="fa-solid fa-envelope"></i>
                            </div>

                            <div class="notice-content">

                            <p>
                                Hệ thống hiện có
                                <strong>
                                    <?= $wedding_count ?>
                                </strong>
                                thiệp cưới.
                            </p>

                            <div class="notice-time">
                                Thống kê hệ thống
                            </div>

                        </div>

                    </div>

                    <div class="notice">

                        <div class="notice-icon">
                            <i class="fa-solid fa-palette"></i>
                        </div>

                        <div class="notice-content">

                            <p>
                                Hiện có
                                <strong>
                                    <?= $template_count ?>
                                </strong>
                                mẫu thiệp đang hoạt động.
                            </p>

                            <div class="notice-time">
                                Mẫu thiệp
                            </div>

                        </div>

                    </div>

                    <div class="notice">

                        <div class="notice-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>

                        <div class="notice-content">

                            <p>
                                Tổng lượt xem thiệp:
                                <strong>
                                    <?= number_format(
                                        $visit_count
                                    ) ?>
                                </strong>
                            </p>

                            <div class="notice-time">
                                Từ trước đến nay
                            </div>

                        </div>

                    </div>

                    <div
                        style="
                            text-align:right;
                            margin-top:15px;
                        "
                    >

                        <a
                            href="statistics.php"
                            style="
                                color:#8F1D2C;
                                text-decoration:none;
                                font-size:10px;
                            "
                        >
                            Xem tất cả thông báo →
                        </a>

                    </div>

                </div>

                </section>

            </div>

        </main>

    </div>

</body>

</html>