<?php

/*
|--------------------------------------------------------------------------
| THYU_NHUN WEDDINGS
| CUSTOMER - THÔNG TIN THIỆP
|--------------------------------------------------------------------------
|
| FILE: customer/wedding_info.php
|
| CHỨC NĂNG:
| - Hiển thị thông tin đám cưới
| - Chỉnh sửa tên cô dâu / chú rể
| - Chỉnh sửa ngày / giờ
| - Chỉnh sửa địa điểm / địa chỉ
| - Nhập link Google Maps
| - Chỉnh sửa lời giới thiệu
|
| LƯU Ý:
| Phần giao diện được tách rõ với phần PHP xử lý dữ liệu.
| Sau này muốn đổi concept website có thể chỉnh phần:
|
|        THEME CONFIG
|
| mà không cần sửa logic database.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| SESSION + DATABASE
|--------------------------------------------------------------------------
*/

session_start();

require_once "../config/database.php";
require_once "../config/url_helper.php";


/*
|--------------------------------------------------------------------------
| CACHE CONTROL
|--------------------------------------------------------------------------
|
| Tránh trình duyệt giữ lại giao diện cũ sau khi chỉnh sửa.
|--------------------------------------------------------------------------
*/

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header("Pragma: no-cache");

header("Expires: 0");


/*
|--------------------------------------------------------------------------
| KIỂM TRA ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {

    header(
        "Location: ../auth/login.php"
    );

    exit;
}


$user_id = intval(
    $_SESSION["user_id"]
);


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
| LẤY WEDDING ID
|--------------------------------------------------------------------------
|
| Trang này nhận:
|
| wedding_info.php?id=1
|
|--------------------------------------------------------------------------
*/

$wedding_id = isset($_GET["id"])
    ? intval($_GET["id"])
    : 0;


if ($wedding_id <= 0) {

    die(
        "Đám cưới không hợp lệ."
    );
}


/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN WEDDING
|--------------------------------------------------------------------------
|
| QUAN TRỌNG:
|
| Chỉ cho phép customer lấy wedding thuộc chính tài khoản của mình.
|
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT *
     FROM weddings
     WHERE id = ?
       AND user_id = ?
     LIMIT 1"
);


if (!$stmt) {

    die(
        "Không thể chuẩn bị truy vấn."
    );
}


$stmt->bind_param(
    "ii",
    $wedding_id,
    $user_id
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows !== 1) {

    $stmt->close();

    die(
        "Không tìm thấy đám cưới."
    );
}


$wedding =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| BIẾN THÔNG BÁO
|--------------------------------------------------------------------------
*/

$success = "";

$error = "";


/*
|--------------------------------------------------------------------------
| XỬ LÝ FORM CẬP NHẬT
|--------------------------------------------------------------------------
|
| Khi người dùng bấm:
|
|        LƯU THÔNG TIN
|
| dữ liệu sẽ được cập nhật vào bảng weddings.
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_POST["csrf_token"]) ||
        !is_string($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error =
            "Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.";

    } else {

    /*
    |--------------------------------------------------------------------------
    | LẤY DỮ LIỆU FORM
    |--------------------------------------------------------------------------
    */

    $groom_name =
        trim(
            $_POST["groom_name"] ?? ""
        );


    $bride_name =
        trim(
            $_POST["bride_name"] ?? ""
        );


    $wedding_date =
        trim(
            $_POST["wedding_date"] ?? ""
        );


    $wedding_time =
        trim(
            $_POST["wedding_time"] ?? ""
        );


    $venue =
        trim(
            $_POST["venue"] ?? ""
        );


    $address =
        trim(
            $_POST["address"] ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | GOOGLE MAPS
    |--------------------------------------------------------------------------
    |
    | Đây là trường mới.
    |
    | Database:
    |
    | weddings.google_maps_url
    |
    |
    | Nếu để trống:
    | -> Thiệp không bắt buộc phải hiển thị Google Maps.
    |
    | Nếu có link:
    | -> Template nào hỗ trợ Google Maps có thể sử dụng biến:
    |
    | $wedding["google_maps_url"]
    |
    |--------------------------------------------------------------------------
    */

    $google_maps_url =
        trim(
            $_POST["google_maps_url"] ?? ""
        );


    $description =
        trim(
            $_POST["description"] ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA TÊN
    |--------------------------------------------------------------------------
    */

    if (
        $groom_name === "" ||
        $bride_name === ""
    ) {

        $error =
            "Vui lòng nhập tên cô dâu và chú rể.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | KIỂM TRA GOOGLE MAPS
        |--------------------------------------------------------------------------
        |
        | Nếu người dùng nhập link thì phải bắt đầu bằng:
        |
        | http://
        | hoặc
        | https://
        |
        |--------------------------------------------------------------------------
        */

        if (
            $google_maps_url !== "" &&
            !filter_var(
                $google_maps_url,
                FILTER_VALIDATE_URL
            )
        ) {

            $error =
                "Link Google Maps không hợp lệ. Vui lòng nhập đầy đủ đường dẫn.";

        } else {


            $wedding_slug = ensure_unique_wedding_slug(
                $conn,
                $wedding_id,
                $groom_name,
                $bride_name
            );


            /*
            |--------------------------------------------------------------------------
            | UPDATE DATABASE
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "UPDATE weddings
                 SET
                    groom_name = ?,
                    bride_name = ?,
                    wedding_date = NULLIF(?, ''),
                    wedding_time = NULLIF(?, ''),
                    venue = ?,
                    address = ?,
                    google_maps_url = NULLIF(?, ''),
                    description = ?,
                    wedding_slug = ?
                 WHERE id = ?
                   AND user_id = ?"
            );


            if (!$stmt) {

                $error =
                    "Không thể chuẩn bị cập nhật: " .
                    $conn->error;

            } else {


                $stmt->bind_param(
                    "sssssssssii",
                    $groom_name,
                    $bride_name,
                    $wedding_date,
                    $wedding_time,
                    $venue,
                    $address,
                    $google_maps_url,
                    $description,
                    $wedding_slug,
                    $wedding_id,
                    $user_id
                );


                /*
                |--------------------------------------------------------------------------
                | THỰC HIỆN UPDATE
                |--------------------------------------------------------------------------
                */

                if (
                    $stmt->execute()
                ) {

                    $success =
                        "Đã lưu thông tin đám cưới.";

                    /*
                    |--------------------------------------------------------------------------
                    | CẬP NHẬT DỮ LIỆU HIỂN THỊ NGAY
                    |--------------------------------------------------------------------------
                    */

                    $wedding["groom_name"] =
                        $groom_name;

                    $wedding["bride_name"] =
                        $bride_name;

                    $wedding["wedding_date"] =
                        $wedding_date;

                    $wedding["wedding_time"] =
                        $wedding_time;

                    $wedding["venue"] =
                        $venue;

                    $wedding["address"] =
                        $address;

                    $wedding["google_maps_url"] =
                        $google_maps_url;

                    $wedding["description"] =
                        $description;

                    $wedding["wedding_slug"] =
                        $wedding_slug;

                } else {

                    $error =
                        "Có lỗi xảy ra khi lưu thông tin: " .
                        $stmt->error;
                }


                $stmt->close();
            }
        }
    }
    }
}


/*
|--------------------------------------------------------------------------
| XỬ LÝ DỮ LIỆU HIỂN THỊ
|--------------------------------------------------------------------------
*/

$full_name =
    $_SESSION["full_name"] ??
    "Khách hàng";


/*
|--------------------------------------------------------------------------
| LẤY CHỮ CÁI ĐẦU CHO AVATAR
|--------------------------------------------------------------------------
*/

$avatar_letter =
    mb_substr(
        $full_name,
        0,
        1,
        "UTF-8"
    );


$avatar_letter =
    mb_strtoupper(
        $avatar_letter,
        "UTF-8"
    );


/*
|--------------------------------------------------------------------------
| FORMAT NGÀY
|--------------------------------------------------------------------------
|
| Database:
| 2026-10-21
|
| Hiển thị:
| 21/10/2026
|--------------------------------------------------------------------------
*/

$display_date = "";

if (
    !empty(
        $wedding["wedding_date"]
    )
) {

    $timestamp =
        strtotime(
            $wedding["wedding_date"]
        );

    if ($timestamp !== false) {

        $display_date =
            date(
                "d/m/Y",
                $timestamp
            );
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT GIỜ
|--------------------------------------------------------------------------
*/

$display_time = "";

if (
    !empty(
        $wedding["wedding_time"]
    )
) {

    $display_time =
        substr(
            $wedding["wedding_time"],
            0,
            5
        );
}

?>


<!DOCTYPE html>

<html
    lang="vi"
>

<head>

    <meta
        charset="UTF-8"
    >


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >


    <meta
        name="theme-color"
        content="#5c3034"
    >


    <title>
        Thông tin thiệp - thyu_nhun Weddings
    </title>


    <!--
    ========================================================================
    FONT
    ========================================================================
    -->

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
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!--
    ========================================================================
    ICON
    ========================================================================
    -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>


        /*
        ====================================================================
        THEME CONFIG
        ====================================================================

        ĐÂY LÀ KHU VỰC QUAN TRỌNG NHẤT CỦA FILE.

        Nếu sau này muốn đổi concept website:

        Ví dụ:

        Concept hiện tại:
        - Burgundy
        - Kem
        - Nâu
        - Sang trọng
        - Wedding luxury

        Chỉ cần thay các biến bên dưới.

        KHÔNG CẦN tìm và sửa từng màu trong toàn bộ file.

        ====================================================================
        */


        :root {

            /* MÀU NỀN CHÍNH */

            --theme-bg:
                #f7f2ee;


            /* MÀU TRẮNG */

            --theme-white:
                #ffffff;


            /* MÀU BURGUNDY CHÍNH */

            --theme-primary:
                #7f4548;


            /* MÀU BURGUNDY ĐẬM */

            --theme-primary-dark:
                #5c3034;


            /* MÀU BURGUNDY NHẠT */

            --theme-primary-soft:
                #f1e4e1;


            /* MÀU VÀNG / GOLD */

            --theme-gold:
                #b28a62;


            /* MÀU CHỮ CHÍNH */

            --theme-text:
                #34282a;


            /* MÀU CHỮ PHỤ */

            --theme-muted:
                #8b7b7b;


            /* MÀU VIỀN */

            --theme-border:
                #e8dcd7;


            /* MÀU SUCCESS */

            --theme-success:
                #557963;


            /* FONT SANG TRỌNG */

            --font-display:
                "Cormorant Garamond",
                Georgia,
                serif;


            /* FONT GIAO DIỆN */

            --font-ui:
                "DM Sans",
                Arial,
                sans-serif;


            /* BO GÓC */

            --radius-lg:
                20px;

            --radius-md:
                12px;

            --radius-sm:
                9px;


            /* SHADOW */

            --shadow-soft:
                0 15px 40px rgba(
                    65,
                    39,
                    37,
                    .07
                );


            /* TRANSITION */

            --transition:
                .25s ease;

        }


        /*
        ====================================================================
        RESET
        ====================================================================
        */

        * {

            box-sizing:
                border-box;

            margin:
                0;

            padding:
                0;

        }


        /*
        ====================================================================
        BODY
        ====================================================================
        */

        body {

            min-height:
                100vh;

            background:
                var(--theme-bg);

            color:
                var(--theme-text);

            font-family:
                var(--font-ui);

        }


        /*
        ====================================================================
        MAIN
        ====================================================================
        */

        .page {

            min-height:
                100vh;

            display:
                flex;

            flex-direction:
                column;

        }


        /*
        ====================================================================
        TOPBAR
        ====================================================================
        */

        .topbar {

            height:
                78px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .96
                );

            border-bottom:
                1px solid
                var(--theme-border);

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 38px;

            position:
                sticky;

            top:
                0;

            z-index:
                20;

            backdrop-filter:
                blur(12px);

        }


        /*
        ====================================================================
        BRAND
        ====================================================================
        */

        .brand {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            color:
                var(--theme-primary-dark);

        }


        .brand-icon {

            width:
                42px;

            height:
                42px;

            border-radius:
                50%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                var(--theme-primary-soft);

            color:
                var(--theme-primary);

            font-size:
                17px;

        }


        .brand-name {

            display:
                flex;

            flex-direction:
                column;

            line-height:
                1;

        }


        .brand-name strong {

            font-family:
                var(--font-display);

            font-size:
                25px;

            font-weight:
                600;

            letter-spacing:
                .3px;

        }


        .brand-name span {

            margin-top:
                5px;

            font-size:
                8px;

            letter-spacing:
                2.5px;

            color:
                var(--theme-muted);

        }


        /*
        ====================================================================
        USER
        ====================================================================
        */

        .user-area {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

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

            color:
                var(--theme-text);

        }


        .user-info span {

            display:
                block;

            margin-top:
                3px;

            font-size:
                10px;

            color:
                var(--theme-muted);

        }


        .avatar {

            width:
                40px;

            height:
                40px;

            border-radius:
                50%;

            background:
                var(--theme-primary-soft);

            color:
                var(--theme-primary-dark);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-weight:
                700;

            font-size:
                13px;

        }


        /*
        ====================================================================
        CONTENT
        ====================================================================
        */

        .content {

            width:
                100%;

            max-width:
                1050px;

            margin:
                0 auto;

            padding:
                38px 28px 55px;

            flex:
                1;

        }


        /*
        ====================================================================
        BACK HOME
        ====================================================================
        */

        .back-home {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                10px 15px;

            border:
                1px solid
                var(--theme-border);

            border-radius:
                var(--radius-sm);

            background:
                var(--theme-white);

            color:
                var(--theme-primary-dark);

            text-decoration:
                none;

            font-size:
                12px;

            font-weight:
                600;

            transition:
                var(--transition);

            box-shadow:
                0 5px 15px
                rgba(
                    70,
                    45,
                    40,
                    .03
                );

        }


        .back-home:hover {

            transform:
                translateX(-3px);

            border-color:
                var(--theme-primary);

            color:
                var(--theme-primary);

        }


        /*
        ====================================================================
        PAGE HEADING
        ====================================================================
        */

        .heading {

            margin:
                25px 0;

        }


        .eyebrow {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            color:
                var(--theme-primary);

            font-size:
                10px;

            font-weight:
                700;

            letter-spacing:
                2px;

            text-transform:
                uppercase;

            margin-bottom:
                7px;

        }


        .eyebrow::before {

            content:
                "";

            width:
                25px;

            height:
                1px;

            background:
                var(--theme-gold);

        }


        .heading h1 {

            font-family:
                var(--font-display);

            font-size:
                clamp(
                    36px,
                    5vw,
                    52px
                );

            font-weight:
                500;

            line-height:
                1;

            color:
                var(--theme-text);

        }


        .heading p {

            margin-top:
                10px;

            color:
                var(--theme-muted);

            font-size:
                13px;

            line-height:
                1.7;

        }


        /*
        ====================================================================
        FORM CARD
        ====================================================================
        */

        .form-card {

            background:
                var(--theme-white);

            border:
                1px solid
                var(--theme-border);

            border-radius:
                var(--radius-lg);

            padding:
                32px;

            box-shadow:
                var(--shadow-soft);

        }


        /*
        ====================================================================
        SECTION
        ====================================================================
        */

        .form-section {

            margin-bottom:
                32px;

        }


        .form-section:last-child {

            margin-bottom:
                0;

        }


        .section-heading {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            padding-bottom:
                15px;

            margin-bottom:
                20px;

            border-bottom:
                1px solid
                var(--theme-border);

        }


        .section-icon {

            width:
                38px;

            height:
                38px;

            flex-shrink:
                0;

            border-radius:
                11px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                var(--theme-primary-soft);

            color:
                var(--theme-primary);

        }


        .section-heading h2 {

            font-family:
                var(--font-display);

            font-size:
                23px;

            font-weight:
                600;

            color:
                var(--theme-text);

        }


        .section-heading p {

            margin-top:
                2px;

            color:
                var(--theme-muted);

            font-size:
                10px;

        }


        /*
        ====================================================================
        FORM GRID
        ====================================================================
        */

        .form-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                18px;

        }


        .form-group {

            margin-bottom:
                17px;

        }


        .form-grid .form-group {

            margin-bottom:
                0;

        }


        .form-group.full {

            grid-column:
                1 / -1;

        }


        /*
        ====================================================================
        LABEL
        ====================================================================
        */

        label {

            display:
                block;

            margin-bottom:
                7px;

            color:
                var(--theme-text);

            font-size:
                11px;

            font-weight:
                700;

        }


        .required {

            color:
                var(--theme-primary);

        }


        /*
        ====================================================================
        INPUT
        ====================================================================
        */

        input,
        textarea {

            width:
                100%;

            border:
                1px solid
                var(--theme-border);

            border-radius:
                var(--radius-sm);

            background:
                #fffdfc;

            color:
                var(--theme-text);

            padding:
                13px 14px;

            font-family:
                var(--font-ui);

            font-size:
                13px;

            outline:
                none;

            transition:
                var(--transition);

        }


        input:hover,
        textarea:hover {

            border-color:
                #d9c7c1;

        }


        input:focus,
        textarea:focus {

            border-color:
                var(--theme-primary);

            background:
                var(--theme-white);

            box-shadow:
                0 0 0 4px
                rgba(
                    127,
                    69,
                    72,
                    .08
                );

        }


        textarea {

            min-height:
                145px;

            resize:
                vertical;

            line-height:
                1.7;

        }


        /*
        ====================================================================
        INPUT HINT
        ====================================================================
        */

        .input-hint {

            margin-top:
                6px;

            color:
                var(--theme-muted);

            font-size:
                10px;

            line-height:
                1.5;

        }


        /*
        ====================================================================
        GOOGLE MAPS SPECIAL BOX
        ====================================================================
        */

        .maps-box {

            padding:
                18px;

            border:
                1px dashed
                #d8c4bc;

            border-radius:
                var(--radius-md);

            background:
                #fcf8f6;

        }


        .maps-label {

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

            margin-bottom:
                10px;

            color:
                var(--theme-primary-dark);

            font-size:
                12px;

            font-weight:
                700;

        }


        .maps-label i {

            color:
                var(--theme-primary);

        }


        /*
        ====================================================================
        MAP LINK PREVIEW
        ====================================================================
        */

        .map-preview {

            margin-top:
                10px;

        }


        .map-preview a {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            color:
                var(--theme-primary);

            font-size:
                11px;

            font-weight:
                600;

            text-decoration:
                none;

        }


        .map-preview a:hover {

            text-decoration:
                underline;

        }


        /*
        ====================================================================
        MESSAGE
        ====================================================================
        */

        .message {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            padding:
                12px 14px;

            border-radius:
                var(--radius-sm);

            margin-bottom:
                22px;

            font-size:
                11px;

        }


        .message.success {

            background:
                #edf6ef;

            border:
                1px solid
                #d7e9da;

            color:
                var(--theme-success);

        }


        .message.error {

            background:
                #fff0ee;

            border:
                1px solid
                #efd6d1;

            color:
                #9b5550;

        }


        /*
        ====================================================================
        ACTION AREA
        ====================================================================
        */

        .actions {

            display:
                flex;

            align-items:
                center;

            justify-content:
                flex-end;

            gap:
                10px;

            padding-top:
                8px;

            border-top:
                1px solid
                var(--theme-border);

        }


        /*
        ====================================================================
        SAVE BUTTON
        ====================================================================
        */

        .btn-save {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            min-width:
                160px;

            padding:
                13px 22px;

            border:
                none;

            border-radius:
                var(--radius-sm);

            background:
                var(--theme-primary);

            color:
                white;

            font-family:
                var(--font-ui);

            font-size:
                12px;

            font-weight:
                700;

            cursor:
                pointer;

            transition:
                var(--transition);

        }


        .btn-save:hover {

            background:
                var(--theme-primary-dark);

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(
                    92,
                    48,
                    52,
                    .18
                );

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

            background:
                var(--theme-white);

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


        /*
        ====================================================================
        RESPONSIVE TABLET
        ====================================================================
        */

        @media (
            max-width: 800px
        ) {

            .topbar {

                padding:
                    0 20px;

            }


            .content {

                padding:
                    30px 20px 45px;

            }


            .form-card {

                padding:
                    25px;

            }

        }


        /*
        ====================================================================
        RESPONSIVE MOBILE
        ====================================================================
        */

        @media (
            max-width: 600px
        ) {

            .topbar {

                height:
                    70px;

                padding:
                    0 15px;

            }


            .brand-name strong {

                font-size:
                    21px;

            }


            .brand-name span {

                font-size:
                    7px;

            }


            .brand-icon {

                width:
                    37px;

                height:
                    37px;

            }


            .user-info {

                display:
                    none;

            }


            .avatar {

                width:
                    36px;

                height:
                    36px;

            }


            .content {

                padding:
                    24px 14px 40px;

            }


            .heading {

                margin:
                    22px 0;

            }


            .heading h1 {

                font-size:
                    38px;

            }


            .form-card {

                padding:
                    20px 16px;

                border-radius:
                    16px;

            }


            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    auto;

            }


            .section-heading h2 {

                font-size:
                    21px;

            }


            .actions {

                justify-content:
                    stretch;

            }


            .btn-save {

                width:
                    100%;

            }


            footer {

                padding:
                    28px 15px;

            }

        }


    
        /* ==========================================================
           THYU_NHUN WEDDINGS - CUSTOMER DETAIL PAGE OVERRIDE
           Quy tắc: wedding_info.php không có sidebar.
           Header gọn + nội dung trung tâm + footer đồng bộ.
        ========================================================== */

        body {
            background: #f7f3ef !important;
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
            border-bottom: 1px solid #eaded9 !important;
            backdrop-filter: blur(14px);
        }

        .page,
        .container,
        .content-wrap {
            width: min(1120px, calc(100% - 32px)) !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        .page {
            padding: 42px 0 52px !important;
        }

        .page-title,
        h1 {
            color: #3d3030;
        }

        .form-section {
            background: #fff !important;
            border: 1px solid #eaded9 !important;
            border-radius: 20px !important;
            padding: 28px !important;
            margin-bottom: 22px !important;
            box-shadow: 0 10px 35px rgba(61,48,48,.05);
        }

        .section-heading {
            margin-bottom: 24px !important;
        }

        .section-heading h2 {
            font-family: "Cormorant Garamond", Georgia, serif !important;
            color: #3d3030 !important;
        }

        .form-group label {
            color: #5b4847 !important;
            font-weight: 600 !important;
        }

        input,
        textarea,
        select {
            border: 1px solid #eaded9 !important;
            border-radius: 12px !important;
            background: #fffdfc !important;
            transition: border-color .2s ease, box-shadow .2s ease !important;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #8b4f55 !important;
            box-shadow: 0 0 0 4px rgba(139,79,85,.10) !important;
            outline: none !important;
        }

        .btn-primary,
        .save-btn,
        button[type="submit"] {
            background: linear-gradient(135deg, #8b4f55, #713e44) !important;
            border-color: #8b4f55 !important;
            color: #fff !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 22px rgba(113,62,68,.16);
        }

        .btn-primary:hover,
        .save-btn:hover,
        button[type="submit"]:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .topbar,
            header.topbar {
                padding: 12px 16px !important;
            }

            .page {
                padding: 28px 0 38px !important;
            }

            .form-section {
                padding: 20px !important;
                border-radius: 16px !important;
            }
        }

    </style>

</head>


<body>


<div class="page">


    <!--
    ========================================================================
    TOPBAR
    ========================================================================

    Trang này KHÔNG sử dụng sidebar.

    Theo concept mới:
    - Trang chủ
    - Trang Cài đặt

    sẽ có menu đầy đủ.

    Các trang chức năng khác:
    - Không có sidebar
    - Chỉ có nút quay về trang chủ

    ========================================================================
    -->


    <header class="topbar">


        <!--
        ====================================================================
        BRAND
        ====================================================================
        -->

        <a
            href="dashboard.php"
            class="brand"
        >

            <div class="brand-icon">

                <i class="fa-regular fa-heart"></i>

            </div>


            <div class="brand-name">

                <strong>
                    thyu_nhun Weddings
                </strong>

                <span>
                    WEDDING INVITATION
                </span>

            </div>

        </a>


        <!--
        ====================================================================
        USER
        ====================================================================
        -->

        <div class="user-area">


            <div class="user-info">

                <strong>
                    Xin chào,
                    <?= htmlspecialchars(
                        $full_name,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </strong>

                <span>
                    Khách hàng
                </span>

            </div>


            <div class="avatar">

                <?= htmlspecialchars(
                    $avatar_letter,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>


        </div>


    </header>



    <!--
    ========================================================================
    MAIN CONTENT
    ========================================================================
    -->

    <main class="content">


        <!--
        ====================================================================
        BACK HOME
        ====================================================================
        -->

        <a
            href="dashboard.php"
            class="back-home"
        >

            <i class="fa-solid fa-arrow-left"></i>

            Về trang chủ

        </a>



        <!--
        ====================================================================
        PAGE HEADING
        ====================================================================
        -->

        <div class="heading">

            <div class="eyebrow">

                THYU_NHUN WEDDINGS

            </div>


            <h1>
                Thông tin thiệp
            </h1>


            <p>
                Cập nhật những thông tin sẽ được sử dụng
                trên thiệp cưới của bạn.
            </p>

        </div>



        <!--
        ====================================================================
        FORM CARD
        ====================================================================
        -->

        <div class="form-card">


            <!--
            =================================================================
            SUCCESS MESSAGE
            =================================================================
            -->

            <?php if ($success !== ""): ?>

                <div class="message success">

                    <i class="fa-solid fa-circle-check"></i>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>



            <!--
            =================================================================
            ERROR MESSAGE
            =================================================================
            -->

            <?php if ($error !== ""): ?>

                <div class="message error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>



            <!--
            =================================================================
            FORM
            =================================================================
            -->

            <form
                method="POST"
                autocomplete="off"
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


                <!--
                =============================================================
                SECTION 1
                THÔNG TIN CẶP ĐÔI
                =============================================================
                -->

                <section class="form-section">


                    <div class="section-heading">


                        <div class="section-icon">

                            <i class="fa-regular fa-heart"></i>

                        </div>


                        <div>

                            <h2>
                                Thông tin cặp đôi
                            </h2>

                            <p>
                                Tên cô dâu và chú rể
                            </p>

                        </div>


                    </div>



                    <div class="form-grid">


                        <!-- CHÚ RỂ -->

                        <div class="form-group">

                            <label>
                                Tên chú rể
                                <span class="required">*</span>
                            </label>


                            <input
                                type="text"
                                name="groom_name"
                                value="<?= htmlspecialchars(
                                    $wedding["groom_name"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                placeholder="Ví dụ: Thanh Tùng"
                                maxlength="100"
                                required
                            >

                        </div>



                        <!-- CÔ DÂU -->

                        <div class="form-group">

                            <label>
                                Tên cô dâu
                                <span class="required">*</span>
                            </label>


                            <input
                                type="text"
                                name="bride_name"
                                value="<?= htmlspecialchars(
                                    $wedding["bride_name"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                placeholder="Ví dụ: Hoàng Thị B"
                                maxlength="100"
                                required
                            >

                        </div>


                    </div>


                </section>



                <!--
                =============================================================
                SECTION 2
                THỜI GIAN & ĐỊA ĐIỂM
                =============================================================
                -->

                <section class="form-section">


                    <div class="section-heading">


                        <div class="section-icon">

                            <i class="fa-regular fa-calendar"></i>

                        </div>


                        <div>

                            <h2>
                                Thời gian & địa điểm
                            </h2>

                            <p>
                                Thông tin tổ chức lễ cưới
                            </p>

                        </div>


                    </div>



                    <div class="form-grid">


                        <!-- NGÀY -->

                        <div class="form-group">

                            <label>
                                Ngày cưới
                            </label>


                            <input
                                type="date"
                                name="wedding_date"
                                value="<?= htmlspecialchars(
                                    $wedding["wedding_date"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                        </div>



                        <!-- GIỜ -->

                        <div class="form-group">

                            <label>
                                Giờ tổ chức
                            </label>


                            <input
                                type="time"
                                name="wedding_time"
                                value="<?= htmlspecialchars(
                                    $wedding["wedding_time"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                            >

                        </div>



                        <!-- ĐỊA ĐIỂM -->

                        <div class="form-group">

                            <label>
                                Tên địa điểm
                            </label>


                            <input
                                type="text"
                                name="venue"
                                value="<?= htmlspecialchars(
                                    $wedding["venue"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                placeholder="Ví dụ: Tư gia / Nhà hàng ABC"
                                maxlength="255"
                            >

                        </div>



                        <!-- ĐỊA CHỈ -->

                        <div class="form-group">

                            <label>
                                Địa chỉ
                            </label>


                            <input
                                type="text"
                                name="address"
                                value="<?= htmlspecialchars(
                                    $wedding["address"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                placeholder="Địa chỉ tổ chức"
                                maxlength="500"
                            >

                        </div>


                    </div>


                </section>



                <!--
                =============================================================
                SECTION 3
                GOOGLE MAPS
                =============================================================
                -->

                <section class="form-section">


                    <div class="section-heading">


                        <div class="section-icon">

                            <i class="fa-solid fa-location-dot"></i>

                        </div>


                        <div>

                            <h2>
                                Google Maps
                            </h2>

                            <p>
                                Thêm vị trí để khách dễ dàng tìm đường
                            </p>

                        </div>


                    </div>



                    <div class="maps-box">


                        <div class="maps-label">

                            <i class="fa-solid fa-map-location-dot"></i>

                            Link Google Maps

                        </div>


                        <input
                            type="url"
                            name="google_maps_url"
                            value="<?= htmlspecialchars(
                                $wedding["google_maps_url"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            placeholder="https://maps.google.com/..."
                            maxlength="1000"
                        >


                        <div class="input-hint">

                            Mở Google Maps → chọn địa điểm →
                            Chia sẻ → Sao chép đường liên kết →
                            dán link vào đây.

                        </div>


                        <?php if (
                            !empty(
                                $wedding["google_maps_url"]
                            )
                        ): ?>

                            <div class="map-preview">

                                <a
                                    href="<?= htmlspecialchars(
                                        $wedding["google_maps_url"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >

                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>

                                    Kiểm tra link Google Maps

                                </a>

                            </div>

                        <?php endif; ?>


                    </div>


                </section>



                <!--
                =============================================================
                SECTION 4
                LỜI GIỚI THIỆU
                =============================================================
                -->

                <section class="form-section">


                    <div class="section-heading">


                        <div class="section-icon">

                            <i class="fa-regular fa-envelope"></i>

                        </div>


                        <div>

                            <h2>
                                Lời giới thiệu
                            </h2>

                            <p>
                                Nội dung lời mời hiển thị trên thiệp
                            </p>

                        </div>


                    </div>



                    <div class="form-group">


                        <label>
                            Nội dung lời mời
                        </label>


                        <textarea
                            name="description"
                            maxlength="2000"
                            placeholder="Ví dụ: Trân trọng kính mời quý khách đến chung vui cùng gia đình chúng tôi..."
                        ><?= htmlspecialchars(
                            $wedding["description"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>


                        <div class="input-hint">

                            Bạn có thể viết lời mời,
                            lời cảm ơn hoặc một lời nhắn riêng.

                        </div>


                    </div>


                </section>



                <!--
                =============================================================
                ACTIONS
                =============================================================
                -->

                <div class="actions">


                    <button
                        type="submit"
                        class="btn-save"
                    >

                        <i class="fa-solid fa-floppy-disk"></i>

                        Lưu thông tin

                    </button>


                </div>


            </form>


        </div>


    </main>



    <!--
    ========================================================================
    FOOTER
    ========================================================================

    FOOTER DÙNG CHUNG CHO CUSTOMER.

    Sau này nếu muốn đổi:
    - Tên thương hiệu
    - Số điện thoại
    - Facebook
    - Website

    có thể chỉnh tại khu vực này.

    ========================================================================
    -->

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

                0900 000 000

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


</div>


</body>

</html>