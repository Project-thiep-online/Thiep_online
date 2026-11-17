
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
| LẤY DANH SÁCH KHÁCH HÀNG
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        username,
        full_name,
        email,
        phone,
        created_at
    FROM users
    WHERE role = 'customer'
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Lỗi SQL: " . $conn->error);
}


/*
|--------------------------------------------------------------------------
| ĐẾM KHÁCH HÀNG
|--------------------------------------------------------------------------
*/

$total_customers = $result->num_rows;

/* KHÁCH HÀNG MỚI TRONG 24 GIỜ */
$recent_customer_count = 0;

$recent_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'customer'
     AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
);

if ($recent_result) {
    $recent_customer_count =
        (int)$recent_result->fetch_assoc()["total"];
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
    Quản lý khách hàng - thyu_nhun Weddings
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


body {

    font-family:
        "Be Vietnam Pro",
        Arial,
        sans-serif;

    background:
        var(--cream);

    color:
        var(--text);

    min-height: 100vh;

}

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
    
/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 245px;

    width:
        calc(100% - 245px);

    min-height: 100vh;

}


/* =========================================================
   TOPBAR
========================================================= */


.topbar {

    height: 70px;

    background:
        rgba(255,255,255,.96);

    border-bottom:
        1px solid
        var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 30px;

}


.page-title {

    display: flex;

    align-items: center;

    gap: 13px;

    font-size: 14px;

    font-weight: 600;

}


.page-title i {

    color:
        var(--wine);

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

    color:
        var(--text-light);

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

    color:
        var(--text-light);

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


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        30px 32px;

    max-width:
        1500px;

    margin: auto;

}


/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    margin-bottom: 25px;

}


.heading-left h1 {

    font-family:
        "Playfair Display",
        serif;

    font-size: 27px;

    font-weight: 600;

    margin-bottom: 7px;

}


.heading-left p {

    color:
        var(--text-light);

    font-size: 12px;

}


.heading-right {

    display: flex;

    gap: 10px;

}


.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding:
        10px 16px;

    border-radius:
        7px;

    text-decoration: none;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size: 11px;

    font-weight: 500;

    cursor: pointer;

    transition:
        .2s;

}


.btn-primary {

    background:
        var(--wine);

    color:
        white;

    border:
        1px solid
        var(--wine);

}


.btn-primary:hover {

    background:
        var(--wine-dark);

    transform:
        translateY(-1px);

}


.btn-outline {

    background:
        white;

    color:
        var(--wine);

    border:
        1px solid
        #eaded9;

}


.btn-outline:hover {

    background:
        #fbf3f0;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary-card {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        12px;

    padding:
        18px 20px;

    margin-bottom:
        18px;

    box-shadow:
        var(--shadow);

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.summary-left {

    display: flex;

    align-items: center;

    gap: 14px;

}


.summary-icon {

    width: 45px;

    height: 45px;

    border-radius: 50%;

    background:
        #f8e5e5;

    color:
        var(--wine);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

}


.summary-text strong {

    display: block;

    font-size: 13px;

}


.summary-text span {

    display: block;

    margin-top: 3px;

    color:
        var(--text-light);

    font-size: 10px;

}


.summary-number {

    font-family:
        "Playfair Display",
        serif;

    color:
        var(--wine);

    font-size: 25px;

}


/* =========================================================
   TABLE PANEL
========================================================= */

.table-panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    box-shadow:
        var(--shadow);

    overflow:
        hidden;

}


.table-toolbar {

    padding:
        18px 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    border-bottom:
        1px solid
        var(--border);

}


.search-box {

    width:
        350px;

    max-width:
        100%;

    position: relative;

}


.search-box i {

    position: absolute;

    left: 13px;

    top: 50%;

    transform:
        translateY(-50%);

    color:
        #aaa;

    font-size: 12px;

}


.search-box input {

    width: 100%;

    height: 38px;

    border:
        1px solid
        #e7dfdb;

    border-radius:
        7px;

    padding:
        0 13px 0 36px;

    outline: none;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size: 11px;

    color:
        var(--text);

}


.search-box input:focus {

    border-color:
        var(--wine);

    box-shadow:
        0 0 0 3px
        rgba(150,80,80,.08);

}


.table-count {

    color:
        var(--text-light);

    font-size: 10px;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x:
        auto;

}


table {

    width:
        100%;

    border-collapse:
        collapse;

    min-width:
        900px;

}


thead th {

    background:
        #fcf8f6;

    color:
        #766d69;

    font-size:
        10px;

    font-weight:
        600;

    text-align:
        left;

    padding:
        13px 16px;

    border-bottom:
        1px solid
        var(--border);

    white-space:
        nowrap;

}


tbody td {

    padding:
        14px 16px;

    border-bottom:
        1px solid
        #f2ece9;

    vertical-align:
        middle;

    font-size:
        11px;

}


tbody tr {

    transition:
        .2s;

}


tbody tr:hover {

    background:
        #fdf9f7;

}


tbody tr:last-child td {

    border-bottom:
        none;

}


/* =========================================================
   CUSTOMER
========================================================= */

.customer-info {

    display: flex;

    align-items: center;

    gap: 10px;

}


.customer-avatar {

    width: 35px;

    height: 35px;

    flex-shrink: 0;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #ecd5cf,
            #c68f83
        );

    color:
        white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size:
        11px;

    font-weight:
        600;

}


.customer-name {

    font-weight:
        600;

    color:
        #302a29;

}


.customer-username {

    color:
        #999;

    font-size:
        9px;

    margin-top:
        2px;

}


.phone {

    color:
        #595250;

}


.email {

    color:
        #777;

    max-width:
        210px;

    overflow:
        hidden;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;

}


.date {

    color:
        #777;

    white-space:
        nowrap;

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

    background:
        #e8f3eb;

    color:
        #5b916e;

}


.status-dot {

    width:
        5px;

    height:
        5px;

    background:
        #69a77f;

    border-radius:
        50%;

}


/* =========================================================
   ACTION
========================================================= */

.action-buttons {

    display:
        flex;

    align-items:
        center;

    gap:
        6px;

}


.icon-btn {

    width:
        30px;

    height:
        30px;

    border-radius:
        6px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    text-decoration:
        none;

    border:
        1px solid
        #eadfd9;

    color:
        #8b7771;

    background:
        white;

    transition:
        .2s;

}


.icon-btn:hover {

    color:
        var(--wine);

    background:
        #fbf3f0;

    border-color:
        #d9bebb;

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
   PAGINATION
========================================================= */

.table-footer {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding:
        14px 18px;

    border-top:
        1px solid
        var(--border);

}


.showing {

    color:
        var(--text-light);

    font-size:
        9px;

}


.pagination {

    display:
        flex;

    gap:
        4px;

}


.page-btn {

    width:
        28px;

    height:
        28px;

    border:
        1px solid
        #eadfd9;

    background:
        white;

    border-radius:
        5px;

    color:
        #777;

    font-size:
        10px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


.page-btn.active {

    background:
        var(--wine);

    color:
        white;

    border-color:
        var(--wine);

}


/* =========================================================
   RESPONSIVE
========================================================= */

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

    .content {

        padding:
            20px 15px;

    }

}


@media (max-width: 650px) {

    .page-heading {

        align-items:
            flex-start;

        flex-direction:
            column;

        gap:
            15px;

    }

    .heading-right {

        width:
            100%;

    }

    .heading-right .btn {

        flex:
            1;

    }

    .table-toolbar {

        flex-direction:
            column;

        align-items:
            stretch;

    }

    .search-box {

        width:
            100%;

    }

    .user-text {

        display:
            none;

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


/* SUBPAGE LAYOUT: only return-home navigation, no full sidebar */
.sidebar{display:none!important;}
.main{margin-left:0!important;width:100%!important;}
@media(max-width:900px){.main{width:100%!important;margin-left:0!important;}.topbar{padding-left:18px!important;padding-right:18px!important;}}
</style>

</head>


<body>

<main class="main">

<!-- TOPBAR= -->

<header class="topbar">

            <a href="dashboard.php" class="home-btn">
                <i class="fa-solid fa-house"></i>
                Trang chủ
            </a>

    <div class="page-title">
        <i class="fa-solid fa-users"></i>
        <span>Quản lý khách hàng</span>
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
            Danh sách khách mời
        </h1>

        <p>
            Quản lý những khách hàng đang sử dụng
            hệ thống thiệp online.
        </p>

    </div>


    <div class="heading-right">


        <a
            href="add_customer.php"
            class="btn btn-primary"
        >

            <i class="fa-solid fa-plus"></i>

            Thêm khách hàng

        </a>


    </div>


</div>



<!-- SUMMARY -->

<div class="summary-card">


    <div class="summary-left">


        <div class="summary-icon">

            <i class="fa-solid fa-users"></i>

        </div>


        <div class="summary-text">

            <strong>
                Tổng số khách hàng
            </strong>

            <span>
                Tài khoản khách đang sử dụng hệ thống
            </span>

        </div>


    </div>


    <div class="summary-number">

        <?= number_format($total_customers) ?>

    </div>


</div>



<!-- TABLE -->

<div class="table-panel">


    <!-- TOOLBAR -->

    <div class="table-toolbar">


        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                id="searchCustomer"
                placeholder="Tìm kiếm khách hàng..."
                autocomplete="off"
            >

        </div>


        <div class="table-count">

            Hiển thị
            <strong id="visibleCount">
                <?= $total_customers ?>
            </strong>
            khách hàng

        </div>


    </div>



    <?php if ($result->num_rows > 0): ?>


        <div class="table-wrapper">


            <table id="customerTable">


                <thead>

                    <tr>

                        <th>
                            STT
                        </th>

                        <th>
                            HỌ VÀ TÊN
                        </th>

                        <th>
                            SỐ ĐIỆN THOẠI
                        </th>

                        <th>
                            EMAIL
                        </th>

                        <th>
                            TÀI KHOẢN
                        </th>

                        <th>
                            TRẠNG THÁI
                        </th>

                        <th>
                            NGÀY TẠO
                        </th>

                        <th>
                            THAO TÁC
                        </th>

                    </tr>

                </thead>



                <tbody>


                <?php

                $stt = 1;

                while (
                    $customer =
                    $result->fetch_assoc()
                ):

                    $customer_name =
                        trim(
                            $customer["full_name"] ?? ""
                        );

                    $initial =
                        strtoupper(
                            mb_substr(
                                $customer_name !== ""
                                    ? $customer_name
                                    : "K",
                                0,
                                1,
                                "UTF-8"
                            )
                        );

                ?>


                    <tr class="customer-row">


                        <!-- STT -->

                        <td>

                            <?= $stt++ ?>

                        </td>



                        <!-- HỌ TÊN -->

                        <td>


                            <div class="customer-info">


                                <div class="customer-avatar">

                                    <?= htmlspecialchars(
                                        $initial
                                    ) ?>

                                </div>


                                <div>


                                    <div class="customer-name">

                                        <?= htmlspecialchars(
                                            $customer_name
                                        ) ?>

                                    </div>


                                    <div class="customer-username">

                                        @<?= htmlspecialchars(
                                            $customer["username"]
                                        ) ?>

                                    </div>


                                </div>


                            </div>


                        </td>



                        <!-- PHONE -->

                        <td class="phone">

                            <?= !empty(
                                $customer["phone"]
                            )
                                ? htmlspecialchars(
                                    $customer["phone"]
                                )
                                : "Chưa cập nhật"
                            ?>

                        </td>



                        <!-- EMAIL -->

                        <td class="email">

                            <?= !empty(
                                $customer["email"]
                            )
                                ? htmlspecialchars(
                                    $customer["email"]
                                )
                                : "Chưa cập nhật"
                            ?>

                        </td>



                        <!-- USERNAME -->

                        <td>

                            <?= htmlspecialchars(
                                $customer["username"]
                            ) ?>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <span class="status">

                                <span
                                    class="status-dot"
                                ></span>

                                Đang hoạt động

                            </span>

                        </td>



                        <!-- DATE -->

                        <td class="date">

                            <?php

                            if (
                                !empty(
                                    $customer["created_at"]
                                )
                            ) {

                                echo date(
                                    "d/m/Y",
                                    strtotime(
                                        $customer["created_at"]
                                    )
                                );

                            } else {

                                echo "—";

                            }

                            ?>

                        </td>



                        <!-- ACTION -->

                        <td>


                            <div class="action-buttons">


                                <a
                                    href="edit_customer.php?id=<?= intval(
                                        $customer["id"]
                                    ) ?>"
                                    class="icon-btn"
                                    title="Sửa khách hàng"
                                >

                                    <i
                                        class="fa-regular fa-pen-to-square"
                                    ></i>

                                </a>


                            </div>


                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        </div>



        <!-- FOOTER -->

        <div class="table-footer">


            <div class="showing">

                Tổng cộng
                <strong>
                    <?= number_format(
                        $total_customers
                    ) ?>
                </strong>
                khách hàng

            </div>


            <div class="pagination">

                <div class="page-btn active">
                    1
                </div>

            </div>


        </div>


    <?php else: ?>


        <div class="empty">


            <i
                class="fa-regular fa-user"
            ></i>


            <strong>
                Chưa có khách hàng nào
            </strong>


            <span>
                Hãy thêm khách hàng đầu tiên
                vào hệ thống.
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
        "searchCustomer"
    );

const rows =
    document.querySelectorAll(
        ".customer-row"
    );

const visibleCount =
    document.getElementById(
        "visibleCount"
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
                        row.innerText
                            .toLowerCase();


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

        }
    );

}

</script>


</body>

</html>