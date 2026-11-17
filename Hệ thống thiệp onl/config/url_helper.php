<?php
function wedding_slugify(string $text): string
{
    $text = trim($text);
    if ($text === "") return "thiep-cuoi";

    $map = [
        "Đ"=>"D","đ"=>"d","À"=>"A","Á"=>"A","Ả"=>"A","Ã"=>"A","Ạ"=>"A","Ă"=>"A","Ằ"=>"A","Ắ"=>"A","Ẳ"=>"A","Ẵ"=>"A","Ặ"=>"A","Â"=>"A","Ầ"=>"A","Ấ"=>"A","Ẩ"=>"A","Ẫ"=>"A","Ậ"=>"A",
        "à"=>"a","á"=>"a","ả"=>"a","ã"=>"a","ạ"=>"a","ă"=>"a","ằ"=>"a","ắ"=>"a","ẳ"=>"a","ẵ"=>"a","ặ"=>"a","â"=>"a","ầ"=>"a","ấ"=>"a","ẩ"=>"a","ẫ"=>"a","ậ"=>"a",
        "È"=>"E","É"=>"E","Ẻ"=>"E","Ẽ"=>"E","Ẹ"=>"E","Ê"=>"E","Ề"=>"E","Ế"=>"E","Ể"=>"E","Ễ"=>"E","Ệ"=>"E","è"=>"e","é"=>"e","ẻ"=>"e","ẽ"=>"e","ẹ"=>"e","ê"=>"e","ề"=>"e","ế"=>"e","ể"=>"e","ễ"=>"e","ệ"=>"e",
        "Ì"=>"I","Í"=>"I","Ỉ"=>"I","Ĩ"=>"I","Ị"=>"I","ì"=>"i","í"=>"i","ỉ"=>"i","ĩ"=>"i","ị"=>"i",
        "Ò"=>"O","Ó"=>"O","Ỏ"=>"O","Õ"=>"O","Ọ"=>"O","Ô"=>"O","Ồ"=>"O","Ố"=>"O","Ổ"=>"O","Ỗ"=>"O","Ộ"=>"O","Ơ"=>"O","Ờ"=>"O","Ớ"=>"O","Ở"=>"O","Ỡ"=>"O","Ợ"=>"O",
        "ò"=>"o","ó"=>"o","ỏ"=>"o","õ"=>"o","ọ"=>"o","ô"=>"o","ồ"=>"o","ố"=>"o","ổ"=>"o","ỗ"=>"o","ộ"=>"o","ơ"=>"o","ờ"=>"o","ớ"=>"o","ở"=>"o","ỡ"=>"o","ợ"=>"o",
        "Ù"=>"U","Ú"=>"U","Ủ"=>"U","Ũ"=>"U","Ụ"=>"U","Ư"=>"U","Ừ"=>"U","Ứ"=>"U","Ử"=>"U","Ữ"=>"U","Ự"=>"U","ù"=>"u","ú"=>"u","ủ"=>"u","ũ"=>"u","ụ"=>"u","ư"=>"u","ừ"=>"u","ứ"=>"u","ử"=>"u","ữ"=>"u","ự"=>"u",
        "Ỳ"=>"Y","Ý"=>"Y","Ỷ"=>"Y","Ỹ"=>"Y","Ỵ"=>"Y","ỳ"=>"y","ý"=>"y","ỷ"=>"y","ỹ"=>"y","ỵ"=>"y"
    ];

    $text = strtolower(strtr($text, $map));
    $text = preg_replace("/[^a-z0-9]+/", "-", $text);
    $text = trim($text, "-");

    return $text !== "" ? $text : "thiep-cuoi";
}

function ensure_unique_wedding_slug(mysqli $conn, int $wedding_id, string $groom_name, string $bride_name): string
{
    $base = wedding_slugify($groom_name . " " . $bride_name);
    $candidate = $base;
    $n = 2;

    while (true) {
        $stmt = $conn->prepare("SELECT id FROM weddings WHERE wedding_slug = ? AND id <> ? LIMIT 1");
        if (!$stmt) return $candidate;

        $stmt->bind_param("si", $candidate, $wedding_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$exists) return $candidate;

        $candidate = $base . "-" . $n;
        $n++;
    }
}

function generate_public_code(): string
{
    $alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $max = strlen($alphabet) - 1;
    $code = "";

    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }

    return $code;
}

function ensure_unique_public_code(mysqli $conn, int $link_id = 0): string
{
    do {
        $code = generate_public_code();
        $stmt = $conn->prepare("SELECT id FROM invitation_links WHERE public_code = ? AND id <> ? LIMIT 1");
        if (!$stmt) return $code;

        $stmt->bind_param("si", $code, $link_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $code;
}

function detect_app_base_url(): string
{
    $is_https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (isset($_SERVER["SERVER_PORT"]) && intval($_SERVER["SERVER_PORT"]) === 443);

    $scheme = $is_https ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $script = str_replace("\\", "/", $_SERVER["SCRIPT_NAME"] ?? "");
    $dir = dirname($script);

    if (str_ends_with($dir, "/customer")) {
        $dir = dirname($dir);
    }

    $dir = rtrim($dir, "/");
    if ($dir === "/" || $dir === ".") $dir = "";

    return $scheme . "://" . $host . $dir;
}

function build_public_invite_url(string $wedding_slug, string $public_code): string
{
    return rtrim(detect_app_base_url(), "/")
        . "/i/" . rawurlencode($wedding_slug)
        . "/" . rawurlencode($public_code);
}
