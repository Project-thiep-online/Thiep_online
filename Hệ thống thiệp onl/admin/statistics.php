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
| 1. TỔNG SỐ KHÁCH HÀNG
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
";

$result = $conn->query($sql);
$total_customers = $result ? (int)($result->fetch_assoc()["total"] ?? 0) : 0;


/*
|--------------------------------------------------------------------------
| 2. TỔNG SỐ THIỆP
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM weddings
";

$result = $conn->query($sql);
$total_weddings = $result ? (int)($result->fetch_assoc()["total"] ?? 0) : 0;


/*
|--------------------------------------------------------------------------
| 3. TỔNG SỐ KHÁCH MỜI
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM guests
";

$result = $conn->query($sql);
$total_guests = $result ? (int)($result->fetch_assoc()["total"] ?? 0) : 0;


/*
|--------------------------------------------------------------------------
| 4. TỔNG LƯỢT TRUY CẬP
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COALESCE(SUM(visit_count), 0) AS total
    FROM invitation_links
";

$result = $conn->query($sql);
$total_views = $result ? (int)($result->fetch_assoc()["total"] ?? 0) : 0;


/*
|--------------------------------------------------------------------------
| 5. TỔNG LINK THIỆP
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM invitation_links
";

$result = $conn->query($sql);
$total_links = $result ? (int)($result->fetch_assoc()["total"] ?? 0) : 0;


/*
|--------------------------------------------------------------------------
| 6. SỐ MẪU THIỆP ĐANG HOẠT ĐỘNG
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM templates
    WHERE status = 'active'
";

$result = $conn->query($sql);
$total_templates = $result ? (int)($result->fetch_assoc()["total"] ?? 0) : 0;


/*
|--------------------------------------------------------------------------
| 7. THIỆP CÓ NHIỀU LƯỢT XEM NHẤT
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        w.id,
        w.groom_name,
        w.bride_name,
        w.wedding_code,
        COALESCE(SUM(il.visit_count), 0) AS views
    FROM weddings w

    LEFT JOIN invitation_links il
        ON w.id = il.wedding_id

    GROUP BY
        w.id,
        w.groom_name,
        w.bride_name,
        w.wedding_code

    ORDER BY views DESC
    LIMIT 5
";

$result = $conn->query($sql);

$top_weddings = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $top_weddings[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| 8. THỐNG KÊ THIỆP THEO MẪU
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.template_name,
        COUNT(w.id) AS total
    FROM templates t

    LEFT JOIN weddings w
        ON w.template_id = t.id

    GROUP BY
        t.id,
        t.template_name

    ORDER BY total DESC
";

$result = $conn->query($sql);

$template_stats = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $template_stats[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| 9. THỐNG KÊ TRẠNG THÁI LINK
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(*) AS total_links,
        SUM(
            CASE
                WHEN visit_count > 0 THEN 1
                ELSE 0
            END
        ) AS opened_links
    FROM invitation_links
";

$result = $conn->query($sql);

$link_stats = $result ? $result->fetch_assoc() : [];

$total_link_records = intval(
    $link_stats["total_links"] ?? 0
);

$opened_links = intval(
    $link_stats["opened_links"] ?? 0
);

$unopened_links =
    $total_link_records - $opened_links;


/*
|--------------------------------------------------------------------------
| TÍNH % CHO BIỂU ĐỒ
|--------------------------------------------------------------------------
*/

$max_views = 1;

foreach ($top_weddings as $wedding) {

    if (
        intval($wedding["views"])
        > $max_views
    ) {

        $max_views =
            intval($wedding["views"]);

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
    Thống kê - thyu_nhun Weddings
</title>


<!-- FONT -->

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
    href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
    rel="stylesheet"
>


<!-- ICON -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =====================================================
   RESET
===================================================== */

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


/* =====================================================
   BODY
===================================================== */

body {

    font-family:
        "Be Vietnam Pro",
        Arial,
        sans-serif;

    background:
        var(--cream);

    color:
        var(--text);

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
                    #4A101A 65%,
                    #3D0B12 100%
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
                rgba(212,167,61,.25);
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

/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 265px;

    min-height: 100vh;

}


/* =====================================================
   TOPBAR
===================================================== */

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

    height: 70px;

    background:
        white;

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
        0 32px;

}


.page-title {

    display:
        flex;

    align-items:
        center;

    gap: 12px;

    font-size: 14px;

    font-weight: 600;

}


.page-title i {

    color:
        var(--wine);

}


.user-area {

    display:
        flex;

    align-items:
        center;

    gap: 10px;

}


.user-info {

    text-align:
        right;

}


.user-info strong {

    display:
        block;

    font-size: 12px;

}


.user-info span {

    color:
        var(--muted);

    font-size: 10px;

}


.avatar {

    width: 39px;

    height: 39px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #ead8cf,
            #b98778
        );

    color: white;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


/* =====================================================
   CONTENT
===================================================== */

.content {

    padding:
        32px;

}


.heading {

    margin-bottom:
        25px;

}


.heading h1 {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        28px;

    margin-bottom:
        6px;

}


.heading p {

    color:
        var(--muted);

    font-size:
        12px;

}


/* =====================================================
   STAT CARDS
===================================================== */

.stat-grid {

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        16px;

    margin-bottom:
        22px;

}


.stat-card {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    padding:
        20px;

    display:
        flex;

    align-items:
        center;

    gap:
        14px;

    box-shadow:
        0 5px 20px
        rgba(50,30,25,.04);

}


.stat-icon {

    width:
        48px;

    height:
        48px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        17px;

    flex-shrink:
        0;

}


.stat-card:nth-child(1)
.stat-icon {

    background:
        #f7e5e2;

    color:
        var(--wine);

}


.stat-card:nth-child(2)
.stat-icon {

    background:
        #f6eddd;

    color:
        #ae864c;

}


.stat-card:nth-child(3)
.stat-icon {

    background:
        #e7f1ea;

    color:
        var(--green);

}


.stat-card:nth-child(4)
.stat-icon {

    background:
        #eee9f4;

    color:
        var(--purple);

}


.stat-info span {

    display:
        block;

    color:
        var(--muted);

    font-size:
        10px;

    margin-bottom:
        4px;

}


.stat-info strong {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        25px;

}


/* =====================================================
   TWO COLUMNS
===================================================== */

.chart-grid {

    display:
        grid;

    grid-template-columns:
        1.5fr 1fr;

    gap:
        20px;

    margin-bottom:
        20px;

}


.panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    padding:
        22px;

    box-shadow:
        0 5px 20px
        rgba(50,30,25,.04);

}


.panel-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        22px;

}


.panel-header h2 {

    font-size:
        14px;

    font-weight:
        600;

}


.panel-header span {

    color:
        var(--muted);

    font-size:
        10px;

}


/* =====================================================
   BAR CHART
===================================================== */

.bar-chart {

    display:
        flex;

    flex-direction:
        column;

    gap:
        17px;

}


.bar-row {

    display:
        grid;

    grid-template-columns:
        145px 1fr 35px;

    align-items:
        center;

    gap:
        10px;

}


.bar-name {

    font-size:
        10px;

    white-space:
        nowrap;

    overflow:
        hidden;

    text-overflow:
        ellipsis;

}


.bar-track {

    height:
        9px;

    background:
        #f1eae7;

    border-radius:
        10px;

    overflow:
        hidden;

}


.bar-fill {

    height:
        100%;

    background:
        linear-gradient(
            90deg,
            #b16b6b,
            #965050
        );

    border-radius:
        10px;

    transition:
        width .5s;

}


.bar-value {

    text-align:
        right;

    font-size:
        10px;

    font-weight:
        600;

    color:
        var(--wine);

}


/* =====================================================
   TEMPLATE STATS
===================================================== */

.template-list {

    display:
        flex;

    flex-direction:
        column;

    gap:
        14px;

}


.template-row {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding-bottom:
        13px;

    border-bottom:
        1px solid
        #f0e9e6;

}


.template-row:last-child {

    border-bottom:
        none;

}


.template-info {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

}


.template-icon {

    width:
        35px;

    height:
        35px;

    border-radius:
        8px;

    background:
        #f7e9e5;

    color:
        var(--wine);

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


.template-name {

    font-size:
        10px;

    font-weight:
        500;

}


.template-count {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        18px;

    color:
        var(--wine);

}


/* =====================================================
   LINK STATS
===================================================== */

.bottom-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        20px;

}


.link-summary {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        15px;

}


.link-box {

    border:
        1px solid
        var(--border);

    border-radius:
        10px;

    padding:
        18px;

}


.link-box i {

    font-size:
        17px;

    margin-bottom:
        12px;

}


.link-box strong {

    display:
        block;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        26px;

}


.link-box span {

    display:
        block;

    color:
        var(--muted);

    font-size:
        10px;

    margin-top:
        4px;

}


.link-open i {

    color:
        var(--green);

}


.link-not-open i {

    color:
        #b99b82;

}


/* =====================================================
   SYSTEM INFO
===================================================== */

.info-list {

    display:
        flex;

    flex-direction:
        column;

}


.info-row {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding:
        13px 0;

    border-bottom:
        1px solid
        #f0e9e6;

}


.info-row:last-child {

    border-bottom:
        none;

}


.info-label {

    color:
        var(--muted);

    font-size:
        10px;

}


.info-value {

    font-weight:
        600;

    font-size:
        11px;

}


/* =====================================================
   EMPTY
===================================================== */

.empty {

    text-align:
        center;

    padding:
        25px;

    color:
        var(--muted);

    font-size:
        10px;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {

    .stat-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .chart-grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 850px) {

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

        height:
            110px;

    }

    .brand-icon {

        width:
            43px;

        height:
            43px;

    }

    .menu-item {

        justify-content:
            center;

    }

    .main {

        margin-left:
            70px;

    }

    .bottom-grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 600px) {

    .content {

        padding:
            20px 15px;

    }

    .stat-grid {

        grid-template-columns:
            1fr;

    }

    .user-info {

        display:
            none;

    }

    .bar-row {

        grid-template-columns:
            100px 1fr 30px;

    }

}


@media (max-width: 600px) {
    .page-title {
        display: none;
    }

    .topbar {
        padding: 0 15px;
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


<div class="layout">


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

        <i
            class="fa-solid fa-chart-column"
        ></i>

        <span>
            Thống kê hệ thống
        </span>

    </div>


    <div class="user-area">


        <div class="user-info">

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


</header>



<div class="content">


<!-- =====================================================
     HEADING
===================================================== -->


<?php
$status_message = "";
$status_class = "";

switch ($_GET["status"] ?? "") {
    case "updated":
        $status_message = "Đã cập nhật thành công.";
        $status_class = "success";
        break;
    case "error":
        $status_message = "Đã xảy ra lỗi khi xử lý dữ liệu.";
        $status_class = "error";
        break;
}

if ($status_message !== ""):
?>
    <div class="flash-message <?= $status_class ?>">
        <i class="fa-solid <?= $status_class === "success" ? "fa-circle-check" : "fa-circle-exclamation" ?>"></i>
        <span><?= htmlspecialchars($status_message) ?></span>
    </div>
<?php endif; ?>

<div class="heading">

    <h1>
        Thống kê
    </h1>

    <p>
        Tổng quan hoạt động của hệ thống thiệp online.
    </p>

</div>



<!-- =====================================================
     STAT CARDS
===================================================== -->

<div class="stat-grid">


    <div class="stat-card">

        <div class="stat-icon">

            <i
                class="fa-regular fa-eye"
            ></i>

        </div>

        <div class="stat-info">

            <span>
                Lượt truy cập
            </span>

            <strong>
                <?= number_format(
                    $total_views
                ) ?>
            </strong>

        </div>

    </div>



    <div class="stat-card">

        <div class="stat-icon">

            <i
                class="fa-regular fa-envelope"
            ></i>

        </div>

        <div class="stat-info">

            <span>
                Thiệp đã tạo
            </span>

            <strong>
                <?= number_format(
                    $total_weddings
                ) ?>
            </strong>

        </div>

    </div>



    <div class="stat-card">

        <div class="stat-icon">

            <i
                class="fa-solid fa-users"
            ></i>

        </div>

        <div class="stat-info">

            <span>
                Khách mời
            </span>

            <strong>
                <?= number_format(
                    $total_guests
                ) ?>
            </strong>

        </div>

    </div>



    <div class="stat-card">

        <div class="stat-icon">

            <i
                class="fa-solid fa-palette"
            ></i>

        </div>

        <div class="stat-info">

            <span>
                Mẫu đang hoạt động
            </span>

            <strong>
                <?= number_format(
                    $total_templates
                ) ?>
            </strong>

        </div>

    </div>


</div>



<!-- =====================================================
     CHARTS
===================================================== -->

<div class="chart-grid">


<!-- TOP WEDDINGS -->

<div class="panel">


    <div class="panel-header">

        <h2>
            Thiệp có lượt truy cập cao nhất
        </h2>

        <span>
            Top 5
        </span>

    </div>


    <?php if (
        count($top_weddings) > 0
    ): ?>


        <div class="bar-chart">


            <?php foreach (
                $top_weddings
                as $wedding
            ): ?>


                <?php

                $views =
                    intval(
                        $wedding["views"]
                    );

                $percent =
                    $views > 0
                    ? ($views / $max_views) * 100
                    : 0;

                ?>


                <div class="bar-row">


                    <div class="bar-name">

                        <?= htmlspecialchars(
                            $wedding["groom_name"]
                            ?: "Chưa có tên"
                        ) ?>

                        &

                        <?= htmlspecialchars(
                            $wedding["bride_name"]
                            ?: "Chưa có tên"
                        ) ?>

                    </div>


                    <div class="bar-track">

                        <div
                            class="bar-fill"
                            style="width: <?= $percent ?>%;"
                        ></div>

                    </div>


                    <div class="bar-value">

                        <?= number_format(
                            $views
                        ) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty">

            Chưa có dữ liệu lượt truy cập.

        </div>


    <?php endif; ?>


</div>



<!-- TEMPLATE -->

<div class="panel">


    <div class="panel-header">

        <h2>
            Số thiệp theo mẫu
        </h2>

        <span>
            Tất cả mẫu
        </span>

    </div>


    <?php if (
        count($template_stats) > 0
    ): ?>


        <div class="template-list">


            <?php foreach (
                $template_stats
                as $template
            ): ?>


                <div class="template-row">


                    <div class="template-info">


                        <div class="template-icon">

                            <i
                                class="fa-solid fa-palette"
                            ></i>

                        </div>


                        <div class="template-name">

                            <?= htmlspecialchars(
                                $template["template_name"]
                            ) ?>

                        </div>


                    </div>


                    <div class="template-count">

                        <?= intval(
                            $template["total"]
                        ) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty">

            Chưa có mẫu thiệp.

        </div>


    <?php endif; ?>


</div>


</div>



<!-- =====================================================
     BOTTOM
===================================================== -->

<div class="bottom-grid">


<!-- LINK -->

<div class="panel">


    <div class="panel-header">

        <h2>
            Tình trạng link thiệp
        </h2>

        <span>
            <?= number_format(
                $total_links
            ) ?>
            link
        </span>

    </div>


    <div class="link-summary">


        <div class="link-box link-open">

            <i
                class="fa-solid fa-link"
            ></i>

            <strong>
                <?= number_format(
                    $opened_links
                ) ?>
            </strong>

            <span>
                Đã được mở
            </span>

        </div>


        <div class="link-box link-not-open">

            <i
                class="fa-regular fa-clock"
            ></i>

            <strong>
                <?= number_format(
                    $unopened_links
                ) ?>
            </strong>

            <span>
                Chưa mở
            </span>

        </div>


    </div>


</div>



<!-- SYSTEM -->

<div class="panel">


    <div class="panel-header">

        <h2>
            Tổng quan hệ thống
        </h2>

        <span>
            Hiện tại
        </span>

    </div>


    <div class="info-list">


        <div class="info-row">

            <span class="info-label">
                Khách hàng
            </span>

            <span class="info-value">

                <?= number_format(
                    $total_customers
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Thiệp cưới
            </span>

            <span class="info-value">

                <?= number_format(
                    $total_weddings
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Khách mời
            </span>

            <span class="info-value">

                <?= number_format(
                    $total_guests
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Link thiệp
            </span>

            <span class="info-value">

                <?= number_format(
                    $total_links
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Mẫu đang hoạt động
            </span>

            <span class="info-value">

                <?= number_format(
                    $total_templates
                ) ?>

            </span>

        </div>


    </div>


</div>


</div>


</div>


</main>


</div>

</body>

</html>