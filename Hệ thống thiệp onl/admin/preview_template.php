<?php
session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| KHÔNG CACHE
|--------------------------------------------------------------------------
*/
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
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY ID THIỆP
|--------------------------------------------------------------------------
*/
$wedding_id = intval($_GET["wedding_id"] ?? 0);

if ($wedding_id <= 0) {
    header("Location: weddings.php?status=not_found");
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN THIỆP + KHÁCH HÀNG
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT
        w.*,
        u.full_name,
        u.username
     FROM weddings w
     INNER JOIN users u ON u.id = w.user_id
     WHERE w.id = ?
     LIMIT 1"
);

if (!$stmt) {
    exit("Không thể chuẩn bị dữ liệu thiệp.");
}

$stmt->bind_param("i", $wedding_id);
$stmt->execute();

$result = $stmt->get_result();
$wedding = $result ? $result->fetch_assoc() : null;

$stmt->close();

if (!$wedding) {
    header("Location: weddings.php?status=not_found");
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY TEMPLATE ĐÚNG VỚI THIỆP
|--------------------------------------------------------------------------
| Không lấy template_id từ URL để tránh xem nhầm mẫu.
|--------------------------------------------------------------------------
*/
$template_id = intval($wedding["template_id"] ?? 0);

if ($template_id <= 0) {
    header("Location: weddings.php?status=invalid");
    exit;
}

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
     LIMIT 1"
);

if (!$stmt) {
    exit("Không thể chuẩn bị dữ liệu mẫu thiệp.");
}

$stmt->bind_param("i", $template_id);
$stmt->execute();

$result = $stmt->get_result();
$template = $result ? $result->fetch_assoc() : null;

$stmt->close();

if (!$template) {
    header("Location: weddings.php?status=not_found");
    exit;
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA FOLDER TEMPLATE
|--------------------------------------------------------------------------
*/
$template_folder = trim((string)($template["folder"] ?? ""), "/");

if (
    $template_folder === "" ||
    !preg_match('/^[a-zA-Z0-9_\/-]+$/', $template_folder)
) {
    exit("Đường dẫn mẫu thiệp không hợp lệ.");
}

/*
|--------------------------------------------------------------------------
| XÁC ĐỊNH FILE INDEX TEMPLATE
|--------------------------------------------------------------------------
*/
$template_file =
    dirname(__DIR__) .
    "/" .
    $template_folder .
    "/index.php";

if (!is_file($template_file)) {
    exit(
        "Mẫu thiệp chưa được cài đặt hoặc không tìm thấy file index.php."
    );
}

/*
|--------------------------------------------------------------------------
| LẤY ẢNH CỦA THIỆP
|--------------------------------------------------------------------------
*/
$cover_image = null;
$album_images = [];

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
            WHEN image_type = 'cover' THEN 0
            ELSE 1
        END,
        image_order ASC,
        id DESC"
);

if ($stmt) {
    $stmt->bind_param("i", $wedding_id);
    $stmt->execute();

    $images_result = $stmt->get_result();

    while ($image = $images_result->fetch_assoc()) {
        if (($image["image_type"] ?? "") === "cover") {
            if ($cover_image === null) {
                $cover_image = $image;
            }
        } else {
            $album_images[] = $image;
        }
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| KHÁCH DEMO
|--------------------------------------------------------------------------
| Dùng cho các template cần biến $guest để hiển thị lời chào.
|--------------------------------------------------------------------------
*/
$guest = [
    "id" => 0,
    "guest_name" => "Quý khách"
];

/*
|--------------------------------------------------------------------------
| THÔNG TIN HIỂN THỊ
|--------------------------------------------------------------------------
*/
$customer_name = trim(
    (string)(
        $wedding["full_name"] ??
        $wedding["username"] ??
        "Khách hàng"
    )
);

$template_name = trim(
    (string)($template["template_name"] ?? "Mẫu thiệp")
);

/*
|--------------------------------------------------------------------------
| FIX QUAN TRỌNG: ĐƯỜNG DẪN CSS / JS / ẢNH CỦA TEMPLATE
|--------------------------------------------------------------------------
|
| Khi index.php của mẫu được include trực tiếp vào:
|     /admin/preview_template.php
|
| các đường dẫn tương đối như:
|     css/style.css
|     js/script.js
|     images/xxx.jpg
|
| sẽ bị trình duyệt hiểu thành:
|     /admin/css/style.css
|
| làm toàn bộ giao diện mẫu bị mất CSS như ảnh bạn gửi.
|
| <base href=""> bên dưới buộc trình duyệt resolve các tài nguyên
| tương đối theo đúng thư mục của template.
|--------------------------------------------------------------------------
*/
$script_dir = str_replace(
    "\\",
    "/",
    dirname($_SERVER["SCRIPT_NAME"] ?? "/admin/preview_template.php")
);

/*
 * Xác định thư mục gốc của project bằng cách bỏ /admin khỏi URL.
 * Ví dụ:
 * /ThiepOnl/admin -> /ThiepOnl/
 */
$project_base = preg_replace(
    '#/admin/?$#i',
    "/",
    $script_dir
);

if (!$project_base) {
    $project_base = "/";
}

$template_base_url =
    rtrim($project_base, "/") .
    "/" .
    trim($template_folder, "/") .
    "/";

/*
|--------------------------------------------------------------------------
| URL QUAY LẠI
|--------------------------------------------------------------------------
*/
$back_url =
    rtrim($script_dir, "/") .
    "/weddings.php";

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <!--
        Đây là phần sửa lỗi chính:
        CSS / JS / ảnh tương đối của template sẽ lấy theo thư mục template.
    -->
    <base href="<?= htmlspecialchars($template_base_url, ENT_QUOTES, "UTF-8") ?>">

    <title>
        Xem mẫu - <?= htmlspecialchars($template_name, ENT_QUOTES, "UTF-8") ?>
    </title>

    <style>
        :root {
            --wine: #965050;
            --wine-dark: #7f4141;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            background: #f7f3ef;
        }

        /*
         * Thanh preview nằm ngoài template.
         * Dùng position: fixed để không phá layout của mẫu.
         */
        .preview-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;

            height: 58px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;
            padding: 0 22px;

            background: rgba(61, 48, 48, .97);

            border-bottom: 1px solid rgba(201, 164, 106, .35);

            box-shadow: 0 8px 28px rgba(0, 0, 0, .12);

            z-index: 2147483647;
        }

        .preview-left,
        .preview-right {
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

            padding: 9px 14px;

            border-radius: 10px;

            color: #fff;
            background: rgba(255, 255, 255, .10);

            border: 1px solid rgba(255, 255, 255, .18);

            text-decoration: none;

            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;

            transition: .2s ease;
        }

        .back-home:hover {
            background: #fff;
            color: var(--wine-dark);
        }

        .preview-title {
            color: rgba(255, 255, 255, .72);

            font-family: Arial, sans-serif;
            font-size: 12px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .preview-title strong {
            color: #fff;
            margin-left: 5px;
        }

        .template-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 7px 11px;

            border-radius: 999px;

            background: rgba(201, 164, 106, .14);

            border: 1px solid rgba(201, 164, 106, .40);

            color: #f4dfb8;

            font-family: Arial, sans-serif;
            font-size: 10px;

            white-space: nowrap;
        }

        /*
         * Chừa đúng chiều cao cho thanh preview.
         * Không dùng margin trên body để tránh ảnh hưởng template.
         */
        .template-wrapper {
            min-height: 100vh;
            padding-top: 58px;
        }

        /*
         * Nếu template có html/body riêng thì vẫn đảm bảo
         * vùng hiển thị không bị giới hạn chiều cao.
         */
        .template-wrapper > * {
            max-width: 100%;
        }

        @media (max-width: 650px) {
            .preview-bar {
                height: 54px;
                padding: 0 12px;
            }

            .preview-title,
            .template-badge {
                display: none;
            }

            .template-wrapper {
                padding-top: 54px;
            }

            .back-home {
                padding: 8px 12px;
            }
        }
    </style>
</head>

<body>

    <!-- THANH CÔNG CỤ XEM TRƯỚC -->
    <div class="preview-bar">

        <div class="preview-left">

            <a
                href="<?= htmlspecialchars($back_url, ENT_QUOTES, "UTF-8") ?>"
                class="back-home"
            >
                ← Quay lại
            </a>

            <div class="preview-title">
                Khách hàng:
                <strong>
                    <?= htmlspecialchars($customer_name, ENT_QUOTES, "UTF-8") ?>
                </strong>

                &nbsp;•&nbsp;

                Mẫu:
                <strong>
                    <?= htmlspecialchars($template_name, ENT_QUOTES, "UTF-8") ?>
                </strong>
            </div>

        </div>

        <div class="preview-right">

            <div class="template-badge">
                ✦ Bản xem trước
            </div>

        </div>

    </div>

    <!-- TEMPLATE -->
    <div class="template-wrapper">

        <?php
        /*
        |--------------------------------------------------------------------------
        | HIỂN THỊ MẪU THIỆP
        |--------------------------------------------------------------------------
        |
        | Các biến được truyền trực tiếp cho index.php của template:
        |
        | $wedding
        | $template
        | $cover_image
        | $album_images
        | $guest
        |
        */
        include $template_file;
        ?>

    </div>

</body>
</html>
