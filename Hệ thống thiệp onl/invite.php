<?php

/*
|--------------------------------------------------------------------------
| THYU_NHUN WEDDINGS
| INVITE.PHP - 4B.4
|--------------------------------------------------------------------------
|
| [4B.4]
| KẾT NỐI QR CODE:
|
| invitation_links
|       ↓
| wedding_id
|       ↓
| wedding_images
|       ↓
| image_type = 'qr'
|       ↓
| $qr_image
|       ↓
| $qr
|       ↓
| template/index.php
|
| LƯU Ý:
| - File này KHÔNG tự hiển thị QR.
| - File này chỉ lấy QR và truyền dữ liệu cho template.
| - Template nào cần QR thì tự kiểm tra $qr.
|
|--------------------------------------------------------------------------
*/


require_once "config/database.php";
require_once "config/url_helper.php";


/*
|--------------------------------------------------------------------------
| 01. URL ĐẸP MỚI + TOKEN CŨ
|--------------------------------------------------------------------------
*/

$public_slug = trim($_GET["slug"] ?? "");
$public_code = trim($_GET["code"] ?? "");
$token = trim($_GET["token"] ?? "");

if (
    ($public_slug === "" || $public_code === "")
    &&
    $token === ""
) {
    http_response_code(400);
    die("Link thiệp không hợp lệ.");
}


/*
|--------------------------------------------------------------------------
| 02. LẤY THÔNG TIN:
| LINK + KHÁCH + WEDDING + TEMPLATE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        il.id AS link_id,
        il.wedding_id,
        il.guest_id,
        il.token,

        g.guest_name,

        w.groom_name,
        w.bride_name,
        w.wedding_date,
        w.wedding_time,
        w.venue,
        w.address,

        /*Link Google Maps được lưu trong bảng weddings.*/

        w.google_maps_url,

        w.description,
        w.cover_image,
        w.template_id,

        t.template_name,
        t.template_code,
        t.folder,
        t.status AS template_status

    FROM invitation_links il

    INNER JOIN guests g
        ON il.guest_id = g.id

    INNER JOIN weddings w
        ON il.wedding_id = w.id

    INNER JOIN templates t
        ON w.template_id = t.id

    WHERE
        (
            w.wedding_slug = ?
            AND il.public_code = ?
        )
        OR
        (
            ? <> ''
            AND il.token = ?
        )

    LIMIT 1
";


$stmt = $conn->prepare($sql);


if (!$stmt) {

    http_response_code(500);

    die("Không thể chuẩn bị truy vấn.");

}


$stmt->bind_param(
    "ssss",
    $public_slug,
    $public_code,
    $token,
    $token
);


if (!$stmt->execute()) {

    http_response_code(500);

    die("Không thể tải thiệp.");

}


$result =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| 03. KIỂM TRA LINK
|--------------------------------------------------------------------------
*/

if ($result->num_rows !== 1) {

    http_response_code(404);

    die(
        "Link thiệp không tồn tại hoặc đã bị xóa."
    );

}


$invitation =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| 04. KIỂM TRA TEMPLATE
|--------------------------------------------------------------------------
*/

if (
    empty($invitation["folder"])
) {

    http_response_code(500);

    die(
        "Thiệp chưa được cấu hình mẫu."
    );

}


/*
|--------------------------------------------------------------------------
| 05. TĂNG LƯỢT XEM
|--------------------------------------------------------------------------
*/

$update_stmt = $conn->prepare(
    "UPDATE invitation_links
     SET
        visit_count = visit_count + 1,
        last_visited = NOW()
     WHERE id = ?"
);


if ($update_stmt) {

    $update_stmt->bind_param(
        "i",
        $invitation["link_id"]
    );

    $update_stmt->execute();

    $update_stmt->close();

}


/*
|--------------------------------------------------------------------------
| 06. LẤY ẢNH CỦA WEDDING
|--------------------------------------------------------------------------
|
| Các loại hiện tại:
|
| cover  = ảnh bìa
| couple = ảnh cô dâu/chú rể
| album  = album
| qr     = QR Code
|
|--------------------------------------------------------------------------
*/

$image_stmt = $conn->prepare(
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

            WHEN image_type = 'couple'
            THEN 1

            WHEN image_type = 'album'
            THEN 2

            WHEN image_type = 'qr'
            THEN 3

            ELSE 4
        END,

        image_order ASC,

        id DESC"
);


if (!$image_stmt) {

    http_response_code(500);

    die(
        "Không thể tải hình ảnh của thiệp."
    );

}


$image_stmt->bind_param(
    "i",
    $invitation["wedding_id"]
);


if (!$image_stmt->execute()) {

    http_response_code(500);

    die(
        "Không thể tải hình ảnh của thiệp."
    );

}


$images_result =
    $image_stmt->get_result();


/*
|--------------------------------------------------------------------------
| 07. PHÂN LOẠI ẢNH
|--------------------------------------------------------------------------
*/

$cover_image = null;

$album_images = [];

$couple_images = [];

$qr_image = null;


/*
|--------------------------------------------------------------------------
| 08. ĐỌC TỪNG ẢNH
|--------------------------------------------------------------------------
*/

while (
    $image =
    $images_result->fetch_assoc()
) {

    /*
    |--------------------------------------------------------------------------
    | COVER
    |--------------------------------------------------------------------------
    */

    if (
        $image["image_type"] === "cover"
    ) {

        /*
        | Chỉ lấy cover đầu tiên.
        | Query đã sắp xếp id DESC.
        */

        if (
            $cover_image === null
        ) {

            $cover_image = $image;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | COUPLE
    |--------------------------------------------------------------------------
    */

    elseif (
        $image["image_type"] === "couple"
    ) {

        $couple_images[] =
            $image;

    }


    /*
    |--------------------------------------------------------------------------
    | ALBUM
    |--------------------------------------------------------------------------
    */

    elseif (
        $image["image_type"] === "album"
    ) {

        $album_images[] =
            $image;

    }


    /*
    |--------------------------------------------------------------------------
    | [4B.4 - QR CODE]
    |--------------------------------------------------------------------------
    |
    | Chỉ lấy QR đầu tiên.
    | Nếu customer có nhiều QR vì dữ liệu cũ,
    | hệ thống vẫn chỉ sử dụng QR mới nhất.
    |
    |--------------------------------------------------------------------------
    */

    elseif (
        $image["image_type"] === "qr"
    ) {

        if (
            $qr_image === null
        ) {

            $qr_image = $image;

        }

    }

}


$image_stmt->close();


/*
|--------------------------------------------------------------------------
| 09. BIẾN WEDDING CHO TEMPLATE
|--------------------------------------------------------------------------
*/

$wedding =
    $invitation;


/*| [4C.4 - GOOGLE MAPS]
| Truyền Google Maps vào template.
| Có link:
| → template có thể hiển thị Google Maps.
| Không có link:
| → template tự ẩn khu vực Google Maps.*/

$wedding["google_maps_url"] =
    $invitation["google_maps_url"] ?? "";

/*10. BIẾN GUEST CHO TEMPLATE*/

$guest = [

    "id" =>
        $invitation["guest_id"],

    "guest_name" =>
        $invitation["guest_name"]

];


/*
|--------------------------------------------------------------------------
| 11. BIẾN TEMPLATE
|--------------------------------------------------------------------------
*/

$template = [

    "id" =>
        $invitation["template_id"],

    "template_name" =>
        $invitation["template_name"],

    "template_code" =>
        $invitation["template_code"],

    "folder" =>
        $invitation["folder"],

    "status" =>
        $invitation["template_status"]

];


/*
|--------------------------------------------------------------------------
| [4B.4 - QR CODE]
|--------------------------------------------------------------------------
| BIẾN QR DÙNG TRỰC TIẾP TRONG TEMPLATE
|--------------------------------------------------------------------------
|
| Template có thể dùng:
|
| if ($qr)
|
| hoặc:
|
| if ($wedding["qr_image"])
|
|--------------------------------------------------------------------------
*/

$qr =
    $qr_image;


/*
|--------------------------------------------------------------------------
| 12. ĐƯỜNG DẪN TEMPLATE
|--------------------------------------------------------------------------
*/

$template_folder =
    trim(
        $template["folder"] ?? ""
    );


/*
|--------------------------------------------------------------------------
| 13. CHỐNG PATH TRAVERSAL
|--------------------------------------------------------------------------
*/

if (
    $template_folder === "" ||
    !preg_match(
        '/^[a-zA-Z0-9_\/-]+$/',
        $template_folder
    )
) {

    http_response_code(500);

    die(
        "Đường dẫn mẫu thiệp không hợp lệ."
    );

}


$template_file =
    __DIR__ .
    "/" .
    $template_folder .
    "/index.php";


/*
|--------------------------------------------------------------------------
| 14. KIỂM TRA FILE TEMPLATE
|--------------------------------------------------------------------------
*/

if (
    !is_file($template_file)
) {

    http_response_code(500);

    die(
        "Không tìm thấy file mẫu thiệp: " .
        htmlspecialchars(
            $template_folder,
            ENT_QUOTES,
            "UTF-8"
        )
    );

}


/*
|--------------------------------------------------------------------------
| 15. HIỂN THỊ TEMPLATE
|--------------------------------------------------------------------------
|
| Các biến được truyền sang template:
|
| $wedding
| $guest
| $template
| $cover_image
| $album_images
| $couple_images
| $qr_image
| $qr
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| TEST 4C.4 - GOOGLE MAPS
|--------------------------------------------------------------------------
*/

if (!empty($wedding["google_maps_url"])) {

    // Chỉ dùng để kiểm tra tạm thời
    // echo htmlspecialchars($wedding["google_maps_url"]);

}

/*
|--------------------------------------------------------------------------
| 15A. FIX ĐƯỜNG DẪN CSS / JS / ẢNH / NHẠC CHO URL ĐẸP
|--------------------------------------------------------------------------
|
| URL đẹp có dạng:
| /i/ten-co-dau-chu-re/MAKHACH
|
| Khi template được include, trình duyệt vẫn giữ URL đẹp hiện tại.
| Vì vậy các đường dẫn tương đối như:
|
|   style.css
|   script.js
|   image/...
|   assets/...
|
| sẽ bị tìm sai nếu không có <base>.
|
| QUAN TRỌNG:
| - Luôn chèn <base> nếu template chưa có.
| - Nếu template đã có style.css thì <base> sẽ làm style.css chạy đúng.
| - Nếu template chưa tự nạp style.css thì invite.php sẽ tự nạp.
|
|--------------------------------------------------------------------------
*/

/* Ví dụ:
   SCRIPT_NAME: /ThiepOnl/invite.php
   APP BASE:    /ThiepOnl
*/
$script_name = str_replace(
    "\\",
    "/",
    $_SERVER["SCRIPT_NAME"] ?? ""
);

$app_base_url = rtrim(
    str_replace(
        "\\",
        "/",
        dirname($script_name)
    ),
    "/"
);

if (
    $app_base_url === "." ||
    $app_base_url === "/" ||
    $app_base_url === "\\"
) {
    $app_base_url = "";
}


/*
| Template folder trong database.
| Ví dụ: templates/mau01
*/
$template_web_path = trim(
    str_replace(
        "\\",
        "/",
        $template_folder
    ),
    "/"
);


/*
| Kết quả:
| localhost  -> /ThiepOnl/templates/mau01/
| hosting root -> /templates/mau01/
*/
$template_base_url = rtrim(
    $app_base_url .
    "/" .
    $template_web_path,
    "/"
) . "/";


/*
|--------------------------------------------------------------------------
| 15B. RENDER TEMPLATE
|--------------------------------------------------------------------------
*/

ob_start();

include $template_file;

$template_output = ob_get_clean();


/*
|--------------------------------------------------------------------------
| 15C. LUÔN BẢO ĐẢM <base> ĐƯỢC THÊM VÀO <head>
|--------------------------------------------------------------------------
|
| Bản cũ chỉ thêm <base> khi không tìm thấy chuỗi "style.css".
| Điều đó gây lỗi:
|
| template đã có:
|   <link rel="stylesheet" href="style.css">
|
| nhưng không có <base>
|
| => URL đẹp sẽ tìm sai:
| /i/slug/code/style.css
|
| Bản mới:
| - Template có style.css -> thêm <base>.
| - Template không có style.css -> thêm <base> + tự nạp style.css.
|
|--------------------------------------------------------------------------
*/

$has_base_tag =
    preg_match(
        '/<base\b[^>]*>/i',
        $template_output
    ) === 1;


$has_template_css =
    preg_match(
        '/href\s*=\s*["\'][^"\']*style\.css[^"\']*["\']/i',
        $template_output
    ) === 1;


/* Luôn ưu tiên dùng đường dẫn tuyệt đối tính từ domain. */
$asset_head = "";


/* Nếu template chưa có <base>, thêm <base> trước. */
if (!$has_base_tag) {

    $asset_head .=
        '<base href="' .
        htmlspecialchars(
            $template_base_url,
            ENT_QUOTES,
            "UTF-8"
        ) .
        '">';

}


/*
| Nếu template chưa tự nạp style.css thì invite.php nạp.
| Không nạp trùng nếu template đã có <link href="style.css">.
*/
if (!$has_template_css) {

    $asset_head .=
        '<link rel="stylesheet" href="' .
        htmlspecialchars(
            $template_base_url . "style.css",
            ENT_QUOTES,
            "UTF-8"
        ) .
        '">';

}


/*
| Chỉ thêm Google Fonts / Font Awesome nếu template chưa có.
| Các link này là tuyệt đối nên không bị ảnh hưởng bởi URL đẹp.
*/
if (
    stripos(
        $template_output,
        "fonts.googleapis.com"
    ) === false
) {

    $asset_head .=
        '<link rel="preconnect" href="https://fonts.googleapis.com">' .
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
        '<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&family=Cormorant+Garamond:wght@400;500;600&family=Great+Vibes&family=Noto+Serif:wght@400;500;600&display=swap" rel="stylesheet">';

}


if (
    stripos(
        $template_output,
        "font-awesome"
    ) === false &&
    stripos(
        $template_output,
        "fontawesome"
    ) === false
) {

    $asset_head .=
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">';

}


/*
| Chèn asset vào trong <head>.
|
| Nếu template không có <head>, tạo một head hợp lệ ngay sau <html>.
| Nếu template hoàn toàn không có cấu trúc HTML chuẩn, thêm trực tiếp
| lên đầu output để vẫn giữ asset hoạt động.
*/
if ($asset_head !== "") {

    $replace_count = 0;

    $template_output =
        preg_replace(
            '/<head\b[^>]*>/i',
            '$0' . $asset_head,
            $template_output,
            1,
            $replace_count
        );


    if ($replace_count === 0) {

        $html_replace_count = 0;

        $template_output =
            preg_replace(
                '/<html\b[^>]*>/i',
                '$0<head>' .
                $asset_head .
                '</head>',
                $template_output,
                1,
                $html_replace_count
            );


        if ($html_replace_count === 0) {

            $template_output =
                '<!DOCTYPE html><html lang="vi"><head>' .
                $asset_head .
                '</head><body>' .
                $template_output .
                '</body></html>';

        }

    }

}


echo $template_output;

?>