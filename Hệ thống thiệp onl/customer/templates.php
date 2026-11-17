<?php

session_start();

require_once "../config/database.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


/*
|--------------------------------------------------------------------------
| KIỂM TRA CUSTOMER
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

$full_name = trim(
    $_SESSION["full_name"] ?? ""
);

if ($full_name === "") {
    $full_name = "Khách hàng";
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


/*
|--------------------------------------------------------------------------
| LẤY WEDDING ID TỪ URL
|--------------------------------------------------------------------------
|
| Ưu tiên wedding_id được truyền từ Dashboard / trang trước.
| Nếu không có wedding_id, hệ thống fallback về thiệp mới nhất
| để tương thích các đường dẫn cũ.
|
*/

$requested_wedding_id =
    intval(
        $_GET["wedding_id"] ?? 0
    );


/*
|--------------------------------------------------------------------------
| LẤY WEDDING CỦA CUSTOMER
|--------------------------------------------------------------------------
*/

$wedding = null;

$wedding_id = 0;

$current_template_id = 0;


if ($requested_wedding_id > 0) {

    /*
    |--------------------------------------------------------------------------
    | LẤY ĐÚNG THIỆP ĐANG QUẢN LÝ
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "SELECT
            id,
            template_id,
            groom_name,
            bride_name
         FROM weddings
         WHERE id = ?
           AND user_id = ?
         LIMIT 1"
    );

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $requested_wedding_id,
            $user_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $wedding =
            $result->fetch_assoc();

        $stmt->close();
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | FALLBACK CHO ĐƯỜNG DẪN CŨ
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "SELECT
            id,
            template_id,
            groom_name,
            bride_name
         FROM weddings
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );


    if ($stmt) {

        $stmt->bind_param(
            "i",
            $user_id
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $wedding =
            $result->fetch_assoc();

        $stmt->close();
    }
}


if ($wedding) {

    $wedding_id =
        intval($wedding["id"]);

    $current_template_id =
        intval(
            $wedding["template_id"] ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| LẤY CÁC MẪU ĐANG HOẠT ĐỘNG
|--------------------------------------------------------------------------
*/

$templates = [];


$sql = "
    SELECT
        id,
        template_name,
        template_code,
        folder,
        description,
        status
    FROM templates
    WHERE status = 'active'
    ORDER BY id DESC
";


$result =
    $conn->query($sql);


if ($result) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $templates[] =
            $row;
    }
}


$total_templates =
    count($templates);


/*
|--------------------------------------------------------------------------
| THÔNG BÁO
|--------------------------------------------------------------------------
*/

$error_message = "";

$success_message = "";


$error_code =
    $_GET["error"] ?? "";


switch ($error_code) {

    case "invalid_template":

        $error_message =
            "Mẫu thiệp không hợp lệ.";

        break;


    case "template_unavailable":

        $error_message =
            "Mẫu thiệp không tồn tại hoặc đã bị tắt.";

        break;


    case "template_not_installed":

        $error_message =
            "Mẫu thiệp chưa được cài đặt đầy đủ.";

        break;


    case "invalid_folder":

        $error_message =
            "Đường dẫn thư mục mẫu không hợp lệ.";

        break;


    case "no_wedding":

        $error_message =
            "Bạn chưa có thông tin thiệp. Hãy chọn mẫu để bắt đầu.";

        break;


    case "update_failed":

        $error_message =
            "Không thể cập nhật mẫu thiệp. Vui lòng thử lại.";

        break;


    case "create_failed":

        $error_message =
            "Không thể tạo thiệp. Vui lòng thử lại.";

        break;


    case "wedding_check":

        $error_message =
            "Không thể kiểm tra thông tin thiệp.";

        break;


    case "template_check":

        $error_message =
            "Không thể kiểm tra mẫu thiệp.";

        break;
}


/*
|--------------------------------------------------------------------------
| HÀM KIỂM TRA FILE MẪU
|--------------------------------------------------------------------------
*/

function templateExists(string $folder): bool
{
    if (
        $folder === "" ||
        !preg_match(
            '/^[a-zA-Z0-9_\/-]+$/',
            $folder
        )
    ) {
        return false;
    }


    return is_file(
        dirname(__DIR__) .
        "/" .
        trim($folder, "/") .
        "/index.php"
    );
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
        Chọn mẫu thiệp - Thyu_nhun Weddings
    </title>


    <!-- FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- FONT AWESOME -->

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

            --red-soft: #FFF0F1;

            --shadow:
                0 5px 25px rgba(48,31,29,.06);

            --font-display:
                "Cormorant Garamond",
                Georgia,
                serif;
  

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

            background:
                var(--cream);

            color:
                var(--text);

            min-height: 100vh;

        }


        a {

            text-decoration: none;

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main {

            width: 100%;

            min-height: 100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

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
                0 32px;

        }


        /*
        |--------------------------------------------------------------------------
        | BRAND HEADER
        |--------------------------------------------------------------------------
        |
        | Đồng bộ concept với wedding_info.php:
        | biểu tượng + tên thương hiệu + phụ đề.
        |
        */

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


        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

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

            color:
                var(--muted);

            font-size: 10px;

            margin-top: 2px;

        }


        .avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #EBCFD0,
                    #D95B64
                );

            display: flex;

            align-items: center;

            justify-content: center;

            color: white;

            font-size: 14px;

        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .content {

            max-width: 1500px;

            margin: 0 auto;

            padding:
                30px 32px 50px;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADING
        |--------------------------------------------------------------------------
        */

        .page-heading {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 22px;

        }


        .heading-left h1 {

            font-family:
                "Playfair Display",
                serif;

            font-size: 29px;

            font-weight: 600;

            color:
                #32191C;

            margin-bottom: 7px;

        }


        .heading-left p {

            color:
                var(--muted);

            font-size: 12px;

            line-height: 1.6;

        }


        /*
        |--------------------------------------------------------------------------
        | BACK HOME
        |--------------------------------------------------------------------------
        */

        .back-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding:
                9px 14px;

            background:
                white;

            color:
                var(--wine);

            border:
                1px solid
                #F0D8D6;

            border-radius: 7px;

            font-size: 11px;

            font-weight: 600;

            transition: .2s ease;

            white-space: nowrap;

        }


        .back-btn:hover {

            background:
                var(--wine);

            color:
                white;

            transform:
                translateX(-2px);

        }


        /*
        |--------------------------------------------------------------------------
        | ALERT
        |--------------------------------------------------------------------------
        */

        .alert {

            padding:
                12px 15px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-size: 11px;

            display: flex;

            align-items: center;

            gap: 9px;

        }


        .alert-error {

            background:
                var(--red-soft);

            border:
                1px solid
                #f0d5d0;

            color:
                #C93B4A;

        }


        .alert-success {

            background:
                #edf7f0;

            border:
                1px solid
                #d6eadc;

            color:
                #4f8061;

        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 22px;

        }


        .summary-card {

            background:
                white;

            border:
                1px solid
                var(--border);

            border-radius: 12px;

            padding:
                17px 18px;

            box-shadow:
                var(--shadow);

            display: flex;

            align-items: center;

            gap: 13px;

        }


        .summary-icon {

            width: 43px;

            height: 43px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;

            flex-shrink: 0;

        }


        .summary-icon.all {

            background:
                #FCE8E7;

            color:
                var(--wine);

        }


        .summary-icon.active {

            background:
                #e7f2ea;

            color:
                var(--green);

        }


        .summary-icon.current {

            background:
                #FCF5E5;

            color:
                #a47d42;

        }


        .summary-text {

            flex: 1;

        }


        .summary-text span {

            display: block;

            color:
                var(--muted);

            font-size: 10px;

            margin-bottom: 2px;

        }


        .summary-text strong {

            font-family:
                "Playfair Display",
                serif;

            font-size: 22px;

        }


        /*
        |--------------------------------------------------------------------------
        | TEMPLATE PANEL
        |--------------------------------------------------------------------------
        */

        .template-panel {

            background:
                white;

            border:
                1px solid
                var(--border);

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                var(--shadow);

        }


        .panel-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 18px;

        }


        .panel-title {

            font-size: 14px;

            font-weight: 600;

        }


        .panel-note {

            color:
                var(--muted);

            font-size: 10px;

        }


        /*
        |--------------------------------------------------------------------------
        | TEMPLATE GRID
        |--------------------------------------------------------------------------
        */

        .template-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(285px, 1fr)
                );

            gap: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | TEMPLATE CARD
        |--------------------------------------------------------------------------
        */

        .template-card {

            background:
                white;

            border:
                1px solid
                #F5E4E2;

            border-radius: 12px;

            overflow: hidden;

            display: flex;

            flex-direction: column;

            transition:
                .25s ease;

        }


        .template-card:hover {

            transform:
                translateY(-4px);

            box-shadow:
                0 12px 30px
                rgba(66,41,37,.10);

            border-color:
                #dfd0ca;

        }


        /*
        |--------------------------------------------------------------------------
        | PREVIEW
        |--------------------------------------------------------------------------
        */

        .preview {

            height: 270px;

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #f3eeeb,
                    #e9e2de
                );

        }


        .preview iframe {

            width: 100%;

            height: 100%;

            border: 0;

            background:
                white;

        }


        .preview-overlay {

            position: absolute;

            inset: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                rgba(255,255,255,.78);

            color:
                var(--muted);

            font-size: 12px;

            opacity: 1;

            transition:
                .2s ease;

            pointer-events: none;

        }


        .preview:hover
        .preview-overlay {

            opacity: 0;

        }


        /*
        |--------------------------------------------------------------------------
        | TEMPLATE BODY
        |--------------------------------------------------------------------------
        */

        .template-body {

            padding:
                18px;

            display: flex;

            flex-direction: column;

            flex: 1;

        }


        .template-name {

            font-family:
                "Playfair Display",
                serif;

            font-size: 18px;

            font-weight: 600;

            color:
                #342b2c;

            margin-bottom: 7px;

        }


        .template-code {

            color:
                var(--muted);

            font-size: 9px;

            margin-bottom: 8px;

        }


        .template-description {

            color:
                #766d6b;

            font-size: 11px;

            line-height: 1.65;

            min-height: 38px;

            margin-bottom: 12px;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            width: fit-content;

            padding:
                6px 9px;

            border-radius: 20px;

            background:
                #e7f3ea;

            color:
                #568363;

            font-size: 9px;

            font-weight: 600;

            margin-bottom: 15px;

        }


        .status-badge.current {

            background:
                #f4eadb;

            color:
                #9a753d;

        }


        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .template-actions {

            display: flex;

            gap: 8px;

            margin-top: auto;

        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            min-height: 38px;

            padding:
                0 13px;

            border-radius: 7px;

            border: 0;

            font-size: 10px;

            font-weight: 600;

            cursor: pointer;

            transition:
                .2s ease;

            text-align: center;

        }


        .btn-preview {

            flex: 1;

            background:
                #f2efed;

            color:
                #665d5b;

        }


        .btn-preview:hover {

            background:
                #e7e0dc;

        }


        .btn-select {

            flex: 1;

            width: 100%;

            background:
                var(--wine);

            color:
                white;

        }


        .btn-select:hover {

            background:
                var(--wine-dark);

            transform:
                translateY(-1px);

        }


        .btn-current {

            flex: 1;

            background:
                #e8f2eb;

            color:
                #568363;

            cursor:
                default;

        }


        .btn-disabled {

            flex: 1;

            background:
                #f0eeec;

            color:
                #a09a97;

            cursor:
                not-allowed;

        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        .empty {

            padding:
                65px 25px;

            text-align:
                center;

            color:
                var(--muted);

        }


        .empty i {

            display:
                block;

            font-size:
                38px;

            margin-bottom:
                14px;

            color:
                #c7b9b3;

        }


        .empty strong {

            display:
                block;

            color:
                #4b4040;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                18px;

            margin-bottom:
                7px;

        }


        .empty span {

            font-size:
                11px;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1050px) {

            .template-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(250px, 1fr)
                    );

            }

        }


        @media (max-width: 800px) {

            .summary-grid {

                grid-template-columns:
                    1fr;

            }


            .content {

                padding:
                    25px 18px 35px;

            }

        }


        @media (max-width: 650px) {

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


            .brand-icon {

                width:
                    38px;

                height:
                    38px;

            }


            .topbar {

                min-height:
                    65px;

                height:
                    auto;

                padding:
                    12px 16px;

            }


            .user-text {

                display:
                    none;

            }


            .page-heading {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap:
                    14px;

            }


            .back-btn {

                width:
                    100%;

            }


            .template-grid {

                grid-template-columns:
                    1fr;

            }


            .template-panel {

                padding:
                    14px;

            }


            .preview {

                height:
                    250px;

            }


            .template-actions {

                flex-direction:
                    column;

            }


            .btn {

                width:
                    100%;

            }

        }

    
        /* ==========================================================
           THYU_NHUN WEDDINGS - TEMPLATE GALLERY OVERRIDE
           Customer pages except Dashboard/Settings do not use sidebar.
        ========================================================== */

        body {
            background: #FFF8F6 !important;
            color: #32191C !important;
        }

        .sidebar,
        aside.sidebar,
        .main-sidebar,
        .sidebar-overlay {
            display: none !important;
        }

        .main,
        main.main,
        .content,
        .page-content {
            width: 100% !important;
            margin-left: 0 !important;
        }

        .topbar,
        header.topbar {
            position: sticky;
            top: 0;
            z-index: 200;
            min-height: 74px;
            height: auto;
            padding: 14px 5vw !important;
            background: rgba(255,255,255,.92) !important;
            border-bottom: 1px solid #ECD8D5 !important;
            backdrop-filter: blur(14px);
        }

        .page,
        .container,
        .content-wrap {
            width: min(1180px, calc(100% - 32px)) !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        .page {
            padding: 42px 0 52px !important;
        }

        .page-header {
            margin-bottom: 30px !important;
        }

        .page-title,
        h1 {
            font-family: "Cormorant Garamond", Georgia, serif !important;
            color: #32191C !important;
        }

        .template-grid {
            gap: 24px !important;
        }

        .template-card {
            background: #fff !important;
            border: 1px solid #ECD8D5 !important;
            border-radius: 20px !important;
            overflow: hidden !important;
            box-shadow: 0 10px 35px rgba(61,48,48,.05) !important;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease !important;
        }

        .template-card:hover {
            transform: translateY(-5px) !important;
            border-color: rgba(143,29,44,.38) !important;
            box-shadow: 0 18px 42px rgba(61,48,48,.10) !important;
        }

        .preview {
            background: #f1e9e5 !important;
            border-bottom: 1px solid #ECD8D5 !important;
        }

        .preview iframe {
            pointer-events: none;
            background: #fff;
        }

        .template-info {
            padding: 22px !important;
        }

        .template-info h3,
        .template-title {
            color: #32191C !important;
            font-family: "Cormorant Garamond", Georgia, serif !important;
        }

        .template-code,
        .template-description {
            color: #7E6669 !important;
        }

        .current-badge,
        .badge-current,
        .selected-badge {
            background: #FCE8E7 !important;
            color: #65131F !important;
            border: 1px solid #E9C7CA !important;
        }

        .template-actions {
            gap: 10px !important;
        }

        .btn,
        .btn-primary,
        .select-template-btn,
        button[type="submit"] {
            border-radius: 12px !important;
        }

        .btn-primary,
        .select-template-btn,
        button[type="submit"] {
            background: linear-gradient(135deg, #8F1D2C, #65131F) !important;
            border-color: #8F1D2C !important;
            color: #fff !important;
            box-shadow: 0 10px 22px rgba(143,29,44,.14);
        }

        .btn-secondary,
        .preview-btn {
            background: #FFF7F6 !important;
            color: #65131F !important;
            border: 1px solid #ECD8D5 !important;
        }

        .btn:hover,
        .select-template-btn:hover {
            transform: translateY(-1px);
        }

        .empty-state {
            background: #fff !important;
            border: 1px dashed #DDB7BA !important;
            border-radius: 20px !important;
        }

        @media (max-width: 768px) {
            .topbar,
            header.topbar {
                padding: 12px 16px !important;
            }

            .page {
                padding: 28px 0 38px !important;
            }

            .template-grid {
                gap: 18px !important;
            }

            .template-card {
                border-radius: 16px !important;
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

 /*
        ====================================================================
        FOOTER
        ====================================================================
        */

        footer {

            margin-top:
                auto;

            padding:
                32px 25px;

            border-top:
                1px solid
                var(--theme-border);

            background: white;

            text-align:
                center;

        }


        .footer-brand {

            font-family:
                var(--font-display);

            color:
                var(--theme-primary-dark);

            font-size:
                23px;

            font-weight:
                600;

        }


        .footer-tagline {

            margin-top:
                4px;

            color:
                var(--theme-muted);

            font-size:
                8px;

            letter-spacing:
                2px;

            text-transform:
                uppercase;

        }


        .footer-contact {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex-wrap:
                wrap;

            gap:
                8px 20px;

            margin:
                17px 0;

        }


        .footer-contact a {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            color:
                var(--theme-muted);

            font-size:
                10px;

            text-decoration:
                none;

            transition:
                var(--transition);

        }


        .footer-contact a:hover {

            color:
                var(--theme-primary);

        }


        .footer-copy {

            padding-top:
                15px;

            border-top:
                1px solid
                var(--theme-border);

            color:
                #a59693;

            font-size:
                9px;

        }


        .footer-copy strong {

            color:
                var(--theme-primary);

        }


</style>

</head>


<body>


<main class="main">


    <!-- =========================================================
         TOPBAR
    ========================================================== -->

    <!-- =====================================================
         HEADER THƯƠNG HIỆU
         Đồng bộ với concept wedding_info.php
         Trang chức năng: KHÔNG có sidebar/menu đầy đủ
    ====================================================== -->

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
                        $full_name
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


    <!-- =========================================================
         CONTENT
    ========================================================== -->

    <section class="content">


        <!-- QUAY VỀ TRANG CHỦ -->

        <div class="page-heading">

            <div class="heading-left">

                <h1>
                    Mẫu thiệp
                </h1>

                <p>
                    Chọn mẫu thiệp phù hợp với phong cách
                    đám cưới của bạn.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="back-btn"
            >

                <i class="fa-solid fa-arrow-left"></i>

                Về trang chủ

            </a>

        </div>


        <!-- =====================================================
             ALERT
        ====================================================== -->

        <?php if ($error_message !== ""): ?>

            <div class="alert alert-error">

                <i
                    class="fa-solid fa-circle-exclamation"
                ></i>

                <span>

                    <?= htmlspecialchars(
                        $error_message
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <?php if ($success_message !== ""): ?>

            <div class="alert alert-success">

                <i
                    class="fa-solid fa-circle-check"
                ></i>

                <span>

                    <?= htmlspecialchars(
                        $success_message
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <div class="summary-grid">


            <!-- TỔNG MẪU -->

            <div class="summary-card">

                <div class="summary-icon all">

                    <i
                        class="fa-solid fa-palette"
                    ></i>

                </div>


                <div class="summary-text">

                    <span>
                        Mẫu đang có
                    </span>

                    <strong>
                        <?= $total_templates ?>
                    </strong>

                </div>

            </div>


            <!-- CÓ THỂ SỬ DỤNG -->

            <div class="summary-card">

                <div class="summary-icon active">

                    <i
                        class="fa-solid fa-circle-check"
                    ></i>

                </div>


                <div class="summary-text">

                    <span>
                        Có thể sử dụng
                    </span>

                    <strong>
                        <?= $total_templates ?>
                    </strong>

                </div>

            </div>


            <!-- MẪU HIỆN TẠI -->

            <div class="summary-card">

                <div class="summary-icon current">

                    <i
                        class="fa-solid fa-heart"
                    ></i>

                </div>


                <div class="summary-text">

                    <span>
                        Mẫu đang sử dụng
                    </span>

                    <strong>

                        <?= $current_template_id > 0
                            ? "1"
                            : "0"
                        ?>

                    </strong>

                </div>

            </div>


        </div>


        <!-- =====================================================
             TEMPLATE PANEL
        ====================================================== -->

        <div class="template-panel">


            <div class="panel-header">

                <div class="panel-title">

                    Các mẫu thiệp

                </div>


                <div class="panel-note">

                    <?= $total_templates ?>

                    mẫu đang hoạt động

                </div>

            </div>


            <?php if (!empty($templates)): ?>


                <div class="template-grid">


                    <?php foreach (
                        $templates
                        as $template
                    ): ?>


                        <?php

                        $template_id =
                            intval(
                                $template["id"]
                            );


                        $template_name =
                            $template[
                                "template_name"
                            ] ??
                            "Mẫu thiệp";


                        $description =
                            $template[
                                "description"
                            ] ??
                            "Mẫu thiệp cưới.";


                        $template_code =
                            $template[
                                "template_code"
                            ] ??
                            "";


                        $folder =
                            trim(
                                $template[
                                    "folder"
                                ] ?? ""
                            );


                        $has_template_file =
                            templateExists(
                                $folder
                            );


                        $is_current =
                            $current_template_id ===
                            $template_id;

                        ?>


                        <article
                            class="template-card"
                        >


                            <!-- PREVIEW -->

                            <div class="preview">


                                <?php if (
                                    $has_template_file
                                ): ?>


                                    <iframe
                                        src="preview_template.php?template_id=<?= $template_id ?>&wedding_id=<?= $wedding_id ?>"
                                        title="<?= htmlspecialchars(
                                            $template_name
                                        ) ?>"
                                        loading="lazy"
                                    ></iframe>


                                    <div
                                        class="preview-overlay"
                                    >

                                        <span>

                                            <i
                                                class="fa-solid fa-eye"
                                            ></i>

                                            Xem trước

                                        </span>

                                    </div>


                                <?php else: ?>


                                    <div
                                        class="preview-overlay"
                                        style="opacity:1;"
                                    >

                                        <span>

                                            <i
                                                class="fa-solid fa-triangle-exclamation"
                                            ></i>

                                            Mẫu chưa được cài đặt

                                        </span>

                                    </div>


                                <?php endif; ?>


                            </div>


                            <!-- TEMPLATE BODY -->

                            <div
                                class="template-body"
                            >


                                <div
                                    class="template-name"
                                >

                                    <?= htmlspecialchars(
                                        $template_name
                                    ) ?>

                                </div>


                                <?php if (
                                    $template_code !== ""
                                ): ?>

                                    <div
                                        class="template-code"
                                    >

                                        Mã mẫu:

                                        <?= htmlspecialchars(
                                            $template_code
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <div
                                    class="template-description"
                                >

                                    <?= htmlspecialchars(
                                        $description !== ""
                                            ? $description
                                            : "Mẫu thiệp cưới đẹp và sang trọng."
                                    ) ?>

                                </div>


                                <!-- STATUS -->

                                <?php if (
                                    $is_current
                                ): ?>


                                    <span
                                        class="status-badge current"
                                    >

                                        <i
                                            class="fa-solid fa-heart"
                                        ></i>

                                        Mẫu đang sử dụng

                                    </span>


                                <?php else: ?>


                                    <span
                                        class="status-badge"
                                    >

                                        <i
                                            class="fa-solid fa-circle-check"
                                        ></i>

                                        Có thể sử dụng

                                    </span>


                                <?php endif; ?>


                                <!-- ACTIONS -->

                                <div
                                    class="template-actions"
                                >


                                    <?php if (
                                        $has_template_file
                                    ): ?>


                                        <a
                                            href="preview_template.php?template_id=<?= $template_id ?>&wedding_id=<?= $wedding_id ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-preview"
                                        >

                                            <i
                                                class="fa-solid fa-eye"
                                            ></i>

                                            Xem mẫu

                                        </a>


                                    <?php else: ?>


                                        <span
                                            class="btn btn-disabled"
                                        >

                                            <i
                                                class="fa-solid fa-ban"
                                            ></i>

                                            Chưa cài đặt

                                        </span>


                                    <?php endif; ?>


                                    <?php if (
                                        $is_current
                                    ): ?>


                                        <span
                                            class="btn btn-current"
                                        >

                                            <i
                                                class="fa-solid fa-check"
                                            ></i>

                                            Đang sử dụng

                                        </span>


                                    <?php elseif (
                                        $has_template_file
                                    ): ?>


                                        <form
                                            method="POST"
                                            action="select_template.php"
                                            style="flex:1;"
                                            onsubmit="return confirm(
                                                'Bạn có chắc muốn chọn mẫu này không?'
                                            );"
                                        >


                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(
                                                    $csrf_token,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="template_id"
                                                value="<?= $template_id ?>"
                                            >


                                            <!-- WEDDING ĐANG QUẢN LÝ -->

                                            <input
                                                type="hidden"
                                                name="wedding_id"
                                                value="<?= $wedding_id ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-select"
                                            >

                                                <i
                                                    class="fa-solid fa-check"
                                                ></i>

                                                Chọn mẫu này

                                            </button>


                                        </form>


                                    <?php else: ?>


                                        <span
                                            class="btn btn-disabled"
                                        >

                                            <i
                                                class="fa-solid fa-ban"
                                            ></i>

                                            Không khả dụng

                                        </span>


                                    <?php endif; ?>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty">

                    <i
                        class="fa-regular fa-images"
                    ></i>


                    <strong>
                        Chưa có mẫu thiệp
                    </strong>


                    <span>
                        Hiện chưa có mẫu thiệp đang hoạt động.
                        Vui lòng quay lại sau.
                    </span>

                </div>


            <?php endif; ?>


        </div>


    </section>

  <footer>


        <div class="footer-brand">

            thyu_nhun Weddings

        </div>


        <div class="footer-tagline">

            WEDDING INVITATION

        </div>


        <!--
        ====================================================================
        THÔNG TIN LIÊN HỆ
        ====================================================================
        -->

        <div class="footer-contact">


            <!--
            THAY SỐ ĐIỆN THOẠI TẠI ĐÂY
            -->

            <a
                href="tel:0900000000"
            >

                <i class="fa-solid fa-phone"></i>

                0968 117 006

            </a>



            <!--
            THAY LINK FACEBOOK TẠI ĐÂY
            -->

            <a
                href="https://facebook.com/"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="fa-brands fa-facebook-f"></i>

                Facebook

            </a>



            <!--
            WEBSITE
            -->

            <a
                href="https://thyu_nhun.id.vn"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="fa-solid fa-globe"></i>

                thyu_nhun.id.vn

            </a>


        </div>



        <div class="footer-copy">

            © <?= date("Y") ?>

            <strong>
                thyu_nhun Weddings
            </strong>

            · Copyright by thyu_nhun.id.vn

        </div>


    </footer>

</main>


</body>

</html>