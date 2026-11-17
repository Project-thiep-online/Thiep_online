<?php

/* ==========================================================
   THYU_NHUN WEDDINGS
   CUSTOMER - SETTINGS PAGE

   NOTE CHO NGƯỜI PHÁT TRIỂN:
   ----------------------------------------------------------
   🎨 Muốn đổi concept toàn bộ giao diện:
      → Sửa khu vực "THEME CONFIG" bên dưới.

   🧭 Sidebar:
      → Khu vực "SIDEBAR"

   📄 Nội dung trang:
      → Khu vực "SETTINGS CONTENT"

   🦶 Footer:
      → Khu vực "FOOTER"

   📱 Responsive:
      → Cuối file CSS.

   KHÔNG sửa database trong file này.
========================================================== */


session_start();

require_once "../config/database.php";


/* ==========================================================
   🔐 KIỂM TRA QUYỀN CUSTOMER
========================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {
    header("Location: ../auth/login.php");
    exit;
}


$user_id = intval($_SESSION["user_id"]);


/* ==========================================================
   👤 LẤY THÔNG TIN TÀI KHOẢN
   ----------------------------------------------------------
   Chỉ lấy những thông tin đã có trong database.
   Không tạo thêm cột mới.
========================================================== */

$username = "Khách hàng";

$stmt = $conn->prepare(
    "SELECT username
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            $username =
                trim(
                    $user["username"] ?? "Khách hàng"
                );

        }

    }

    $stmt->close();
}


/* ==========================================================
   🎨 THEME CONFIG
   ----------------------------------------------------------
   ⭐ ĐÂY LÀ KHU VỰC QUAN TRỌNG NHẤT ⭐

   Sau này muốn đổi concept web:
   → Chủ yếu chỉnh các biến màu tại đây.

   Không cần tìm từng màu trong toàn bộ CSS.
========================================================== */

$theme = [

    /* Màu sidebar chính */
    "sidebar" => "#3D0B12",

    /* Màu sidebar phụ */
    "sidebar_light" => "#65131F",

    /* Màu thương hiệu */
    "primary" => "#8F1D2C",

    /* Màu hover */
    "primary_dark" => "#65131F",

    /* Màu nền toàn trang */
    "background" => "#FFF8F6",

    /* Màu card */
    "card" => "#ffffff",

    /* Màu nền mềm */
    "soft" => "#FCE8E7",

    /* Màu chữ chính */
    "text" => "#32191C",

    /* Màu chữ phụ */
    "muted" => "#7E6669",

    /* Màu viền */
    "border" => "#ECD8D5"

];


/* ==========================================================
   🏷️ THÔNG TIN THƯƠNG HIỆU
   ----------------------------------------------------------
   Sau này đổi thương hiệu:
   → Chỉ cần sửa khu vực này.
========================================================== */

$brand_name =
    "thyu_nhun Weddings";

$brand_domain =
    "thyu_nhun.id.vn";

$brand_phone =
    "0968117006";

$brand_facebook =
    "https://www.facebook.com/nhung.nnthuy/";


/* ==========================================================
   🧭 MENU SIDEBAR
   ----------------------------------------------------------
   Dashboard + Settings là 2 trang có đầy đủ menu.
========================================================== */

$menu_main = [

    [
        "icon" => "home",
        "name" => "Trang chủ",
        "url" => "dashboard.php"
    ],

    [
        "icon" => "invite",
        "name" => "Thông tin thiệp",
        "url" => "wedding_info.php"
    ],

    [
        "icon" => "templates",
        "name" => "Mẫu thiệp",
        "url" => "templates.php"
    ],

    [
        "icon" => "album",
        "name" => "Album ảnh",
        "url" => "wedding_images.php"
    ],

    [
        "icon" => "guests",
        "name" => "Khách mời",
        "url" => "guests.php"
    ],

    [
        "icon" => "links",
        "name" => "Link thiệp",
        "url" => "invitation_links.php"
    ],

    [
        "icon" => "preview",
        "name" => "Xem thiệp",
        "url" => "preview_template.php"
    ]

];


$menu_system = [

    [
        "icon" => "settings",
        "name" => "Cài đặt",
        "url" => "settings.php"
    ]

];


/* ==========================================================
   🛡️ HÀM ESCAPE HTML
========================================================== */

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}



function customer_nav_icon($name)
{
    $icons = [
        "home" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 10.5 12 3.7l8.5 6.8v9.2a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5z"/><path d="M9 21v-6.2h6V21"/></svg>',
        "invite" => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5"/><path d="M7 9h10M7 13h4.5"/><path d="M17.2 13.5c0 1.7-2.2 3-2.2 3s-2.2-1.3-2.2-3a1.15 1.15 0 0 1 2.2-.5 1.15 1.15 0 0 1 2.2.5z"/></svg>',
        "templates" => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="12.5" height="14" rx="2"/><path d="M8 4.5h8.5a2 2 0 0 1 2 2v9.5"/><path d="m18.2 4.1.55 1.45 1.45.55-1.45.55-.55 1.45-.55-1.45-1.45-.55 1.45-.55z"/></svg>',
        "album" => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="8.5" cy="9" r="1.4"/><path d="m5.5 17 4.4-4.2 3.2 2.9 2.2-2.1 3.2 3.4"/></svg>',
        "guests" => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="9" r="3"/><circle cx="17" cy="10" r="2.2"/><path d="M3.8 20c.5-3.4 2.4-5.2 5.2-5.2s4.7 1.8 5.2 5.2"/><path d="M14.4 15.7c2.9.2 4.8 1.7 5.3 4.3"/></svg>',
        "links" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.2 14.8-1.4 1.4a3.7 3.7 0 0 1-5.2-5.2l3.1-3.1a3.7 3.7 0 0 1 5.2 0"/><path d="m14.8 9.2 1.4-1.4a3.7 3.7 0 0 1 5.2 5.2L18.3 16a3.7 3.7 0 0 1-5.2 0"/><path d="m8.7 15.3 6.6-6.6"/></svg>',
        "preview" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.7 12s3.4-5.5 9.3-5.5S21.3 12 21.3 12 17.9 17.5 12 17.5 2.7 12 2.7 12z"/><circle cx="12" cy="12" r="2.4"/></svg>',
        "settings" => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h9M17 7h3M4 17h4M12 17h8"/><circle cx="15" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg>',
    ];
    return $icons[$name] ?? "";
}

?>

<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Cài đặt - <?= e($brand_name) ?>
    </title>


    <!-- =====================================================
         🎨 FONT
         -----------------------------------------------------
         Font serif tạo cảm giác luxury.
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <style>

        /* ==================================================
           🎨 THEME VARIABLES

           ⭐ ĐỔI CONCEPT → CHỈNH Ở ĐÂY
        ================================================== */

        :root {

            --sidebar:
                <?= e($theme["sidebar"]) ?>;

            --sidebar-light:
                <?= e($theme["sidebar_light"]) ?>;

            --primary:
                <?= e($theme["primary"]) ?>;

            --primary-dark:
                <?= e($theme["primary_dark"]) ?>;

            --background:
                <?= e($theme["background"]) ?>;

            --card:
                <?= e($theme["card"]) ?>;

            --soft:
                <?= e($theme["soft"]) ?>;

            --text:
                <?= e($theme["text"]) ?>;

            --muted:
                <?= e($theme["muted"]) ?>;

            --border:
                <?= e($theme["border"]) ?>;

            --serif:
                "Cormorant Garamond",
                Georgia,
                serif;

            --sans:
                "DM Sans",
                Arial,
                sans-serif;

        }


        /* ==================================================
           🔧 RESET
        ================================================== */

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            background:
                var(--background);

            color:
                var(--text);

            font-family:
                var(--sans);

        }


        a {
            text-decoration: none;
            color: inherit;
        }


        button,
        input {
            font-family: inherit;
        }


        /* ==================================================
           🧭 LAYOUT
        ================================================== */

        .app {

            min-height: 100vh;

            display: flex;

        }


        /* ==================================================
           🧭 SIDEBAR
           --------------------------------------------------
           Đây là sidebar dùng cho Dashboard + Settings.
        ================================================== */

        .sidebar {

            width: 258px;

            min-height: 100vh;

            position: fixed;

            top: 0;
            left: 0;

            background:
                linear-gradient(
                    180deg,
                    var(--sidebar),
                    #2A070C
                );

            color: white;

            display: flex;

            flex-direction: column;

            z-index: 100;

            overflow-y: auto;

        }


        /* ==================================================
           🏷️ BRAND SIDEBAR
        ================================================== */

        .brand {

            padding:
                30px 25px 28px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

        }


        .brand-mark {

            width: 54px;
            height: 54px;

            border-radius: 16px;

            display: flex;

            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    145deg,
                    #D95B64,
                    #7A1524
                );

            font-size: 26px;

            margin-bottom: 15px;

            box-shadow:
                0 10px 25px
                rgba(0,0,0,.18);

        }


        .brand-name {

            font-family:
                var(--serif);

            font-size: 27px;

            line-height: 1;

            font-weight: 600;

            letter-spacing: -.4px;

        }


        .brand-sub {

            margin-top: 8px;

            font-size: 9px;

            letter-spacing: 2.4px;

            text-transform: uppercase;

            opacity: .58;

        }


        /* ==================================================
           📂 SIDEBAR SECTION
        ================================================== */

        .sidebar-section {

            padding:
                25px 15px 0;

        }


        .sidebar-title {

            padding:
                0 15px 11px;

            font-size: 10px;

            letter-spacing: 1.7px;

            text-transform: uppercase;

            color:
                rgba(255,255,255,.42);

        }


        /* ==================================================
           🔗 SIDEBAR ITEM
        ================================================== */

        .nav-item {

            display: flex;

            align-items: center;

            gap: 14px;

            padding:
                12px 15px;

            margin-bottom: 5px;

            border-radius: 10px;

            color:
                rgba(255,255,255,.75);

            font-size: 13px;

            transition:
                .2s ease;

        }


        .nav-item:hover {

            background:
                rgba(255,255,255,.07);

            color: white;

            transform:
                translateX(2px);

        }


        .nav-item.active {

            background:
                linear-gradient(
                    90deg,
                    var(--primary),
                    var(--sidebar-light)
                );

            color: white;

            box-shadow:
                0 7px 18px
                rgba(0,0,0,.16);

        }


        .nav-icon {

            width: 22px;

            text-align: center;

            font-size: 17px;

            opacity: .9;

        }


        /* ==================================================
           🚪 LOGOUT
        ================================================== */

        .logout-area {

            margin-top: auto;

            padding: 18px 15px 25px;

        }


        .logout {

            display: flex;

            align-items: center;

            gap: 14px;

            padding:
                12px 15px;

            border-radius: 10px;

            color:
                rgba(255,255,255,.7);

            font-size: 13px;

            border-top:
                1px solid
                rgba(255,255,255,.08);

        }


        .logout:hover {

            background:
                rgba(255,255,255,.06);

            color: white;

        }


        /* ==================================================
           📄 MAIN
        ================================================== */

        .main {

            width: calc(100% - 258px);

            margin-left: 258px;

            min-height: 100vh;

        }


        /* ==================================================
           🔝 TOPBAR
        ================================================== */

        .topbar {

            height: 82px;

            background:
                rgba(255,255,255,.92);

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 42px;

            position: sticky;

            top: 0;

            z-index: 50;

            backdrop-filter:
                blur(12px);

        }


        .breadcrumb {

            font-size: 13px;

            color:
                var(--muted);

        }


        .breadcrumb strong {

            color:
                var(--text);

            margin-left: 8px;

        }


        .user-area {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .notification {

            width: 42px;
            height: 42px;

            border:
                1px solid var(--border);

            border-radius: 50%;

            display: flex;

            align-items: center;
            justify-content: center;

            color:
                var(--primary);

            background: white;

        }


        .user-text {

            text-align: right;

        }


        .user-name {

            font-size: 13px;

            font-weight: 600;

        }


        .user-role {

            font-size: 10px;

            color:
                var(--muted);

            margin-top: 3px;

        }


        .avatar {

            width: 46px;
            height: 46px;

            border-radius: 50%;

            background:
                linear-gradient(
                    145deg,
                    #EBCFD0,
                    #E5B0B3
                );

            color:
                var(--primary);

            display: flex;

            align-items: center;
            justify-content: center;

            font-family:
                var(--serif);

            font-size: 22px;

            font-weight: 600;

        }


        /* ==================================================
           📄 PAGE
        ================================================== */

        .page {

            padding:
                42px;

            max-width:
                1450px;

            margin: auto;

        }


        /* ==================================================
           🏷️ PAGE TITLE
        ================================================== */

        .page-heading {

            margin-bottom: 32px;

        }


        .eyebrow {

            color:
                var(--primary);

            font-size: 10px;

            letter-spacing: 2px;

            text-transform: uppercase;

            font-weight: 600;

            margin-bottom: 8px;

        }


        .page-title {

            margin: 0;

            font-family:
                var(--serif);

            font-size: 45px;

            line-height: 1;

            font-weight: 500;

        }


        .page-description {

            margin:
                10px 0 0;

            color:
                var(--muted);

            font-size: 13px;

        }


        /* ==================================================
           📐 SETTINGS GRID
        ================================================== */

        .settings-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 22px;

        }


        /* ==================================================
           🃏 SETTINGS CARD
        ================================================== */

        .settings-card {

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius: 18px;

            padding: 27px;

            box-shadow:
                0 8px 30px
                rgba(61,48,48,.045);

            position: relative;

            overflow: hidden;

        }


        .settings-card::after {

            content: "";

            position: absolute;

            width: 150px;
            height: 150px;

            right: -75px;
            top: -75px;

            border:
                1px solid
                rgba(143,29,44,.09);

            border-radius: 50%;

        }


        .card-heading {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 24px;

        }


        .card-icon {

            width: 46px;
            height: 46px;

            border-radius: 13px;

            background:
                var(--soft);

            display: flex;

            align-items: center;
            justify-content: center;

            color:
                var(--primary);

            font-size: 20px;

        }


        .card-title {

            margin: 0;

            font-family:
                var(--serif);

            font-size: 25px;

            font-weight: 600;

        }


        .card-description {

            margin:
                4px 0 0;

            color:
                var(--muted);

            font-size: 11px;

        }


        /* ==================================================
           👤 ACCOUNT INFO
        ================================================== */

        .info-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding:
                15px 0;

            border-bottom:
                1px solid var(--border);

        }


        .info-row:last-child {

            border-bottom: none;

        }


        .info-label {

            font-size: 12px;

            color:
                var(--muted);

        }


        .info-value {

            font-size: 13px;

            font-weight: 500;

            color:
                var(--text);

            text-align: right;

        }


        /* ==================================================
           🏷️ BRAND CARD
        ================================================== */

        .brand-preview {

            padding: 22px;

            border-radius: 14px;

            background:
                linear-gradient(
                    135deg,
                    #53131C,
                    #7A1524
                );

            color: white;

            margin-bottom: 18px;

        }


        .brand-preview-name {

            font-family:
                var(--serif);

            font-size: 30px;

            font-weight: 600;

        }


        .brand-preview-domain {

            margin-top: 7px;

            font-size: 9px;

            letter-spacing: 2px;

            text-transform: uppercase;

            opacity: .65;

        }


        /* ==================================================
           📞 CONTACT
        ================================================== */

        .contact-item {

            display: flex;

            align-items: center;

            gap: 13px;

            padding:
                13px 0;

            border-bottom:
                1px solid var(--border);

        }


        .contact-item:last-child {

            border-bottom: none;

        }


        .contact-icon {

            width: 36px;
            height: 36px;

            border-radius: 10px;

            background:
                var(--soft);

            display: flex;

            align-items: center;
            justify-content: center;

            color:
                var(--primary);

        }


        .contact-label {

            font-size: 10px;

            color:
                var(--muted);

            margin-bottom: 3px;

        }


        .contact-value {

            font-size: 13px;

        }


        /* ==================================================
           🎨 DESIGN PREVIEW
        ================================================== */

        .color-preview {

            display: flex;

            gap: 12px;

            margin:
                18px 0;

        }


        .color-box {

            width: 46px;
            height: 46px;

            border-radius: 12px;

            border:
                1px solid
                rgba(0,0,0,.06);

        }


        .color-sidebar {

            background:
                var(--sidebar);

        }


        .color-primary {

            background:
                var(--primary);

        }


        .color-soft {

            background:
                var(--soft);

        }


        .color-background {

            background:
                var(--background);

        }


        .design-note {

            padding:
                14px 16px;

            border-radius: 12px;

            background:
                var(--soft);

            color:
                var(--muted);

            font-size: 11px;

            line-height: 1.7;

        }


        /* ==================================================
           🔘 BUTTON
        ================================================== */

        .button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding:
                11px 17px;

            border-radius: 9px;

            border: none;

            background:
                var(--primary);

            color: white;

            font-size: 12px;

            cursor: pointer;

            transition:
                .2s ease;

        }


        .button:hover {

            background:
                var(--primary-dark);

            transform:
                translateY(-1px);

        }


        .button-outline {

            background: white;

            color:
                var(--primary);

            border:
                1px solid var(--border);

        }


        .button-outline:hover {

            background:
                var(--soft);

        }


        /* ==================================================
           🦶 FOOTER
        ================================================== */

        .footer {

            margin-top: 55px;

            padding:
                28px 0;

            border-top:
                1px solid var(--border);

            display: flex;

            justify-content: space-between;

            align-items: center;

            color:
                var(--muted);

            font-size: 11px;

        }


        .footer-brand {

            font-family:
                var(--serif);

            font-size: 18px;

            color:
                var(--primary);

        }


        .footer-right {

            text-align: right;

        }


        /* ==================================================
           📱 RESPONSIVE
        ================================================== */

        @media (max-width: 1050px) {

            .sidebar {

                width: 235px;

            }

            .main {

                width: calc(100% - 235px);

                margin-left: 235px;

            }

            .page {

                padding: 30px;

            }

        }


        @media (max-width: 850px) {

            .sidebar {

                width: 75px;

            }


            .brand {

                padding:
                    20px 10px;

                text-align: center;

            }


            .brand-mark {

                margin:
                    0 auto 10px;

            }


            .brand-name,
            .brand-sub,
            .sidebar-title,
            .nav-item span:not(.nav-icon),
            .logout span:not(.nav-icon) {

                display: none;

            }


            .sidebar-section {

                padding:
                    18px 8px 0;

            }


            .nav-item {

                justify-content: center;

                padding:
                    13px 8px;

            }


            .logout-area {

                padding:
                    15px 8px;

            }


            .logout {

                justify-content: center;

            }


            .main {

                width: calc(100% - 75px);

                margin-left: 75px;

            }


            .topbar {

                padding:
                    0 22px;

            }


            .settings-grid {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 600px) {

            .topbar {

                height: 70px;

                padding:
                    0 15px;

            }


            .breadcrumb {

                font-size: 11px;

            }


            .user-text {

                display: none;

            }


            .page {

                padding:
                    25px 15px;

            }


            .page-title {

                font-size: 38px;

            }


            .settings-card {

                padding: 20px;

            }


            .footer {

                flex-direction: column;

                align-items: flex-start;

                gap: 12px;

            }


            .footer-right {

                text-align: left;

            }

        }

    
        /* ==========================================================
           VIETNAMESE RED — GLOBAL CUSTOMER COLOR SYNC
           Shared with auth/login.php
        ========================================================== */
        :root {
            --vn-red: #8F1D2C;
            --vn-red-dark: #65131F;
            --vn-red-deep: #3D0B12;
            --vn-red-accent: #B52A3A;
            --vn-red-soft: #FCE8E7;
            --vn-red-light: #F3C7C5;
            --vn-bg: #FFF8F6;
            --vn-card: #FFFFFF;
            --vn-text: #32191C;
            --vn-muted: #7E6669;
            --vn-border: #ECD8D5;
            --vn-gold: #D4A73D;
        }

        ::selection {
            background: var(--vn-red-soft);
            color: var(--vn-red-dark);
        }

        a:hover {
            color: var(--vn-red);
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible {
            outline-color: var(--vn-red);
        }

    
        /* ==========================================================
           GOLD BRAND LOGO + ABSTRACT NAVIGATION ICONS
        ========================================================== */
        .brand-symbol,
        .brand-mark {
            background: linear-gradient(145deg, #B52A3A 0%, #8F1D2C 55%, #65131F 100%) !important;
            border: 1px solid rgba(242,213,138,.34);
            color: #F2D58A !important;
            overflow: hidden;
            position: relative;
        }

        .brand-symbol::after,
        .brand-mark::after {
            content: "";
            position: absolute;
            inset: 7px;
            border: 1px solid rgba(242,213,138,.18);
            border-radius: inherit;
            pointer-events: none;
        }

        .brand-logo-svg {
            width: 31px;
            height: 31px;
            display: block;
            position: relative;
            z-index: 1;
            color: #F2D58A;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.15;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .menu-icon,
        .nav-icon {
            width: 22px !important;
            height: 22px;
            min-width: 22px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            color: currentColor;
            opacity: .92;
        }

        .menu-icon svg,
        .nav-icon svg {
            width: 19px;
            height: 19px;
            display: block;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .menu-item.active .menu-icon,
        .nav-item.active .nav-icon {
            color: #F2D58A;
            opacity: 1;
        }

        .menu-item:hover .menu-icon,
        .nav-item:hover .nav-icon {
            color: #F2D58A;
        }

    
/* ================= FINAL CUSTOMER BRAND SYNC ================= */
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
.brand-symbol,.brand-mark,.brand-icon{
    background:linear-gradient(145deg,#B52A3A 0%,#8F1D2C 55%,#65131F 100%) !important;
    border:1px solid rgba(242,213,138,.34) !important;
    color:#F2D58A !important;
    position:relative;
    overflow:hidden;
}
.brand-symbol::after,.brand-mark::after,.brand-icon::after{
    content:"";
    position:absolute;
    inset:7px;
    border:1px solid rgba(242,213,138,.18);
    border-radius:inherit;
    pointer-events:none;
}
.brand-logo-svg{
    width:31px;height:31px;display:block;position:relative;z-index:1;
    color:#F2D58A;fill:none;stroke:currentColor;stroke-width:2.15;
    stroke-linecap:round;stroke-linejoin:round;
}
.menu-icon,.nav-icon{
    width:22px !important;height:22px;min-width:22px;
    display:inline-flex !important;align-items:center;justify-content:center;
    color:currentColor;opacity:.94;
}
.menu-icon svg,.nav-icon svg{
    width:19px;height:19px;display:block;fill:none;stroke:currentColor;
    stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;
}
.menu-item.active .menu-icon,.nav-item.active .nav-icon,
.menu-item:hover .menu-icon,.nav-item:hover .nav-icon{
    color:#F2D58A !important;opacity:1;
}


.action-icon svg,.stat-icon svg,.section-mini-icon svg,.card-icon svg,.notification svg{
    width:22px;height:22px;display:block;fill:none;stroke:currentColor;
    stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;
}

</style>

</head>


<body>


<div class="app">


    <!-- ==================================================
         🧭 SIDEBAR
         --------------------------------------------------
         FULL MENU CHO TRANG CÀI ĐẶT
    ================================================== -->

    <aside class="sidebar">


        <!-- ==================================================
             🏷️ BRAND
        ================================================== -->

        <div class="brand">

            <div class="brand-mark"><svg class="brand-logo-svg" viewBox="0 0 64 64" aria-label="Wedding logo" role="img">
    <path d="M17 19c-5 0-9 4-9 9 0 11 12.5 19 24 28 11.5-9 24-17 24-28 0-5-4-9-9-9-6 0-11 4.5-15 10-4-5.5-9-10-15-10Z"/>
    <path d="M22 18c4 1 7.2 4.1 10 8.2 2.8-4.1 6-7.2 10-8.2"/>
    <circle cx="32" cy="10.5" r="1.6" fill="currentColor" stroke="none"/>
</svg></div>

            <div class="brand-name">
                thyu_nhun
                <br>
                Weddings
            </div>

            <div class="brand-sub">
                Wedding Invitation
            </div>

        </div>


        <!-- ==================================================
             📂 QUẢN LÝ
        ================================================== -->

        <div class="sidebar-section">

            <div class="sidebar-title">
                Quản lý
            </div>


            <?php foreach ($menu_main as $item): ?>

                <a
                    href="<?= e($item["url"]) ?>"
                    class="nav-item"
                >

                    <span class="nav-icon">
                        <?= customer_nav_icon($item["icon"]) ?>
                    </span>

                    <span>
                        <?= e($item["name"]) ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </div>


        <!-- ==================================================
             HỆ THỐNG
        ================================================== -->

        <div class="sidebar-section">

            <div class="sidebar-title">
                Hệ thống
            </div>


            <?php foreach ($menu_system as $item): ?>

                <a
                    href="<?= e($item["url"]) ?>"
                    class="nav-item
                    <?= $item["name"] === "Cài đặt"
                        ? "active"
                        : ""
                    ?>"
                >

                    <span class="nav-icon">
                        <?= customer_nav_icon($item["icon"]) ?>
                    </span>

                    <span>
                        <?= e($item["name"]) ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </div>


        <!-- ==================================================
             🚪 ĐĂNG XUẤT
        ================================================== -->

        <div class="logout-area">

            <a
                href="../auth/logout.php"
                class="logout"
            >

                <span class="nav-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10"/><path d="M13 8l4 4-4 4M7.5 12H17"/></svg>
                </span>

                <span>
                    Đăng xuất
                </span>

            </a>

        </div>

    </aside>


    <!-- ==================================================
         📄 MAIN CONTENT
    ================================================== -->

    <main class="main">


        <!-- ==================================================
             🔝 TOPBAR
        ================================================== -->

        <header class="topbar">


            <div class="breadcrumb">

                thyu_nhun Weddings

                <strong>
                    /
                    Cài đặt
                </strong>

            </div>


            <div class="user-area">


                <div class="notification">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5"/><path d="M7 9h10M7 13h4.5"/><path d="M17.2 13.5c0 1.7-2.2 3-2.2 3s-2.2-1.3-2.2-3a1.15 1.15 0 0 1 2.2-.5 1.15 1.15 0 0 1 2.2.5z"/></svg>
                </div>


                <div class="user-text">

                    <div class="user-name">

                        Xin chào,
                        <?= e($username) ?>

                    </div>

                    <div class="user-role">
                        Khách hàng
                    </div>

                </div>


                <div class="avatar">

                    <?= e(
                        mb_strtoupper(
                            mb_substr(
                                $username,
                                0,
                                1,
                                "UTF-8"
                            ),
                            "UTF-8"
                        )
                    ) ?>

                </div>

            </div>

        </header>


        <!-- ==================================================
             📄 PAGE CONTENT
        ================================================== -->

        <section class="page">


            <!-- ==================================================
                 🏷️ TITLE
            ================================================== -->

            <div class="page-heading">

                <div class="eyebrow">
                    thyu_nhun Weddings
                </div>

                <h1 class="page-title">
                    Cài đặt
                </h1>

                <p class="page-description">
                    Quản lý thông tin tài khoản,
                    thương hiệu và thiết lập giao diện hệ thống.
                </p>

            </div>


            <!-- ==================================================
                 📐 SETTINGS GRID
            ================================================== -->

            <div class="settings-grid">


                <!-- ==================================================
                     👤 TÀI KHOẢN
                ================================================== -->

                <div class="settings-card">


                    <div class="card-heading">

                        <div class="card-icon">
                            ♙
                        </div>

                        <div>

                            <h2 class="card-title">
                                Tài khoản
                            </h2>

                            <p class="card-description">
                                Thông tin tài khoản hiện tại
                            </p>

                        </div>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Tên tài khoản
                        </span>

                        <span class="info-value">
                            <?= e($username) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Vai trò
                        </span>

                        <span class="info-value">
                            Khách hàng
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Hệ thống
                        </span>

                        <span class="info-value">
                            thyu_nhun Weddings
                        </span>

                    </div>


                </div>


                <!-- ==================================================
                     🏷️ THƯƠNG HIỆU
                ================================================== -->

                <div class="settings-card">


                    <div class="card-heading">

                        <div class="card-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5"/><path d="M7 9h10M7 13h4.5"/><path d="M17.2 13.5c0 1.7-2.2 3-2.2 3s-2.2-1.3-2.2-3a1.15 1.15 0 0 1 2.2-.5 1.15 1.15 0 0 1 2.2.5z"/></svg>
                        </div>

                        <div>

                            <h2 class="card-title">
                                Thương hiệu
                            </h2>

                            <p class="card-description">
                                Nhận diện thương hiệu của hệ thống
                            </p>

                        </div>

                    </div>


                    <div class="brand-preview">

                        <div class="brand-preview-name">
                            <?= e($brand_name) ?>
                        </div>

                        <div class="brand-preview-domain">
                            <?= e($brand_domain) ?>
                        </div>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Tên thương hiệu
                        </span>

                        <span class="info-value">
                            <?= e($brand_name) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Website
                        </span>

                        <span class="info-value">
                            <?= e($brand_domain) ?>
                        </span>

                    </div>


                </div>


                <!-- ==================================================
                     📞 LIÊN HỆ
                ================================================== -->

                <div class="settings-card">


                    <div class="card-heading">

                        <div class="card-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="9" r="3"/><circle cx="17" cy="10" r="2.2"/><path d="M3.8 20c.5-3.4 2.4-5.2 5.2-5.2s4.7 1.8 5.2 5.2"/><path d="M14.4 15.7c2.9.2 4.8 1.7 5.3 4.3"/></svg>
                        </div>

                        <div>

                            <h2 class="card-title">
                                Thông tin liên hệ
                            </h2>

                            <p class="card-description">
                                Thông tin hiển thị ở cuối hệ thống
                            </p>

                        </div>

                    </div>


                    <div class="contact-item">

                        <div class="contact-icon">
                            ☎
                        </div>

                        <div>

                            <div class="contact-label">
                                Số điện thoại
                            </div>

                            <div class="contact-value">
                                <?= e($brand_phone) ?>
                            </div>

                        </div>

                    </div>


                    <div class="contact-item">

                        <div class="contact-icon">
                            f
                        </div>

                        <div>

                            <div class="contact-label">
                                Facebook
                            </div>

                            <div class="contact-value">
                                <?= e($brand_facebook) ?>
                            </div>

                        </div>

                    </div>


                    <div class="contact-item">

                        <div class="contact-icon">
                            @
                        </div>

                        <div>

                            <div class="contact-label">
                                Website
                            </div>

                            <div class="contact-value">
                                <?= e($brand_domain) ?>
                            </div>

                        </div>

                    </div>


                </div>


                <!-- ==================================================
                     🎨 GIAO DIỆN
                ================================================== -->

                <div class="settings-card">


                    <div class="card-heading">

                        <div class="card-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="12.5" height="14" rx="2"/><path d="M8 4.5h8.5a2 2 0 0 1 2 2v9.5"/><path d="m18.2 4.1.55 1.45 1.45.55-1.45.55-.55 1.45-.55-1.45-1.45-.55 1.45-.55z"/></svg>
                        </div>

                        <div>

                            <h2 class="card-title">
                                Giao diện
                            </h2>

                            <p class="card-description">
                                Concept hiện tại của hệ thống
                            </p>

                        </div>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Phong cách
                        </span>

                        <span class="info-value">
                            Luxury Wedding
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Màu chủ đạo
                        </span>

                        <span class="info-value">
                            Burgundy / Champagne
                        </span>

                    </div>


                    <div class="color-preview">

                        <div
                            class="color-box color-sidebar"
                            title="Sidebar"
                        ></div>

                        <div
                            class="color-box color-primary"
                            title="Primary"
                        ></div>

                        <div
                            class="color-box color-soft"
                            title="Soft"
                        ></div>

                        <div
                            class="color-box color-background"
                            title="Background"
                        ></div>

                    </div>


                    <div class="design-note">

                        💡
                        <strong>
                            Ghi chú cho developer:
                        </strong>

                        <br>

                        Nếu muốn đổi toàn bộ concept website,
                        hãy chỉnh các biến màu trong phần
                        <strong>
                            THEME CONFIG
                        </strong>
                        ở đầu file.

                        Không cần sửa từng class CSS.

                    </div>


                </div>


            </div>


            <!-- ==================================================
                 🦶 FOOTER
            ================================================== -->

            <footer class="footer">


                <div>

                    <div class="footer-brand">
                        <?= e($brand_name) ?>
                    </div>

                    <div>
                        Wedding Invitation System
                    </div>

                </div>


                <div class="footer-right">

                    <div>
                        Copyright © <?= date("Y") ?>
                        <?= e($brand_domain) ?>
                    </div>

                    <div>
                        Hotline:
                        <?= e($brand_phone) ?>
                        · Facebook:
                        <?= e($brand_facebook) ?>
                    </div>

                </div>


            </footer>


        </section>


    </main>


</div>


</body>

</html>