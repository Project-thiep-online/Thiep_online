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


/*
|--------------------------------------------------------------------------
| LẤY TEMPLATE ID
|--------------------------------------------------------------------------
*/

$template_id = intval(
    $_GET["template_id"] ?? 0
);


if ($template_id <= 0) {

    header(
        "Location: templates.php?error=invalid_template"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY WEDDING CỦA CUSTOMER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT *
     FROM weddings
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);


if (!$stmt) {

    header(
        "Location: templates.php?error=wedding_check"
    );

    exit;
}


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


if (!$wedding) {

    header(
        "Location: templates.php?error=no_wedding"
    );

    exit;
}


$wedding_id =
    intval($wedding["id"]);


/*
|--------------------------------------------------------------------------
| LẤY TEMPLATE ACTIVE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        template_name,
        template_code,
        folder,
        description,
        status
     FROM templates
     WHERE id = ?
       AND status = 'active'
     LIMIT 1"
);


if (!$stmt) {

    header(
        "Location: templates.php?error=template_check"
    );

    exit;
}


$stmt->bind_param(
    "i",
    $template_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$template =
    $result->fetch_assoc();


$stmt->close();


if (!$template) {

    header(
        "Location: templates.php?error=template_unavailable"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| KIỂM TRA FOLDER TEMPLATE
|--------------------------------------------------------------------------
*/

$template_folder =
    trim(
        $template["folder"] ?? ""
    );


if (
    $template_folder === "" ||
    !preg_match(
        '/^[a-zA-Z0-9_\/-]+$/',
        $template_folder
    )
) {

    header(
        "Location: templates.php?error=invalid_folder"
    );

    exit;
}


$template_file =
    dirname(__DIR__) .
    "/" .
    $template_folder .
    "/index.php";


if (!is_file($template_file)) {

    header(
        "Location: templates.php?error=template_not_installed"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY ẢNH CỦA WEDDING
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        image_path,
        image_type,
        image_order,
        created_at
     FROM wedding_images
     WHERE wedding_id = ?
     ORDER BY
        CASE
            WHEN image_type = 'cover'
            THEN 0
            ELSE 1
        END,
        image_order ASC,
        id DESC"
);


if (!$stmt) {

    header(
        "Location: templates.php?error=image_check"
    );

    exit;
}


$stmt->bind_param(
    "i",
    $wedding_id
);


$stmt->execute();


$images_result =
    $stmt->get_result();


$cover_image = null;

$album_images = [];


while (
    $image =
    $images_result->fetch_assoc()
) {

    if (
        $image["image_type"] === "cover"
    ) {

        if (
            $cover_image === null
        ) {

            $cover_image =
                $image;

        }

    } else {

        $album_images[] =
            $image;
    }
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| URL BASE + CHUẨN HÓA ĐƯỜNG DẪN ẢNH / QR CHO PREVIEW
|--------------------------------------------------------------------------
|
| LỖI CŨ:
| File template được include từ customer/preview_template.php.
| Vì vậy mọi src/href tương đối trong index.php của template sẽ bị trình duyệt
| resolve theo thư mục customer/, làm ảnh, QR và asset của template bị 404.
|
| CÁCH FIX:
| 1. Tạo app_base_url theo thư mục gốc project.
| 2. Chuẩn hóa toàn bộ asset lấy từ database thành URL tuyệt đối theo project.
| 3. Tạo template_base_url để dùng thẻ <base> cho asset tương đối của template.
|--------------------------------------------------------------------------
*/

$script_name = str_replace(
    "\\",
    "/",
    $_SERVER["SCRIPT_NAME"] ?? ""
);

$app_base_url = str_replace(
    "\\",
    "/",
    dirname(dirname($script_name))
);

$app_base_url = rtrim($app_base_url, "/");

if (
    $app_base_url === "/" ||
    $app_base_url === "." ||
    $app_base_url === "\\"
) {
    $app_base_url = "";
}

$template_web_path = trim(
    str_replace("\\", "/", $template_folder),
    "/"
);

$template_base_url = rtrim(
    ($app_base_url !== "" ? $app_base_url : "") .
    "/" .
    $template_web_path,
    "/"
) . "/";


function preview_asset_url($path, $app_base_url)
{
    $path = trim((string) $path);

    if ($path === "") {
        return "";
    }

    // Giữ nguyên URL tuyệt đối, protocol-relative, data/blob URL.
    if (
        preg_match('#^(https?:)?//#i', $path) ||
        preg_match('#^(data|blob):#i', $path)
    ) {
        return $path;
    }

    $path = str_replace("\\", "/", $path);

    // Nếu database vô tình lưu filesystem path của XAMPP,
    // cố gắng lấy phần bắt đầu từ thư mục web phổ biến.
    if (preg_match('#^[A-Za-z]:/#', $path)) {
        if (
            preg_match(
                '#/(uploads|assets|images|image|qr|qrcode)/#i',
                $path,
                $matches,
                PREG_OFFSET_CAPTURE
            )
        ) {
            $path = substr(
                $path,
                $matches[0][1] + 1
            );
        }
    }

    // Loại bỏ ./ và ../ để URL không bị resolve theo customer/.
    $path = preg_replace(
        '#^(?:\./|\.\./)+#',
        "",
        $path
    );

    $path = ltrim($path, "/");

    if ($path === "") {
        return "";
    }

    // Tránh prefix trùng /ThiepOn/ThiepOn/...
    $app_prefix = trim($app_base_url, "/");

    if (
        $app_prefix !== "" &&
        (
            $path === $app_prefix ||
            strpos($path, $app_prefix . "/") === 0
        )
    ) {
        return "/" . $path;
    }

    return
        ($app_base_url !== "" ? $app_base_url : "") .
        "/" .
        $path;
}


function normalize_preview_assets($data, $app_base_url)
{
    if (!is_array($data)) {
        return $data;
    }

    foreach ($data as $key => $value) {

        $key_lower = strtolower((string) $key);

        if (is_array($value)) {
            $data[$key] = normalize_preview_assets(
                $value,
                $app_base_url
            );
            continue;
        }

        $is_asset_field =
            strpos($key_lower, "image") !== false ||
            strpos($key_lower, "photo") !== false ||
            strpos($key_lower, "avatar") !== false ||
            strpos($key_lower, "cover") !== false ||
            strpos($key_lower, "qr") !== false ||
            strpos($key_lower, "qrcode") !== false ||
            strpos($key_lower, "banner") !== false ||
            strpos($key_lower, "background") !== false ||
            strpos($key_lower, "bg") === 0;

        if (
            $is_asset_field &&
            is_string($value) &&
            trim($value) !== ""
        ) {
            $data[$key] = preview_asset_url(
                $value,
                $app_base_url
            );
        }
    }

    return $data;
}


/*
|--------------------------------------------------------------------------
| ÁP DỤNG URL ĐÚNG CHO DỮ LIỆU ĐƯỢC TEMPLATE SỬ DỤNG
|--------------------------------------------------------------------------
*/

$wedding = normalize_preview_assets(
    $wedding,
    $app_base_url
);

if ($cover_image !== null) {
    $cover_image = normalize_preview_assets(
        $cover_image,
        $app_base_url
    );
}

$album_images = normalize_preview_assets(
    $album_images,
    $app_base_url
);


/*
|--------------------------------------------------------------------------
| BIẾN TƯƠNG THÍCH
|--------------------------------------------------------------------------
|
| Một số mẫu cũ có thể dùng tên biến khác nhau.
| Tạo alias để preview không lỗi khi template tham chiếu biến dạng phổ biến.
|--------------------------------------------------------------------------
*/

$wedding_images = array_merge(
    $cover_image !== null ? [$cover_image] : [],
    $album_images
);

$images = $wedding_images;

$coverImage = $cover_image;

$albumImages = $album_images;

$preview_mode = true;

/*
|--------------------------------------------------------------------------
| KHÁCH DEMO CHO PREVIEW
|--------------------------------------------------------------------------

|
| Preview không tạo invitation_links.
|
|--------------------------------------------------------------------------
*/

$guest = [

    "id" => 0,

    "guest_name" =>
        "Quý khách"

];


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


/*
|--------------------------------------------------------------------------
| HIỂN THỊ PREVIEW
|--------------------------------------------------------------------------
*/

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

        Xem mẫu -
        <?= htmlspecialchars(
            $template["template_name"] ?? "Thiệp cưới"
        ) ?>

    </title>

    <!--
        Quan trọng: template được include từ customer/preview_template.php.
        Thẻ base đảm bảo src="image/..." và href="assets/..." trong template
        luôn trỏ đúng về thư mục của template.
    -->
    <base href="<?= htmlspecialchars($template_base_url, ENT_QUOTES, 'UTF-8') ?>">

    <!-- CSS CỦA TEMPLATE ĐANG XEM -->
<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        $template_base_url . 'style.css',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

    <style>

        :root {

            --wine: #B52A3A;

            --wine-dark: #9F1E2E;

            --cream: #FFFBFA;

            --white: #ffffff;

            --gold: #D4A73D;

            --text: #3A171D;

            --muted: #877275;

            --border: #F5E4E2;

        }


        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }


        html {

            scroll-behavior: smooth;

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

        }


        /*
        |--------------------------------------------------------------------------
        | PREVIEW BAR
        |--------------------------------------------------------------------------
        */

        .preview-bar {

            position: fixed;

            top: 0;

            left: 0;

            right: 0;

            height: 58px;

            background:
                rgba(33,25,27,.97);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 22px;

            z-index: 999999;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,.15);

        }


        .preview-left {

            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 0;

        }


        .back-home {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding:
                8px 13px;

            border:
                1px solid
                rgba(255,255,255,.16);

            border-radius: 7px;

            color:
                #FCEFF0;

            background:
                rgba(255,255,255,.06);

            font-size: 11px;

            font-weight: 600;

            text-decoration: none;

            transition:
                .2s ease;

            white-space: nowrap;

        }


        .back-home:hover {

            background:
                var(--wine);

            border-color:
                var(--wine);

            transform:
                translateX(-2px);

        }


        .preview-title {

            color:
                #F5E4E2;

            font-size:
                12px;

            white-space:
                nowrap;

            overflow:
                hidden;

            text-overflow:
                ellipsis;

        }


        .preview-title strong {

            color:
                white;

        }


        .preview-right {

            display: flex;

            align-items: center;

            gap: 9px;

        }


        .template-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                7px 10px;

            border-radius:
                20px;

            background:
                rgba(201,164,106,.13);

            border:
                1px solid
                rgba(201,164,106,.22);

            color:
                #F2D58A;

            font-size:
                9px;

        }


        /*
        |--------------------------------------------------------------------------
        | TEMPLATE WRAPPER
        |--------------------------------------------------------------------------
        */

        .template-wrapper {

            padding-top:
                58px;

            min-height:
                100vh;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 650px) {

            .preview-bar {

                height:
                    54px;

                padding:
                    0 12px;

            }


            .preview-title {

                display:
                    none;

            }


            .template-badge {

                display:
                    none;

            }


            .back-home {

                padding:
                    8px 11px;

                font-size:
                    10px;

            }


            .template-wrapper {

                padding-top:
                    54px;

            }

        }

    
        /* THYU_NHUN WEDDINGS - MASTER CUSTOMER PREVIEW STYLE */
        .preview-bar {
            background: rgba(61,48,48,.96) !important;
            border-bottom: 1px solid rgba(201,164,106,.35) !important;
            box-shadow: 0 8px 28px rgba(0,0,0,.12) !important;
        }

        .back-home {
            border-radius: 12px !important;
            background: rgba(255,255,255,.10) !important;
            border: 1px solid rgba(255,255,255,.18) !important;
            font-family: "DM Sans", Arial, sans-serif !important;
        }

        .back-home:hover {
            background: #fff !important;
            color: #65131F !important;
            transform: translateY(-1px);
        }

        .preview-title {
            font-family: "DM Sans", Arial, sans-serif !important;
            color: rgba(255,255,255,.75) !important;
        }

        .preview-title strong {
            font-family: "Cormorant Garamond", Georgia, serif !important;
            font-size: 20px;
            color: #fff !important;
        }

        .template-badge {
            border-radius: 999px !important;
            background: rgba(201,164,106,.14) !important;
            border: 1px solid rgba(201,164,106,.40) !important;
            color: #F7E7B9 !important;
        }

        .template-wrapper {
            background: #FFF8F6 !important;
        }

        @media (max-width: 768px) {
            .preview-bar {
                background: #32191C !important;
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
         THANH PREVIEW
    ========================================================== -->

    <div class="preview-bar">


        <div class="preview-left">


            <a
                href="<?= htmlspecialchars(($app_base_url !== "" ? $app_base_url : "") . "/customer/templates.php", ENT_QUOTES, 'UTF-8') ?>"
                class="back-home"
            >

                ←
                Về mẫu thiệp

            </a>


            <div class="preview-title">

                Đang xem:

                <strong>

                    <?= htmlspecialchars(
                        $template["template_name"]
                    ) ?>

                </strong>

            </div>


        </div>


        <div class="preview-right">


            <div class="template-badge">

                Bản xem trước

            </div>


        </div>


    </div>


    <!-- =========================================================
         TEMPLATE
    ========================================================== -->

    <div class="template-wrapper">

        <?php

        /*
        |--------------------------------------------------------------------------
        | HIỂN THỊ MẪU THIỆP
        |--------------------------------------------------------------------------
        */

        /*
         * Đưa thư mục template vào include_path để các template cũ dùng
         * include/require tương đối vẫn có thể tìm đúng file PHP phụ.
         */
        $previous_include_path = get_include_path();
        set_include_path(
            dirname($template_file) .
            PATH_SEPARATOR .
            $previous_include_path
        );

        include $template_file;

        set_include_path($previous_include_path);

        ?>

    </div>


</body>

</html>