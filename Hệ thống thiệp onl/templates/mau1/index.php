<?php

/*
|--------------------------------------------------------------------------
| MẪU MAU01 - NHẬN DỮ LIỆU ĐỘNG TỪ HỆ THỐNG
|--------------------------------------------------------------------------
*/

$wedding = $wedding ?? [];
$guest = $guest ?? [];
$bride_image = is_array($bride_image ?? null) ? $bride_image : null;
$groom_image = is_array($groom_image ?? null) ? $groom_image : null;
$album_images = is_array($album_images ?? null) ? $album_images : [];

/*
|--------------------------------------------------------------------------
| LẤY ẢNH CÔ DÂU / CHÚ RỂ
|--------------------------------------------------------------------------
| Hệ thống wedding_images thực tế lưu ảnh cặp đôi với:
|     image_type = "couple"
|
| Một số trang preview có thể truyền riêng $bride_image/$groom_image,
| nhưng link thiệp công khai truyền $couple_images.
|
| Vì vậy template phải ưu tiên:
| 1. $bride_image / $groom_image nếu có.
| 2. $couple_images nếu trang gọi template có truyền.
| 3. Các kiểu bride/groom cũ nếu dữ liệu cũ tồn tại.
|--------------------------------------------------------------------------
*/

$wedding_image_id = intval(
    $wedding["id"] ??
    $wedding["wedding_id"] ??
    0
);

if (
    (
        !is_array($bride_image) ||
        empty($bride_image["image_path"])
    ) &&
    isset($conn) &&
    $conn instanceof mysqli &&
    $wedding_image_id > 0
) {
    $stmt_image = $conn->prepare(
        "SELECT id, image_path, image_type, image_order, created_at
         FROM wedding_images
         WHERE wedding_id = ?
           AND image_type = 'bride'
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($stmt_image) {
        $stmt_image->bind_param("i", $wedding_image_id);
        $stmt_image->execute();

        $result_image = $stmt_image->get_result();

        if ($result_image && $result_image->num_rows === 1) {
            $bride_image = $result_image->fetch_assoc();
        }

        $stmt_image->close();
    }
}

if (
    (
        !is_array($groom_image) ||
        empty($groom_image["image_path"])
    ) &&
    isset($conn) &&
    $conn instanceof mysqli &&
    $wedding_image_id > 0
) {
    $stmt_image = $conn->prepare(
        "SELECT id, image_path, image_type, image_order, created_at
         FROM wedding_images
         WHERE wedding_id = ?
           AND image_type = 'groom'
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($stmt_image) {
        $stmt_image->bind_param("i", $wedding_image_id);
        $stmt_image->execute();

        $result_image = $stmt_image->get_result();

        if ($result_image && $result_image->num_rows === 1) {
            $groom_image = $result_image->fetch_assoc();
        }

        $stmt_image->close();
    }
}



/*
 * Hàm tìm ảnh theo các kiểu dữ liệu cũ.
 */
$findWeddingImage = static function (array $images, array $types): ?array {
    foreach ($images as $image) {
        $type = strtolower(trim((string) ($image["image_type"] ?? "")));

        if (
            in_array($type, $types, true) &&
            !empty($image["image_path"])
        ) {
            return $image;
        }
    }

    return null;
};

/*
 * Nếu hệ thống truyền ảnh riêng thì giữ nguyên.
 * Nếu không, lấy 2 ảnh đầu tiên của image_type=couple.
 */
if (
    !is_array($bride_image) ||
    empty($bride_image["image_path"])
) {
    $bride_image = $findWeddingImage(
        $album_images,
        ["bride", "bride_image", "bride_photo", "co_dau", "codau", "anh_co_dau"]
    );

    if (
        $bride_image === null &&
        isset($couple_images[0])
    ) {
        $bride_image = $couple_images[0];
    }
}

if (
    !is_array($groom_image) ||
    empty($groom_image["image_path"])
) {
    $groom_image = $findWeddingImage(
        $album_images,
        ["groom", "groom_image", "groom_photo", "chu_re", "chubre", "anh_chu_re"]
    );

    if (
        $groom_image === null &&
        isset($couple_images[1])
    ) {
        $groom_image = $couple_images[1];
    }
}

$bride_name = trim((string) ($wedding["bride_name"] ?? "Hoài Thư"));
$groom_name = trim((string) ($wedding["groom_name"] ?? "Thanh Tùng"));
$guest_name = trim((string) ($guest["guest_name"] ?? "Quý khách"));

if ($bride_name === "") $bride_name = "Hoài Thư";
if ($groom_name === "") $groom_name = "Thanh Tùng";
if ($guest_name === "") $guest_name = "Quý khách";


/*
|--------------------------------------------------------------------------
| THÔNG TIN ĐỘNG TỪ customer/wedding_info.php
|--------------------------------------------------------------------------
*/

$groom_role = trim((string) ($wedding["groom_role"] ?? ""));
$bride_role = trim((string) ($wedding["bride_role"] ?? ""));

$groom_father_name = trim((string) ($wedding["groom_father_name"] ?? ""));
$groom_mother_name = trim((string) ($wedding["groom_mother_name"] ?? ""));
$groom_home_address = trim((string) ($wedding["groom_home_address"] ?? ""));

$bride_father_name = trim((string) ($wedding["bride_father_name"] ?? ""));
$bride_mother_name = trim((string) ($wedding["bride_mother_name"] ?? ""));
$bride_home_address = trim((string) ($wedding["bride_home_address"] ?? ""));

$wedding_date_raw = trim((string) ($wedding["wedding_date"] ?? ""));
$wedding_time_raw = trim((string) ($wedding["wedding_time"] ?? ""));
$venue = trim((string) ($wedding["venue"] ?? ""));
$address = trim((string) ($wedding["address"] ?? ""));
$google_maps_url = trim((string) ($wedding["google_maps_url"] ?? ""));
$description = trim((string) ($wedding["description"] ?? ""));

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
};

$formatWeddingDate = static function (string $date): array {
    $result = ["display" => "", "long" => "", "weekday" => ""];
    if ($date === "") return $result;

    try {
        $dt = new DateTime($date);
        $weekdays = [
            "Sunday" => "Chủ Nhật",
            "Monday" => "Thứ Hai",
            "Tuesday" => "Thứ Ba",
            "Wednesday" => "Thứ Tư",
            "Thursday" => "Thứ Năm",
            "Friday" => "Thứ Sáu",
            "Saturday" => "Thứ Bảy",
        ];
        $result["display"] = $dt->format("d • m • Y");
        $result["long"] = "Ngày " . $dt->format("d") . " tháng " . $dt->format("m") . " năm " . $dt->format("Y");
        $result["weekday"] = $weekdays[$dt->format("l")] ?? "";
    } catch (Throwable $exception) {
        $result["display"] = $date;
        $result["long"] = $date;
    }

    return $result;
};

$formatWeddingTime = static function (string $time): string {
    if ($time === "") return "";
    try {
        return (new DateTime($time))->format("H:i");
    } catch (Throwable $exception) {
        return $time;
    }
};

$date_info = $formatWeddingDate($wedding_date_raw);
$wedding_date_display = $date_info["display"];
$wedding_date_long = $date_info["long"];
$wedding_weekday = $date_info["weekday"];
$wedding_time_display = $formatWeddingTime($wedding_time_raw);


/*
|--------------------------------------------------------------------------
| TẠO URL CHO ẢNH ĐỘNG
|--------------------------------------------------------------------------
|
| Ảnh mẫu gốc nằm trong thư mục template nên dùng đường dẫn tương đối.
| Ảnh khách tải lên nằm ở uploads/... tại thư mục gốc hệ thống.
|
*/
/*
 * Ảnh upload phải dùng URL tuyệt đối theo BASE_URL.
 * Nếu không, khi mở thiệp bằng URL đẹp /i/.../...,
 * đường dẫn "uploads/..." sẽ bị trình duyệt ghép sai.
 */
$app_base_url = trim((string) ($app_base_url ?? ""));

if ($app_base_url === "" && defined("BASE_URL")) {
    $app_base_url = trim((string) BASE_URL);
}

/*
 * Fallback khi BASE_URL chưa được nạp.
 */
if ($app_base_url === "") {
    $https = (
        (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        (($_SERVER["SERVER_PORT"] ?? "") === "443")
    );

    $scheme = $https ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");

    if (preg_match('#^(.+?)/invite\\.php$#i', $script, $m)) {
        $project_path = $m[1];
    } elseif (preg_match('#^(.+?)/admin/[^/]+\\.php$#i', $script, $m)) {
        $project_path = $m[1];
    } else {
        $project_path = rtrim(dirname($script), "/");
    }

    $app_base_url = $scheme . "://" . $host . $project_path;
}

/*
 * Chuyển đường dẫn ảnh thành URL đúng.
 */
$imageUrl = static function (string $path) use ($app_base_url): string {
    $path = trim($path);

    if ($path === "") {
        return "";
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_starts_with($path, "//")) {
        return $path;
    }

    $path = str_replace("\\", "/", $path);
    $path = ltrim($path, "/");

    if (
        str_starts_with($path, "uploads/") ||
        str_starts_with($path, "upload/")
    ) {
        return rtrim($app_base_url, "/") . "/" . $path;
    }

    return $path;
};

/*
 * Template base giúp CSS/JS/ảnh mẫu vẫn đúng khi thiệp mở bằng
 * /i/slug/code thay vì URL thư mục template.
 */
$template_base_url = trim((string) ($template_base_url ?? ""));

if (
    $template_base_url === "" &&
    !empty($template["folder"]) &&
    $app_base_url !== ""
) {
    $template_base_url =
        rtrim($app_base_url, "/") .
        "/" .
        trim((string) $template["folder"], "/") .
        "/";
}

/*
|--------------------------------------------------------------------------
| GOOGLE MAPS
|--------------------------------------------------------------------------
| google_maps_url thường là link chia sẻ Google Maps (maps.app.goo.gl,
| google.com/maps/..., v.v.) và không phải lúc nào cũng nhúng trực tiếp
| được bằng iframe.
|
| Vì vậy:
| - Nút "Xem bản đồ" dùng đúng google_maps_url người dùng đã nhập.
| - Ô bản đồ dùng Google Maps q=...&output=embed theo địa chỉ/địa điểm.
|--------------------------------------------------------------------------
*/

$google_maps_link = $google_maps_url;

$map_query = trim(
    $venue !== "" && $address !== ""
        ? $venue . ", " . $address
        : ($address !== "" ? $address : $venue)
);

$google_maps_embed_url = "";

if ($map_query !== "") {
    $google_maps_embed_url =
        "https://www.google.com/maps?q=" .
        rawurlencode($map_query) .
        "&output=embed";
}

/*
 * Nếu dữ liệu đã là URL embed Google Maps thì ưu tiên URL đó.
 */
if (
    $google_maps_url !== "" &&
    preg_match(
        '#^https?://(www\.)?google\.[^/]+/maps/embed#i',
        $google_maps_url
    )
) {
    $google_maps_embed_url = $google_maps_url;
}

$bride_image_url =
    !empty($bride_image["image_path"])
        ? $imageUrl((string) $bride_image["image_path"])
        : "image/1.jpg";

$groom_image_url =
    !empty($groom_image["image_path"])
        ? $imageUrl((string) $groom_image["image_path"])
        : "image/2.jpg";

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if ($template_base_url !== ""): ?>
        <base href="<?= htmlspecialchars($template_base_url, ENT_QUOTES, "UTF-8") ?>">
    <?php endif; ?>

    <title>Thiệp mời <?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?> - <?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?></title>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600&family=Cormorant+Garamond:wght@400;500;600&family=Great+Vibes&family=Noto+Serif:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

    <link rel="stylesheet" href="style.css">


    <style>
        .guest-invitation {
            margin: 18px 0 22px;
            text-align: center;
        }

        .guest-invitation .guest-title {
            margin: 0 0 4px;
            font-family: "Cormorant Garamond", serif;
            font-size: 20px;
            font-style: italic;
            font-weight: 500;
            letter-spacing: .7px;
            color: #7d1717;
        }

        .guest-invitation .guest-name {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 600;
            line-height: 1.2;
            color: #8b0000;
            word-break: break-word;
        }
    </style>

</head>

<body>
    
    <section id="welcome">

        <div class="door door-left"></div>
        <div class="door door-right"></div>

        <div class="envelope">

            <div class="letter">

                <p>SAVE THE DATE</p>

                <h1><?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?></h1>
                <h1>&</h1>
                <h1><?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?></h1>

                <div class="guest-invitation">
                    <div class="guest-title">Trân trọng kính mời:</div>
                    <div class="guest-name"><?= htmlspecialchars($guest_name, ENT_QUOTES, "UTF-8") ?></div>
                </div>

                <button id="openCard" type="button">Mở Thiệp</button>
            </div>
        </div>
    </section>

    <main id="card">
        <section id="home" class="home-section">

        <div class="home-content">
            <div class="double-happiness">囍</div>

            <p class="save-date">SAVE THE DATE</p>

            <h1 class="couple-names">
                <?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?>
                <span>&</span>
                <?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?>
            </h1>

            <?php if ($wedding_date_display !== ""): ?>
                <div class="home-date">
                    <?= $e($wedding_date_display) ?>
                </div>
            <?php endif; ?>

            <?php if (
                $bride_role !== "" || $groom_role !== "" ||
                $bride_father_name !== "" || $bride_mother_name !== "" || $bride_home_address !== "" ||
                $groom_father_name !== "" || $groom_mother_name !== "" || $groom_home_address !== ""
            ): ?>
                <div class="families">

                    <div class="family">
                        <h3>NHÀ GÁI</h3>
                        <div class="family-line"></div>
                        <?php if ($bride_role !== ""): ?><p><?= $e($bride_role) ?></p><?php endif; ?>
                        <?php if ($bride_father_name !== ""): ?><p>Ông: <?= $e($bride_father_name) ?></p><?php endif; ?>
                        <?php if ($bride_mother_name !== ""): ?><p>Bà: <?= $e($bride_mother_name) ?></p><?php endif; ?>
                        <?php if ($bride_home_address !== ""): ?><p class="family-address">Địa chỉ: <?= $e($bride_home_address) ?></p><?php endif; ?>
                    </div>

                    <div class="family">
                        <h3>NHÀ TRAI</h3>
                        <div class="family-line"></div>
                        <?php if ($groom_role !== ""): ?><p><?= $e($groom_role) ?></p><?php endif; ?>
                        <?php if ($groom_father_name !== ""): ?><p>Ông: <?= $e($groom_father_name) ?></p><?php endif; ?>
                        <?php if ($groom_mother_name !== ""): ?><p>Bà: <?= $e($groom_mother_name) ?></p><?php endif; ?>
                        <?php if ($groom_home_address !== ""): ?><p class="family-address">Địa chỉ: <?= $e($groom_home_address) ?></p><?php endif; ?>
                    </div>

                </div>
            <?php endif; ?>

        </div>

    </section>

    <!--lởi mời-->
    <section id="invitation" class="invitation-section">

        <div class="section-inner">

            <div class="section-symbol">❦</div>

            <p class="section-subtitle">
                WEDDING INVITATION</p>

            <h2>Trân trọng kính mời</h2>

            <p class="invitation-text">
                <?php if ($description !== ""): ?>
                    <?= nl2br($e($description)) ?>
                <?php else: ?>
                    Gia đình chúng tôi vô cùng hân hạnh
                    được đón tiếp quý khách đến tham dự
                    buổi tiệc chung vui cùng gia đình
                    nhân ngày trọng đại của
                <?php endif; ?>
            </p>

            <h3 class="invitation-names">
                <?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?>
                <span>&</span>
                <?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?>
            </h3>

            <?php if ($wedding_time_display !== "" || $wedding_date_long !== ""): ?>
                <p class="invitation-date">
                    <?php if ($wedding_time_display !== ""): ?>Vào lúc <?= $e($wedding_time_display) ?><?php endif; ?>
                    <?php if ($wedding_weekday !== ""): ?><?= $wedding_time_display !== "" ? " — " : "" ?><?= $e($wedding_weekday) ?><?php endif; ?>
                    <?php if ($wedding_date_long !== ""): ?><br><?= $e($wedding_date_long) ?><?php endif; ?>
                </p>
            <?php endif; ?>

            <div class="gold-line"></div>
            <p class="invitation-note">
                Sự hiện diện của quý khách
                là niềm vinh hạnh cho gia đình chúng tôi.
            </p>

        </div>

    </section>

<!-- Cdau&cre -->
    <section id="couple" class="couple-section">

        <div class="section-inner">

            <p class="section-subtitle">
                OUR STORY</p>
            <h2>Cô Dâu & Chú Rể</h2>

            <div class="couple-wrapper">

                <!--cdaau-->
                <div class="person">
                    <div class="person-image">
                        <img
                            src="<?= $e($bride_image_url) ?>"
                            alt="Cô dâu <?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?>">
                    </div>
                    <h3><?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?></h3>
                    <p>Cô dâu </p>

                    <div class="person-line"></div>
                </div>

                <div class="couple-and">&</div>

                <!-- Cre -->
                <div class="person">
                    <div class="person-image">
                        <img
                            src="<?= $e($groom_image_url) ?>"
                            alt="Chú rể <?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?>">
                    </div>
                    <h3><?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?></h3>
                    <p>Chú rể</p>
                    <div class="person-line"></div>
                </div>

            </div>

        </div>

    </section>

    <!-- album ảnh-->
    <section id="album" class="album-section">

        <div class="section-inner">

            <p class="section-subtitle">OUR MEMORIES </p>

            <h2>Album Ảnh Cưới</h2>
            <div class="album-grid">

                <?php if (!empty($album_images)): ?>

                    <?php foreach ($album_images as $index => $image): ?>

                        <?php
                        $classes = "album-item";

                        if ($index === 0) {
                            $classes .= " album-large";
                        } elseif ($index === 4) {
                            $classes .= " album-wide";
                        }

                        $album_path = $imageUrl((string) ($image["image_path"] ?? ""));
                        ?>

                        <div class="<?= $classes ?>">
                            <img
                                src="<?= htmlspecialchars($album_path, ENT_QUOTES, "UTF-8") ?>"
                                alt="Ảnh cưới <?= $index + 1 ?>">
                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="album-item album-large">
                        <img src="image/1.jpg" alt="Ảnh cưới 1">
                    </div>

                    <div class="album-item">
                        <img src="image/2.jpg" alt="Ảnh cưới 2">
                    </div>

                    <div class="album-item">
                        <img src="image/3.jpg" alt="Ảnh cưới 3">
                    </div>

                    <div class="album-item">
                        <img src="image/4.jpg" alt="Ảnh cưới 4">
                    </div>

                    <div class="album-item album-wide">
                        <img src="image/5.jpg" alt="Ảnh cưới 5">
                    </div>

                    <div class="album-item">
                        <img src="image/6.jpg" alt="Ảnh cưới 6">
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>



    <!-- thông tin tiệc-->
    <section id="event" class="event-section">

        <div class="section-inner">

            <div class="section-symbol">囍</div>
            <p class="section-subtitle">WEDDING RECEPTION</p>
            <h2>Thông Tin Tiệc Cưới</h2>

            <div class="event-cards">

                <?php if ($wedding_date_display !== "" || $wedding_weekday !== ""): ?>
                    <div class="event-card">
                        <div class="event-icon"><i class="fa-regular fa-calendar"></i></div>
                        <h3>Ngày tổ chức</h3>
                        <?php if ($wedding_weekday !== ""): ?><p><?= $e($wedding_weekday) ?></p><?php endif; ?>
                        <?php if ($wedding_date_display !== ""): ?><strong><?= $e($wedding_date_display) ?></strong><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($wedding_time_display !== ""): ?>
                    <div class="event-card">
                        <div class="event-icon"><i class="fa-regular fa-clock"></i></div>
                        <h3>Thời gian</h3>
                        <p>Thời gian tổ chức</p>
                        <strong><?= $e($wedding_time_display) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($venue !== ""): ?>
                    <div class="event-card">
                        <div class="event-icon"><i class="fa-solid fa-champagne-glasses"></i></div>
                        <h3>Tiệc cưới</h3>
                        <p>Tiệc mừng hạnh phúc</p>
                        <strong><?= $e($venue) ?></strong>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </section>



    <!--địa chỉ-->
    <section id="location" class="location-section">

        <div class="section-inner">

            <p class="section-subtitle">WEDDING LOCATION</p>
            <h2>Địa Điểm</h2>

            <div class="location-content">
                <div class="location-info">
                    <div class="location-icon">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <h3><?= $venue !== "" ? $e($venue) : "ĐỊA ĐIỂM TIỆC CƯỚI" ?></h3>
                    <p>Địa chỉ tổ chức tiệc cưới</p>

                    <?php if ($address !== ""): ?>
                        <p><?= nl2br($e($address)) ?></p>
                    <?php endif; ?>

                    <?php if ($google_maps_url !== ""): ?>
                        <a href="<?= $e($google_maps_url) ?>" class="map-button" target="_blank" rel="noopener noreferrer">
                            <i class="fa-solid fa-map-location-dot"></i>
                            Xem bản đồ
                        </a>
                    <?php endif; ?>
                </div>

                <div class="map-box">
                    <?php if ($google_maps_embed_url !== ""): ?>

                        <iframe
                            src="<?= $e($google_maps_embed_url) ?>"
                            width="100%"
                            height="100%"
                            style="border:0; min-height:360px; display:block;"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                            title="Bản đồ <?= $e($venue !== "" ? $venue : "địa điểm tiệc cưới") ?>"
                        ></iframe>

                    <?php elseif ($google_maps_link !== ""): ?>

                        <div class="map-placeholder">
                            <i class="fa-solid fa-map-location-dot"></i>
                            <p>Đã có địa chỉ Google Maps</p>

                            <a
                                href="<?= $e($google_maps_link) ?>"
                                class="map-button"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                Mở Google Maps
                            </a>
                        </div>

                    <?php else: ?>

                        <div class="map-placeholder">
                            <i class="fa-solid fa-map-location-dot"></i>
                            <p>Chưa có thông tin bản đồ</p>
                        </div>

                    <?php endif; ?>
                </div>
            </div>

        </div>

    </section>



    <!-- lưu bút -->
    <section id="guestbook" class="guestbook-section">

        <div class="section-inner">

            <div class="section-symbol">♡</div>

            <p class="section-subtitle">
                GUESTBOOK
            </p>

            <h2>Sổ Lưu Bút</h2>

            <p class="guestbook-intro">
                Hãy để lại những lời chúc tốt đẹp
                dành cho cô dâu và chú rể nhé!
            </p>


            <form id="guestbookForm" class="guestbook-form">

                <div class="form-group">

                    <input
                        type="text"
                        id="guestName"
                        placeholder="Tên của bạn"
                        required>

                </div>


                <div class="form-group">

                    <textarea
                        id="guestMessage"
                        rows="5"
                        placeholder="Gửi lời chúc đến cô dâu chú rể..."
                        required></textarea>

                </div>


                <button
                    type="submit"
                    class="submit-button">

                    <i class="fa-regular fa-paper-plane"></i>

                    Gửi lời chúc

                </button>

            </form>


            <div id="messages" class="messages">

                <!-- Lời chúc sẽ xuất hiện ở đây -->

            </div>

        </div>

    </section>



    <!-- lời cảm ơn-->
    <section id="thankyou" class="thankyou-section">

        <div class="thankyou-content">

            <div class="double-happiness">
                囍
            </div>

            <p class="section-subtitle">
                THANK YOU
            </p>

            <h2>
                Cảm Ơn
            </h2>

            <p>
                Cảm ơn bạn đã dành thời gian
                đến chung vui và gửi những lời
                chúc tốt đẹp nhất đến chúng mình.
            </p>

            <h3>
                <?= htmlspecialchars($bride_name, ENT_QUOTES, "UTF-8") ?>
                <span>&</span>
                <?= htmlspecialchars($groom_name, ENT_QUOTES, "UTF-8") ?>
            </h3>

            <?php if ($wedding_date_display !== ""): ?>
                <div class="thankyou-date">
                    <?= $e($wedding_date_display) ?>
                </div>
            <?php endif; ?>

            <div class="thankyou-symbol">♡</div>

        </div>

    </section>

</main>

<footer class="wedding-footer">
    <div class="footer-content">

        <div class="footer-names">
            <?= $e($bride_name) ?>
            <span>&</span>
            <?= $e($groom_name) ?>
        </div>

        <p class="footer-subtitle">
            WEDDING INVITATION
        </p>

        <div class="footer-menu">
            <a href="#home">TRANG CHỦ</a>
            <a href="#invitation">LỜI MỜI</a>
            <a href="#couple">CÔ DÂU & CHÚ RỂ</a>
            <a href="#album">ALBUM</a>
            <a href="#event">TIỆC CƯỚI</a>
            <a href="#location">ĐỊA ĐIỂM</a>
            <a href="#guestbook">LƯU BÚT</a>
        </div>

        <div class="footer-line"></div>

        <div class="copyright">
            © 2026 Wedding Invitation
        </div>

        <div class="designer">
            Designed & Crafted by <strong>Thùy Nhung</strong>
        </div>

    </div>

</footer>    

<script src="script.js"></script>

</body>

</html>