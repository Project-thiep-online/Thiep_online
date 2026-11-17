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


/*
|--------------------------------------------------------------------------
| LẤY ID KHÁCH HÀNG
|--------------------------------------------------------------------------
*/

$customer_id = intval(
    $_GET["id"] ?? 0
);

if ($customer_id <= 0) {
    header("Location: customers.php?status=error");
    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN KHÁCH HÀNG
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        username,
        full_name,
        email,
        phone
     FROM users
     WHERE id = ?
     AND role = 'customer'
     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $customer_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: customers.php?status=error");
    exit;
}

$customer = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| THÔNG BÁO
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| XỬ LÝ CẬP NHẬT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim(
        $_POST["full_name"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $password =
        $_POST["password"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA HỌ TÊN
    |--------------------------------------------------------------------------
    */

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
            "Địa chỉ email không hợp lệ.";

    } elseif (
        $phone !== "" &&
        !preg_match('/^[0-9+().\s-]{8,20}$/', $phone)
    ) {

        $error =
            "Số điện thoại không hợp lệ.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | NẾU CÓ NHẬP MẬT KHẨU
        |--------------------------------------------------------------------------
        */

        if ($password !== "") {

            if (strlen($password) < 6) {

                $error =
                    "Mật khẩu mới phải có ít nhất 6 ký tự.";

            } else {

                $hashed_password =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                $stmt = $conn->prepare(
                    "UPDATE users
                     SET
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        password = ?
                     WHERE id = ?
                     AND role = 'customer'"
                );

                $stmt->bind_param(
                    "ssssi",
                    $full_name,
                    $email,
                    $phone,
                    $hashed_password,
                    $customer_id
                );


                if ($stmt->execute()) {

                    $success =
                        "Đã cập nhật tài khoản khách hàng.";

                    $customer["full_name"] =
                        $full_name;

                    $customer["email"] =
                        $email;

                    $customer["phone"] =
                        $phone;

                } else {

                    $error =
                        "Không thể cập nhật tài khoản: " .
                        $stmt->error;

                }

            }

        } else {


            /*
            |--------------------------------------------------------------------------
            | KHÔNG ĐỔI MẬT KHẨU
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "UPDATE users
                 SET
                    full_name = ?,
                    email = ?,
                    phone = ?
                 WHERE id = ?
                 AND role = 'customer'"
            );

            $stmt->bind_param(
                "sssi",
                $full_name,
                $email,
                $phone,
                $customer_id
            );


            if ($stmt->execute()) {

                $success =
                    "Đã cập nhật tài khoản khách hàng.";

                $customer["full_name"] =
                    $full_name;

                $customer["email"] =
                    $email;

                $customer["phone"] =
                    $phone;

            } else {

                $error =
                    "Không thể cập nhật tài khoản: " .
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

    <title>Sửa khách hàng - thyu_nhun Weddings</title>

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

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

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
            --white: #ffffff;
            --gold: #D4A73D;
            --text: #32191C;
            --muted: #7E6669;
            --border: #ECD8D5;
        }

        body {
            font-family: "Be Vietnam Pro", Arial, sans-serif;
            background: var(--cream);
            color: var(--text);
            min-height: 100vh;
        }

        .admin-layout {
            min-height: 100vh;
        }

        /* ================= MAIN ================= */

        .main {
            margin-left: 245px;
            min-height: 100vh;
        }

        /* ================= TOPBAR ================= */

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
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .home-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 13px;
            background: #f8f1ed;
            color: var(--wine);
            border: 1px solid #eadbd4;
            border-radius: 7px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            transition: .2s;
        }

        .home-btn:hover {
            background: var(--wine);
            color: white;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 600;
        }

        .page-title i {
            color: var(--wine);
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info {
            text-align: right;
        }

        .user-info strong {
            display: block;
            font-size: 11px;
        }

        .user-info span {
            display: block;
            color: var(--muted);
            font-size: 9px;
            margin-top: 2px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg,#ead8cf,#b98778);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ================= CONTENT ================= */

        .content {
            width: min(850px, calc(100% - 40px));
            margin: 0 auto;
            padding: 35px 0 50px;
        }

        .heading {
            text-align: center;
            margin-bottom: 25px;
        }

        .heading-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: #f5e4df;
            color: var(--wine);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .heading h1 {
            font-family: "Playfair Display", serif;
            font-size: 28px;
            color: var(--text);
            margin-bottom: 5px;
        }

        .heading p {
            color: var(--muted);
            font-size: 11px;
        }

        .form-box {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(50,30,25,.06);
        }

        /* ================= PROFILE ================= */

        .customer-id {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #faf6f3;
            border: 1px solid #eee3dd;
            border-radius: 9px;
            padding: 12px 15px;
            margin-bottom: 23px;
        }

        .customer-id span {
            color: var(--muted);
            font-size: 10px;
        }

        .customer-id strong {
            color: var(--wine);
            font-size: 12px;
        }

        .profile-box {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 23px;
            padding: 14px;
            background: #faf7f5;
            border: 1px solid #eee5e1;
            border-radius: 10px;
        }

        .profile-avatar {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            border-radius: 50%;
            background: linear-gradient(135deg,#ead8cf,#b98778);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-name {
            font-family: "Playfair Display", serif;
            font-size: 16px;
        }

        .profile-role {
            color: var(--muted);
            font-size: 9px;
            margin-top: 2px;
        }

        /* ================= ALERT ================= */

        .alert {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error {
            background: #fae7e6;
            color: #a04b4b;
            border: 1px solid #efd0ce;
        }

        .success {
            background: #e7f4ea;
            color: #47865b;
            border: 1px solid #d1e7d6;
        }

        /* ================= FORM ================= */

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .section-title i {
            color: var(--wine);
        }

        .section-title span {
            font-size: 12px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #514a48;
            margin-bottom: 7px;
        }

        .required {
            color: var(--wine);
        }

        input {
            width: 100%;
            height: 42px;
            border: 1px solid #e2dad6;
            border-radius: 8px;
            background: white;
            padding: 0 13px;
            color: var(--text);
            font-family: "Be Vietnam Pro", Arial, sans-serif;
            font-size: 11px;
            outline: none;
            transition: .2s;
        }

        input:focus {
            border-color: var(--wine);
            box-shadow: 0 0 0 3px rgba(150,80,80,.08);
        }

        input.readonly {
            background: #f4f1ef;
            color: #999;
            cursor: not-allowed;
        }

        .hint {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-top: 6px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.5;
        }

        .hint i {
            color: var(--gold);
            margin-top: 2px;
        }

        .password-box {
            background: #faf6f3;
            border: 1px solid #eee2dc;
            border-radius: 10px;
            padding: 17px;
            margin-top: 5px;
        }

        .password-note {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.5;
        }

        .password-note i {
            color: var(--gold);
            margin-top: 2px;
        }

        /* ================= BUTTONS ================= */

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn {
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            text-decoration: none;
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .btn-save {
            flex: 1;
            border: none;
            background: var(--wine);
            color: white;
        }

        .btn-save:hover {
            background: var(--wine-dark);
        }

        .btn-cancel {
            padding: 0 20px;
            border: 1px solid #dfd5d0;
            background: white;
            color: #756c68;
        }

        .btn-cancel:hover {
            background: #f8f1ed;
            color: var(--wine);
        }

        .back-link {
            display: flex;
            justify-content: center;
            margin-top: 18px;
            color: var(--wine);
            text-decoration: none;
            font-size: 10px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 900px) {

            .sidebar {
                width: 70px;
            }

            .brand {
                padding: 18px 5px;
                text-align: center;
            }

            .brand-mark {
                margin: auto;
            }

            .brand-name,
            .brand-sub,
            .menu-title,
            .menu-item span {
                display: none;
            }

            .menu-item {
                justify-content: center;
                padding: 13px 5px;
            }

            .main {
                margin-left: 70px;
            }

        }

        @media (max-width: 650px) {

            .topbar {
                padding: 0 15px;
            }

            .user-info {
                display: none;
            }

            .home-btn span {
                display: none;
            }

            .content {
                width: calc(100% - 30px);
                padding-top: 25px;
            }

            .form-box {
                padding: 22px 18px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-cancel {
                width: 100%;
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

<div class="admin-layout">

    <!-- MAIN -->

    <main class="main">

        <!-- TOPBAR -->

        <a href="dashboard.php" class="home-btn">
            <i class="fa-solid fa-house"></i>
            Trang chủ
        </a>

        <header class="topbar">

            <div class="topbar-left">

                <a
                    href="dashboard.php"
                    class="home-btn"
                >
                    <i class="fa-solid fa-house"></i>
                    <span>Trang chủ</span>
                </a>

                <div class="page-title">

                    <i class="fa-solid fa-user-pen"></i>

                    <span>
                        Sửa khách hàng
                    </span>

                </div>

            </div>


            <div class="user-area">

                <div class="user-info">

                    <strong>
                        Xin chào,
                        <?= htmlspecialchars(
                            $_SESSION["full_name"] ?? "Admin"
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


        <!-- CONTENT -->

        <main class="content">

            <div class="heading">

                <div class="heading-icon">
                    <i class="fa-solid fa-user-pen"></i>
                </div>

                <h1>
                    Sửa khách hàng
                </h1>

                <p>
                    Cập nhật thông tin tài khoản khách hàng
                </p>

            </div>


            <div class="form-box">

                <!-- CUSTOMER ID -->

                <div class="customer-id">

                    <span>
                        Mã khách hàng
                    </span>

                    <strong>
                        #<?= intval(
                            $customer["id"]
                        ) ?>
                    </strong>

                </div>


                <!-- PROFILE -->

                <div class="profile-box">

                    <div class="profile-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div>

                        <div class="profile-name">
                            <?= htmlspecialchars(
                                $customer["full_name"]
                            ) ?>
                        </div>

                        <div class="profile-role">
                            Khách hàng sử dụng hệ thống
                        </div>

                    </div>

                </div>


                <!-- ALERT -->

                <?php if ($error !== ""): ?>

                    <div class="alert error">

                        <i
                            class="fa-solid fa-circle-exclamation"
                        ></i>

                        <span>
                            <?= htmlspecialchars($error) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <?php if ($success !== ""): ?>

                    <div class="alert success">

                        <i
                            class="fa-solid fa-circle-check"
                        ></i>

                        <span>
                            <?= htmlspecialchars($success) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    autocomplete="off"
                >

                    <!-- ACCOUNT -->

                    <div class="section-title">

                        <i class="fa-solid fa-id-card"></i>

                        <span>
                            Thông tin tài khoản
                        </span>

                    </div>


                    <!-- USERNAME -->

                    <div class="form-group">

                        <label>
                            Tên đăng nhập
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                $customer["username"]
                            ) ?>"
                            class="readonly"
                            readonly
                        >

                        <div class="hint">

                            <i class="fa-solid fa-lock"></i>

                            <span>
                                Tên đăng nhập không thể thay đổi.
                            </span>

                        </div>

                    </div>


                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label>

                            Họ và tên

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= htmlspecialchars(
                                $customer["full_name"]
                            ) ?>"
                            placeholder="Nhập họ và tên"
                            maxlength="100"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="<?= htmlspecialchars(
                                $customer["email"] ?? ""
                            ) ?>"
                            placeholder="example@email.com"
                            maxlength="150"
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label>
                            Số điện thoại
                        </label>

                        <input
                            type="text"
                            name="phone"
                            value="<?= htmlspecialchars(
                                $customer["phone"] ?? ""
                            ) ?>"
                            placeholder="Nhập số điện thoại"
                            maxlength="20"
                        >

                    </div>


                    <!-- PASSWORD -->

                    <div class="section-title">

                        <i class="fa-solid fa-shield-halved"></i>

                        <span>
                            Bảo mật tài khoản
                        </span>

                    </div>


                    <div class="password-box">

                        <div class="password-note">

                            <i class="fa-solid fa-circle-info"></i>

                            <span>
                                Chỉ nhập mật khẩu mới nếu bạn muốn
                                cấp lại mật khẩu cho khách hàng.
                                Để trống nếu giữ nguyên mật khẩu hiện tại.
                            </span>

                        </div>


                        <div class="form-group">

                            <label>
                                Mật khẩu mới
                            </label>

                            <input
                                type="password"
                                name="password"
                                minlength="6"
                                placeholder="Nhập mật khẩu mới"
                            >

                            <div class="hint">

                                <i class="fa-solid fa-key"></i>

                                <span>
                                    Mật khẩu phải có ít nhất 6 ký tự.
                                </span>

                            </div>

                        </div>

                    </div>


                    <!-- BUTTONS -->

                    <div class="form-actions">

                        <a
                            href="customers.php"
                            class="btn btn-cancel"
                        >
                            <i class="fa-solid fa-arrow-left"></i>
                            Hủy
                        </a>

                        <button
                            type="submit"
                            class="btn btn-save"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Lưu thay đổi
                        </button>

                    </div>

                </form>

            </div>


            <a
                href="customers.php"
                class="back-link"
            >
                <i class="fa-solid fa-arrow-left"></i>
                &nbsp;
                Quay lại danh sách khách hàng
            </a>

        </main>

    </main>

</div>

</body>
</html>
