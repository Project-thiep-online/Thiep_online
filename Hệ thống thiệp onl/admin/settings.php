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


$user_id = intval($_SESSION["user_id"]);

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN ADMIN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        username,
        full_name,
        email,
        phone,
        password
     FROM users
     WHERE id = ?
     AND role = 'admin'
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../auth/login.php?status=account_not_found");
    exit;
}

$admin = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| XỬ LÝ FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | CẬP NHẬT THÔNG TIN
    |--------------------------------------------------------------------------
    */

    if ($action === "update_profile") {

        $full_name = trim(
            $_POST["full_name"] ?? ""
        );

        $email = trim(
            $_POST["email"] ?? ""
        );

        $phone = trim(
            $_POST["phone"] ?? ""
        );


        if ($full_name === "") {

            $error =
                "Vui lòng nhập họ và tên.";

        } elseif (
            $email !== "" &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                "Email không hợp lệ.";

        } else {

            $stmt = $conn->prepare(
                "UPDATE users
                 SET
                    full_name = ?,
                    email = ?,
                    phone = ?
                 WHERE id = ?
                 AND role = 'admin'"
            );

            $stmt->bind_param(
                "sssi",
                $full_name,
                $email,
                $phone,
                $user_id
            );


            if ($stmt->execute()) {

                $_SESSION["full_name"] =
                    $full_name;

                $admin["full_name"] =
                    $full_name;

                $admin["email"] =
                    $email;

                $admin["phone"] =
                    $phone;

                $success =
                    "Đã cập nhật thông tin tài khoản.";

            } else {

                $error =
                    "Không thể cập nhật thông tin: " .
                    $stmt->error;

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ĐỔI MẬT KHẨU
    |--------------------------------------------------------------------------
    */

    if ($action === "change_password") {

        $current_password =
            $_POST["current_password"] ?? "";

        $new_password =
            $_POST["new_password"] ?? "";

        $confirm_password =
            $_POST["confirm_password"] ?? "";


        if (
            $current_password === "" ||
            $new_password === "" ||
            $confirm_password === ""
        ) {

            $error =
                "Vui lòng nhập đầy đủ thông tin mật khẩu.";

        } elseif (
            !password_verify(
                $current_password,
                $admin["password"]
            )
        ) {

            $error =
                "Mật khẩu hiện tại không đúng.";

        } elseif (
            strlen($new_password) < 6
        ) {

            $error =
                "Mật khẩu mới phải có ít nhất 6 ký tự.";

        } elseif (
            $new_password !== $confirm_password
        ) {

            $error =
                "Mật khẩu xác nhận không khớp.";

        } else {

            $new_hash =
                password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );


            $stmt = $conn->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?
                 AND role = 'admin'"
            );

            $stmt->bind_param(
                "si",
                $new_hash,
                $user_id
            );


            if ($stmt->execute()) {

                $admin["password"] =
                    $new_hash;

                $success =
                    "Đổi mật khẩu thành công.";

            } else {

                $error =
                    "Không thể đổi mật khẩu: " .
                    $stmt->error;

            }

        }

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
    Cài đặt - thyu_nhun Weddings
</title>


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

    --wine: #8F1D2C;

    --wine-dark: #65131F;

    --cream: #FFF8F6;

    --white: #fff;

    --gold: #D4A73D;

    --text: #32191C;

    --muted: #7E6669;

    --border: #ECD8D5;

    --green: #6ca582;

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

    min-height:
        100vh;

}


/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position:
        fixed;

    left:
        0;

    top:
        0;

    bottom:
        0;

    width:
        265px;

    background:
        linear-gradient(
            180deg,
            #3D0B12,
            #1d1719
        );

    color:
        white;

    z-index:
        100;

}


.brand {

    height:
        170px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    align-items:
        center;

}


.brand-icon {

    width:
        58px;

    height:
        58px;

    border:
        1px solid
        var(--gold);

    border-radius:
        50%;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    color:
        var(--gold);

    font-size:
        23px;

}


.brand-name {

    margin-top:
        13px;

    color:
        var(--gold);

    font-family:
        "Playfair Display",
        serif;

    font-size:
        19px;

    letter-spacing:
        1px;

}


.brand-sub {

    color:
        rgba(255,255,255,.55);

    font-size:
        8px;

    letter-spacing:
        3px;

    margin-top:
        2px;

}


/* =====================================================
   MENU
===================================================== */

.menu {

    padding:
        20px 13px;

}


.menu-title {

    color:
        rgba(255,255,255,.35);

    font-size:
        10px;

    text-transform:
        uppercase;

    letter-spacing:
        1.5px;

    padding:
        8px 14px;

}


.menu-item {

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    padding:
        12px 14px;

    margin:
        4px 0;

    border-radius:
        8px;

    color:
        rgba(255,255,255,.82);

    text-decoration:
        none;

    font-size:
        13px;

    transition:
        .2s;

}


.menu-item i {

    width:
        18px;

    text-align:
        center;

}


.menu-item:hover {

    background:
        rgba(255,255,255,.08);

}


.menu-item.active {

    background:
        linear-gradient(
            135deg,
            #a75858,
            #8e4848
        );

}


.divider {

    height:
        1px;

    background:
        rgba(255,255,255,.08);

    margin:
        16px 10px;

}


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left:
        265px;

    min-height:
        100vh;

}


/* =====================================================
   TOPBAR
===================================================== */

.topbar {

    height:
        70px;

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

    gap:
        12px;

    font-size:
        14px;

    font-weight:
        600;

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

    gap:
        10px;

}


.user-info {

    text-align:
        right;

}


.user-info strong {

    display:
        block;

    font-size:
        12px;

}


.user-info span {

    color:
        var(--muted);

    font-size:
        10px;

}


.avatar {

    width:
        39px;

    height:
        39px;

    border-radius:
        50%;

    background:
        linear-gradient(
            135deg,
            #ead8cf,
            #b98778
        );

    color:
        white;

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

    max-width:
        1200px;

    margin:
        auto;

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
   ALERT
===================================================== */

.alert {

    padding:
        13px 16px;

    border-radius:
        9px;

    margin-bottom:
        20px;

    font-size:
        11px;

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

}


.alert.success {

    background:
        #e8f4eb;

    color:
        #4d8961;

    border:
        1px solid
        #d2e8d8;

}


.alert.error {

    background:
        #fae8e7;

    color:
        #a04d4d;

    border:
        1px solid
        #efd0ce;

}


/* =====================================================
   GRID
===================================================== */

.settings-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        20px;

}


/* =====================================================
   PANEL
===================================================== */

.panel {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        13px;

    padding:
        24px;

    box-shadow:
        0 5px 20px
        rgba(50,30,25,.04);

}


.panel.full {

    grid-column:
        1 / -1;

}


.panel-header {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

    margin-bottom:
        22px;

    padding-bottom:
        16px;

    border-bottom:
        1px solid
        var(--border);

}


.panel-icon {

    width:
        40px;

    height:
        40px;

    border-radius:
        9px;

    background:
        #f7e7e4;

    color:
        var(--wine);

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


.panel-header h2 {

    font-size:
        14px;

    font-weight:
        600;

}


.panel-header p {

    color:
        var(--muted);

    font-size:
        9px;

    margin-top:
        3px;

}


/* =====================================================
   PROFILE
===================================================== */

.profile-top {

    display:
        flex;

    align-items:
        center;

    gap:
        16px;

    margin-bottom:
        22px;

}


.profile-avatar {

    width:
        65px;

    height:
        65px;

    border-radius:
        50%;

    background:
        linear-gradient(
            135deg,
            #ead8cf,
            #b98778
        );

    color:
        white;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        25px;

}


.profile-name {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        18px;

}


.profile-role {

    color:
        var(--muted);

    font-size:
        10px;

    margin-top:
        3px;

}


/* =====================================================
   FORM
===================================================== */

.form-group {

    margin-bottom:
        16px;

}


.form-group label {

    display:
        block;

    margin-bottom:
        7px;

    color:
        #665e5b;

    font-size:
        10px;

    font-weight:
        600;

}


.form-group input {

    width:
        100%;

    height:
        40px;

    border:
        1px solid
        #e5deda;

    border-radius:
        7px;

    padding:
        0 12px;

    outline:
        none;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size:
        11px;

    color:
        var(--text);

    transition:
        .2s;

}


.form-group input:focus {

    border-color:
        var(--wine);

    box-shadow:
        0 0 0 3px
        rgba(150,80,80,.08);

}


.form-group input[readonly] {

    background:
        #f8f5f3;

    color:
        #999;

}


.form-row {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        12px;

}


/* =====================================================
   BUTTON
===================================================== */

.save-btn {

    width:
        100%;

    height:
        41px;

    border:
        none;

    border-radius:
        7px;

    background:
        var(--wine);

    color:
        white;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size:
        11px;

    cursor:
        pointer;

    transition:
        .2s;

}


.save-btn:hover {

    background:
        var(--wine-dark);

}


.save-btn i {

    margin-right:
        6px;

}


/* =====================================================
   SECURITY INFO
===================================================== */

.security-info {

    background:
        #faf6f3;

    border:
        1px solid
        #eee2dc;

    border-radius:
        9px;

    padding:
        14px;

    margin-bottom:
        18px;

    display:
        flex;

    gap:
        10px;

}


.security-info i {

    color:
        var(--gold);

    margin-top:
        2px;

}


.security-info strong {

    display:
        block;

    font-size:
        10px;

    margin-bottom:
        3px;

}


.security-info span {

    color:
        var(--muted);

    font-size:
        9px;

    line-height:
        1.5;

}


/* =====================================================
   SYSTEM INFO
===================================================== */

.system-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        15px;

}


.system-item {

    padding:
        17px;

    background:
        #faf7f5;

    border:
        1px solid
        #eee5e1;

    border-radius:
        9px;

}


.system-item i {

    color:
        var(--wine);

    font-size:
        16px;

    margin-bottom:
        10px;

}


.system-item span {

    display:
        block;

    color:
        var(--muted);

    font-size:
        9px;

    margin-bottom:
        4px;

}


.system-item strong {

    font-size:
        11px;

}


/* =====================================================
   RESPONSIVE
===================================================== */

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

}


@media (max-width: 700px) {

    .content {

        padding:
            20px 15px;

    }

    .settings-grid {

        grid-template-columns:
            1fr;

    }

    .panel.full {

        grid-column:
            auto;

    }

    .system-grid {

        grid-template-columns:
            1fr;

    }

    .form-row {

        grid-template-columns:
            1fr;

    }

    .user-info {

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

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">


    <div class="brand">

        <div class="brand-icon">
            ♡
        </div>

        <div class="brand-name">
            thyu_nhun</div>

        <div class="brand-sub">
            WEDDINGS</div>

    </div>


    <nav class="menu">


        <div class="menu-title">
            Menu chính
        </div>


        <a
            href="dashboard.php"
            class="menu-item"
        >

            <i class="fa-solid fa-house"></i>

            <span>
                Trang chủ
            </span>

        </a>


        <a
            href="customers.php"
            class="menu-item"
        >

            <i class="fa-solid fa-users"></i>

            <span>
                Quản lý khách hàng
            </span>

        </a>


        <a
            href="templates.php"
            class="menu-item"
        >

            <i class="fa-solid fa-palette"></i>

            <span>
                Mẫu thiệp
            </span>

        </a>


        <a
            href="weddings.php"
            class="menu-item"
        >

            <i class="fa-regular fa-envelope"></i>

            <span>
                Thiệp cưới
            </span>

        </a>


        <a
            href="statistics.php"
            class="menu-item"
        >

            <i class="fa-solid fa-chart-column"></i>

            <span>
                Thống kê
            </span>

        </a>


        <a
            href="settings.php"
            class="menu-item active"
        >

            <i class="fa-solid fa-gear"></i>

            <span>
                Cài đặt
            </span>

        </a>


        <div class="divider"></div>


        <a
            href="../auth/logout.php"
            class="menu-item"
        >

            <i
                class="fa-solid fa-right-from-bracket"
            ></i>

            <span>
                Đăng xuất
            </span>

        </a>


    </nav>

</aside>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<header class="topbar">


    <div class="page-title">

        <i class="fa-solid fa-gear"></i>

        <span>
            Cài đặt
        </span>

    </div>


    <div class="user-area">


        <div class="user-info">

            <strong>

                Xin chào,
                <?= htmlspecialchars(
                    $admin["full_name"]
                ) ?>

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

<div class="heading">

    <h1>
        Cài đặt tài khoản
    </h1>

    <p>
        Quản lý thông tin cá nhân và bảo mật tài khoản quản trị.
    </p>

</div>



<!-- =====================================================
     ALERT
===================================================== -->

<?php if ($success !== ""): ?>

    <div class="alert success">

        <i class="fa-solid fa-circle-check"></i>

        <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>


<?php if ($error !== ""): ?>

    <div class="alert error">

        <i class="fa-solid fa-circle-exclamation"></i>

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>



<div class="settings-grid">


<!-- =====================================================
     PROFILE
===================================================== -->

<div class="panel">


    <div class="panel-header">


        <div class="panel-icon">

            <i class="fa-solid fa-user"></i>

        </div>


        <div>

            <h2>
                Thông tin tài khoản
            </h2>

            <p>
                Cập nhật thông tin cá nhân
            </p>

        </div>


    </div>



    <div class="profile-top">


        <div class="profile-avatar">

            <i class="fa-solid fa-user"></i>

        </div>


        <div>

            <div class="profile-name">

                <?= htmlspecialchars(
                    $admin["full_name"]
                ) ?>

            </div>


            <div class="profile-role">

                Quản trị viên hệ thống

            </div>

        </div>


    </div>



    <form
        method="POST"
    >

        <input
            type="hidden"
            name="action"
            value="update_profile"
        >


        <div class="form-group">

            <label>
                Tên đăng nhập
            </label>

            <input
                type="text"
                value="<?= htmlspecialchars(
                    $admin["username"]
                ) ?>"
                readonly
            >

        </div>



        <div class="form-group">

            <label>
                Họ và tên
            </label>

            <input
                type="text"
                name="full_name"
                value="<?= htmlspecialchars(
                    $admin["full_name"]
                ) ?>"
                required
            >

        </div>



        <div class="form-row">


            <div class="form-group">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars(
                        $admin["email"] ?? ""
                    ) ?>"
                    placeholder="Nhập email"
                >

            </div>



            <div class="form-group">

                <label>
                    Số điện thoại
                </label>

                <input
                    type="text"
                    name="phone"
                    value="<?= htmlspecialchars(
                        $admin["phone"] ?? ""
                    ) ?>"
                    placeholder="Nhập số điện thoại"
                >

            </div>


        </div>



        <button
            type="submit"
            class="save-btn"
        >

            <i class="fa-solid fa-floppy-disk"></i>

            Lưu thông tin

        </button>


    </form>


</div>



<!-- =====================================================
     PASSWORD
===================================================== -->

<div class="panel">


    <div class="panel-header">


        <div class="panel-icon">

            <i class="fa-solid fa-lock"></i>

        </div>


        <div>

            <h2>
                Đổi mật khẩu
            </h2>

            <p>
                Bảo vệ tài khoản của bạn
            </p>

        </div>


    </div>



    <div class="security-info">


        <i class="fa-solid fa-shield-halved"></i>


        <div>

            <strong>
                Bảo mật tài khoản
            </strong>

            <span>
                Mật khẩu mới nên có ít nhất 6 ký tự
                và không nên trùng với mật khẩu cũ.
            </span>

        </div>


    </div>



    <form
        method="POST"
    >

        <input
            type="hidden"
            name="action"
            value="change_password"
        >


        <div class="form-group">

            <label>
                Mật khẩu hiện tại
            </label>

            <input
                type="password"
                name="current_password"
                placeholder="Nhập mật khẩu hiện tại"
                required
            >

        </div>



        <div class="form-group">

            <label>
                Mật khẩu mới
            </label>

            <input
                type="password"
                name="new_password"
                placeholder="Nhập mật khẩu mới"
                minlength="6"
                required
            >

        </div>



        <div class="form-group">

            <label>
                Xác nhận mật khẩu mới
            </label>

            <input
                type="password"
                name="confirm_password"
                placeholder="Nhập lại mật khẩu mới"
                minlength="6"
                required
            >

        </div>



        <button
            type="submit"
            class="save-btn"
        >

            <i class="fa-solid fa-key"></i>

            Đổi mật khẩu

        </button>


    </form>


</div>



<!-- =====================================================
     SYSTEM INFO
===================================================== -->

<div class="panel full">


    <div class="panel-header">


        <div class="panel-icon">

            <i class="fa-solid fa-circle-info"></i>

        </div>


        <div>

            <h2>
                Thông tin hệ thống
            </h2>

            <p>
                Thông tin cơ bản của hệ thống thiệp online
            </p>

        </div>


    </div>



    <div class="system-grid">


        <div class="system-item">

            <i
                class="fa-regular fa-envelope"
            ></i>

            <span>
                Tên hệ thống
            </span>

            <strong>
                thyu_nhun Weddings
            </strong>

        </div>



        <div class="system-item">

            <i
                class="fa-solid fa-user-shield"
            ></i>

            <span>
                Vai trò hiện tại
            </span>

            <strong>
                Quản trị viên
            </strong>

        </div>



        <div class="system-item">

            <i
                class="fa-solid fa-database"
            ></i>

            <span>
                Cơ sở dữ liệu
            </span>

            <strong>
                Wedding System
            </strong>

        </div>


    </div>


</div>


</div>


</div>


</main>


</body>

</html>