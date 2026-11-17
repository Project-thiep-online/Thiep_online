<?php

session_start();

require_once "../config/database.php";
require_once "../config/url_helper.php";

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
| LẤY DANH SÁCH THIỆP
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        w.id,
        w.user_id,
        w.template_id,
        w.wedding_code,
        w.wedding_slug,
        w.groom_name,
        w.bride_name,
        w.wedding_date,
        w.wedding_time,
        w.venue,
        w.address,
        w.description,
        u.full_name,
        u.username,
        t.template_name
    FROM weddings w

    INNER JOIN users u
        ON w.user_id = u.id

    LEFT JOIN templates t
        ON w.template_id = t.id

    ORDER BY w.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    header("Location: weddings.php?status=error");
    exit;
}

/*
|--------------------------------------------------------------------------
| CHUẨN BỊ DỮ LIỆU THIỆP
|--------------------------------------------------------------------------
*/

$weddings = [];
$wedding_ids = [];

while ($row = $result->fetch_assoc()) {
    $row["guests"] = [];

    $template_id = (int)($row["template_id"] ?? 0);
    $wedding_id = (int)($row["id"] ?? 0);

    /* Xem đúng mẫu khách hàng đã chọn, không phụ thuộc khách mời. */
    $row["preview_url"] = $template_id > 0
        ? "preview_template.php?template_id=" . $template_id . "&wedding_id=" . $wedding_id
        : "";

    $weddings[] = $row;
    $wedding_ids[] = $wedding_id;
}

/* Tổng số thiệp - dùng cho các thẻ thống kê và điều kiện hiển thị */
$total_weddings = count($weddings);

$total_guests = 0;
$total_links = 0;

/*
|--------------------------------------------------------------------------
| LẤY TOÀN BỘ KHÁCH MỜI TRONG 1 QUERY
|--------------------------------------------------------------------------
*/

if (!empty($wedding_ids)) {

    $id_list = implode(",", array_map("intval", $wedding_ids));

    $guest_sql = "
        SELECT
            g.wedding_id,
            g.guest_name,
            il.token,
            il.public_code
        FROM guests g
        LEFT JOIN invitation_links il
            ON il.guest_id = g.id
            AND il.wedding_id = g.wedding_id
        WHERE g.wedding_id IN ($id_list)
        ORDER BY g.id DESC
    ";

    $guest_result = $conn->query($guest_sql);

    if ($guest_result) {

        $guest_map = [];

        while ($guest = $guest_result->fetch_assoc()) {
            $wedding_id = (int)$guest["wedding_id"];

            if (!isset($guest_map[$wedding_id])) {
                $guest_map[$wedding_id] = [];
            }

            $guest_map[$wedding_id][] = $guest;
            $total_guests++;

            if (!empty($guest["token"])) {
                $total_links++;
            }
        }

        foreach ($weddings as &$wedding) {
            $id = (int)$wedding["id"];
            $wedding["guests"] = $guest_map[$id] ?? [];
        }
        unset($wedding);
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
    Quản lý thiệp cưới - thyu_nhun Weddings
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
    box-shadow: 4px 0 18px rgba(33, 25, 27, .08);
}

.brand {
    min-height: 88px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,.07);
}

.brand-mark {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #b77b6d, #8f4b4b);
    color: white;
    font-size: 17px;
}

.brand-name {
    font-family: "Playfair Display", serif;
    font-size: 17px;
    font-weight: 600;
}

.brand-sub {
    margin-top: 3px;
    color: #b7aaa8;
    font-size: 8px;
    letter-spacing: 1.2px;
}

.menu {
    padding: 18px 12px;
}

.menu-title {
    padding: 14px 11px 8px;
    color: #8e8180;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 1.2px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 12px;
    margin-bottom: 4px;
    border-radius: 8px;
    color: #c8bfbd;
    text-decoration: none;
    font-size: 11px;
    transition: .2s ease;
}

.menu-item i {
    width: 18px;
    text-align: center;
    font-size: 12px;
}

.menu-item:hover {
    background: rgba(255,255,255,.07);
    color: white;
}

.menu-item.active {
    background: linear-gradient(
        135deg,
        rgba(150,80,80,.95),
        rgba(127,65,65,.95)
    );
    color: white;
    box-shadow: 0 5px 14px rgba(0,0,0,.14);
}

.menu-item.logout-item {
    margin-top: 18px;
    color: #d9aaa5;
}

.menu-item.logout-item:hover {
    background: rgba(217,170,165,.09);
    color: #f2c5bf;
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.12);
    border-radius: 10px;
}


.flash-message {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 11px 14px;
    margin-bottom: 18px;
    border-radius: 9px;
    font-size: 11px;
    border: 1px solid transparent;
}

.flash-message.success {
    background: #edf7f0;
    color: #4d8060;
    border-color: #d6eadc;
}

.flash-message.error {
    background: #fff0ef;
    color: #9b5550;
    border-color: #f0d5d2;
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
        1600px;

    margin:
        auto;

}


/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    margin-bottom:
        23px;

}


.page-heading h1 {

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


.page-heading p {

    color:
        var(--text-light);

    font-size:
        12px;

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
        44px;

    height:
        44px;

    border-radius:
        50%;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    font-size:
        16px;

}


.icon-wedding {

    background:
        #f7e5e2;

    color:
        var(--wine);

}


.icon-guest {

    background:
        #f5eddf;

    color:
        #a87e43;

}


.icon-link {

    background:
        #e7f2ea;

    color:
        var(--green);

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

}


.search-box {

    width:
        380px;

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


.result-count {

    color:
        var(--text-light);

    font-size:
        10px;

}


/* =========================================================
   TABLE
========================================================= */

.table-box {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-top:
        none;

    border-radius:
        0 0 12px 12px;

    overflow-x:
        auto;

    box-shadow:
        var(--shadow);

}


table {

    width:
        100%;

    border-collapse:
        collapse;

    min-width:
        980px;

}


th {

    padding:
        14px 15px;

    background:
        #f9f4f1;

    color:
        #7f6864;

    font-size:
        10px;

    font-weight:
        600;

    text-align:
        left;

    white-space:
        nowrap;

    border-bottom:
        1px solid
        var(--border);

}


td {

    padding:
        15px;

    border-bottom:
        1px solid
        #f0eae7;

    vertical-align:
        middle;

    font-size:
        11px;

}


tr:last-child td {

    border-bottom:
        none;

}


tbody tr {

    transition:
        .2s;

}


tbody tr:hover {

    background:
        #fdfaf8;

}


/* =========================================================
   ID
========================================================= */

.id-badge {

    width:
        31px;

    height:
        31px;

    border-radius:
        8px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #f7e8e5;

    color:
        var(--wine);

    font-weight:
        600;

    font-size:
        10px;

}


/* =========================================================
   CUSTOMER
========================================================= */

.customer-box {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    min-width:
        150px;

}


.customer-avatar {

    width:
        35px;

    height:
        35px;

    border-radius:
        50%;

    background:
        #eee0db;

    color:
        var(--wine);

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    flex-shrink:
        0;

}


.customer-name {

    font-weight:
        600;

    color:
        #3d3635;

}


.customer-username {

    color:
        #a09a97;

    font-size:
        9px;

    margin-top:
        3px;

}


/* =========================================================
   COUPLE
========================================================= */

.couple-box {

    min-width:
        170px;

}


.couple-name {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        14px;

    color:
        #554240;

}


.couple-name span {

    color:
        var(--wine);

    margin:
        0 4px;

}


.wedding-code {

    margin-top:
        4px;

    color:
        #aaa;

    font-size:
        9px;

}


/* =========================================================
   DATE
========================================================= */

.date-box {

    white-space:
        nowrap;

}


.date-main {

    font-weight:
        600;

    color:
        #4b4240;

}


.time {

    margin-top:
        4px;

    color:
        #a19a97;

    font-size:
        9px;

}


/* =========================================================
   VENUE
========================================================= */

.venue-box {

    max-width:
        210px;

}


.venue-name {

    font-weight:
        600;

    color:
        #554c49;

}


.venue-address {

    margin-top:
        4px;

    color:
        #99918e;

    line-height:
        1.5;

    font-size:
        9px;

}


/* =========================================================
   TEMPLATE
========================================================= */

.template-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        7px 10px;

    border-radius:
        7px;

    background:
        #f6ebe7;

    color:
        var(--wine);

    font-size:
        9px;

    white-space:
        nowrap;

}


.template-badge i {

    font-size:
        9px;

}


/* =========================================================
   ACTION
========================================================= */

.action-cell {

    text-align:
        center;

}


.view-wedding {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        6px;

    padding:
        9px 12px;

    background:
        var(--wine);

    color:
        white;

    text-decoration:
        none;

    border-radius:
        7px;

    font-size:
        9px;

    white-space:
        nowrap;

    transition:
        .2s;

}


.view-wedding:hover {

    background:
        var(--wine-dark);

    transform:
        translateY(-1px);

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        70px 20px;

    text-align:
        center;

    color:
        var(--text-light);

}


.empty i {

    font-size:
        38px;

    color:
        #d8c6c0;

    margin-bottom:
        13px;

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
   SEARCH EMPTY
========================================================= */

.search-empty {

    display:
        none;

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

        gap:
            10px;

    }

    .search-box {

        width:
            100%;

    }

    .result-count {

        text-align:
            right;

    }

    .user-text {

        display:
            none;

    }

}


@media (max-width: 650px) {
    .topbar {
        padding: 0 15px;
    }

    .page-title {
        display: none;
    }

    .home-btn {
        font-size: 10px;
        padding: 7px 10px;
    }
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


<header class="topbar">

<a href="dashboard.php" class="home-btn">
    <i class="fa-solid fa-house"></i>
    Trang chủ
</a>

    <div class="page-title">

        <i class="fa-regular fa-envelope"></i>

        <span>
            Quản lý thiệp cưới
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


    
<?php
$status_message = "";
$status_class = "";

switch ($_GET["status"] ?? "") {
    case "updated":
        $status_message = "Đã cập nhật trạng thái mẫu thiệp.";
        $status_class = "success";
        break;
    case "invalid":
        $status_message = "Mã mẫu thiệp không hợp lệ.";
        $status_class = "error";
        break;
    case "not_found":
        $status_message = "Không tìm thấy mẫu thiệp.";
        $status_class = "error";
        break;
    case "invalid_status":
        $status_message = "Trạng thái mẫu thiệp không hợp lệ.";
        $status_class = "error";
        break;
    case "error":
        $status_message = "Không thể cập nhật dữ liệu.";
        $status_class = "error";
        break;
}
?>

<?php if ($status_message !== ""): ?>
    <div class="flash-message <?= $status_class ?>">
        <i class="fa-solid <?= $status_class === "success" ? "fa-circle-check" : "fa-circle-exclamation" ?>"></i>
        <span><?= htmlspecialchars($status_message) ?></span>
    </div>
<?php endif; ?>


<div class="page-heading">

        <h1>
            Thiệp cưới
        </h1>

        <p>
            Quản lý các thiệp cưới đang được tạo
            trong hệ thống.
        </p>

    </div>



    <!-- =================================================
         SUMMARY
    ================================================== -->

    <div class="summary-grid">


        <div class="summary-card">


            <div class="summary-icon icon-wedding">

                <i class="fa-regular fa-envelope"></i>

            </div>


            <div class="summary-text">

                <span>
                    Tổng số thiệp
                </span>

                <strong>
                    <?= $total_weddings ?>
                </strong>

            </div>


        </div>



        <div class="summary-card">


            <div class="summary-icon icon-guest">

                <i class="fa-solid fa-user-group"></i>

            </div>


            <div class="summary-text">

                <span>
                    Tổng khách mời
                </span>

                <strong>
                    <?= $total_guests ?>
                </strong>

            </div>


        </div>



        <div class="summary-card">


            <div class="summary-icon icon-link">

                <i class="fa-solid fa-link"></i>

            </div>


            <div class="summary-text">

                <span>
                    Link thiệp đã tạo
                </span>

                <strong>
                    <?= $total_links ?>
                </strong>

            </div>


        </div>


    </div>



    <!-- =================================================
         TOOLBAR
    ================================================== -->

    <div class="toolbar">


        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                id="searchWedding"
                placeholder="Tìm theo khách hàng, cô dâu, chú rể..."
                autocomplete="off"
            >

        </div>


        <div class="result-count">

            Hiển thị
            <strong id="visibleCount">
                <?= $total_weddings ?>
            </strong>
            thiệp

        </div>


    </div>



    <!-- =================================================
         TABLE
    ================================================== -->

    <div class="table-box">


    <?php if ($total_weddings > 0): ?>


        <table>


            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        KHÁCH HÀNG
                    </th>

                    <th>
                        CẶP ĐÔI
                    </th>

                    <th>
                        NGÀY CƯỚI
                    </th>

                    <th>
                        ĐỊA ĐIỂM
                    </th>

                    <th>
                        MẪU THIỆP
                    </th>
                    <th>
                        THAO TÁC
                    </th>

                </tr>

            </thead>


            <tbody id="weddingTable">


            <?php foreach ($weddings as $wedding): ?>


                <?php

                $search_text =
                    strtolower(
                        $wedding["full_name"] .
                        " " .
                        $wedding["username"] .
                        " " .
                        $wedding["groom_name"] .
                        " " .
                        $wedding["bride_name"] .
                        " " .
                        $wedding["venue"] .
                        " " .
                        $wedding["address"]
                    );

                ?>


                <tr
                    class="wedding-row"
                    data-search="<?= htmlspecialchars(
                        $search_text
                    ) ?>"
                >


                    <!-- ID -->

                    <td>

                        <div class="id-badge">

                            <?= intval(
                                $wedding["id"]
                            ) ?>

                        </div>

                    </td>



                    <!-- CUSTOMER -->

                    <td>


                        <div class="customer-box">


                            <div class="customer-avatar">

                                <i
                                    class="fa-solid fa-user"
                                ></i>

                            </div>


                            <div>

                                <div class="customer-name">

                                    <?= htmlspecialchars(
                                        $wedding["full_name"]
                                    ) ?>

                                </div>


                                <div class="customer-username">

                                    @<?= htmlspecialchars(
                                        $wedding["username"]
                                    ) ?>

                                </div>

                            </div>


                        </div>


                    </td>



                    <!-- COUPLE -->

                    <td>


                        <div class="couple-box">


                            <div class="couple-name">

                                <?= htmlspecialchars(
                                    $wedding["groom_name"] ?: "Chưa có"
                                ) ?>

                                <span>
                                    &
                                </span>

                                <?= htmlspecialchars(
                                    $wedding["bride_name"] ?: "Chưa có"
                                ) ?>

                            </div>


                            <div class="wedding-code">

                                Mã:
                                <?= htmlspecialchars(
                                    $wedding["wedding_code"]
                                ) ?>

                            </div>


                        </div>


                    </td>



                    <!-- DATE -->

                    <td>


                        <div class="date-box">


                            <div class="date-main">

                                <?php

                                if (
                                    !empty(
                                        $wedding["wedding_date"]
                                    )
                                ) {

                                    echo date(
                                        "d/m/Y",
                                        strtotime(
                                            $wedding["wedding_date"]
                                        )
                                    );

                                } else {

                                    echo "Chưa có";

                                }

                                ?>

                            </div>


                            <div class="time">

                                <i
                                    class="fa-regular fa-clock"
                                ></i>

                                <?= !empty(
                                    $wedding["wedding_time"]
                                )
                                    ? htmlspecialchars(
                                        $wedding["wedding_time"]
                                    )
                                    : "Chưa có"
                                ?>

                            </div>


                        </div>


                    </td>



                    <!-- VENUE -->

                    <td>


                        <div class="venue-box">


                            <div class="venue-name">

                                <?= !empty(
                                    $wedding["venue"]
                                )
                                    ? htmlspecialchars(
                                        $wedding["venue"]
                                    )
                                    : "Chưa có địa điểm"
                                ?>

                            </div>


                            <?php if (
                                !empty(
                                    $wedding["address"]
                                )
                            ): ?>


                                <div class="venue-address">

                                    <?= htmlspecialchars(
                                        $wedding["address"]
                                    ) ?>

                                </div>


                            <?php endif; ?>


                        </div>


                    </td>



                    <!-- TEMPLATE -->

                    <td>


                        <div class="template-badge">


                            <i
                                class="fa-solid fa-palette"
                            ></i>


                            <?php if (
                                !empty(
                                    $wedding["template_name"]
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $wedding["template_name"]
                                ) ?>

                            <?php else: ?>

                                Mẫu
                                <?= intval(
                                    $wedding["template_id"]
                                ) ?>

                            <?php endif; ?>


                        </div>


                    </td>





                    <!-- ACTION -->

                    <td class="action-cell">


                        <?php if (!empty($wedding["preview_url"])): ?>

                            <a
                                href="<?= htmlspecialchars(
                                    $wedding["preview_url"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="view-wedding"
                                title="Xem mẫu thiệp khách hàng đã chọn"
                            >

                                <i
                                    class="fa-regular fa-eye"
                                ></i>

                                Xem mẫu

                            </a>

                        <?php else: ?>

                            <span
                                class="no-link"
                                title="Thiệp này chưa được chọn mẫu"
                            >
                                <i
                                    class="fa-solid fa-link-slash"
                                ></i>

                                Chưa chọn mẫu

                            </span>

                        <?php endif; ?>


                    </td>


                </tr>


            <?php endforeach; ?>


            </tbody>


        </table>


        <div
            id="searchEmpty"
            class="empty search-empty"
        >

            <i
                class="fa-solid fa-magnifying-glass"
            ></i>

            <strong>
                Không tìm thấy thiệp
            </strong>

            <span>
                Thử tìm kiếm bằng tên khách hàng,
                cô dâu hoặc chú rể.
            </span>

        </div>


    <?php else: ?>


        <div class="empty">

            <i
                class="fa-regular fa-envelope"
            ></i>

            <strong>
                Chưa có thiệp cưới nào
            </strong>

            <span>
                Hiện chưa có khách hàng nào
                tạo thiệp trong hệ thống.
            </span>

        </div>


    <?php endif; ?>


    </div>


</div>


</main>


</div>



<!-- =====================================================
     SEARCH
===================================================== -->

<script>

const searchInput =
    document.getElementById(
        "searchWedding"
    );

const rows =
    document.querySelectorAll(
        ".wedding-row"
    );

const visibleCount =
    document.getElementById(
        "visibleCount"
    );

const searchEmpty =
    document.getElementById(
        "searchEmpty"
    );


if (searchInput) {

    searchInput.addEventListener(
        "input",
        function () {

            const keyword =
                this.value
                    .toLowerCase()
                    .trim();

            let count = 0;


            rows.forEach(
                function (row) {

                    const text =
                        row.dataset.search
                        || "";


                    if (
                        text.includes(keyword)
                    ) {

                        row.style.display =
                            "";

                        count++;

                    } else {

                        row.style.display =
                            "none";

                    }

                }
            );


            if (visibleCount) {

                visibleCount.textContent =
                    count;

            }


            if (searchEmpty) {

                searchEmpty.style.display =
                    count === 0
                        ? "block"
                        : "none";

            }

        }
    );

}

</script>


</body>

</html>