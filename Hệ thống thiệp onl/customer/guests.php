<?php

session_start();

require_once "../config/database.php";
require_once "../config/url_helper.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "customer") {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = intval($_SESSION["user_id"]);

$requested_wedding_id = intval(
    $_GET["wedding_id"] ?? 0
);

/* =====================================================
   [SECURITY] CSRF TOKEN
   ===================================================== */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

$success = "";
$error = "";

$created_link = isset($_GET["link"])
    ? $_GET["link"]
    : "";

/*
|--------------------------------------------------------------------------
| Lấy wedding của người đang đăng nhập
|--------------------------------------------------------------------------
*/

if ($requested_wedding_id > 0) {

    $stmt = $conn->prepare(
        "SELECT id, wedding_slug
         FROM weddings
         WHERE id = ?
           AND user_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        die("Lỗi SQL: " . $conn->error);
    }

    $stmt->bind_param(
        "ii",
        $requested_wedding_id,
        $user_id
    );

} else {

    $stmt = $conn->prepare(
        "SELECT id, wedding_slug
         FROM weddings
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        die("Lỗi SQL: " . $conn->error);
    }

    $stmt->bind_param(
        "i",
        $user_id
    );
}

if (!$stmt->execute()) {
    die("Lỗi thực thi SQL: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    die(
        "Không tìm thấy đám cưới cho user_id = " .
        htmlspecialchars($user_id)
    );

}

$wedding = $result->fetch_assoc();

$wedding_id = intval($wedding["id"]);


/*
|--------------------------------------------------------------------------
| Thêm khách mời
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        empty($_POST["csrf_token"]) ||
        empty($_SESSION["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error =
            "Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.";

    } else {

        $mode = $_POST["mode"] ?? "single";

    /*
    |--------------------------------------------------------------------------
    | NHẬP NHANH NHIỀU KHÁCH + TẠO LINK RIÊNG
    |--------------------------------------------------------------------------
    */

    if ($mode === "quick") {

        $quick_names_text = trim(
            $_POST["quick_guest_names"] ?? ""
        );

        if ($quick_names_text === "") {

            $error =
                "Vui lòng nhập ít nhất một tên khách.";

        } else {

            $lines = preg_split(
                '/\\R/u',
                $quick_names_text
            );

            $names = [];
            $seen = [];

            foreach ($lines as $line) {

                $name = trim($line);

                if ($name === "") {
                    continue;
                }

                $key = mb_strtolower(
                    preg_replace('/\\s+/u', ' ', $name),
                    'UTF-8'
                );

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $names[] = $name;
            }

            if (count($names) > 200) {

                $error =
                    "Mỗi lần chỉ được thêm tối đa 200 khách.";

            } elseif (count($names) === 0) {

                $error =
                    "Không tìm thấy tên khách hợp lệ.";

            } else {

                $conn->begin_transaction();

                try {

                    $guest_stmt = $conn->prepare(
                        "INSERT INTO guests
                        (
                            wedding_id,
                            guest_name,
                            phone,
                            group_name,
                            relationship
                        )
                        VALUES (?, ?, '', '', '')"
                    );

                    if (!$guest_stmt) {
                        throw new Exception(
                            "Không thể chuẩn bị câu lệnh thêm khách."
                        );
                    }

                    $link_stmt = $conn->prepare(
                        "INSERT INTO invitation_links
                        (
                            wedding_id,
                            guest_id,
                            token,
                            public_code
                        )
                        VALUES (?, ?, ?, ?)"
                    );

                    if (!$link_stmt) {
                        throw new Exception(
                            "Không thể chuẩn bị câu lệnh tạo link."
                        );
                    }

                    $created_count = 0;

                    foreach ($names as $name) {

                        $guest_stmt->bind_param(
                            "is",
                            $wedding_id,
                            $name
                        );

                        if (!$guest_stmt->execute()) {
                            throw new Exception(
                                "Không thể thêm khách '" .
                                $name .
                                "': " .
                                $guest_stmt->error
                            );
                        }

                        $guest_id = $conn->insert_id;

                        /*
                        | Tạo token ngẫu nhiên, kiểm tra trùng
                        */
                        do {

                            $token = bin2hex(
                                random_bytes(32)
                            );

                            $check_stmt = $conn->prepare(
                                "SELECT id
                                 FROM invitation_links
                                 WHERE token = ?
                                 LIMIT 1"
                            );

                            if (!$check_stmt) {
                                throw new Exception(
                                    "Không thể kiểm tra token."
                                );
                            }

                            $check_stmt->bind_param(
                                "s",
                                $token
                            );

                            if (!$check_stmt->execute()) {
                                $check_stmt->close();

                                throw new Exception(
                                    "Không thể kiểm tra token."
                                );
                            }

                            $check_result =
                                $check_stmt->get_result();

                            $token_exists =
                                $check_result->num_rows > 0;

                            $check_stmt->close();

                        } while ($token_exists);

                        $public_code = ensure_unique_public_code(
                            $conn
                        );

                        $link_stmt->bind_param(
                            "iiss",
                            $wedding_id,
                            $guest_id,
                            $token,
                            $public_code
                        );

                        if (!$link_stmt->execute()) {
                            throw new Exception(
                                "Không thể tạo link cho khách '" .
                                $name .
                                "': " .
                                $link_stmt->error
                            );
                        }

                        $created_count++;
                    }

                    $guest_stmt->close();
                    $link_stmt->close();

                    $conn->commit();

                    $success =
                        "Đã thêm " .
                        $created_count .
                        " khách và tạo " .
                        $created_count .
                        " link thiệp riêng thành công.";

                } catch (Throwable $e) {

                    $conn->rollback();

                    $error =
                        "Không thể nhập nhanh danh sách khách: " .
                        $e->getMessage();
                }
            }
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | THÊM MỘT KHÁCH NHƯ CŨ
        |--------------------------------------------------------------------------
        */

        $guest_name =
            trim($_POST["guest_name"] ?? "");

        $phone =
            trim($_POST["phone"] ?? "");

        $group_name =
            trim($_POST["group_name"] ?? "");

        $relationship =
            trim($_POST["relationship"] ?? "");

        if ($guest_name === "") {

            $error =
                "Vui lòng nhập tên khách mời.";

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO guests
                (
                    wedding_id,
                    guest_name,
                    phone,
                    group_name,
                    relationship
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            if (!$stmt) {

                $error =
                    "Lỗi SQL: " .
                    $conn->error;

            } else {

                $stmt->bind_param(
                    "issss",
                    $wedding_id,
                    $guest_name,
                    $phone,
                    $group_name,
                    $relationship
                );

                $conn->begin_transaction();

                try {

                    if (!$stmt->execute()) {
                        throw new Exception(
                            "Không thể thêm khách mời: " .
                            $stmt->error
                        );
                    }

                    $guest_id = $conn->insert_id;

                    do {

                        $token = bin2hex(
                            random_bytes(32)
                        );

                        $check_stmt = $conn->prepare(
                            "SELECT id
                             FROM invitation_links
                             WHERE token = ?
                             LIMIT 1"
                        );

                        if (!$check_stmt) {
                            throw new Exception(
                                "Không thể kiểm tra token."
                            );
                        }

                        $check_stmt->bind_param(
                            "s",
                            $token
                        );

                        if (!$check_stmt->execute()) {
                            $check_stmt->close();

                            throw new Exception(
                                "Không thể kiểm tra token."
                            );
                        }

                        $token_exists =
                            $check_stmt
                                ->get_result()
                                ->num_rows > 0;

                        $check_stmt->close();

                    } while ($token_exists);

                    $link_stmt = $conn->prepare(
                        "INSERT INTO invitation_links
                        (
                            wedding_id,
                            guest_id,
                            token,
                            public_code
                        )
                        VALUES (?, ?, ?, ?)"
                    );

                    if (!$link_stmt) {
                        throw new Exception(
                            "Không thể chuẩn bị câu lệnh tạo link."
                        );
                    }

                    $public_code = ensure_unique_public_code(
                        $conn
                    );

                    $link_stmt->bind_param(
                        "iiss",
                        $wedding_id,
                        $guest_id,
                        $token,
                        $public_code
                    );

                    if (!$link_stmt->execute()) {
                        $link_error = $link_stmt->error;
                        $link_stmt->close();

                        throw new Exception(
                            "Không thể tạo link thiệp: " .
                            $link_error
                        );
                    }

                    $link_stmt->close();
                    $conn->commit();

                    $success =
                        "Đã thêm khách mời và tạo link thiệp riêng thành công.";

                } catch (Throwable $e) {

                    $conn->rollback();

                    $error =
                        "Không thể thêm khách mời: " .
                        $e->getMessage();
                }

                $stmt->close();
            }
        }
    }
    }
}


/*
|--------------------------------------------------------------------------
| Lấy danh sách khách + link + lượt mở
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        g.*,
        il.token,
        il.public_code,
        il.visit_count,
        il.last_visited

     FROM guests g

     LEFT JOIN invitation_links il
        ON g.id = il.guest_id

     WHERE g.wedding_id = ?

     ORDER BY g.id DESC"
);

if (!$stmt) {
    die("Lỗi SQL: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $wedding_id
);

if (!$stmt->execute()) {
    die("Lỗi thực thi SQL: " . $stmt->error);
}

$guests_result = $stmt->get_result();
$guest_total = $guests_result->num_rows;

?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Khách mời - Thiệp Online</title>

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

        :root {
            --dark: #3D0B12;
            --wine: #B52A3A;
            --wine-dark: #9F1E2E;
            --cream: #FFFBFA;
            --white: #ffffff;
            --gold: #D4A73D;
            --text: #3A171D;
            --muted: #877275;
            --border: #F5E4E2;
            --green: #6ca582;
            --green-bg: #e9f4ec;
            --red: #C93B4A;
            --red-bg: #FFF0F1;
            --shadow: 0 5px 25px rgba(48,31,29,.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                "Be Vietnam Pro",
                Arial,
                sans-serif;

            background: var(--cream);
            color: var(--text);
            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }

        button,
        input {
            font-family: inherit;
        }

        /* =====================================================
           MAIN LAYOUT
           Trang Khách mời không sử dụng sidebar/menu.
        ===================================================== */

        .main {
            width: 100%;
            min-height: 100vh;

            margin-left: 0;
        }

        .topbar {
            height: 70px;

            padding: 0 30px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background:
                rgba(255,255,255,.96);

            border-bottom:
                1px solid
                var(--border);
        }

        /* =====================================================
           [5C.3.6.3 - BRAND]
           Nhận diện thương hiệu đồng bộ wedding_info.php
        ===================================================== */

        .brand-area {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                12px;

            color:
                var(--text);

        }


        .brand-icon {

            width:
                42px;

            height:
                42px;

            border-radius:
                50%;

            background:
                rgba(146, 93, 80, .10);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            color:
                var(--wine);

            font-size:
                17px;

            transition:
                transform .2s ease,
                background .2s ease;

        }


        .brand-area:hover .brand-icon {

            transform:
                translateY(-1px);

            background:
                rgba(146, 93, 80, .15);

        }


        .brand-text {

            display:
                flex;

            flex-direction:
                column;

            gap:
                2px;

        }


        .brand-text strong {

            font-family:
                "Playfair Display",
                serif;

            font-size:
                21px;

            font-weight:
                500;

            letter-spacing:
                .2px;

        }


        .brand-text span {

            font-size:
                8px;

            letter-spacing:
                2px;

            color:
                var(--muted);

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
            display: block;

            margin-top: 2px;

            color: var(--muted);
            font-size: 10px;
        }

        .avatar {
            width: 38px;
            height: 38px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #EBCFD0,
                    #D95B64
                );
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content {
            max-width: 1450px;

            margin: auto;

            padding:
                30px 32px 45px;
        }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;

            margin-bottom: 22px;
        }

        .heading-left h1 {
            font-family:
                "Playfair Display",
                serif;

            font-size: 28px;
            font-weight: 600;

            margin-bottom: 7px;
        }

        .heading-left p {
            color: var(--muted);
            font-size: 12px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 7px;

            padding: 9px 14px;

            border:
                1px solid
                #F0D8D6;

            border-radius: 7px;

            background: white;
            color: var(--wine);

            font-size: 11px;
            font-weight: 600;

            transition: .2s;
        }

        .back-btn:hover {
            background: var(--wine);
            color: white;
        }

        /* =====================================================
           ALERT
        ===================================================== */

        .message {
            display: flex;
            align-items: center;

            gap: 9px;

            padding: 12px 15px;

            margin-bottom: 18px;

            border-radius: 9px;

            font-size: 11px;
        }

        .message.success {
            background: var(--green-bg);
            border: 1px solid #d5e9db;
            color: #4f8061;
        }

        .message.error {
            background: var(--red-bg);
            border: 1px solid #F5D5D7;
            color: #C93B4A;
        }

        /* =====================================================
           CREATED LINK
        ===================================================== */

        .created-link {
            padding: 16px;

            margin-bottom: 18px;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #f3f8f3,
                    #edf5ee
                );

            border:
                1px solid
                #d8e9db;
        }

        .created-link-title {
            display: flex;
            align-items: center;

            gap: 8px;

            color: #4f8061;

            font-size: 11px;
            font-weight: 700;

            margin-bottom: 10px;
        }

        .created-link-row {
            display: flex;
            gap: 8px;
        }

        .created-link-row input {
            flex: 1;

            min-width: 0;

            height: 38px;

            padding: 0 11px;

            border:
                1px solid
                #d7e3d9;

            border-radius: 7px;

            background: white;

            color: #5c5754;

            font-size: 10px;

            outline: none;
        }

        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 22px;
        }

        .summary-card {
            display: flex;
            align-items: center;

            gap: 13px;

            padding: 17px 18px;

            background: white;

            border:
                1px solid
                var(--border);

            border-radius: 12px;

            box-shadow: var(--shadow);
        }

        .summary-icon {
            width: 43px;
            height: 43px;

            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            font-size: 16px;
        }

        .summary-icon.guest {
            background: #FCE8E7;
            color: var(--wine);
        }

        .summary-icon.link {
            background: #FCF5E5;
            color: #a47d42;
        }

        .summary-icon.view {
            background: #e7f2ea;
            color: var(--green);
        }

        .summary-text span {
            display: block;

            color: var(--muted);

            font-size: 10px;
            margin-bottom: 2px;
        }

        .summary-text strong {
            font-family:
                "Playfair Display",
                serif;

            font-size: 22px;
        }

        /* =====================================================
           SECTION
        ===================================================== */

        .section {
            padding: 21px;

            margin-bottom: 20px;

            background: white;

            border:
                1px solid
                var(--border);

            border-radius: 12px;

            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            margin-bottom: 18px;
        }

        .section-title {
            display: flex;
            align-items: center;

            gap: 10px;
        }

        .section-title-icon {
            width: 35px;
            height: 35px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: #FCE8E7;
            color: var(--wine);

            font-size: 13px;
        }

        .section-title h2 {
            font-family:
                "Playfair Display",
                serif;

            font-size: 18px;
            font-weight: 600;
        }

        .section-title p {
            margin-top: 2px;

            color: var(--muted);
            font-size: 9px;
        }

        .section-count {
            padding: 6px 9px;

            border-radius: 20px;

            background: #FFF8F6;
            color: var(--muted);

            font-size: 9px;
        }

        /* =====================================================
           FORM
        ===================================================== */

        .form-card {
            padding: 20px;

            border:
                1px dashed
                #dfd2cc;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #FFFCFB,
                    #fbf7f4
                );
        }

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 14px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;

            margin-bottom: 6px;

            color: #756367;

            font-size: 10px;
            font-weight: 600;
        }

        .required {
            color: var(--wine);
        }

        .form-control {
            width: 100%;

            height: 40px;

            padding: 0 12px;

            border:
                1px solid
                #EBDDDD;

            border-radius: 7px;

            background: white;

            color: var(--text);

            font-size: 10px;

            outline: none;

            transition: .2s;
        }

        .form-control:focus {
            border-color: var(--wine);

            box-shadow:
                0 0 0 3px
                rgba(150,80,80,.08);
        }

        .form-control::placeholder {
            color: #b2aaa7;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;

            margin-top: 15px;
        }

        .btn {
            min-height: 38px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 7px;

            padding: 0 15px;

            border: 0;
            border-radius: 7px;

            font-size: 10px;
            font-weight: 600;

            cursor: pointer;

            transition: .2s;
        }

        .btn-primary {
            background: var(--wine);
            color: white;
        }

        .btn-primary:hover {
            background: var(--wine-dark);
            transform: translateY(-1px);
        }

        .btn-light {
            background: #f6f1ee;
            color: var(--wine);

            border:
                1px solid
                #ECD8D5;
        }

        .btn-light:hover {
            background: #F5E4E2;
        }

        .btn-danger {
            background: #FFF0F1;
            color: var(--red);

            border:
                1px solid
                #F5D5D7;
        }

        .btn-danger:hover {
            background: #fbe2df;
        }

        .btn-small {
            min-height: 31px;
            padding: 0 10px;

            font-size: 9px;
        }

        /* =====================================================
           TABLE
        ===================================================== */

        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;

            margin-bottom: 13px;
        }

        .search-box {
            position: relative;

            flex: 1;

            max-width: 380px;
        }

        .search-box i {
            position: absolute;

            left: 12px;
            top: 50%;

            transform: translateY(-50%);

            color: #B9A7A9;

            font-size: 11px;
        }

        .search-box input {
            width: 100%;
            height: 36px;

            padding:
                0 12px 0 34px;

            border:
                1px solid
                #e7dfdb;

            border-radius: 7px;

            background: white;

            color: var(--text);

            font-size: 10px;

            outline: none;
        }

        .search-box input:focus {
            border-color: var(--wine);

            box-shadow:
                0 0 0 3px
                rgba(150,80,80,.08);
        }

        .table-count {
            color: var(--muted);
            font-size: 9px;
        }

        .table-wrapper {
            overflow-x: auto;

            border:
                1px solid
                var(--border);

            border-radius: 9px;
        }

        table {
            width: 100%;
            min-width: 1120px;

            border-collapse: collapse;
        }

        thead th {
            padding: 12px 13px;

            background: #fcf8f6;

            border-bottom:
                1px solid
                var(--border);

            color: #766d69;

            font-size: 9px;
            font-weight: 600;

            text-align: left;

            white-space: nowrap;
        }

        tbody td {
            padding: 12px 13px;

            border-bottom:
                1px solid
                #f2ece9;

            vertical-align: middle;

            font-size: 9px;
        }

        tbody tr {
            transition: .2s;
        }

        tbody tr:hover {
            background: #fdf9f7;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        /* =====================================================
           [4D.5] CHECKBOX + XÓA NHANH
        ===================================================== */

        .select-column {
            width: 46px;
            min-width: 46px;
            text-align: center !important;
        }

        .select-column input,
        .select-all-label input {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: var(--wine);
        }

        .bulk-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 15px;
            padding: 13px 16px;
            border: 1px solid #F0D8D6;
            border-radius: 10px;
            background: #FFF7F6;
        }

        .bulk-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .select-all-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #756367;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .selected-count {
            color: var(--wine);
            font-size: 9px;
            font-weight: 600;
        }

        .bulk-delete-btn {
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 13px;
            border: 0;
            border-radius: 7px;
            background: var(--wine);
            color: white;
            font-size: 9px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .bulk-delete-btn:hover:not(:disabled) {
            background: var(--wine-dark);
            transform: translateY(-1px);
        }

        .bulk-delete-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .icon-action.delete {
            border: 1px solid #ECD8D5;
            background: white;
            color: var(--red);
        }

        .icon-action.delete:hover {
            background: var(--red-bg);
        }

        .guest-row.removing {
            opacity: 0;
            transform: translateX(-12px);
            transition: .25s ease;
        }

        .bulk-toast {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            max-width: 360px;
            padding: 12px 16px;
            border-radius: 9px;
            font-size: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,.12);
            transition: opacity .25s ease;
        }

        .bulk-toast.success {
            background: var(--green-bg);
            border: 1px solid #cfe4d5;
            color: #4f8061;
        }

        .bulk-toast.error {
            background: var(--red-bg);
            border: 1px solid #F5D5D7;
            color: #C93B4A;
        }

        .guest-row.selected {
            background: #fff8f5;
        }

        .guest-info {
            display: flex;
            align-items: center;

            gap: 9px;

            min-width: 150px;
        }

        .guest-avatar {
            width: 34px;
            height: 34px;

            flex-shrink: 0;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #ecd5cf,
                    #c68f83
                );

            font-size: 11px;
        }

        .guest-name {
            color: #302a29;

            font-weight: 600;
        }

        .guest-phone {
            color: #7E6669;
            white-space: nowrap;
        }

        .tag {
            display: inline-flex;
            align-items: center;

            padding: 5px 8px;

            border-radius: 20px;

            background: #FFF8F6;
            color: #756b67;

            font-size: 8px;

            white-space: nowrap;
        }

        .status {
            display: inline-flex;
            align-items: center;

            gap: 5px;

            padding: 5px 8px;

            border-radius: 20px;

            font-size: 8px;

            white-space: nowrap;
        }

        .status.active {
            background: var(--green-bg);
            color: #5b916e;
        }

        .status-dot {
            width: 5px;
            height: 5px;

            border-radius: 50%;

            background: #69a77f;
        }

        .status.waiting {
            background: #FFF8F6;
            color: #8a7d77;
        }

        .link-cell {
            min-width: 245px;
        }

        .link-box {
            display: flex;
            align-items: center;

            gap: 6px;
        }

        .link-box input {
            width: 185px;
            height: 30px;

            padding: 0 8px;

            border:
                1px solid
                #EBDDDD;

            border-radius: 6px;

            background: #fbfaf9;

            color: #7E6669;

            font-size: 8px;
            outline: none;
        }

        .icon-action {
            width: 30px;
            height: 30px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 6px;

            cursor: pointer;

            font-size: 10px;

            transition: .2s;
        }

        .icon-action.copy {
            border:
                1px solid
                #ECD8D5;

            background: white;
            color: var(--wine);
        }

        .icon-action.copy:hover {
            background: #fbf3f0;
        }

        .icon-action.view {
            background: #FFF8F6;
            color: #756367;
        }

        .icon-action.view:hover {
            background: #F5E4E2;
        }

        .no-link {
            color: #B9A7A9;
            font-size: 9px;
        }

        .views {
            min-width: 55px;

            text-align: center;

            font-family:
                "Playfair Display",
                serif;

            font-size: 17px;
        }

        .last-open {
            color: #7E6669;

            white-space: nowrap;
        }

        .actions {
            display: flex;
            align-items: center;

            gap: 5px;
        }

        .empty {
            padding: 55px 20px;

            text-align: center;

            color: var(--muted);

            border:
                1px dashed
                #e2d9d5;

            border-radius: 9px;

            background: #FFFAF9;
        }

        .empty i {
            display: block;

            margin-bottom: 12px;

            color: #cbbab3;

            font-size: 32px;
        }

        .empty strong {
            display: block;

            margin-bottom: 5px;

            color: #756367;

            font-family:
                "Playfair Display",
                serif;

            font-size: 16px;
        }

        .empty span {
            font-size: 10px;
        }

        .search-empty {
            display: none;

            margin-top: 12px;
        }


        /* =====================================================
           NHẬP NHANH
        ===================================================== */

        .quick-entry {
            padding: 20px;
            border: 1px solid #ECD8D5;
            border-radius: 10px;
            background: linear-gradient(135deg, #FFF7F6, #fbf5f1);
        }

        .quick-entry-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
        }

        .quick-entry-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-entry-icon {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: #FCE8E7;
            color: var(--wine);
        }

        .quick-entry-title h3 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            font-weight: 600;
        }

        .quick-entry-title p {
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
            line-height: 1.5;
        }

        .quick-badge {
            padding: 6px 9px;
            border-radius: 20px;
            background: #FCF5E5;
            color: #9b7744;
            font-size: 8px;
            white-space: nowrap;
        }

        .quick-textarea {
            width: 100%;
            min-height: 150px;
            padding: 13px;
            resize: vertical;
            border: 1px solid #EBDDDD;
            border-radius: 8px;
            background: white;
            color: var(--text);
            font-family: inherit;
            font-size: 11px;
            line-height: 1.7;
            outline: none;
        }

        .quick-textarea:focus {
            border-color: var(--wine);
            box-shadow: 0 0 0 3px rgba(150,80,80,.08);
        }

        .quick-help {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 9px;
        }

        .quick-help i {
            color: var(--gold);
        }

        .quick-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px;
        }

        .quick-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0 2px;
            color: #a39a96;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .quick-divider::before,
        .quick-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #eee4df;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        /* =====================================================
           [5C.3.6.3 - RESPONSIVE]
        ===================================================== */

        @media (max-width: 900px) {

            .summary-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns:
                    1fr;
            }

            .form-group.full {
                grid-column:
                    auto;
            }

        }


        .quick-count-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 10px;
            color: var(--muted);
            font-size: 10px;
        }

        .quick-count-info strong {
            color: var(--wine);
            font-size: 12px;
        }

        .quick-count-info i {
            margin-right: 4px;
            color: var(--wine);
        }

        .quick-warning {
            color: var(--red);
            font-size: 9px;
            font-weight: 600;
        }

        @media (max-width: 650px) {

            .topbar {
                height:
                    auto;

                min-height:
                    68px;

                padding:
                    12px 16px;
            }


            .brand-icon {

                width:
                    38px;

                height:
                    38px;

            }


            .brand-text strong {

                font-size:
                    17px;

            }


            .brand-text span {

                font-size:
                    7px;

                letter-spacing:
                    1.4px;

            }


            .user-text {
                display:
                    none;
            }


            .content {
                padding:
                    24px 15px 35px;
            }


            .page-heading {
                flex-direction:
                    column;

                align-items:
                    flex-start;

                gap:
                    14px;
            }


            .back-btn {
                width:
                    100%;
            }


            .section {
                padding:
                    15px;
            }


            .summary-grid {
                grid-template-columns:
                    1fr;
            }


            .table-toolbar {
                align-items:
                    stretch;

                flex-direction:
                    column;
            }


            .search-box {
                max-width:
                    none;
            }


            .created-link-row {
                flex-direction:
                    column;
            }


            .created-link-row .btn,
            .form-actions .btn {
                width:
                    100%;
            }

        }

    
        /* =====================================================
           THYU_NHUN WEDDINGS — GUEST MANAGEMENT MASTER STYLE
           Đồng bộ với Dashboard/Settings, không có sidebar.
        ===================================================== */
        body { background:#FFF8F6 !important; color:#32191C !important; }
        .main { width:100% !important; margin-left:0 !important; }
        .topbar {
            background:rgba(255,255,255,.94) !important;
            border-bottom:1px solid #ECD8D5 !important;
            box-shadow:none !important;
            backdrop-filter:blur(14px);
        }
        .content { width:min(1180px, calc(100% - 32px)) !important; margin:0 auto !important; }
        .page-title, .page-heading h1, h1, .section-title h2 {
            font-family:"Cormorant Garamond", Georgia, serif !important;
            color:#32191C !important;
        }
        .summary-card, .section, .quick-add, .table-section, .empty-state {
            border-color:#ECD8D5 !important;
            box-shadow:0 10px 35px rgba(61,48,48,.05) !important;
            border-radius:20px !important;
        }
        .summary-card { background:#fff !important; }
        .summary-card .value, .summary-number { color:#65131F !important; }
        .section { background:#fff !important; }
        .search-box input, .form-control, input, textarea, select {
            border-color:#ECD8D5 !important;
            background:#FFFCFB !important;
            border-radius:12px !important;
        }
        .search-box input:focus, .form-control:focus, input:focus, textarea:focus, select:focus {
            border-color:#8F1D2C !important;
            box-shadow:0 0 0 4px rgba(143,29,44,.10) !important;
            outline:none !important;
        }
        .btn-primary, .btn-add, .btn-create, button[type="submit"] {
            background:linear-gradient(135deg,#8F1D2C,#65131F) !important;
            border-color:#8F1D2C !important;
            color:#fff !important;
            border-radius:12px !important;
            box-shadow:0 10px 22px rgba(143,29,44,.14);
        }
        .btn-secondary, .back-btn {
            background:#FFF7F6 !important;
            color:#65131F !important;
            border-color:#ECD8D5 !important;
            border-radius:12px !important;
        }
        .table-wrap, .guest-table-wrap {
            border:1px solid #ECD8D5 !important;
            border-radius:16px !important;
            overflow:auto !important;
        }
        table thead th {
            background:#f7f0ed !important;
            color:#6a5554 !important;
        }
        table tbody tr:hover { background:#FFF7F6 !important; }
        .quick-textarea {
            border-color:#ECD8D5 !important;
            background:#FFFCFB !important;
            border-radius:14px !important;
        }
        .quick-textarea:focus {
            border-color:#8F1D2C !important;
            box-shadow:0 0 0 4px rgba(143,29,44,.10) !important;
        }
        .quick-divider::before, .quick-divider::after { background:#ECD8D5 !important; }
        .empty-state { background:#fff !important; border-style:dashed !important; }
        @media(max-width:650px){
            .content { width:min(100% - 24px, 1180px) !important; }
            .section, .summary-card, .quick-add { border-radius:16px !important; }
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

</style>

</head>

<body>

    <!-- =====================================================
         MAIN
    ===================================================== -->

    <main class="main">

        <!-- =================================================
             [5C.3.6.3 - BRAND HEADER]
             Trang chức năng: KHÔNG có sidebar/menu đầy đủ.
             Đồng bộ concept với wedding_info.php.
        ================================================== -->

        <header class="topbar">

            <a
                href="dashboard.php"
                class="brand-area"
                aria-label="Về trang chủ"
            >

                <div class="brand-icon"><svg class="brand-logo-svg" viewBox="0 0 64 64" aria-hidden="true">
<path d="M17 19c-5 0-9 4-9 9 0 11 12.5 19 24 28 11.5-9 24-17 24-28 0-5-4-9-9-9-6 0-11 4.5-15 10-4-5.5-9-10-15-10Z"/>
<path d="M22 18c4 1 7.2 4.1 10 8.2 2.8-4.1 6-7.2 10-8.2"/>
<circle cx="32" cy="10.5" r="1.6" fill="currentColor" stroke="none"/>
</svg></div>


                <div class="brand-text">

                    <strong>
                        thyu_nhun Weddings
                    </strong>

                    <span>
                        WEDDING INVITATION
                    </span>

                </div>

            </a>


            <div class="user-area">

                <div class="user-text">

                    <strong>
                        Xin chào,
                        <?= htmlspecialchars(
                            $_SESSION["full_name"] ?? "Khách hàng"
                        ) ?>
                    </strong>

                    <span>
                        Khách hàng
                    </span>

                </div>

                <div class="avatar">
                    <i class="fa-solid fa-user"></i>
                </div>

            </div>

        </header>


        <section class="content">

            <div class="page-heading">

                <div class="heading-left">

                    <h1>
                        Khách mời
                    </h1>

                    <p>
                        Quản lý danh sách khách, link thiệp riêng
                        và lượt mở thiệp.
                    </p>

                </div>

                <a
                    href="dashboard.php"
                    class="back-btn"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Trang chủ
                </a>

            </div>


            <!-- =================================================
                 CREATED LINK
            ================================================== -->

            <?php if ($created_link !== ""): ?>

                <div class="created-link">

                    <div class="created-link-title">

                        <i class="fa-solid fa-circle-check"></i>

                        <span>
                            Đã tạo link thiệp thành công
                        </span>

                    </div>

                    <div class="created-link-row">

                        <input
                            type="text"
                            id="createdLink"
                            value="<?= htmlspecialchars($created_link) ?>"
                            readonly
                        >

                        <button
                            type="button"
                            class="btn btn-primary"
                            onclick="copyCreatedLink()"
                        >
                            <i class="fa-regular fa-copy"></i>
                            Copy link
                        </button>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 MESSAGE
            ================================================== -->

            <?php if ($success !== ""): ?>

                <div class="message success">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        <?= htmlspecialchars($success) ?>
                    </span>

                </div>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <div class="message error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($error) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 SUMMARY
            ================================================== -->

            <div class="summary-grid">

                <div class="summary-card">

                    <div class="summary-icon guest">
                        <i class="fa-solid fa-user-group"></i>
                    </div>

                    <div class="summary-text">

                        <span>
                            Tổng khách mời
                        </span>

                        <strong>
                            <?= $guest_total ?>
                        </strong>

                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-icon link">
                        <i class="fa-solid fa-link"></i>
                    </div>

                    <div class="summary-text">

                        <span>
                            Link đã tạo
                        </span>

                        <strong
                            id="linkCount"
                        >
                            0
                        </strong>

                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-icon view">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>

                    <div class="summary-text">

                        <span>
                            Tổng lượt mở
                        </span>

                        <strong
                            id="viewCount"
                        >
                            0
                        </strong>

                    </div>

                </div>

            </div>



            <!-- =================================================
                 QUICK ADD GUESTS
            ================================================== -->

            <section class="section">

                <div class="section-header">

                    <div class="section-title">

                        <div class="section-title-icon">
                            <i class="fa-solid fa-bolt"></i>
                        </div>

                        <div>

                            <h2>
                                Nhập nhanh danh sách khách
                            </h2>

                            <p>
                                Mỗi dòng một khách — tự tạo link riêng.
                            </p>

                        </div>

                    </div>

                    <span class="section-count">
                        Tối đa 200 khách/lần
                    </span>

                </div>

                <div class="quick-entry">

                    <div class="quick-entry-header">

                        <div class="quick-entry-title">

                            <div class="quick-entry-icon">
                                <i class="fa-solid fa-bolt"></i>
                            </div>

                            <div>

                                <h3>
                                    Thêm khách & tạo link tự động
                                </h3>

                                <p>
                                    Không cần nhập số điện thoại,
                                    nhóm hoặc mối quan hệ.
                                </p>

                            </div>

                        </div>

                        <span class="quick-badge">
                            ⚡ Nhanh nhất
                        </span>

                    </div>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>"
                        >

                        <input
                            type="hidden"
                            name="mode"
                            value="quick"
                        >

                        <textarea
                            id="quickGuestNames"
                            name="quick_guest_names"
                            class="quick-textarea"
                            placeholder="Ví dụ:
anh Văn
chị Chung
cô Lan
chú Minh
bạn Hương"
                        ></textarea>

                        <div class="quick-help">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>
                                Mỗi dòng sẽ tạo 1 khách và 1 link thiệp riêng.
                            </span>
                        </div>

                        <div class="quick-count-info" aria-live="polite">
                            <span>
                                <i class="fa-solid fa-users"></i>
                                Đã nhập:
                                <strong id="quickGuestCount">0</strong>
                                khách hợp lệ
                            </span>

                            <span
                                id="quickGuestWarning"
                                class="quick-warning"
                            ></span>
                        </div>

                        <div class="quick-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="fa-solid fa-bolt"></i>
                                Thêm nhanh & tạo link
                            </button>

                        </div>

                    </form>

                </div>

                <div class="quick-divider">
                    hoặc nhập chi tiết
                </div>

            </section>

            <!-- =================================================
                 ADD GUEST
            ================================================== -->

            <section class="section">

                <div class="section-header">

                    <div class="section-title">

                        <div class="section-title-icon">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>

                        <div>

                            <h2>
                                Thêm khách mời
                            </h2>

                            <p>
                                Tạo hồ sơ khách để cấp link thiệp riêng.
                            </p>

                        </div>

                    </div>

                    <span class="section-count">
                        Bắt buộc tên khách
                    </span>

                </div>


                <div class="form-card">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>"
                        >

                        <input type="hidden" name="mode" value="single">

                        <div class="form-grid">

                            <div class="form-group full">

                                <label>
                                    Tên khách mời
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="guest_name"
                                    class="form-control"
                                    placeholder="Ví dụ: Nguyễn Văn An"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Số điện thoại
                                </label>

                                <input
                                    type="text"
                                    name="phone"
                                    class="form-control"
                                    placeholder="0901234567"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Nhóm khách
                                </label>

                                <input
                                    type="text"
                                    name="group_name"
                                    class="form-control"
                                    placeholder="Bạn bè / Gia đình / Đồng nghiệp"
                                >

                            </div>


                            <div class="form-group full">

                                <label>
                                    Mối quan hệ
                                </label>

                                <input
                                    type="text"
                                    name="relationship"
                                    class="form-control"
                                    placeholder="Bạn của chú rể"
                                >

                            </div>

                        </div>


                        <div class="form-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="fa-solid fa-user-plus"></i>
                                Thêm khách mời
                            </button>

                        </div>

                    </form>

                </div>

            </section>


            <!-- =================================================
                 GUEST LIST
            ================================================== -->

            <section class="section">

                <div class="section-header">

                    <div class="section-title">

                        <div class="section-title-icon">
                            <i class="fa-solid fa-address-book"></i>
                        </div>

                        <div>

                            <h2>
                                Danh sách khách mời
                            </h2>

                            <p>
                                Theo dõi link và lượt mở của từng khách.
                            </p>

                        </div>

                    </div>

                    <span class="section-count">
                        <?= $guest_total ?> khách
                    </span>

                </div>


                <?php if ($guest_total > 0): ?>

                    <!-- =====================================================
                         [4D.5] XÓA NHANH NHIỀU KHÁCH
                         Không reload trang.
                    ====================================================== -->

                    <div
                        class="bulk-action-bar"
                        id="bulkActionBar"
                    >

                        <div class="bulk-left">

                            <label class="select-all-label">
                                <input
                                    type="checkbox"
                                    id="selectAllGuestsTop"
                                >
                                <span>Chọn tất cả</span>
                            </label>

                            <span
                                id="selectedGuestCount"
                                class="selected-count"
                            >
                                0 khách được chọn
                            </span>

                        </div>

                        <button
                            type="button"
                            id="deleteSelectedGuests"
                            class="bulk-delete-btn"
                            disabled
                        >
                            <i class="fa-solid fa-trash"></i>
                            Xóa đã chọn
                        </button>

                    </div>

                    <div class="table-toolbar">

                        <div class="search-box">

                            <i
                                class="fa-solid fa-magnifying-glass"
                            ></i>

                            <input
                                type="text"
                                id="guestSearch"
                                placeholder="Tìm theo tên, số điện thoại, nhóm..."
                            >

                        </div>

                        <div class="table-count">

                            Hiển thị
                            <strong id="visibleCount">
                                <?= $guest_total ?>
                            </strong>
                            khách

                        </div>

                    </div>


                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th class="select-column">
                                        <input
                                            type="checkbox"
                                            id="selectAllGuests"
                                            title="Chọn tất cả"
                                            aria-label="Chọn tất cả khách mời"
                                        >
                                    </th>

                                    <th>
                                        Khách mời
                                    </th>

                                    <th>
                                        Nhóm
                                    </th>

                                    <th>
                                        Mối quan hệ
                                    </th>

                                    <th>
                                        Trạng thái
                                    </th>

                                    <th>
                                        Link thiệp
                                    </th>

                                    <th>
                                        Lượt mở
                                    </th>

                                    <th>
                                        Lần mở cuối
                                    </th>

                                    <th>
                                        Thao tác
                                    </th>

                                </tr>

                            </thead>


                            <tbody id="guestTableBody">

                                <?php while (
                                    $guest =
                                    $guests_result->fetch_assoc()
                                ): ?>

                                    <?php

                                    $invite_url = "";

                                    if (!empty($guest["token"])) {
                                        $wedding_slug = trim((string)($wedding["wedding_slug"] ?? ""));
                                        $public_code = trim((string)($guest["public_code"] ?? ""));

                                        if ($wedding_slug !== "" && $public_code !== "") {
                                            $invite_url = build_public_invite_url(
                                                $wedding_slug,
                                                $public_code
                                            );
                                        } else {
                                            // Fallback cho dữ liệu cũ chưa có slug/code.
                                            $invite_url = rtrim(detect_app_base_url(), "/") .
                                                "/invite.php?token=" .
                                                rawurlencode((string)$guest["token"]);
                                        }
                                    }

                                    $search_text =
                                        strtolower(
                                            ($guest["guest_name"] ?? "") .
                                            " " .
                                            ($guest["phone"] ?? "") .
                                            " " .
                                            ($guest["group_name"] ?? "") .
                                            " " .
                                            ($guest["relationship"] ?? "")
                                        );

                                    ?>

                                    <tr
                                        class="guest-row"
                                        data-guest-id="<?= intval($guest["id"]) ?>"
                                        data-search="<?= htmlspecialchars(
                                            $search_text,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>"
                                    >

                                        <td class="select-column">
                                            <input
                                                type="checkbox"
                                                class="guest-checkbox"
                                                value="<?= intval($guest["id"]) ?>"
                                                data-name="<?= htmlspecialchars($guest["guest_name"], ENT_QUOTES, "UTF-8") ?>"
                                                aria-label="Chọn <?= htmlspecialchars($guest["guest_name"], ENT_QUOTES, "UTF-8") ?>"
                                            >
                                        </td>

                                        <!-- GUEST -->

                                        <td>

                                            <div class="guest-info">

                                                <div
                                                    class="guest-avatar"
                                                >
                                                    <i
                                                        class="fa-solid fa-user"
                                                    ></i>
                                                </div>

                                                <div>

                                                    <div
                                                        class="guest-name"
                                                    >
                                                        <?= htmlspecialchars(
                                                            $guest["guest_name"]
                                                        ) ?>
                                                    </div>

                                                    <?php if (
                                                        !empty(
                                                            $guest["phone"]
                                                        )
                                                    ): ?>

                                                        <div
                                                            class="guest-phone"
                                                        >
                                                            <?= htmlspecialchars(
                                                                $guest["phone"]
                                                            ) ?>
                                                        </div>

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- GROUP -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $guest["group_name"]
                                                )
                                            ): ?>

                                                <span class="tag">
                                                    <?= htmlspecialchars(
                                                        $guest["group_name"]
                                                    ) ?>
                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="no-link"
                                                >
                                                    Chưa cập nhật
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- RELATIONSHIP -->

                                        <td>

                                            <?= !empty(
                                                $guest["relationship"]
                                            )
                                                ? htmlspecialchars(
                                                    $guest["relationship"]
                                                )
                                                : '<span class="no-link">Chưa cập nhật</span>'
                                            ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php
                                            $guest_status =
                                                trim(
                                                    (string) (
                                                        $guest["status"]
                                                        ?? ""
                                                    )
                                                );
                                            ?>

                                            <?php if (
                                                $guest_status === ""
                                                ||
                                                strtolower(
                                                    $guest_status
                                                ) === "active"
                                                ||
                                                $guest_status === "Đang hoạt động"
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
                                                    class="status waiting"
                                                >
                                                    <?= htmlspecialchars(
                                                        $guest_status
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- LINK -->

                                        <td class="link-cell">

                                            <?php if (
                                                $invite_url !== ""
                                            ): ?>

                                                <div
                                                    class="link-box"
                                                >

                                                    <input
                                                        type="text"
                                                        value="<?= htmlspecialchars(
                                                            $invite_url
                                                        ) ?>"
                                                        readonly
                                                    >

                                                    <button
                                                        type="button"
                                                        class="icon-action copy"
                                                        title="Copy link"
                                                        onclick="copyInviteLink(
                                                            this
                                                        )"
                                                        data-link="<?= htmlspecialchars(
                                                            $invite_url,
                                                            ENT_QUOTES
                                                        ) ?>"
                                                    >
                                                        <i
                                                            class="fa-regular fa-copy"
                                                        ></i>
                                                    </button>

                                                    <a
                                                        href="<?= htmlspecialchars(
                                                            $invite_url
                                                        ) ?>"
                                                        target="_blank"
                                                        class="icon-action view"
                                                        title="Xem thiệp"
                                                    >
                                                        <i
                                                            class="fa-regular fa-eye"
                                                        ></i>
                                                    </a>

                                                </div>

                                            <?php else: ?>

                                                <a
                                                    href="create_invitation_link.php?guest_id=<?= intval(
                                                        $guest["id"]
                                                    ) ?>"
                                                    class="btn btn-primary btn-small"
                                                >
                                                    <i
                                                        class="fa-solid fa-link"
                                                    ></i>
                                                    Tạo link
                                                </a>

                                            <?php endif; ?>

                                        </td>


                                        <!-- VIEWS -->

                                        <td>

                                            <div
                                                class="views"
                                                data-views="<?= !empty(
                                                    $guest["token"]
                                                )
                                                    ? intval(
                                                        $guest["visit_count"]
                                                    )
                                                    : 0
                                                ?>"
                                            >
                                                <?= !empty(
                                                    $guest["token"]
                                                )
                                                    ? intval(
                                                        $guest["visit_count"]
                                                    )
                                                    : 0
                                                ?>
                                            </div>

                                        </td>


                                        <!-- LAST OPEN -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $guest["last_visited"]
                                                )
                                            ): ?>

                                                <span
                                                    class="last-open"
                                                >
                                                    <?= date(
                                                        "d/m/Y H:i",
                                                        strtotime(
                                                            $guest["last_visited"]
                                                        )
                                                    ) ?>
                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="no-link"
                                                >
                                                    Chưa mở
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- ACTION -->

                                        <td>

                                            <div class="actions">

                                                <a
                                                    href="edit_guest.php?id=<?= intval(
                                                        $guest["id"]
                                                    ) ?>&wedding_id=<?= $wedding_id ?>"
                                                    class="icon-action view"
                                                    title="Chỉnh sửa"
                                                >
                                                    <i
                                                        class="fa-solid fa-pen"
                                                    ></i>
                                                </a>

                                                <button
                                                    type="button"
                                                    class="icon-action delete single-delete"
                                                    title="Xóa khách"
                                                    data-id="<?= intval($guest["id"]) ?>"
                                                    data-name="<?= htmlspecialchars($guest["guest_name"], ENT_QUOTES, "UTF-8") ?>"
                                                    aria-label="Xóa <?= htmlspecialchars($guest["guest_name"], ENT_QUOTES, "UTF-8") ?>"
                                                >
                                                    <i
                                                        class="fa-solid fa-trash"
                                                    ></i>
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            </tbody>

                        </table>

                    </div>


                    <div
                        id="searchEmpty"
                        class="empty search-empty"
                    >

                        <i
                            class="fa-solid fa-magnifying-glass"
                        ></i>

                        <strong>
                            Không tìm thấy khách mời
                        </strong>

                        <span>
                            Thử tìm bằng tên, số điện thoại
                            hoặc nhóm khách.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="empty">

                        <i
                            class="fa-regular fa-user"
                        ></i>

                        <strong>
                            Chưa có khách mời
                        </strong>

                        <span>
                            Hãy thêm khách mời để tạo
                            link thiệp riêng cho từng người.
                        </span>

                    </div>

                <?php endif; ?>

            </section>

        </section>

    </main>


    <!-- =====================================================
         JAVASCRIPT
    ===================================================== -->

    <script>

        function copyText(
            text,
            button,
            originalHTML
        ) {

            if (
                navigator.clipboard &&
                window.isSecureContext
            ) {

                navigator.clipboard
                    .writeText(text)
                    .then(function () {

                        button.innerHTML =
                            '<i class="fa-solid fa-check"></i>';

                        setTimeout(
                            function () {
                                button.innerHTML =
                                    originalHTML;
                            },
                            1400
                        );

                    })
                    .catch(
                        function () {
                            copyFallback(
                                text,
                                button,
                                originalHTML
                            );
                        }
                    );

            } else {

                copyFallback(
                    text,
                    button,
                    originalHTML
                );

            }

        }


        function copyFallback(
            text,
            button,
            originalHTML
        ) {

            const textarea =
                document.createElement(
                    "textarea"
                );

            textarea.value = text;

            textarea.style.position =
                "fixed";

            textarea.style.left =
                "-9999px";

            document.body.appendChild(
                textarea
            );

            textarea.focus();
            textarea.select();

            try {

                document.execCommand(
                    "copy"
                );

                button.innerHTML =
                    '<i class="fa-solid fa-check"></i>';

                setTimeout(
                    function () {

                        button.innerHTML =
                            originalHTML;

                    },
                    1400
                );

            } catch (error) {

                alert(
                    "Không copy tự động được. " +
                    "Hãy copy link thủ công."
                );

            }

            document.body.removeChild(
                textarea
            );

        }


        function copyInviteLink(
            button
        ) {

            const link =
                button.getAttribute(
                    "data-link"
                );

            const originalHTML =
                button.innerHTML;

            copyText(
                link,
                button,
                originalHTML
            );

        }


        function copyCreatedLink() {

            const input =
                document.getElementById(
                    "createdLink"
                );

            if (!input) {
                return;
            }

            const button =
                input.parentElement.querySelector(
                    "button"
                );

            const originalHTML =
                button.innerHTML;

            copyText(
                input.value,
                button,
                originalHTML
            );

        }


        /* =====================================================
           SEARCH
        ===================================================== */

        const searchInput =
            document.getElementById(
                "guestSearch"
            );

        const rows =
            document.querySelectorAll(
                ".guest-row"
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
                                text.includes(
                                    keyword
                                )
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


        /* =====================================================
           SUMMARY COUNT
        ===================================================== */

        const linkCount =
            document.getElementById(
                "linkCount"
            );

        const viewCount =
            document.getElementById(
                "viewCount"
            );

        if (linkCount || viewCount) {

            let links = 0;
            let views = 0;

            document
                .querySelectorAll(
                    ".guest-row"
                )
                .forEach(
                    function (row) {

                        if (
                            row.querySelector(
                                ".link-box"
                            )
                        ) {
                            links++;
                        }

                        const view =
                            row.querySelector(
                                ".views"
                            );

                        if (view) {

                            views +=
                                parseInt(
                                    view.dataset.views
                                    || "0",
                                    10
                                );

                        }

                    }
                );

            if (linkCount) {
                linkCount.textContent =
                    links;
            }

            if (viewCount) {
                viewCount.textContent =
                    views;
            }

        }

        /* =====================================================
           [4D.5] XÓA KHÁCH KHÔNG RELOAD
           - Xóa 1 khách tại đúng dòng hiện tại
           - Xóa nhiều khách bằng checkbox
           - Không chạy lại đầu trang
        ===================================================== */

        const csrfToken = <?= json_encode($csrf_token, JSON_UNESCAPED_UNICODE) ?>;

        const selectAllGuests = document.getElementById("selectAllGuests");
        const selectAllGuestsTop = document.getElementById("selectAllGuestsTop");
        const deleteSelectedGuests = document.getElementById("deleteSelectedGuests");
        const selectedGuestCount = document.getElementById("selectedGuestCount");
        const guestTableBody = document.getElementById("guestTableBody");

        function getGuestCheckboxes() {
            return Array.from(document.querySelectorAll(".guest-checkbox"));
        }

        function getSelectedGuestCheckboxes() {
            return getGuestCheckboxes().filter(function (checkbox) {
                return checkbox.checked;
            });
        }

        function updateSelectionUI() {
            const checkboxes = getGuestCheckboxes();
            const selected = getSelectedGuestCheckboxes();
            const count = selected.length;
            const total = checkboxes.length;
            const allChecked = total > 0 && count === total;

            if (selectedGuestCount) {
                selectedGuestCount.textContent = count + " khách được chọn";
            }

            if (deleteSelectedGuests) {
                deleteSelectedGuests.disabled = count === 0;
            }

            [selectAllGuests, selectAllGuestsTop].forEach(function (checkbox) {
                if (!checkbox) return;
                checkbox.checked = allChecked;
                checkbox.indeterminate = count > 0 && count < total;
            });

            checkboxes.forEach(function (checkbox) {
                const row = checkbox.closest(".guest-row");
                if (row) {
                    row.classList.toggle("selected", checkbox.checked);
                }
            });
        }

        function toggleAllGuests(checked) {
            getGuestCheckboxes().forEach(function (checkbox) {
                checkbox.checked = checked;
            });
            updateSelectionUI();
        }

        if (selectAllGuests) {
            selectAllGuests.addEventListener("change", function () {
                toggleAllGuests(this.checked);
            });
        }

        if (selectAllGuestsTop) {
            selectAllGuestsTop.addEventListener("change", function () {
                toggleAllGuests(this.checked);
            });
        }

        if (guestTableBody) {
            guestTableBody.addEventListener("change", function (event) {
                if (event.target.classList.contains("guest-checkbox")) {
                    updateSelectionUI();
                }
            });
        }

        function showBulkToast(message, type) {
            const oldToast = document.querySelector(".bulk-toast");
            if (oldToast) oldToast.remove();

            const toast = document.createElement("div");
            toast.className = "bulk-toast " + (type || "success");
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(function () {
                toast.style.opacity = "0";
                setTimeout(function () {
                    toast.remove();
                }, 250);
            }, 2500);
        }

        function updateSummaryAfterDelete() {
            const rows = document.querySelectorAll(".guest-row");
            let links = 0;
            let views = 0;

            rows.forEach(function (row) {
                if (row.querySelector(".link-box")) links++;
                const view = row.querySelector(".views");
                if (view) views += parseInt(view.dataset.views || "0", 10);
            });

            const linkCount = document.getElementById("linkCount");
            const viewCount = document.getElementById("viewCount");

            if (linkCount) linkCount.textContent = links;
            if (viewCount) viewCount.textContent = views;

            const summaryCards = document.querySelectorAll(".summary-card");
            if (summaryCards[0]) {
                const value = summaryCards[0].querySelector(".summary-text strong");
                if (value) value.textContent = rows.length;
            }

            const sectionCounts = document.querySelectorAll(".section-count");
            sectionCounts.forEach(function (el) {
                if (el.textContent.trim().match(/^\d+\s*khách$/i)) {
                    el.textContent = rows.length + " khách";
                }
            });

            const visibleCount = document.getElementById("visibleCount");
            if (visibleCount) {
                visibleCount.textContent = Array.from(rows).filter(function (row) {
                    return getComputedStyle(row).display !== "none";
                }).length;
            }

            const searchEmpty = document.getElementById("searchEmpty");
            if (searchEmpty && rows.length === 0) {
                searchEmpty.style.display = "block";
            }
        }

        async function deleteGuestsByIds(ids, confirmMessage) {
            if (!ids.length) return;
            if (!confirm(confirmMessage)) return;

            const formData = new FormData();
            formData.append("csrf_token", csrfToken);
            ids.forEach(function (id) {
                formData.append("guest_ids[]", id);
            });

            const selectedRows = ids.map(function (id) {
                return document.querySelector('.guest-row[data-guest-id="' + id + '"]');
            }).filter(Boolean);

            selectedRows.forEach(function (row) {
                row.classList.add("removing");
            });

            if (deleteSelectedGuests) {
                deleteSelectedGuests.disabled = true;
                deleteSelectedGuests.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xóa...';
            }

            try {
                const response = await fetch("delete_guest.php", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || "Không thể xóa khách mời.");
                }

                selectedRows.forEach(function (row) {
                    row.remove();
                });

                updateSelectionUI();
                updateSummaryAfterDelete();

                showBulkToast(
                    data.message || "Đã xóa khách mời.",
                    "success"
                );

            } catch (error) {

                selectedRows.forEach(function (row) {
                    row.classList.remove("removing");
                });

                showBulkToast(
                    error.message || "Đã xảy ra lỗi khi xóa.",
                    "error"
                );

            } finally {

                if (deleteSelectedGuests) {
                    deleteSelectedGuests.innerHTML = '<i class="fa-solid fa-trash"></i> Xóa đã chọn';
                }

                updateSelectionUI();
            }
        }

        if (deleteSelectedGuests) {
            deleteSelectedGuests.addEventListener("click", function () {
                const selected = getSelectedGuestCheckboxes();

                const ids = selected.map(function (checkbox) {
                    return checkbox.value;
                });

                const names = selected.map(function (checkbox) {
                    return checkbox.dataset.name;
                });

                let message =
                    "Bạn có chắc muốn xóa " + ids.length + " khách mời?";

                if (names.length <= 5) {
                    message += "\n\n" + names.join("\n");
                }

                message +=
                    "\n\nLink thiệp riêng của các khách này cũng sẽ bị xóa.";

                deleteGuestsByIds(ids, message);
            });
        }

        if (guestTableBody) {
            guestTableBody.addEventListener("click", function (event) {
                const button = event.target.closest(".single-delete");
                if (!button) return;

                const id = button.dataset.id;
                const name = button.dataset.name || "khách mời này";

                deleteGuestsByIds(
                    [id],
                    "Bạn có chắc muốn xóa khách mời:\n\n" +
                    name +
                    "\n\nLink thiệp riêng của khách này cũng sẽ bị xóa."
                );
            });
        }

        /* =====================================================
           QUICK GUEST COUNTER
        ===================================================== */

        const quickGuestNames =
            document.getElementById("quickGuestNames");

        const quickGuestCount =
            document.getElementById("quickGuestCount");

        const quickGuestWarning =
            document.getElementById("quickGuestWarning");

        function updateQuickGuestCount() {

            if (!quickGuestNames || !quickGuestCount) {
                return;
            }

            const lines =
                quickGuestNames.value.split(/\r?\n/);

            const seen = new Set();
            let count = 0;

            lines.forEach(function (line) {

                const name =
                    line
                        .trim()
                        .replace(/\s+/g, " ");

                if (name === "") {
                    return;
                }

                const key =
                    name.toLocaleLowerCase();

                if (seen.has(key)) {
                    return;
                }

                seen.add(key);
                count++;
            });

            quickGuestCount.textContent = count;

            if (quickGuestWarning) {

                if (count > 200) {
                    quickGuestWarning.textContent =
                        "Tối đa 200 khách/lần";
                } else {
                    quickGuestWarning.textContent = "";
                }
            }
        }

        if (quickGuestNames) {

            quickGuestNames.addEventListener(
                "input",
                updateQuickGuestCount
            );

            updateQuickGuestCount();
        }

        updateSelectionUI();

    </script>

</body>

</html>
