<?php

session_start();

require_once "../config/database.php";


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


$user_id =
    intval($_SESSION["user_id"]);


$guest_id =
    intval($_GET["id"] ?? $_POST["guest_id"] ?? 0);


$requested_wedding_id =
    intval(
        $_GET["wedding_id"] ??
        $_POST["wedding_id"] ??
        0
    );


if ($guest_id <= 0) {

    die("Khách mời không hợp lệ.");

}


/*
|--------------------------------------------------------------------------
| LẤY KHÁCH + KIỂM TRA QUYỀN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        g.*
     FROM guests g
     INNER JOIN weddings w
        ON g.wedding_id = w.id
     WHERE g.id = ?
       AND w.user_id = ?
     LIMIT 1"
);


if (!$stmt) {

    die(
        "Không thể kiểm tra khách mời:"
    );

}


$stmt->bind_param(
    "ii",
    $guest_id,
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows === 0) {

    die(
        "Không tìm thấy khách mời."
    );

}


$guest =
    $result->fetch_assoc();


$stmt->close();


$wedding_id =
    intval($guest["wedding_id"]);


if (
    $requested_wedding_id > 0 &&
    $requested_wedding_id !== $wedding_id
) {

    die(
        "Yêu cầu không hợp lệ."
    );

}


/*
|--------------------------------------------------------------------------
| XỬ LÝ CẬP NHẬT
|--------------------------------------------------------------------------
*/

$success = "";

$error = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST["csrf_token"]) ||
        empty($_SESSION["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error =
            "Yêu cầu không hợp lệ.";

    } else {


        $guest_name =
            trim(
                $_POST["guest_name"] ?? ""
            );


        $phone =
            trim(
                $_POST["phone"] ?? ""
            );


        $group_name =
            trim(
                $_POST["group_name"] ?? ""
            );


        $relationship =
            trim(
                $_POST["relationship"] ?? ""
            );


        /*
        |--------------------------------------------------------------------------
        | KIỂM TRA TÊN
        |--------------------------------------------------------------------------
        */

        if ($guest_name === "") {

            $error =
                "Vui lòng nhập tên khách mời.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | CẬP NHẬT
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "UPDATE guests g
                 INNER JOIN weddings w
                    ON g.wedding_id = w.id
                 SET
                    g.guest_name = ?,
                    g.phone = ?,
                    g.group_name = ?,
                    g.relationship = ?
                 WHERE
                    g.id = ?
                    AND w.user_id = ?"
            );


            if (!$stmt) {

                $error =
                    "Không thể chuẩn bị cập nhật:";

            } else {


                $stmt->bind_param(
                    "ssssii",
                    $guest_name,
                    $phone,
                    $group_name,
                    $relationship,
                    $guest_id,
                    $user_id
                );


                if (
                    $stmt->execute()
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | CẬP NHẬT DỮ LIỆU HIỂN THỊ
                    |--------------------------------------------------------------------------
                    */

                    $guest["guest_name"] =
                        $guest_name;

                    $guest["phone"] =
                        $phone;

                    $guest["group_name"] =
                        $group_name;

                    $guest["relationship"] =
                        $relationship;


                    /*
                    |--------------------------------------------------------------------------
                    | QUAY VỀ DANH SÁCH
                    |--------------------------------------------------------------------------
                    */

                    header(
                        "Location: guests.php?wedding_id=" .
                        $wedding_id .
                        "&updated=1"
                    );

                    exit;

                } else {

                    $error =
                        "Không thể cập nhật khách mời:";

                }


                $stmt->close();

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["csrf_token"])
) {

    $_SESSION["csrf_token"] =
        bin2hex(
            random_bytes(32)
        );

}


$csrf_token =
    $_SESSION["csrf_token"];


/*
|--------------------------------------------------------------------------
| TÊN CUSTOMER
|--------------------------------------------------------------------------
*/

$customer_name =
    trim(
        $_SESSION["full_name"] ??
        $_SESSION["username"] ??
        "Khách hàng"
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
        Sửa khách mời - Thyu_nhun Weddings
    </title>


    <!-- Google Fonts -->

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
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

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

            --shadow:
                0 5px 25px rgba(48,31,29,.06);

        }


        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }


        body {

            font-family:
                "DM Sans",
                Arial,
                sans-serif;

            background:
                var(--cream);

            color:
                var(--text);

            min-height:
                100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            height:
                70px;

            padding:
                0 32px;

            background:
                rgba(255,255,255,.97);

            border-bottom:
                1px solid
                var(--border);

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

        }


        .brand {

            display:
                flex;

            align-items:
                center;

            gap:
                11px;

        }


        .brand-icon {

            width:
                36px;

            height:
                36px;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                linear-gradient(
                    135deg,
                    #c79583,
                    #B52A3A
                );

            color:
                white;

            font-size:
                13px;

        }


        .brand-name {

            font-family:
                "Cormorant Garamond",
                serif;

            font-size:
                17px;

            font-weight:
                600;

            color:
                #32191C;

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

            display:
                block;

            margin-top:
                2px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        .avatar {

            width:
                38px;

            height:
                38px;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    #EBCFD0,
                    #D95B64
                );

            font-size:
                13px;

        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .content {

            max-width:
                900px;

            margin:
                0 auto;

            padding:
                30px 32px 50px;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADING
        |--------------------------------------------------------------------------
        */

        .page-heading {

            display:
                flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                22px;

        }


        .heading-left h1 {

            font-family:
                "Cormorant Garamond",
                serif;

            font-size:
                29px;

            font-weight:
                600;

            color:
                #32191C;

            margin-bottom:
                7px;

        }


        .heading-left p {

            color:
                var(--muted);

            font-size:
                11px;

            line-height:
                1.6;

        }


        /*
        |--------------------------------------------------------------------------
        | BACK
        |--------------------------------------------------------------------------
        */

        .back-btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                9px 14px;

            background:
                white;

            color:
                var(--wine);

            border:
                1px solid
                #F0D8D6;

            border-radius:
                7px;

            font-size:
                10px;

            font-weight:
                600;

            text-decoration:
                none;

            transition:
                .2s ease;

            white-space:
                nowrap;

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
        | FORM CARD
        |--------------------------------------------------------------------------
        */

        .form-card {

            background:
                white;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            padding:
                24px;

            box-shadow:
                var(--shadow);

        }


        .card-heading {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            padding-bottom:
                18px;

            margin-bottom:
                20px;

            border-bottom:
                1px solid
                #FCEFED;

        }


        .card-icon {

            width:
                40px;

            height:
                40px;

            border-radius:
                10px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #FCE8E7;

            color:
                var(--wine);

            font-size:
                14px;

        }


        .card-heading h2 {

            font-family:
                "Cormorant Garamond",
                serif;

            font-size:
                19px;

            font-weight:
                600;

            color:
                #32191C;

        }


        .card-heading p {

            margin-top:
                3px;

            color:
                var(--muted);

            font-size:
                9px;

        }


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            padding:
                12px 14px;

            margin-bottom:
                18px;

            border-radius:
                8px;

            background:
                var(--red-bg);

            border:
                1px solid
                #F5D5D7;

            color:
                #C93B4A;

            font-size:
                10px;

            line-height:
                1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-grid {

            display:
                grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap:
                17px;

        }


        .form-group {

            display:
                flex;

            flex-direction:
                column;

        }


        .form-group.full {

            grid-column:
                1 / -1;

        }


        .form-group label {

            margin-bottom:
                7px;

            color:
                #4c4442;

            font-size:
                10px;

            font-weight:
                600;

        }


        .required {

            color:
                var(--wine);

        }


        .input-wrap {

            position:
                relative;

        }


        .input-icon {

            position:
                absolute;

            top:
                50%;

            left:
                12px;

            transform:
                translateY(-50%);

            color:
                #b0a6a2;

            font-size:
                11px;

            pointer-events:
                none;

        }


        .form-control {

            width:
                100%;

            height:
                43px;

            padding:
                0 12px 0 34px;

            border:
                1px solid
                #e4dbd7;

            border-radius:
                7px;

            background:
                #fff;

            color:
                #393332;

            font-size:
                11px;

            transition:
                .2s ease;

        }


        .form-control:focus {

            outline:
                none;

            border-color:
                var(--wine);

            box-shadow:
                0 0 0 3px
                rgba(150,80,80,.08);

        }


        .form-control::placeholder {

            color:
                #b5aeab;

        }


        .field-note {

            margin-top:
                5px;

            color:
                #B9A7A919e;

            font-size:
                8px;

        }


        /*
        |--------------------------------------------------------------------------
        | ACTIONS
        |--------------------------------------------------------------------------
        */

        .form-actions {

            display:
                flex;

            align-items:
                center;

            justify-content:
                flex-end;

            gap:
                9px;

            padding-top:
                22px;

            margin-top:
                21px;

            border-top:
                1px solid
                #FCEFED;

        }


        .btn {

            min-height:
                39px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                7px;

            padding:
                0 15px;

            border:
                0;

            border-radius:
                7px;

            font-size:
                10px;

            font-weight:
                600;

            cursor:
                pointer;

            text-decoration:
                none;

            transition:
                .2s ease;

        }


        .btn-cancel {

            background:
                #f2efed;

            color:
                #665d5b;

        }


        .btn-cancel:hover {

            background:
                #e7e0dc;

        }


        .btn-save {

            background:
                var(--wine);

            color:
                white;

        }


        .btn-save:hover {

            background:
                var(--wine-dark);

            transform:
                translateY(-1px);

        }


        /*
        |--------------------------------------------------------------------------
        | NOTE
        |--------------------------------------------------------------------------
        */

        .info-box {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                9px;

            margin-top:
                18px;

            padding:
                12px 14px;

            border-radius:
                8px;

            background:
                #faf6ef;

            border:
                1px solid
                #eee1ce;

            color:
                #816d52;

            font-size:
                9px;

            line-height:
                1.6;

        }


        .info-box i {

            margin-top:
                2px;

            color:
                var(--gold);

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 650px) {

            .topbar {

                height:
                    62px;

                padding:
                    0 15px;

            }


            .brand-name {

                font-size:
                    14px;

            }


            .brand-icon {

                width:
                    32px;

                height:
                    32px;

            }


            .user-text {

                display:
                    none;

            }


            .content {

                padding:
                    22px 15px 35px;

            }


            .page-heading {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap:
                    13px;

            }


            .back-btn {

                width:
                    100%;

            }


            .form-card {

                padding:
                    17px;

            }


            .form-grid {

                grid-template-columns:
                    1fr;

                gap:
                    15px;

            }


            .form-group.full {

                grid-column:
                    auto;

            }


            .form-actions {

                flex-direction:
                    column-reverse;

            }


            .form-actions .btn {

                width:
                    100%;

            }

        }

    
        /* ==========================================================
           THYU_NHUN WEDDINGS — CUSTOMER EDIT GUEST MASTER STYLE
           Đồng bộ Dashboard / Settings. Trang này không có sidebar.
        ========================================================== */

        :root {
            --burgundy: #8F1D2C;
            --burgundy-dark: #65131F;
            --beige: #FFF8F6;
            --soft-beige: #FFF7F6;
            --luxury-border: #ECD8D5;
            --title: #32191C;
        }

        body {
            font-family: "DM Sans", Arial, sans-serif !important;
            background: var(--beige) !important;
            color: #6F5B5E !important;
        }

        .topbar {
            position: sticky !important;
            top: 0 !important;
            z-index: 200 !important;
            min-height: 74px !important;
            height: auto !important;
            padding: 14px 5vw !important;
            background: rgba(255,255,255,.94) !important;
            border-bottom: 1px solid var(--luxury-border) !important;
            box-shadow: none !important;
            backdrop-filter: blur(14px);
        }

        .brand-icon {
            background: linear-gradient(135deg, var(--burgundy), var(--burgundy-dark)) !important;
        }

        .brand-name,
        .heading-left h1,
        .card-heading h2 {
            font-family: "Cormorant Garamond", Georgia, serif !important;
            color: var(--title) !important;
        }

        .brand-name {
            font-size: 21px !important;
        }

        .content {
            width: min(900px, calc(100% - 32px)) !important;
            max-width: none !important;
            margin: 0 auto !important;
            padding: 42px 0 52px !important;
        }

        .page-heading {
            margin-bottom: 28px !important;
        }

        .heading-left h1 {
            font-size: clamp(32px, 4vw, 42px) !important;
            line-height: 1 !important;
            margin-bottom: 9px !important;
        }

        .heading-left p {
            font-size: 14px !important;
            color: #7E6669 !important;
        }

        .back-btn {
            border-radius: 12px !important;
            padding: 11px 16px !important;
            background: var(--soft-beige) !important;
            color: var(--burgundy-dark) !important;
            border: 1px solid var(--luxury-border) !important;
            font-size: 13px !important;
        }

        .back-btn:hover {
            background: var(--burgundy-dark) !important;
            color: #fff !important;
            transform: translateX(-2px);
        }

        .form-card {
            background: #fff !important;
            border: 1px solid var(--luxury-border) !important;
            border-radius: 20px !important;
            padding: 30px !important;
            box-shadow: 0 10px 35px rgba(61,48,48,.05) !important;
        }

        .card-heading {
            padding-bottom: 22px !important;
            margin-bottom: 25px !important;
            border-bottom-color: var(--luxury-border) !important;
        }

        .card-icon {
            width: 46px !important;
            height: 46px !important;
            border-radius: 14px !important;
            background: #FCE8E7 !important;
            color: var(--burgundy) !important;
        }

        .card-heading h2 {
            font-size: 28px !important;
        }

        .card-heading p,
        .field-note {
            color: #7E6669 !important;
        }

        .form-group label {
            font-size: 13px !important;
            color: #6F5B5E !important;
            font-weight: 600 !important;
        }

        .form-control {
            height: 48px !important;
            border-radius: 12px !important;
            border-color: var(--luxury-border) !important;
            background: #FFFCFB !important;
            font-size: 14px !important;
        }

        .form-control:focus {
            border-color: var(--burgundy) !important;
            box-shadow: 0 0 0 4px rgba(143,29,44,.10) !important;
        }

        .input-icon {
            color: #a18f8c !important;
            font-size: 13px !important;
        }

        .info-box {
            margin-top: 24px !important;
            border-radius: 14px !important;
            background: #fffaf4 !important;
            border-color: #eee1ce !important;
            font-size: 13px !important;
        }

        .form-actions {
            margin-top: 28px !important;
        }

        .btn {
            min-height: 46px !important;
            border-radius: 12px !important;
            font-size: 13px !important;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--burgundy), var(--burgundy-dark)) !important;
            box-shadow: 0 10px 22px rgba(143,29,44,.14) !important;
        }

        .btn-cancel {
            background: var(--soft-beige) !important;
            color: var(--burgundy-dark) !important;
            border-color: var(--luxury-border) !important;
        }

        @media (max-width: 650px) {
            .topbar {
                padding: 12px 16px !important;
            }

            .content {
                width: min(100% - 24px, 900px) !important;
                padding: 28px 0 38px !important;
            }

            .form-card {
                padding: 20px !important;
                border-radius: 16px !important;
            }

            .page-heading {
                align-items: stretch !important;
            }

            .back-btn {
                width: 100% !important;
            }

            .card-heading h2 {
                font-size: 25px !important;
            }
        }

    
        .customer-footer {
            width: min(900px, calc(100% - 32px));
            margin: 0 auto;
            padding: 0 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            color: #9a8985;
            font-size: 12px;
        }

        .footer-contact {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .customer-footer a {
            color: #8F1D2C;
            text-decoration: none;
        }

        .customer-footer a:hover {
            color: #65131F;
        }

        @media (max-width: 650px) {
            .customer-footer {
                width: min(100% - 24px, 900px);
                padding-bottom: 24px;
                flex-direction: column;
                align-items: flex-start;
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

</style>

</head>


<body>


<!-- =========================================================
     TOPBAR
========================================================== -->

<header class="topbar">


    <div class="brand">

        <div class="brand-icon"><svg class="brand-logo-svg" viewBox="0 0 64 64" aria-hidden="true">
<path d="M17 19c-5 0-9 4-9 9 0 11 12.5 19 24 28 11.5-9 24-17 24-28 0-5-4-9-9-9-6 0-11 4.5-15 10-4-5.5-9-10-15-10Z"/>
<path d="M22 18c4 1 7.2 4.1 10 8.2 2.8-4.1 6-7.2 10-8.2"/>
<circle cx="32" cy="10.5" r="1.6" fill="currentColor" stroke="none"/>
</svg></div>


        <div class="brand-name">

            Thyu_nhun Weddings

        </div>

    </div>


    <div class="user-area">


        <div class="user-text">

            <strong>

                Xin chào,

                <?= htmlspecialchars(
                    $customer_name
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

<main class="content">


    <!-- HEADING -->

    <div class="page-heading">


        <div class="heading-left">

            <h1>
                Sửa khách mời
            </h1>

            <p>
                Cập nhật thông tin của khách mời trong danh sách.
            </p>

        </div>


        <a
            href="guests.php?wedding_id=<?= $wedding_id ?>"
            class="back-btn"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Danh sách khách

        </a>


    </div>


    <!-- =====================================================
         FORM CARD
    ====================================================== -->

    <section class="form-card">


        <div class="card-heading">


            <div class="card-icon">

                <i
                    class="fa-solid fa-user-pen"
                ></i>

            </div>


            <div>

                <h2>
                    Thông tin khách mời
                </h2>

                <p>
                    Chỉnh sửa thông tin bên dưới rồi lưu thay đổi.
                </p>

            </div>


        </div>


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="error">

                <i
                    class="fa-solid fa-circle-exclamation"
                ></i>

                <span>

                    <?= htmlspecialchars(
                        $error
                    ) ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <form
            method="POST"
            autocomplete="off"
        >


            <input
                type="hidden"
                name="guest_id"
                value="<?= $guest_id ?>"
            >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token
                ) ?>"
            >

            <input
                type="hidden"
                name="wedding_id"
                value="<?= $wedding_id ?>"
            >


            <div class="form-grid">


                <!-- TÊN -->

                <div class="form-group full">


                    <label>

                        Tên khách mời

                        <span class="required">
                            *
                        </span>

                    </label>


                    <div class="input-wrap">

                        <i
                            class="fa-solid fa-user input-icon"
                        ></i>


                        <input
                            type="text"
                            name="guest_name"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $guest["guest_name"] ?? ""
                            ) ?>"
                            placeholder="Ví dụ: anh Văn"
                            maxlength="255"
                            required
                        >

                    </div>


                    <span class="field-note">

                        Đây là tên sẽ được hiển thị trong danh sách khách mời.

                    </span>


                </div>


                <!-- SỐ ĐIỆN THOẠI -->

                <div class="form-group">


                    <label>
                        Số điện thoại
                    </label>


                    <div class="input-wrap">

                        <i
                            class="fa-solid fa-phone input-icon"
                        ></i>


                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $guest["phone"] ?? ""
                            ) ?>"
                            placeholder="Nhập số điện thoại"
                            maxlength="30"
                        >

                    </div>


                </div>


                <!-- NHÓM -->

                <div class="form-group">


                    <label>
                        Nhóm khách
                    </label>


                    <div class="input-wrap">

                        <i
                            class="fa-solid fa-users input-icon"
                        ></i>


                        <input
                            type="text"
                            name="group_name"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $guest["group_name"] ?? ""
                            ) ?>"
                            placeholder="Ví dụ: Nhà trai"
                            maxlength="100"
                        >

                    </div>


                </div>


                <!-- QUAN HỆ -->

                <div class="form-group full">


                    <label>
                        Mối quan hệ
                    </label>


                    <div class="input-wrap">

                        <i
                            class="fa-solid fa-heart input-icon"
                        ></i>


                        <input
                            type="text"
                            name="relationship"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $guest["relationship"] ?? ""
                            ) ?>"
                            placeholder="Ví dụ: Bạn thân, đồng nghiệp..."
                            maxlength="100"
                        >

                    </div>


                </div>


            </div>


            <!-- INFO -->

            <div class="info-box">

                <i
                    class="fa-solid fa-circle-info"
                ></i>

                <span>

                    Việc chỉnh sửa thông tin khách mời
                    <strong>không làm thay đổi link thiệp riêng</strong>
                    đã được tạo cho khách này.

                </span>

            </div>


            <!-- ACTION -->

            <div class="form-actions">


                <a
                    href="guests.php?wedding_id=<?= $wedding_id ?>"
                    class="btn btn-cancel"
                >

                    <i
                        class="fa-solid fa-xmark"
                    ></i>

                    Hủy

                </a>


                <button
                    type="submit"
                    class="btn btn-save"
                >

                    <i
                        class="fa-solid fa-floppy-disk"
                    ></i>

                    Lưu thay đổi

                </button>


            </div>


        </form>


    </section>


</main>



<footer class="customer-footer">
    <div>
        © <?= date("Y") ?> thyu_nhun Weddings · by thyu_nhun.id.vn
    </div>

    <div class="footer-contact">
        <a href="tel:0968117006">
            <i class="fa-solid fa-phone"></i>
            0968117006
        </a>

        <a
            href="https://www.facebook.com/nhung.nnthuy/"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="fa-brands fa-facebook-f"></i>
            Facebook
        </a>
    </div>
</footer>


</body>

</html>