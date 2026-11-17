<?php

/*
|--------------------------------------------------------------------------
| THYU_NHUN WEDDINGS
| CUSTOMER DASHBOARD
|--------------------------------------------------------------------------
|
| FILE NÀY LÀ TRANG CHỦ CỦA HỆ THỐNG CUSTOMER.
|
| QUY TẮC:
| - Backend / database giữ nguyên chức năng.
| - Giao diện được chia thành từng khu vực rõ ràng.
| - Muốn đổi concept website sau này:
|   → ưu tiên chỉnh phần "THEME CONFIG" bên dưới.
|
|--------------------------------------------------------------------------
*/

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| CACHE
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


/*
|--------------------------------------------------------------------------
| 01. KIỂM TRA CUSTOMER
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {
    header("Location: ../auth/login.php");
    exit;
}


$user_id = intval($_SESSION["user_id"]);


/*
|--------------------------------------------------------------------------
| 02. TÊN KHÁCH HÀNG
|--------------------------------------------------------------------------
*/

$full_name = trim(
    $_SESSION["full_name"] ?? ""
);

if ($full_name === "") {
    $full_name = "Khách hàng";
}


/*
|--------------------------------------------------------------------------
| 03. THÔNG TIN THƯƠNG HIỆU
|--------------------------------------------------------------------------
|
| Sau này nếu muốn đổi thông tin thương hiệu,
| có thể chỉnh ngay tại khu vực này.
|
|--------------------------------------------------------------------------
*/

$brand_name = "thyu_nhun";
$brand_sub = "Weddings";

$copyright_text =
    "Copyright by thyu_nhun.id.vn";


/*
|--------------------------------------------------------------------------
| THÔNG TIN LIÊN HỆ
|--------------------------------------------------------------------------
|
| Bạn có thể thay sau.
|
| Ví dụ:
|
| $contact_phone = "09xx xxx xxx";
| $contact_facebook = "https://facebook.com/...";
|
|--------------------------------------------------------------------------
*/

$contact_phone = "0968117006";

$contact_facebook = "https://www.facebook.com/nhung.nnthuy/";


/*
|--------------------------------------------------------------------------
| 04. LẤY ĐÁM CƯỚI
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT *
     FROM weddings
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);


$wedding = null;
$wedding_id = null;


if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $wedding_result =
        $stmt->get_result();

    if (
        $wedding_result->num_rows === 1
    ) {

        $wedding =
            $wedding_result->fetch_assoc();

        $wedding_id =
            intval($wedding["id"]);
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 05. THỐNG KÊ
|--------------------------------------------------------------------------
*/

$guest_count = 0;
$link_count = 0;
$total_views = 0;


/*
|--------------------------------------------------------------------------
| SỐ KHÁCH MỜI
|--------------------------------------------------------------------------
*/

if ($wedding_id) {

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM guests
         WHERE wedding_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $wedding_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $guest_count =
            intval($row["total"]);

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| SỐ LINK
|--------------------------------------------------------------------------
*/

if ($wedding_id) {

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM invitation_links
         WHERE wedding_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $wedding_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $link_count =
            intval($row["total"]);

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| TỔNG LƯỢT XEM
|--------------------------------------------------------------------------
*/

if ($wedding_id) {

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(visit_count), 0) AS total
         FROM invitation_links
         WHERE wedding_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $wedding_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $row =
            $result->fetch_assoc();

        $total_views =
            intval($row["total"]);

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| 06. DỮ LIỆU HIỂN THỊ
|--------------------------------------------------------------------------
*/

if ($wedding) {

    $groom_name =
        trim(
            $wedding["groom_name"] ?? ""
        );

    $bride_name =
        trim(
            $wedding["bride_name"] ?? ""
        );


    $couple_name =
        $groom_name .
        " & " .
        $bride_name;


    if (
        trim($couple_name) === "&"
    ) {

        $couple_name =
            "Chưa cập nhật tên";
    }


    $wedding_date =
        trim(
            $wedding["wedding_date"] ?? ""
        );


    $wedding_time =
        trim(
            $wedding["wedding_time"] ?? ""
        );


    $venue =
        trim(
            $wedding["venue"] ?? ""
        );


    $address =
        trim(
            $wedding["address"] ?? ""
        );

} else {

    $couple_name =
        "Chưa tạo thiệp";

    $wedding_date = "";
    $wedding_time = "";
    $venue = "";
    $address = "";
}


/*
|--------------------------------------------------------------------------
| 07. PREVIEW THIỆP
|--------------------------------------------------------------------------
|
| Lấy token mới nhất để nút "Xem thiệp" hoạt động.
|
|--------------------------------------------------------------------------
*/

$preview_url = "";


if ($wedding_id) {

    $stmt = $conn->prepare(
        "SELECT token
         FROM invitation_links
         WHERE wedding_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $wedding_id
        );

        $stmt->execute();

        $preview_result =
            $stmt->get_result();


        if (
            $preview_result->num_rows === 1
        ) {

            $preview =
                $preview_result->fetch_assoc();


            $preview_url =
                "../invite.php?token=" .
                rawurlencode(
                    $preview["token"]
                );
        }


        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| 08. AVATAR
|--------------------------------------------------------------------------
*/

$avatar_letter =
    mb_strtoupper(
        mb_substr(
            $full_name,
            0,
            1,
            "UTF-8"
        ),
        "UTF-8"
    );

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
        <?= htmlspecialchars($brand_name) ?>
        Weddings - Trang chủ
    </title>


    <!--
    ========================================================================
    🎨 THYU_NHUN WEDDINGS
    CUSTOMER THEME
    ========================================================================

    Đây là khu vực QUAN TRỌNG NHẤT nếu sau này muốn đổi concept.

    Chỉ cần thay đổi các biến màu / font ở :root
    là có thể đổi toàn bộ phong cách giao diện.

    ========================================================================
    -->


    <!--
    ========================================================================
    🔤 FONT THƯƠNG HIỆU
    ========================================================================
    -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <style>


    /* =====================================================================
       🎨 01. THEME CONFIG
       ---------------------------------------------------------------------
       MUỐN ĐỔI CONCEPT WEBSITE → CHỈNH KHU VỰC NÀY
       ===================================================================== */


    :root {

        /* ===== MÀU THƯƠNG HIỆU ===== */

        --brand-primary: #8F1D2C;
        --brand-primary-dark: #65131F;
        --brand-primary-light: #D95B64;

        /* ===== MÀU PHỤ ===== */

        --brand-gold: #D4A73D;
        --brand-gold-light: #F2D58A;

        /* ===== NỀN ===== */

        --page-bg: #FFF8F6;
        --page-bg-soft: #FFFBFA;

        /* ===== CARD ===== */

        --card-bg: #ffffff;
        --card-border: #ECD8D5;

        /* ===== TEXT ===== */

        --text-main: #32191C;
        --text-secondary: #7E6669;
        --text-light: #A58C8F;

        /* ===== SIDEBAR ===== */

        --sidebar-bg: #3D0B12;
        --sidebar-bg-2: #53131C;

        --sidebar-text: #F0DCDD;
        --sidebar-muted: #A58C8F;

        /* ===== TRẠNG THÁI ===== */

        --success: #6c9475;

        /* ===== SHADOW ===== */

        --shadow-soft:
            0 8px 30px rgba(91, 18, 31, .06);

        --shadow-card:
            0 4px 20px rgba(91, 18, 31, .055);

        /* ===== BO GÓC ===== */

        --radius-large: 20px;
        --radius-medium: 14px;
        --radius-small: 10px;

        /* ===== FONT ===== */

        --font-body:
            "DM Sans",
            Arial,
            sans-serif;

        --font-display:
            "Cormorant Garamond",
            Georgia,
            serif;
    }


    /* =====================================================================
       🧹 02. RESET
       ===================================================================== */

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }


    html {
        scroll-behavior: smooth;
    }


    body {

        font-family:
            var(--font-body);

        background:
            var(--page-bg);

        color:
            var(--text-main);

        min-height: 100vh;

        line-height: 1.5;
    }


    a {
        text-decoration: none;
        color: inherit;
    }


    button,
    input {
        font-family: inherit;
    }


    /* =====================================================================
       🌸 03. DECORATIVE BACKGROUND
       ===================================================================== */

    body::before {

        content: "";

        position: fixed;

        width: 430px;
        height: 430px;

        right: -190px;
        top: 70px;

        border: 1px solid
            rgba(183,154,112,.20);

        border-radius: 50%;

        pointer-events: none;

        z-index: -1;
    }


    body::after {

        content: "";

        position: fixed;

        width: 300px;
        height: 300px;

        left: -170px;
        bottom: -130px;

        border: 1px solid
            rgba(113,56,61,.10);

        border-radius: 50%;

        pointer-events: none;

        z-index: -1;
    }


    /* =====================================================================
       🧭 04. SIDEBAR
       ---------------------------------------------------------------------
       Trang chủ và sau này trang Settings dùng sidebar đầy đủ.
       ===================================================================== */

    .sidebar {

        position: fixed;

        left: 0;
        top: 0;

        width: 258px;
        height: 100vh;

        background:
            linear-gradient(
                180deg,
                var(--sidebar-bg),
                var(--sidebar-bg-2)
            );

        color: white;

        z-index: 1000;

        display: flex;

        flex-direction: column;

        box-shadow:
            8px 0 30px rgba(40,25,27,.10);

        overflow-y: auto;
    }


    /* =====================================================================
       💍 05. BRAND
       ===================================================================== */

    .brand {

        min-height: 128px;

        padding:
            28px 25px 22px;

        display: flex;

        align-items: center;

        gap: 15px;

        border-bottom:
            1px solid
            rgba(255,255,255,.07);
    }


    .brand-symbol {

        width: 52px;
        height: 52px;

        border-radius: 16px;

        display: flex;

        align-items: center;

        justify-content: center;

        background:
            linear-gradient(
                135deg,
                #D95B64,
                #7A1524
            );

        color: white;

        font-family:
            var(--font-display);

        font-size: 27px;

        flex-shrink: 0;

        box-shadow:
            0 7px 18px
            rgba(0,0,0,.16);
    }


    .brand-name {

        font-family:
            var(--font-display);

        font-size: 23px;

        font-weight: 600;

        line-height: .9;

        letter-spacing: .2px;
    }


    .brand-name span {

        display: block;

        font-size: 19px;

        margin-top: 4px;

        color:
            #E7B9BC;
    }


    .brand-sub {

        margin-top: 7px;

        color:
            #a89693;

        font-size: 8px;

        letter-spacing: 2px;

        text-transform: uppercase;
    }


    /* =====================================================================
       📋 06. MENU
       ===================================================================== */

    .menu {

        padding:
            22px 15px;

        flex: 1;
    }


    .menu-label {

        padding:
            5px 12px 11px;

        color:
            var(--sidebar-muted);

        font-size: 9px;

        letter-spacing: 1.5px;

        text-transform: uppercase;
    }


    .menu-item {

        display: flex;

        align-items: center;

        gap: 13px;

        width: 100%;

        padding:
            12px 14px;

        margin-bottom: 5px;

        border-radius:
            10px;

        color:
            var(--sidebar-text);

        font-size: 12px;

        transition:
            all .2s ease;
    }


    .menu-item:hover {

        background:
            rgba(255,255,255,.065);

        color:
            #ffffff;

        transform:
            translateX(2px);
    }


    .menu-item.active {

        background:
            linear-gradient(
                135deg,
                #8c5153,
                #8F1D2C
            );

        color: white;

        box-shadow:
            0 7px 18px
            rgba(0,0,0,.15);
    }


    .menu-icon {

        width: 21px;

        text-align: center;

        font-size: 15px;

        flex-shrink: 0;
    }


    .menu-divider {

        height: 1px;

        background:
            rgba(255,255,255,.07);

        margin:
            17px 8px;
    }


    .logout-item {

        color:
            #d7a8a4;
    }


    /* =====================================================================
       👤 07. SIDEBAR BOTTOM
       ===================================================================== */

    .sidebar-bottom {

        padding:
            15px;

        border-top:
            1px solid
            rgba(255,255,255,.07);
    }


    .sidebar-note {

        padding:
            13px;

        border:
            1px solid
            rgba(255,255,255,.07);

        border-radius:
            10px;

        color:
            #aa9a97;

        font-size: 9px;

        line-height: 1.6;
    }


    .sidebar-note strong {

        color:
            #F0DCDD;

        display: block;

        margin-bottom: 2px;
    }


    /* =====================================================================
       📐 08. MAIN
       ===================================================================== */

    .main {

        margin-left:
            258px;

        min-height: 100vh;

        width:
            calc(100% - 258px);
    }


    /* =====================================================================
       🔝 09. TOPBAR
       ===================================================================== */

    .topbar {

        height: 78px;

        background:
            rgba(255,255,255,.94);

        backdrop-filter:
            blur(12px);

        border-bottom:
            1px solid
            var(--card-border);

        display: flex;

        align-items: center;

        justify-content: space-between;

        padding:
            0 42px;

        position:
            sticky;

        top: 0;

        z-index: 100;
    }


    .topbar-left {

        display: flex;

        align-items: center;

        gap: 13px;
    }


    .mobile-menu-btn {

        display: none;

        border: none;

        background:
            #f4ebe7;

        color:
            var(--brand-primary);

        width: 38px;
        height: 38px;

        border-radius: 10px;

        cursor: pointer;

        font-size: 18px;
    }


    .page-breadcrumb {

        display: flex;

        align-items: center;

        gap: 9px;

        color:
            var(--text-secondary);

        font-size: 12px;
    }


    .page-breadcrumb strong {

        color:
            var(--text-main);

        font-weight: 600;
    }


    .user-area {

        display: flex;

        align-items: center;

        gap: 13px;
    }


    .notification {

        width: 38px;
        height: 38px;

        display: flex;

        align-items: center;

        justify-content: center;

        border:
            1px solid
            var(--card-border);

        border-radius: 50%;

        color:
            var(--brand-primary);

        background:
            white;

        position: relative;

        font-size: 15px;
    }


    .notification-dot {

        position: absolute;

        width: 7px;
        height: 7px;

        background:
            #b44b43;

        border:
            2px solid white;

        border-radius: 50%;

        top: 5px;
        right: 6px;
    }


    .user-text {

        text-align: right;
    }


    .user-text strong {

        display: block;

        font-size: 11px;

        font-weight: 600;
    }


    .user-text small {

        display: block;

        color:
            var(--text-light);

        font-size: 9px;

        margin-top: 2px;
    }


    .avatar {

        width: 40px;
        height: 40px;

        border-radius: 50%;

        display: flex;

        align-items: center;

        justify-content: center;

        background:
            linear-gradient(
                135deg,
                #e8d4ce,
                #cda9a0
            );

        color:
            var(--brand-primary-dark);

        font-family:
            var(--font-display);

        font-size: 18px;

        font-weight: 700;

        border:
            2px solid white;

        box-shadow:
            0 3px 10px
            rgba(70,40,40,.10);
    }


    /* =====================================================================
       📄 10. CONTENT
       ===================================================================== */

    .content {

        max-width:
            1420px;

        margin:
            0 auto;

        padding:
            42px 42px 20px;
    }


    /* =====================================================================
       👋 11. WELCOME
       ===================================================================== */

    .welcome {

        margin-bottom:
            28px;
    }


    .eyebrow {

        display: flex;

        align-items: center;

        gap: 8px;

        color:
            var(--brand-primary);

        font-size: 9px;

        font-weight: 700;

        letter-spacing: 1.8px;

        text-transform: uppercase;

        margin-bottom:
            8px;
    }


    .eyebrow-line {

        width: 28px;

        height: 1px;

        background:
            var(--brand-gold);
    }


    .welcome h1 {

        font-family:
            var(--font-display);

        font-size:
            clamp(34px, 4vw, 48px);

        font-weight: 500;

        line-height: 1;

        color:
            var(--text-main);

        margin-bottom:
            10px;
    }


    .welcome h1 span {

        color:
            var(--brand-primary);
    }


    .welcome p {

        color:
            var(--text-secondary);

        font-size:
            12px;
    }


    /* =====================================================================
       💍 12. WEDDING HERO
       ===================================================================== */

    .wedding-hero {

        position: relative;

        overflow: hidden;

        background:
            linear-gradient(
                105deg,
                #ffffff 0%,
                #FFFCFB 58%,
                #f5e9e4 100%
            );

        border:
            1px solid
            var(--card-border);

        border-radius:
            var(--radius-large);

        padding:
            31px 34px;

        margin-bottom:
            20px;

        box-shadow:
            var(--shadow-soft);
    }


    .wedding-hero::before {

        content: "";

        position: absolute;

        width: 230px;
        height: 230px;

        right: -105px;
        top: -120px;

        border:
            1px solid
            rgba(183,154,112,.28);

        border-radius: 50%;
    }


    .wedding-hero::after {

        content: "♡";

        position: absolute;

        right: 45px;
        bottom: -30px;

        font-family:
            var(--font-display);

        font-size: 110px;

        color:
            rgba(113,56,61,.06);
    }


    .hero-label {

        color:
            var(--brand-primary);

        font-size: 9px;

        font-weight: 700;

        letter-spacing: 1.8px;

        text-transform: uppercase;

        margin-bottom: 8px;
    }


    .couple-name {

        position: relative;

        z-index: 1;

        font-family:
            var(--font-display);

        font-size:
            clamp(34px, 4vw, 47px);

        font-weight: 500;

        color:
            #382c2d;

        line-height: 1;

        margin-bottom:
            17px;
    }


    



    /* FIX: Giữ toàn bộ tên cặp đôi cùng màu và cùng kiểu chữ */
    .couple-name {
        color: #382c2d !important;
        font-family: var(--font-display) !important;
        font-weight: 500 !important;
        font-synthesis: none;
        letter-spacing: 0 !important;
    }


    .wedding-meta {

        position: relative;

        z-index: 2;

        display: flex;

        flex-wrap: wrap;

        gap: 10px;
    }


    .meta-item {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        padding:
            8px 12px;

        border:
            1px solid
            #ECD8D5;

        border-radius:
            100px;

        background:
            rgba(255,255,255,.72);

        color:
            var(--text-secondary);

        font-size: 10px;
    }


    .meta-icon {

        color:
            var(--brand-primary);
    }


    /* =====================================================================
       📊 13. STATISTICS
       ===================================================================== */

    .statistics {

        display: grid;

        grid-template-columns:
            repeat(3, 1fr);

        gap: 16px;

        margin-bottom:
            34px;
    }


    .stat-card {

        background:
            var(--card-bg);

        border:
            1px solid
            var(--card-border);

        border-radius:
            var(--radius-medium);

        padding:
            20px 22px;

        display: flex;

        align-items: center;

        gap: 15px;

        box-shadow:
            var(--shadow-card);

        transition:
            transform .2s ease,
            box-shadow .2s ease;
    }


    .stat-card:hover {

        transform:
            translateY(-2px);

        box-shadow:
            0 10px 28px
            rgba(91,18,31,.08);
    }


    .stat-icon {

        width: 48px;
        height: 48px;

        border-radius: 14px;

        background:
            #f3e7e2;

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 19px;

        flex-shrink: 0;
    }


    .stat-info {

        min-width: 0;
    }


    .stat-number {

        font-family:
            var(--font-display);

        font-size: 32px;

        font-weight: 600;

        line-height: .9;

        color:
            var(--brand-primary);
    }


    .stat-label {

        color:
            var(--text-light);

        font-size: 10px;

        margin-top: 6px;
    }


    /* =====================================================================
       🧩 14. SECTION HEADER
       ===================================================================== */

    .section-header {

        display: flex;

        align-items: flex-end;

        justify-content: space-between;

        margin-bottom:
            15px;
    }


    .section-title-wrap {

        display: flex;

        align-items: center;

        gap: 10px;
    }


    .section-mini-icon {

        width: 31px;
        height: 31px;

        border-radius: 9px;

        background:
            #f1e4df;

        color:
            var(--brand-primary);

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 14px;
    }


    .section-title {

        font-size: 14px;

        font-weight: 700;

        color:
            var(--text-main);
    }


    .section-subtitle {

        font-size: 9px;

        color:
            var(--text-light);

        margin-top: 2px;
    }


    /* =====================================================================
       🗂️ 15. ACTION CARDS
       ===================================================================== */

    .cards {

        display: grid;

        grid-template-columns:
            repeat(3, 1fr);

        gap: 16px;
    }


    .action-card {

        background:
            var(--card-bg);

        border:
            1px solid
            var(--card-border);

        border-radius:
            var(--radius-medium);

        padding:
            22px;

        box-shadow:
            var(--shadow-card);

        position: relative;

        overflow: hidden;

        transition:
            transform .2s ease,
            box-shadow .2s ease;
    }


    .action-card:hover {

        transform:
            translateY(-3px);

        box-shadow:
            0 11px 30px
            rgba(91,18,31,.09);
    }


    .action-card::after {

        content: "";

        position: absolute;

        width: 90px;
        height: 90px;

        right: -48px;
        bottom: -48px;

        border:
            1px solid
            rgba(183,154,112,.18);

        border-radius: 50%;
    }


    .action-icon {

        width: 43px;
        height: 43px;

        display: flex;

        align-items: center;

        justify-content: center;

        border-radius: 12px;

        background:
            #f4e9e4;

        color:
            var(--brand-primary);

        font-size: 17px;

        margin-bottom:
            15px;
    }


    .action-card h3 {

        font-size:
            13px;

        font-weight:
            700;

        color:
            var(--text-main);

        margin-bottom:
            7px;
    }


    .action-card p {

        color:
            var(--text-secondary);

        font-size:
            10px;

        line-height:
            1.7;

        min-height:
            50px;
    }


    .action-link {

        display:
            inline-flex;

        align-items:
            center;

        gap:
            7px;

        margin-top:
            15px;

        padding:
            9px 13px;

        background:
            var(--brand-primary);

        color:
            white;

        border-radius:
            8px;

        font-size:
            10px;

        font-weight:
            600;

        transition:
            all .2s ease;
    }


    .action-link:hover {

        background:
            var(--brand-primary-dark);

        transform:
            translateY(-1px);
    }


    .action-link.secondary {

        background:
            #f5ece8;

        color:
            var(--brand-primary);
    }


    .action-link.secondary:hover {

        background:
            #eadbd5;
    }


    /* =====================================================================
       👁️ 16. QUICK PREVIEW
       ===================================================================== */

    .preview-strip {

        margin-top:
            20px;

        background:
            linear-gradient(
                105deg,
                #3a2527,
                #5b3034
            );

        border-radius:
            var(--radius-medium);

        padding:
            19px 22px;

        color:
            white;

        display:
            flex;

        align-items:
            center;

        justify-content:
            space-between;

        gap:
            20px;

        position:
            relative;

        overflow:
            hidden;
    }


    .preview-strip::after {

        content:
            "♡";

        position:
            absolute;

        right:
            30px;

        top:
            -32px;

        font-family:
            var(--font-display);

        font-size:
            110px;

        color:
            rgba(255,255,255,.045);
    }


    .preview-left {

        display:
            flex;

        align-items:
            center;

        gap:
            14px;

        position:
            relative;

        z-index:
            1;
    }


    .preview-icon {

        width:
            42px;

        height:
            42px;

        border-radius:
            12px;

        background:
            rgba(255,255,255,.10);

        display:
            flex;

        align-items:
            center;

        justify-content:
            center;

        font-size:
            17px;
    }


    .preview-title {

        font-family:
            var(--font-display);

        font-size:
            21px;

        line-height:
            1;
    }


    .preview-desc {

        color:
            #cdbdbc;

        font-size:
            9px;

        margin-top:
            4px;
    }


    .preview-button {

        position:
            relative;

        z-index:
            2;

        background:
            white;

        color:
            var(--brand-primary);

        padding:
            10px 15px;

        border-radius:
            8px;

        font-size:
            10px;

        font-weight:
            700;

        white-space:
            nowrap;
    }


    /* =====================================================================
       🦶 17. FOOTER
       ---------------------------------------------------------------------
       Đổi thương hiệu / liên hệ → chỉnh PHP phía trên.
       ===================================================================== */

    .footer {

        margin:
            35px 42px 0;

        padding:
            23px 0 27px;

        border-top:
            1px solid
            var(--card-border);

        display:
            flex;

        align-items:
            center;

        justify-content:
            space-between;

        gap:
            20px;

        color:
            var(--text-light);

        font-size:
            9px;
    }


    .footer-brand {

        font-family:
            var(--font-display);

        color:
            var(--brand-primary);

        font-size:
            17px;

        font-weight:
            600;
    }


    .footer-copy {

        text-align:
            center;
    }


    .footer-contact {

        display:
            flex;

        align-items:
            center;

        gap:
            14px;
    }


    .footer-contact a {

        color:
            var(--brand-primary);

        transition:
            .2s ease;
    }


    .footer-contact a:hover {

        color:
            var(--brand-primary-dark);
    }


    /* =====================================================================
       📭 18. EMPTY STATE
       ===================================================================== */

    .empty-state {

        background:
            white;

        border:
            1px solid
            var(--card-border);

        border-radius:
            var(--radius-large);

        padding:
            70px 30px;

        text-align:
            center;

        box-shadow:
            var(--shadow-card);
    }


    .empty-icon {

        width:
            65px;

        height:
            65px;

        margin:
            0 auto 17px;

        border-radius:
            18px;

        display:
            flex;

        align-items:
            center;

        justify-content:
            center;

        background:
            #f3e5df;

        font-size:
            27px;
    }


    .empty-state h2 {

        font-family:
            var(--font-display);

        font-size:
            28px;

        font-weight:
            500;

        margin-bottom:
            7px;
    }


    .empty-state p {

        color:
            var(--text-secondary);

        font-size:
            11px;

        margin-bottom:
            17px;
    }


    /* =====================================================================
       📱 19. RESPONSIVE
       ===================================================================== */

    @media (max-width: 1150px) {

        .sidebar {

            width:
                230px;
        }


        .main {

            margin-left:
                230px;

            width:
                calc(100% - 230px);
        }


        .content {

            padding-left:
                28px;

            padding-right:
                28px;
        }


        .footer {

            margin-left:
                28px;

            margin-right:
                28px;
        }


        .cards {

            grid-template-columns:
                repeat(2, 1fr);
        }
    }


    @media (max-width: 850px) {

        .sidebar {

            transform:
                translateX(-100%);

            transition:
                transform .25s ease;
        }


        .sidebar.open {

            transform:
                translateX(0);
        }


        .main {

            margin-left:
                0;

            width:
                100%;
        }


        .mobile-menu-btn {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;
        }


        .topbar {

            padding:
                0 20px;
        }


        .statistics {

            grid-template-columns:
                1fr;
        }


        .cards {

            grid-template-columns:
                repeat(2, 1fr);
        }


        .footer {

            flex-direction:
                column;

            text-align:
                center;

            margin-left:
                20px;

            margin-right:
                20px;
        }
    }


    @media (max-width: 600px) {

        .topbar {

            height:
                68px;
        }


        .user-text {

            display:
                none;
        }


        .content {

            padding:
                27px 15px 10px;
        }


        .welcome h1 {

            font-size:
                34px;
        }


        .wedding-hero {

            padding:
                24px 20px;
        }


        .couple-name {

            font-size:
                34px;
        }


        .wedding-meta {

            flex-direction:
                column;

            align-items:
                flex-start;
        }


        .cards {

            grid-template-columns:
                1fr;
        }


        .preview-strip {

            align-items:
                flex-start;

            flex-direction:
                column;
        }


        .preview-button {

            width:
                100%;

            text-align:
                center;
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

    
/* ================= DASHBOARD SIDEBAR FINAL FIX ================= */
.sidebar{
    overflow:hidden !important;
}
.sidebar .menu{
    display:block !important;
    visibility:visible !important;
    opacity:1 !important;
    flex:1 1 auto !important;
    min-height:0 !important;
    overflow-y:auto !important;
    padding:22px 15px !important;
}
.sidebar .menu .menu-item{
    display:flex !important;
    visibility:visible !important;
    opacity:1 !important;
}
.sidebar .sidebar-bottom{
    display:block !important;
    flex:0 0 auto !important;
    background:rgba(61,11,18,.28);
    padding:12px 15px !important;
}
.sidebar .sidebar-note{display:none !important;}
.sidebar .menu-icon{
    display:inline-flex !important;
    align-items:center;
    justify-content:center;
    width:21px !important;
    min-width:21px !important;
}
.sidebar .menu-icon svg{
    width:19px;
    height:19px;
    display:block;
    fill:none;
    stroke:currentColor;
    stroke-width:1.8;
    stroke-linecap:round;
    stroke-linejoin:round;
}
.brand-symbol{
    background:linear-gradient(145deg,#B52A3A 0%,#8F1D2C 55%,#65131F 100%) !important;
    border:1px solid rgba(242,213,138,.36) !important;
    color:#F2D58A !important;
}
.brand-logo-svg{
    width:31px;height:31px;display:block;
    fill:none;stroke:currentColor;stroke-width:2.15;
    stroke-linecap:round;stroke-linejoin:round;
}


    /* FIX: Đồng bộ toàn bộ icon dashboard bằng SVG */
    .dashboard-icon,
    .dashboard-icon svg {
        display: block;
    }

    .dashboard-icon svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .stat-icon .dashboard-icon svg,
    .action-icon .dashboard-icon svg,
    .section-mini-icon .dashboard-icon svg,
    .meta-icon .dashboard-icon svg,
    .preview-icon .dashboard-icon svg,
    .empty-icon .dashboard-icon svg {
        width: 20px;
        height: 20px;
    }

    .section-mini-icon,
    .stat-icon,
    .action-icon,
    .meta-icon,
    .preview-icon,
    .empty-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

</style>

</head>


<body>


<!--
============================================================================
🧭 SIDEBAR
----------------------------------------------------------------------------
MENU ĐẦY ĐỦ CHO TRANG CHỦ.

Sau này settings.php cũng sẽ dùng cùng concept/sidebar này.
============================================================================
-->

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-symbol"><svg class="brand-logo-svg" viewBox="0 0 64 64" aria-hidden="true">
<path d="M17 19c-5 0-9 4-9 9 0 11 12.5 19 24 28 11.5-9 24-17 24-28 0-5-4-9-9-9-6 0-11 4.5-15 10-4-5.5-9-10-15-10Z"/>
<path d="M22 18c4 1 7.2 4.1 10 8.2 2.8-4.1 6-7.2 10-8.2"/>
<circle cx="32" cy="10.5" r="1.6" fill="currentColor" stroke="none"/>
</svg></div>
        <div>
            <div class="brand-name">
                <?= htmlspecialchars($brand_name) ?>
                <span><?= htmlspecialchars($brand_sub) ?></span>
            </div>
            <div class="brand-sub">Wedding Invitation</div>
        </div>
    </div>

    <nav class="menu" aria-label="Điều hướng quản lý">
        <div class="menu-label">Quản lý</div>

        <a href="dashboard.php" class="menu-item active">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3.5 10.5 12 3.7l8.5 6.8v9.2a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5z"/>
                    <path d="M9 21v-6.2h6V21"/>
                </svg>
            </span>
            <span>Trang chủ</span>
        </a>

        <a href="wedding_info.php<?= $wedding_id ? '?id=' . $wedding_id : '' ?>" class="menu-item">
            <span class="menu-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3.5" y="5" width="17" height="14" rx="2.5"/>
                    <path d="M7 9h10M7 13h4.5"/>
                    <path d="M17.2 13.5c0 1.7-2.2 3-2.2 3s-2.2-1.3-2.2-3a1.15 1.15 0 0 1 2.2-.5 1.15 1.15 0 0 1 2.2.5z"/>
                </svg>
            </span>
            <span>Thông tin thiệp</span>
        </a>

        <a href="templates.php" class="menu-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5.5" width="12.5" height="14" rx="2"/><path d="M8 4.5h8.5a2 2 0 0 1 2 2v9.5"/><path d="m18.2 4.1.55 1.45 1.45.55-1.45.55 1.45-.55.55-1.45.55 1.45 1.45.55-1.45.55-.55 1.45-.55-1.45-1.45-.55 1.45-.55z"/></svg></span><span>Mẫu thiệp</span>
        </a>

        <a href="wedding_images.php<?= $wedding_id ? '?id=' . $wedding_id : '' ?>" class="menu-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="8.5" cy="9" r="1.4"/><path d="m5.5 17 4.4-4.2 3.2 2.9 2.2-2.1 3.2 3.4"/></svg></span><span>Album ảnh</span>
        </a>

        <a href="guests.php<?= $wedding_id ? '?wedding_id=' . $wedding_id : '' ?>" class="menu-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="9" r="3"/><circle cx="17" cy="10" r="2.2"/><path d="M3.8 20c.5-3.4 2.4-5.2 5.2-5.2s4.7 1.8 5.2 5.2"/><path d="M14.4 15.7c2.9.2 4.8 1.7 5.3 4.3"/></svg></span><span>Khách mời</span>
        </a>

        <a href="invitation_links.php<?= $wedding_id ? '?wedding_id=' . $wedding_id : '' ?>" class="menu-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.2 14.8-1.4 1.4a3.7 3.7 0 0 1-5.2-5.2l3.1-3.1a3.7 3.7 0 0 1 5.2 0"/><path d="m14.8 9.2 1.4-1.4a3.7 3.7 0 0 1 5.2 5.2L18.3 16a3.7 3.7 0 0 1-5.2 0"/><path d="m8.7 15.3 6.6-6.6"/></svg></span><span>Link thiệp</span>
        </a>

        <a href="<?= $preview_url ? htmlspecialchars($preview_url) : 'templates.php' ?>"
           <?= $preview_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
           class="menu-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.7 12s3.4-5.5 9.3-5.5S21.3 12 21.3 12 17.9 17.5 12 17.5 2.7 12 2.7 12z"/><circle cx="12" cy="12" r="2.4"/></svg></span><span>Xem thiệp</span>
        </a>

        <div class="menu-divider"></div>
        <div class="menu-label">Hệ thống</div>

        <a href="settings.php" class="menu-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h9M17 7h3M4 17h4M12 17h8"/><circle cx="15" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg></span><span>Cài đặt</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a href="../auth/logout.php" class="menu-item logout-item">
            <span class="menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10"/><path d="M13 8l4 4-4 4M7.5 12H17"/></svg></span><span>Đăng xuất</span>
        </a>
    </div>
</aside>


<!--
============================================================================
📄 MAIN
============================================================================
-->

<main class="main">


    <!--
    =========================================================================
    🔝 TOPBAR
    =========================================================================
    -->

    <header class="topbar">


        <div class="topbar-left">

            <button
                type="button"
                class="mobile-menu-btn"
                onclick="toggleSidebar()"
                aria-label="Mở menu"
            >
                ☰
            </button>


            <div class="page-breadcrumb">

                <span>
                    <?= htmlspecialchars($brand_name) ?> Weddings
                </span>

                <span>
                    /
                </span>

                <strong>
                    Trang chủ
                </strong>

            </div>

        </div>


        <div class="user-area">


            <div class="notification">

                ♡

                <span class="notification-dot"></span>

            </div>


            <div class="user-text">

                <strong>
                    Xin chào,
                    <?= htmlspecialchars($full_name) ?>
                </strong>

                <small>
                    Khách hàng
                </small>

            </div>


            <div class="avatar">

                <?= htmlspecialchars($avatar_letter) ?>

            </div>


        </div>


    </header>


    <!--
    =========================================================================
    📄 CONTENT
    =========================================================================
    -->

    <section class="content">


        <!--
        =====================================================================
        👋 WELCOME
        =====================================================================
        -->

        <div class="welcome">


            <div class="eyebrow">

                <span class="eyebrow-line"></span>

                THYU_NHUN WEDDINGS

            </div>


            <h1>

                Xin chào,
                <span>
                    <?= htmlspecialchars($full_name) ?>
                </span>

            </h1>


            <p>
                Quản lý thiệp cưới, khách mời
                và những khoảnh khắc đặc biệt
                của bạn.
            </p>


        </div>


        <?php if ($wedding): ?>


        <!--
        =====================================================================
        💍 WEDDING HERO
        =====================================================================
        -->

        <div class="wedding-hero">


            <div class="hero-label">
                <span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg></span> Thiệp cưới của bạn
            </div>


            <div class="couple-name">

                <?= htmlspecialchars($couple_name) ?>

            </div>


            <div class="wedding-meta">


                <?php if ($wedding_date): ?>

                    <div class="meta-item">

                        <span class="meta-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/></svg></span></span>

                        <span>
                            <?= htmlspecialchars($wedding_date) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <?php if ($wedding_time): ?>

                    <div class="meta-item">

                        <span class="meta-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/></svg></span></span>

                        <span>
                            <?= htmlspecialchars($wedding_time) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <?php if ($venue): ?>

                    <div class="meta-item">

                        <span class="meta-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.2"/></svg></span></span>

                        <span>
                            <?= htmlspecialchars($venue) ?>
                        </span>

                    </div>

                <?php endif; ?>


            </div>


        </div>


        <!--
        =====================================================================
        📊 STATISTICS
        =====================================================================
        -->

        <div class="statistics">


            <!-- KHÁCH -->

            <div class="stat-card">

                <div class="stat-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9.5" r="2.2"/><path d="M3.5 20c.5-3.5 2.5-5.3 5.5-5.3s5 1.8 5.5 5.3"/><path d="M14 15.5c3 .2 5 1.8 5.5 4.5"/></svg></span></div>

                <div class="stat-info">

                    <div class="stat-number">
                        <?= $guest_count ?>
                    </div>

                    <div class="stat-label">
                        Khách mời
                    </div>

                </div>

            </div>


            <!-- LINK -->

            <div class="stat-card">

                <div class="stat-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.5 14.5-1.7 1.7a3.8 3.8 0 0 1-5.4-5.4l3.2-3.2a3.8 3.8 0 0 1 5.4 0l1.1 1.1"/><path d="m14.5 9.5 1.7-1.7a3.8 3.8 0 0 1 5.4 5.4l-3.2 3.2a3.8 3.8 0 0 1-5.4 0l-1.1-1.1"/><path d="m8.5 15.5 7-7"/></svg></span></div>

                <div class="stat-info">

                    <div class="stat-number">
                        <?= $link_count ?>
                    </div>

                    <div class="stat-label">
                        Link đã tạo
                    </div>

                </div>

            </div>


            <!-- VIEW -->

            <div class="stat-card">

                <div class="stat-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 12s3.4-5.5 9.2-5.5S21.2 12 21.2 12 17.8 17.5 12 17.5 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></span></div>

                <div class="stat-info">

                    <div class="stat-number">
                        <?= $total_views ?>
                    </div>

                    <div class="stat-label">
                        Tổng lượt mở
                    </div>

                </div>

            </div>


        </div>


        <!--
        =====================================================================
        🧩 ACTION SECTION
        =====================================================================
        -->

        <div class="section-header">


            <div class="section-title-wrap">

                <div class="section-mini-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 .9 3.1L16 7l-3.1.9L12 11l-.9-3.1L8 7l3.1-.9L12 3Z"/><path d="m18.5 12 .6 2 2 .6-2 .6-.6 2-.6-2-2-.6 2-.6.6-2Z"/><path d="m6 14 .7 2.3L9 17l-2.3.7L6 20l-.7-2.3L3 17l2.3-.7L6 14Z"/></svg></span></div>

                <div>

                    <div class="section-title">
                        Quản lý thiệp
                    </div>

                    <div class="section-subtitle">
                        Tất cả công cụ cho thiệp cưới của bạn
                    </div>

                </div>

            </div>


        </div>


        <div class="cards">


            <!--
            =================================================================
            💌 THÔNG TIN THIỆP
            =================================================================
            -->

            <div class="action-card">

                <div class="action-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg></span></div>

                <h3>
                    Thông tin thiệp
                </h3>

                <p>
                    Chỉnh sửa tên cô dâu, chú rể,
                    ngày cưới, giờ, địa điểm
                    và nội dung lời mời.
                </p>

                <a
                    href="wedding_info.php?id=<?= $wedding_id ?>"
                    class="action-link"
                >
                    Chỉnh sửa
                    →
                </a>

            </div>


            <!--
            =================================================================
            🎨 MẪU THIỆP
            =================================================================
            -->

            <div class="action-card">

                <div class="action-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 .9 3.1L16 7l-3.1.9L12 11l-.9-3.1L8 7l3.1-.9L12 3Z"/><path d="m18.5 12 .6 2 2 .6-2 .6-.6 2-.6-2-2-.6 2-.6.6-2Z"/><path d="m6 14 .7 2.3L9 17l-2.3.7L6 20l-.7-2.3L3 17l2.3-.7L6 14Z"/></svg></span></div>

                <h3>
                    Mẫu thiệp
                </h3>

                <p>
                    Lựa chọn mẫu thiệp phù hợp
                    với phong cách và câu chuyện
                    của hai bạn.
                </p>

                <a
                    href="templates.php"
                    class="action-link"
                >
                    Xem mẫu
                    →
                </a>

            </div>


            <!--
            =================================================================
            📷 ALBUM ẢNH
            =================================================================
            -->

            <div class="action-card">

                <div class="action-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="8.5" cy="9" r="1.5"/><path d="m5.5 17 4.4-4.2 3.2 2.9 2.2-2.1 3.2 3.4"/></svg></span></div>

                <h3>
                    Album ảnh
                </h3>

                <p>
                    Quản lý ảnh bìa và album ảnh
                    cưới hiển thị trên thiệp.
                </p>

                <a
                    href="wedding_images.php?id=<?= $wedding_id ?>"
                    class="action-link"
                >
                    Quản lý ảnh
                    →
                </a>

            </div>


            <!--
            =================================================================
            👥 KHÁCH MỜI
            =================================================================
            -->

            <div class="action-card">

                <div class="action-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9.5" r="2.2"/><path d="M3.5 20c.5-3.5 2.5-5.3 5.5-5.3s5 1.8 5.5 5.3"/><path d="M14 15.5c3 .2 5 1.8 5.5 4.5"/></svg></span></div>

                <h3>
                    Khách mời
                </h3>

                <p>
                    Thêm, sửa, xóa khách mời
                    và quản lý link thiệp riêng
                    cho từng người.
                </p>

                <a
                    href="guests.php"
                    class="action-link"
                >
                    Quản lý khách
                    →
                </a>

            </div>

                    
            <!--⛓ LINK THIỆP -->

            <div class="action-card">
                <div class="action-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.5 14.5-1.7 1.7a3.8 3.8 0 0 1-5.4-5.4l3.2-3.2a3.8 3.8 0 0 1 5.4 0l1.1 1.1"/><path d="m14.5 9.5 1.7-1.7a3.8 3.8 0 0 1 5.4 5.4l-3.2 3.2a3.8 3.8 0 0 1-5.4 0l-1.1-1.1"/><path d="m8.5 15.5 7-7"/></svg></span></div>
                <h3>
                    Link thiệp
                </h3>

                <p>
                    Tạo và quản lý đường dẫn
                    thiệp riêng để gửi đến
                    từng khách mời.
                </p>

                <a
                    href="invitation_links.php<?= $wedding_id ? '?wedding_id=' . intval($wedding_id) : '' ?>"
                    class="action-link"
                >
                    Quản lý link
                    →
                </a>

            </div>


        </div>


        <!--
        =====================================================================
        👁 QUICK PREVIEW
        =====================================================================
        -->

        <div class="preview-strip">


            <div class="preview-left">

                <div class="preview-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 12s3.4-5.5 9.2-5.5S21.2 12 21.2 12 17.8 17.5 12 17.5 2.8 12 2.8 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></span></div>

                <div>

                    <div class="preview-title">
                        Xem thiệp của bạn
                    </div>

                    <div class="preview-desc">
                        Kiểm tra thiệp cưới trước khi gửi cho khách.
                    </div>

                </div>

            </div>


            <?php if ($preview_url): ?>

                <a
                    href="<?= htmlspecialchars($preview_url) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="preview-button"
                >
                    Mở thiệp
                    →
                </a>

            <?php else: ?>

                <a
                    href="guests.php"
                    class="preview-button"
                >
                    Tạo link trước
                    →
                </a>

            <?php endif; ?>


        </div>


        <?php else: ?>


        <!--
        =====================================================================
        📭 EMPTY STATE
        =====================================================================
        -->

        <div class="empty-state">


            <div class="empty-icon"><span class="dashboard-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg></span></div>


            <h2>
                Bạn chưa tạo thiệp cưới
            </h2>


            <p>
                Hãy chọn một mẫu thiệp để bắt đầu
                tạo nên thiệp cưới của riêng bạn.
            </p>


            <a
                href="templates.php"
                class="action-link"
            >
                Chọn mẫu thiệp
                →
            </a>


        </div>


        <?php endif; ?>


    </section>


    <!--
    =========================================================================
    🦶 FOOTER
    =========================================================================
    -->

    <footer class="footer">


        <div class="footer-brand">

            <?= htmlspecialchars($brand_name) ?>
            Weddings

        </div>


        <div class="footer-copy">

            © <?= date("Y") ?>
            <?= htmlspecialchars($copyright_text) ?>

        </div>


        <div class="footer-contact">


            <?php if ($contact_phone !== ""): ?>

                <span>
                    ☎
                    <?= htmlspecialchars($contact_phone) ?>
                </span>

            <?php endif; ?>


            <?php if ($contact_facebook !== ""): ?>

                <a
                    href="<?= htmlspecialchars($contact_facebook) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Facebook
                </a>

            <?php endif; ?>


            <?php if (
                $contact_phone === "" &&
                $contact_facebook === ""
            ): ?>

                <span>
                    Wedding Invitation
                </span>

            <?php endif; ?>


        </div>


    </footer>


</main>


<script>

/*
|--------------------------------------------------------------------------
| 📱 MOBILE SIDEBAR
|--------------------------------------------------------------------------
|
| Phần này chỉ phục vụ giao diện điện thoại.
|
|--------------------------------------------------------------------------
*/

function toggleSidebar() {

    const sidebar =
        document.getElementById("sidebar");

    if (!sidebar) {
        return;
    }

    sidebar.classList.toggle("open");
}


/*
|--------------------------------------------------------------------------
| 📱 ĐÓNG SIDEBAR KHI CLICK LINK TRÊN MOBILE
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(".sidebar .menu-item")
    .forEach(function(item) {

        item.addEventListener(
            "click",
            function() {

                if (
                    window.innerWidth <= 850
                ) {

                    const sidebar =
                        document.getElementById("sidebar");

                    if (sidebar) {
                        sidebar.classList.remove("open");
                    }
                }

            }
        );

    });
                    
</script>


</body>

</html>