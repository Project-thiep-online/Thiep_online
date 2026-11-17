<?php

session_start();

require_once "../config/database.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

$error = "";
$success = "";

$username = "";
$full_name = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if ($username === "" || $password === "" || $full_name === "") {
        $error = "Vui lòng nhập đầy đủ các thông tin bắt buộc.";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $error = "Tên đăng nhập chỉ được gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang (3–50 ký tự).";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Địa chỉ email không hợp lệ.";
    } elseif ($phone !== "" && !preg_match('/^[0-9+\s().-]{8,20}$/', $phone)) {
        $error = "Số điện thoại không hợp lệ.";
    } else {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ? LIMIT 1"
        );

        if (!$check) {
            $error = "Không thể kiểm tra tài khoản: " . $conn->error;
        } else {
            $check->bind_param("s", $username);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Tên đăng nhập đã tồn tại.";
            } else {

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (username, password, full_name, email, phone, role)
                    VALUES (?, ?, ?, ?, ?, 'customer')"
                );

                if (!$stmt) {
                    $error = "Không thể tạo tài khoản: " . $conn->error;
                } else {
                    $stmt->bind_param(
                        "sssss",
                        $username,
                        $hashed_password,
                        $full_name,
                        $email,
                        $phone
                    );

                    if ($stmt->execute()) {
                        $success = "Tạo tài khoản khách hàng thành công.";

                        $username = "";
                        $full_name = "";
                        $email = "";
                        $phone = "";
                        $_POST["password"] = "";
                    } else {
                        $error = "Không thể tạo tài khoản: " . $stmt->error;
                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}

$admin_name = $_SESSION["full_name"] ?? "Quản trị viên";

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thêm khách hàng - thyu_nhun Weddings</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
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
            --white: #fff;
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

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 245px;
            height: 100vh;
            background: var(--dark);
            color: white;
            z-index: 10;
        }

        .brand {
            padding: 25px 22px 22px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: linear-gradient(135deg,#a75858,#65131F);
            font-size: 20px;
            margin-bottom: 12px;
        }

        .brand-name {
            font-family: "Playfair Display", serif;
            font-size: 20px;
        }

        .brand-sub {
            margin-top: 3px;
            color: #b9a9a6;
            font-size: 9px;
            letter-spacing: 2px;
        }

        .menu {
            padding: 18px 12px;
        }

        .menu-title {
            color: #817474;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 14px 8px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 14px;
            margin: 4px 0;
            border-radius: 8px;
            color: #c9bfbd;
            text-decoration: none;
            font-size: 13px;
            transition: .2s;
        }

        .menu-item i {
            width: 18px;
            text-align: center;
        }

        .menu-item:hover {
            background: rgba(255,255,255,.08);
            color: white;
        }

        .menu-item.active {
            background: linear-gradient(135deg,#a75858,#8e4848);
            color: white;
        }

        .menu-divider {
            height: 1px;
            background: rgba(255,255,255,.08);
            margin: 15px 8px;
        }

        .menu-item.logout:hover {
            background: rgba(190,80,80,.16);
            color: #f0aaaa;
        }

        .main {
            margin-left: 245px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            padding: 0 30px;
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar-left,
        .top-right,
        .user-area {
            display: flex;
            align-items: center;
        }

        .topbar-left {
            gap: 18px;
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
        }

        .home-btn:hover {
            background: var(--wine);
            color: white;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .page-title i {
            color: var(--wine);
        }

        .top-right {
            gap: 18px;
        }

        .user-area {
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
            color: var(--muted);
            font-size: 9px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg,#e8cfc5,#b98678);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .content {
            width: min(720px, calc(100% - 40px));
            margin: 0 auto;
            padding: 35px 0 50px;
        }

        .heading {
            margin-bottom: 24px;
        }

        .heading-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: #f5e3df;
            color: var(--wine);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        h1 {
            font-family: "Playfair Display", serif;
            font-size: 28px;
            margin-bottom: 6px;
        }

        .heading p {
            color: var(--muted);
            font-size: 12px;
        }

        .form-box {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(50,30,25,.06);
        }

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

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 17px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            font-weight: 600;
        }

        .section-title i {
            color: var(--wine);
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 11px;
            font-weight: 600;
            color: #514a48;
        }

        .required {
            color: var(--wine);
        }

        input {
            width: 100%;
            height: 42px;
            padding: 0 13px;
            border: 1px solid #e2dad6;
            border-radius: 8px;
            background: white;
            font-family: "Be Vietnam Pro", Arial, sans-serif;
            font-size: 11px;
            color: var(--text);
            outline: none;
        }

        input:focus {
            border-color: var(--wine);
            box-shadow: 0 0 0 3px rgba(150,80,80,.08);
        }

        .hint {
            display: flex;
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

        .password-note {
            padding: 13px 15px;
            background: #faf6f3;
            border: 1px solid #eee2dc;
            border-radius: 9px;
            margin-bottom: 18px;
            display: flex;
            gap: 9px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.5;
        }

        .password-note i {
            color: var(--gold);
        }

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
            font-family: "Be Vietnam Pro", sans-serif;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
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

            .home-btn span,
            .user-info {
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

    

    <main class="main">

        <header class="topbar">

            <div class="topbar-left">

                <a href="dashboard.php" class="home-btn">
                    <i class="fa-solid fa-house"></i>
                    <span>Trang chủ</span>
                </a>

                <div class="page-title">
                    <i class="fa-solid fa-user-plus"></i>
                    <span>Thêm khách hàng</span>
                </div>

            </div>

            <div class="top-right">

                <div class="user-area">

                    <div class="user-info">
                        <strong>
                            Xin chào, <?= htmlspecialchars($admin_name) ?>
                        </strong>
                        <span>Quản trị viên</span>
                    </div>

                    <div class="avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>

                </div>

            </div>

        </header>

        <main class="content">

            <div class="heading">

                <div class="heading-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>

                <h1>Thêm khách hàng</h1>

                <p>
                    Tạo tài khoản mới cho người thuê thiệp.
                </p>

            </div>

            <div class="form-box">

                <?php if ($error !== ""): ?>

                    <div class="alert error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>

                <?php endif; ?>

                <?php if ($success !== ""): ?>

                    <div class="alert success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>

                <?php endif; ?>

                <div class="section-title">
                    <i class="fa-solid fa-user"></i>
                    <span>Thông tin tài khoản</span>
                </div>

                <form method="POST" autocomplete="off">

                    <div class="form-group">

                        <label>
                            Họ và tên
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= htmlspecialchars($full_name) ?>"
                            placeholder="Nhập họ và tên"
                            maxlength="100"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Tên đăng nhập
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="username"
                            value="<?= htmlspecialchars($username) ?>"
                            placeholder="Nhập tên đăng nhập"
                            maxlength="50"
                            required
                        >

                        <div class="hint">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>
                                3–50 ký tự, chỉ dùng chữ, số, dấu chấm,
                                gạch dưới hoặc gạch ngang.
                            </span>
                        </div>

                    </div>

                    <div class="password-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>
                            Mật khẩu sẽ được mã hóa bằng
                            <strong>password_hash()</strong>
                            trước khi lưu vào cơ sở dữ liệu.
                        </span>
                    </div>

                    <div class="form-group">

                        <label>
                            Mật khẩu
                            <span class="required">*</span>
                        </label>

                        <input
                            type="password"
                            name="password"
                            minlength="6"
                            placeholder="Nhập mật khẩu"
                            required
                        >

                        <div class="hint">
                            <i class="fa-solid fa-key"></i>
                            <span>
                                Mật khẩu phải có ít nhất 6 ký tự.
                            </span>
                        </div>

                    </div>

                    <div class="section-title">
                        <i class="fa-solid fa-address-card"></i>
                        <span>Thông tin liên hệ</span>
                    </div>

                    <div class="form-group">

                        <label>Email</label>

                        <input
                            type="email"
                            name="email"
                            value="<?= htmlspecialchars($email) ?>"
                            placeholder="example@gmail.com"
                            maxlength="150"
                        >

                    </div>

                    <div class="form-group">

                        <label>Số điện thoại</label>

                        <input
                            type="text"
                            name="phone"
                            value="<?= htmlspecialchars($phone) ?>"
                            placeholder="Nhập số điện thoại"
                            maxlength="20"
                        >

                    </div>

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
                            <i class="fa-solid fa-user-plus"></i>
                            Tạo tài khoản
                        </button>

                    </div>

                </form>

            </div>

        </main>

    </main>

</div>

</body>
</html>
