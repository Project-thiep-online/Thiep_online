<?php

session_start();

require_once "../config/database.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/*
|--------------------------------------------------------------------------
| KIỂM TRA ADMIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}


$full_name = $_SESSION["full_name"] ?? "Quản trị viên";


/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH MẪU
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM templates
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {
    error_log("Templates query error: " . $conn->error);
    header("Location: dashboard.php?status=database_error");
    exit;
}


$templates = [];

while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}


$total_templates = count($templates);

$active_templates = 0;
$inactive_templates = 0;

foreach ($templates as $item) {

    if ($item["status"] === "active") {
        $active_templates++;
    } else {
        $inactive_templates++;
    }

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
    Quản lý mẫu thiệp - thyu_nhun Weddings
</title>


<!-- FONT -->

<link rel="preconnect" href="https://fonts.googleapis.com">

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
    rel="stylesheet"
>


<!-- ICON -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   RESET
========================================================= */

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


/* =========================================================
   BODY
========================================================= */

body {

    font-family:
        "Be Vietnam Pro",
        Arial,
        sans-serif;

    background:
        var(--cream);

    color:
        var(--text);

    min-height:
        100vh;

}




/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 245px;
    height: 100vh;
    background: var(--dark);
    color: white;
    z-index: 1000;
    overflow-y: auto;
}

.brand {
    padding: 25px 22px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    text-align: center;
}

.brand-mark {
    width: 48px;
    height: 48px;
    margin: 0 auto 10px;
    border-radius: 50%;
    background: linear-gradient(135deg, #F2D58A, #B52A3A);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-family: "Playfair Display", serif;
    font-size: 19px;
}

.brand-name {
    font-family: "Playfair Display", serif;
    font-size: 17px;
    font-weight: 600;
}

.brand-sub {
    margin-top: 3px;
    color: rgba(255,255,255,.55);
    font-size: 9px;
}

.menu {
    padding: 18px 12px;
}

.menu-title {
    color: rgba(255,255,255,.38);
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin: 0 10px 9px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    margin-bottom: 4px;
    border-radius: 8px;
    color: rgba(255,255,255,.68);
    text-decoration: none;
    font-size: 11px;
    transition: .2s;
}

.menu-item i {
    width: 18px;
    text-align: center;
    font-size: 13px;
}

.menu-item:hover {
    background: rgba(255,255,255,.07);
    color: white;
}

.menu-item.active {
    background: rgba(150,80,80,.28);
    color: white;
}

.sidebar-bottom {
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: 15px;
}

.menu-item.logout {
    color: #d9aaa3;
}

@media (max-width: 900px) {
    .sidebar {
        width: 70px;
    }
}

/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left:
        245px;

    width:
        calc(100% - 245px);

    min-height:
        100vh;

}


/* =========================================================
   TOPBAR
========================================================= */

.home-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    padding: 8px 13px;

    background: #f8f1ed;
    color: #8b4a4a;

    border: 1px solid #eadbd4;
    border-radius: 7px;

    text-decoration: none;

    font-size: 11px;
    font-weight: 600;

    transition: .2s;
}

.home-btn:hover {
    background: #8b4a4a;
    color: white;
}

.topbar {

    height:
        70px;

    background:
        rgba(255,255,255,.96);

    border-bottom:
        1px solid
        var(--border);

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        0 30px;

}


.page-title {

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    font-size:
        14px;

    font-weight:
        600;

}


.page-title i {

    color:
        var(--wine);

}


.top-right {

    display:
        flex;

    align-items:
        center;

    gap:
        22px;

}


.notification {

    width:
        36px;

    height:
        36px;

    border-radius:
        50%;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    color:
        var(--text-light);

}


.user-area {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

}


.user-text {

    text-align:
        right;

}


.user-text strong {

    display:
        block;

    font-size:
        12px;

    font-weight:
        600;

}


.user-text span {

    color:
        var(--text-light);

    font-size:
        10px;

}


.avatar {

    width:
        38px;

    height:
        38px;

    border-radius:
        50%;

    background:
        linear-gradient(
            135deg,
            #e8cfc5,
            #b98678
        );

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        white;

    font-size:
        15px;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        30px 32px;

    max-width:
        1500px;

    margin:
        auto;

}


/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        flex-end;

    margin-bottom:
        25px;

}


.heading-left h1 {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        27px;

    font-weight:
        600;

    margin-bottom:
        7px;

}


.heading-left p {

    color:
        var(--text-light);

    font-size:
        12px;

}

.add-template-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    padding: 11px 17px;

    background: var(--wine);
    color: white;

    border-radius: 8px;

    text-decoration: none;

    font-size: 11px;
    font-weight: 600;

    transition: .2s;

    white-space: nowrap;
}

.add-template-btn:hover {
    background: var(--wine-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 15px rgba(127,65,65,.15);
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        15px;

    margin-bottom:
        22px;

}


.summary-card {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        12px;

    padding:
        17px 18px;

    box-shadow:
        var(--shadow);

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

}


.summary-icon {

    width:
        43px;

    height:
        43px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        16px;

}


.summary-icon.all {

    background:
        #f7e5e2;

    color:
        var(--wine);

}


.summary-icon.active {

    background:
        #e7f2ea;

    color:
        var(--green);

}


.summary-icon.inactive {

    background:
        #f1eeee;

    color:
        #7E6669;

}


.summary-text {

    flex:
        1;

}


.summary-text span {

    display:
        block;

    color:
        var(--text-light);

    font-size:
        10px;

    margin-bottom:
        2px;

}


.summary-text strong {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        22px;

    color:
        var(--text);

}


/* =========================================================
   TOOLBAR
========================================================= */

.toolbar {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        12px 12px 0 0;

    padding:
        17px 20px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

}


.search-box {

    width:
        350px;

    max-width:
        100%;

    position:
        relative;

}


.search-box i {

    position:
        absolute;

    left:
        13px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #aaa;

    font-size:
        12px;

}


.search-box input {

    width:
        100%;

    height:
        38px;

    border:
        1px solid
        #e7dfdb;

    border-radius:
        7px;

    padding:
        0 13px 0 36px;

    outline:
        none;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size:
        11px;

}


.search-box input:focus {

    border-color:
        var(--wine);

    box-shadow:
        0 0 0 3px
        rgba(150,80,80,.08);

}


.filter-select {

    height:
        38px;

    padding:
        0 35px 0 12px;

    border:
        1px solid
        #e7dfdb;

    border-radius:
        7px;

    background:
        white;

    color:
        #777;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size:
        11px;

    outline:
        none;

}


.toolbar-right {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

}


/* =========================================================
   TEMPLATE GRID
========================================================= */

.template-panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-top:
        none;

    border-radius:
        0 0 12px 12px;

    padding:
        20px;

    box-shadow:
        var(--shadow);

}


.template-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            auto-fill,
            minmax(255px, 1fr)
        );

    gap:
        20px;

}


/* =========================================================
   TEMPLATE CARD
========================================================= */

.template-card {

    background:
        white;

    border:
        1px solid
        #eee5e1;

    border-radius:
        12px;

    overflow:
        hidden;

    transition:
        .25s;

}


.template-card:hover {

    transform:
        translateY(-4px);

    box-shadow:
        0 12px 30px
        rgba(66,41,37,.10);

    border-color:
        #e2d2cc;

}


/* =========================================================
   PREVIEW
========================================================= */

.preview {

    height:
        310px;

    background:
        #f4efec;

    overflow:
        hidden;

    position:
        relative;

}


.preview iframe {

    width:
        100%;

    height:
        100%;

    border:
        none;

    background:
        white;

}


/*
| lớp phủ nhẹ để card không bị click
| khi đang xem preview
*/

.preview-overlay {

    position:
        absolute;

    inset:
        0;

    pointer-events:
        none;

}


/* =========================================================
   TEMPLATE INFO
========================================================= */

.template-info {

    padding:
        17px;

}


.template-title-row {

    display:
        flex;

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap:
        10px;

}


.template-info h3 {

    font-family:
        "Playfair Display",
        serif;

    color:
        #332b2a;

    font-size:
        16px;

    line-height:
        1.3;

}


.template-code {

    color:
        #aaa;

    font-size:
        9px;

    margin-top:
        4px;

}


.template-info p {

    color:
        #85807d;

    font-size:
        10px;

    line-height:
        1.6;

    min-height:
        32px;

    margin-top:
        9px;

    margin-bottom:
        13px;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        5px 9px;

    border-radius:
        20px;

    font-size:
        9px;

    margin-bottom:
        14px;

}


.status-dot {

    width:
        5px;

    height:
        5px;

    border-radius:
        50%;

}


.status.active {

    background:
        #e7f3ea;

    color:
        #5e916e;

}


.status.active .status-dot {

    background:
        #68a77d;

}


.status.inactive {

    background:
        #eeeeee;

    color:
        #85807d;

}


.status.inactive .status-dot {

    background:
        #999;

}


/* =========================================================
   BUTTONS
========================================================= */

.buttons {

    display: flex;

    gap: 7px;

    align-items: center;

    flex-wrap: wrap;

}


.buttons form {
    margin: 0;
}


.btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        7px;

    padding:
        9px 13px;

    border-radius:
        7px;

    text-decoration:
        none;

    font-size:
        10px;

    font-weight:
        500;

    transition:
        .2s;

}


.btn-view {
    flex: 1;

    background:
        var(--wine);

    color:
        white;

}


.btn-view:hover {

    background:
        var(--wine-dark);

}


.btn-preview {

    width:
        38px;

    background:
        #fbf5f2;

    color:
        var(--wine);

    border:
        1px solid
        #eadbd6;

}


.btn-preview:hover {

    background:
        #f3e7e3;

}

.btn-toggle {
    border: none;
    cursor: pointer;
    font-family: "Be Vietnam Pro", Arial, sans-serif;
}



.btn-edit {
    background: #fbf5f2;
    color: var(--wine);
    border: 1px solid #eadbd6;
}

.btn-edit:hover {
    background: #f3e7e3;
}

/* Nút TẮT */

.btn-off {

    background: #f5e8e5;

    color: #8F1D2C;

}


.btn-off:hover {

    background: #ead5d0;

}


/* Nút BẬT */

.btn-on {

    background: #e5f2e8;

    color: #5c916d;

}


.btn-on:hover {

    background: #d5eadb;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        65px 20px;

    text-align:
        center;

    color:
        var(--text-light);

}


.empty i {

    font-size:
        35px;

    color:
        #d8c6c0;

    margin-bottom:
        12px;

}


.empty strong {

    display:
        block;

    color:
        #665e5b;

    font-size:
        13px;

    margin-bottom:
        5px;

}


.empty span {

    font-size:
        10px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .summary-grid {

        grid-template-columns:
            1fr 1fr;

    }

}


@media (max-width: 900px) {

    .sidebar {

        width:
            70px;

    }

    .brand-name,
    .brand-sub,
    .menu-title,
    .menu-item span {

        display:
            none;

    }

    .brand {

        padding:
            18px 5px;

    }

    .brand-mark {

        width:
            42px;

        height:
            42px;

        font-size:
            17px;

    }

    .menu-item {

        justify-content:
            center;

        padding:
            13px 5px;

    }

    .main {

        margin-left:
            70px;

        width:
            calc(100% - 70px);

    }

}



@media (max-width: 900px) {
    .main {
        margin-left: 70px;
        width: calc(100% - 70px);
    }
}

@media (max-width: 650px) {

    .content {

        padding:
            20px 15px;

    }

    .summary-grid {

        grid-template-columns:
            1fr;

    }

    .toolbar {

        flex-direction:
            column;

        align-items:
            stretch;

    }

    .search-box {

        width:
            100%;

    }

    .toolbar-right {

        width:
            100%;

    }

    .filter-select {

        flex:
            1;

    }

    .user-text {

        display:
            none;

    }

}

@media (max-width: 650px) {

    .page-heading {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .add-template-btn {
        width: 100%;
        justify-content: center;
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


    /* =====================================================
       XÓA MẪU
    ===================================================== */

    .delete-template-form {
        display: inline;
        margin: 0;
    }

    .btn-delete {
        background: #a63f3f;
        color: #ffffff;
        border: 1px solid #a63f3f;
    }

    .btn-delete:hover {
        background: #8f3434;
        border-color: #8f3434;
    }

    /* THÔNG BÁO */

    .flash-message {
        margin: 0 0 22px;
        padding: 14px 18px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 500;
    }

    .flash-success {
        color: #276749;
        background: #edf8f1;
        border: 1px solid #ccebd7;
    }

    .flash-error {
        color: #8b3333;
        background: #fff1f1;
        border: 1px solid #f2cccc;
    }


/* SUBPAGE LAYOUT: only return-home navigation, no full sidebar */
.sidebar{display:none!important;}
.main{margin-left:0!important;width:100%!important;}
@media(max-width:900px){.main{width:100%!important;margin-left:0!important;}.topbar{padding-left:18px!important;padding-right:18px!important;}}
</style>

</head>


<body>


<div class="admin-layout">


<!-- =====================================================
     SIDEBAR
===================================================== -->



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<!-- =====================================================
     TOPBAR
===================================================== -->

<header class="topbar">

<a href="dashboard.php" class="home-btn">
    <i class="fa-solid fa-house"></i>
    Trang chủ
</a>

    <div class="page-title">

        <i class="fa-solid fa-bars"></i>

        <span>
            Quản lý mẫu thiệp
        </span>

    </div>


    <div class="top-right">


        <div class="notification">

            <i class="fa-regular fa-bell"></i>

        </div>


        <div class="user-area">


            <div class="user-text">

                <strong>
                    Xin chào,
                    <?= htmlspecialchars($full_name) ?>
                </strong>

                <span>
                    Quản trị viên
                </span>

            </div>


            <div class="avatar">

                <i class="fa-solid fa-user"></i>

            </div>


        </div>


    </div>


</header>



<!-- =====================================================
     CONTENT
===================================================== -->

<div class="content">


<!-- PAGE HEADING -->

<div class="page-heading">

    <div class="heading-left">

        <h1>
            Mẫu thiệp
        </h1>

        <p>
            Quản lý và xem trước các mẫu thiệp
            đang có trong hệ thống.
        </p>

    </div>

    <a
        href="add_template.php"
        class="add-template-btn"
    >
        <i class="fa-solid fa-plus"></i>
        Thêm mẫu thiệp
    </a>

</div>




<?php if (isset($_GET["success"]) && $_GET["success"] === "template_deleted"): ?>

    <div class="flash-message flash-success">
        <i class="fa-solid fa-circle-check"></i>
        Đã xóa mẫu thiệp khỏi hệ thống.
    </div>

<?php endif; ?>


<?php if (isset($_GET["error"])): ?>

    <div class="flash-message flash-error">

        <i class="fa-solid fa-triangle-exclamation"></i>

        <?php
        $delete_error = $_GET["error"];

        if ($delete_error === "template_in_use") {
            echo "Không thể xóa mẫu này vì hiện đang có thiệp của khách hàng sử dụng.";
        } elseif ($delete_error === "template_not_found") {
            echo "Không tìm thấy mẫu thiệp cần xóa.";
        } elseif ($delete_error === "invalid_request") {
            echo "Yêu cầu xóa mẫu không hợp lệ.";
        } elseif ($delete_error === "delete_failed") {
            echo "Không thể xóa mẫu thiệp. Vui lòng thử lại.";
        }
        ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary-grid">


    <div class="summary-card">


        <div class="summary-icon all">

            <i class="fa-solid fa-palette"></i>

        </div>


        <div class="summary-text">

            <span>
                Tổng số mẫu
            </span>

            <strong>
                <?= $total_templates ?>
            </strong>

        </div>


    </div>



    <div class="summary-card">


        <div class="summary-icon active">

            <i class="fa-solid fa-circle-check"></i>

        </div>


        <div class="summary-text">

            <span>
                Đang hoạt động
            </span>

            <strong>
                <?= $active_templates ?>
            </strong>

        </div>


    </div>



    <div class="summary-card">


        <div class="summary-icon inactive">

            <i class="fa-solid fa-circle-pause"></i>

        </div>


        <div class="summary-text">

            <span>
                Đang tắt
            </span>

            <strong>
                <?= $inactive_templates ?>
            </strong>

        </div>


    </div>


</div>



<!-- =====================================================
     TOOLBAR
===================================================== -->

<div class="toolbar">


    <div class="search-box">

        <i class="fa-solid fa-magnifying-glass"></i>

        <input
            type="text"
            id="searchTemplate"
            placeholder="Tìm kiếm mẫu thiệp..."
            autocomplete="off"
        >

    </div>


    <div class="toolbar-right">


        <select
            id="statusFilter"
            class="filter-select"
        >

            <option value="all">
                Tất cả trạng thái
            </option>

            <option value="active">
                Đang hoạt động
            </option>

            <option value="inactive">
                Đang tắt
            </option>

        </select>


    </div>


</div>



<!-- =====================================================
     TEMPLATE PANEL
===================================================== -->

<div class="template-panel">


<?php if ($total_templates > 0): ?>


    <div
        class="template-grid"
        id="templateGrid"
    >


        <?php foreach ($templates as $template): ?>


            <div
                class="template-card"
                data-name="<?= htmlspecialchars(
                    strtolower(
                        $template["template_name"] .
                        " " .
                        ($template["description"] ?? "")
                    )
                ) ?>"
                data-status="<?= htmlspecialchars(
                    $template["status"]
                ) ?>"
            >


                <!-- PREVIEW -->

                <div class="preview">


                    <iframe
                        src="../<?= htmlspecialchars(
                            $template["folder"]
                        ) ?>/"
                        title="<?= htmlspecialchars(
                            $template["template_name"]
                        ) ?>"
                        loading="lazy"
                    ></iframe>


                    <div class="preview-overlay"></div>


                </div>



                <!-- INFO -->

                <div class="template-info">


                    <div class="template-title-row">


                        <div>

                            <h3>

                                <?= htmlspecialchars(
                                    $template["template_name"]
                                ) ?>

                            </h3>


                            <?php if (!empty(
                                $template["template_code"]
                            )): ?>

                                <div class="template-code">

                                    Mã:
                                    <?= htmlspecialchars(
                                        $template["template_code"]
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </div>


                    </div>



                    <p>

                        <?= htmlspecialchars(
                            $template["description"] ?? ""
                        ) ?>

                    </p>



                    <!-- STATUS -->

                    <?php if (
                        $template["status"] === "active"
                    ): ?>


                        <span
                            class="status active"
                        >

                            <span
                                class="status-dot"
                            ></span>

                            Đang hoạt động

                        </span>


                    <?php else: ?>


                        <span
                            class="status inactive"
                        >

                            <span
                                class="status-dot"
                            ></span>

                            Đang tắt

                        </span>


                    <?php endif; ?>



                    <!-- BUTTONS -->

                    <div class="buttons">


    <!-- XEM MẪU -->

    <a
        href="../<?= htmlspecialchars(
            $template["folder"]
        ) ?>/"
        target="_blank"
        class="btn btn-view"
    >

        <i class="fa-regular fa-eye"></i>

        Xem mẫu

    </a>


    <!-- SỬA MẪU -->
<a
    href="edit_template.php?id=<?= intval($template["id"]) ?>"
    class="btn btn-edit"
    title="Sửa mẫu"
>
    <i class="fa-solid fa-pen"></i>
    Sửa
</a>

<!-- BẬT / TẮT -->

    <form
        method="POST"
        action="toggle_template.php"
        style="display:inline;"
        onsubmit="return confirm(
            'Bạn có chắc muốn <?= $template["status"] === "active"
                ? "tắt"
                : "bật"
            ?> mẫu thiệp này không?'
        );"
    >

        <input
            type="hidden"
            name="template_id"
            value="<?= intval($template["id"]) ?>"
        >


        <?php if ($template["status"] === "active"): ?>

            <button
                type="submit"
                class="btn btn-toggle btn-off"
                title="Tắt mẫu"
            >

                <i class="fa-solid fa-power-off"></i>

                Tắt

            </button>

        <?php else: ?>

            <button
                type="submit"
                class="btn btn-toggle btn-on"
                title="Bật mẫu"
            >

                <i class="fa-solid fa-power-off"></i>

                Bật

            </button>

        <?php endif; ?>

    </form>


    <!-- XÓA MẪU -->
    <form
        method="POST"
        action="delete_template.php"
        class="delete-template-form"
        onsubmit="return confirm('Bạn có chắc chắn muốn xóa mẫu thiệp này khỏi hệ thống?\n\nLưu ý: Nếu đang có khách hàng sử dụng mẫu này, hệ thống sẽ không cho phép xóa để tránh làm hỏng thiệp của khách.');"
    >

        <input
            type="hidden"
            name="template_id"
            value="<?= intval($template["id"]) ?>"
        >

        <button
            type="submit"
            class="btn btn-delete"
            title="Xóa mẫu"
        >
            <i class="fa-solid fa-trash-can"></i>
            Xóa
        </button>

    </form>


</div>


                </div>


            </div>


        <?php endforeach; ?>


    </div>


    <!-- EMPTY SEARCH -->

    <div
        class="empty"
        id="searchEmpty"
        style="display:none;"
    >

        <i
            class="fa-solid fa-magnifying-glass"
        ></i>

        <strong>
            Không tìm thấy mẫu thiệp
        </strong>

        <span>
            Thử tìm kiếm với từ khóa khác.
        </span>

    </div>


<?php else: ?>


    <div class="empty">


        <i
            class="fa-regular fa-images"
        ></i>


        <strong>
            Chưa có mẫu thiệp nào
        </strong>


        <span>
            Hiện chưa có mẫu thiệp
            trong hệ thống.
        </span>


    </div>


<?php endif; ?>


</div>


</div>


</main>


</div>



<!-- =====================================================
     SEARCH + FILTER
===================================================== -->

<script>

const searchInput =
    document.getElementById(
        "searchTemplate"
    );

const statusFilter =
    document.getElementById(
        "statusFilter"
    );

const cards =
    document.querySelectorAll(
        ".template-card"
    );

const searchEmpty =
    document.getElementById(
        "searchEmpty"
    );


function filterTemplates() {

    const keyword =
        searchInput
            ? searchInput.value
                .toLowerCase()
                .trim()
            : "";

    const status =
        statusFilter
            ? statusFilter.value
            : "all";

    let visible =
        0;


    cards.forEach(
        function(card) {


            const name =
                card.dataset.name || "";


            const cardStatus =
                card.dataset.status || "";


            const matchKeyword =
                name.includes(keyword);


            const matchStatus =
                status === "all" ||
                cardStatus === status;


            if (
                matchKeyword &&
                matchStatus
            ) {

                card.style.display =
                    "";

                visible++;

            } else {

                card.style.display =
                    "none";

            }

        }
    );


    if (searchEmpty) {

        searchEmpty.style.display =
            visible === 0
                ? "block"
                : "none";

    }

}


if (searchInput) {

    searchInput.addEventListener(
        "input",
        filterTemplates
    );

}


if (statusFilter) {

    statusFilter.addEventListener(
        "change",
        filterTemplates
    );

}

</script>


</body>

</html>