<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| CHỐNG CACHE
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

    header("Location: ../auth/login.php");
    exit;

}


$user_id = intval($_SESSION["user_id"]);


/* =====================================================
   PRODUCTION HELPERS
   ===================================================== */
function invitation_base_url(): string
{
    $forwarded_proto = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "";
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || strtolower($forwarded_proto) === "https";

    $scheme = $https ? "https" : "http";

    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);

    $base_path = dirname(dirname($_SERVER["SCRIPT_NAME"] ?? "/"));
    $base_path = rtrim($base_path, "/\\");

    return $scheme . "://" . $host . $base_path;
}

function invitation_fail(string $public_message, string $log_message = ""): never
{
    if ($log_message !== "") {
        error_log("[Create Invitation Link] " . $log_message);
    }

    header("Location: guests.php?error=" . rawurlencode($public_message));
    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY GUEST ID
|--------------------------------------------------------------------------
*/

$guest_id = intval(
    $_GET["guest_id"] ?? 0
);


if ($guest_id <= 0) {

    invitation_fail("Khách mời không hợp lệ.");

}


/*
|--------------------------------------------------------------------------
| LẤY WEDDING CỦA CUSTOMER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id
     FROM weddings
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);


if (!$stmt) {

    invitation_fail(
        "Có lỗi hệ thống. Vui lòng thử lại sau.",
        "Database prepare failed: " . $conn->error
    );

}


$stmt->bind_param(
    "i",
    $user_id
);


if (!$stmt->execute()) {

    invitation_fail(
        "Có lỗi hệ thống. Vui lòng thử lại sau.",
        "Database execute failed: " . $stmt->error
    );

}


$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    invitation_fail("Không tìm thấy thiệp cưới.");

}


$wedding = $result->fetch_assoc();


$wedding_id =
    intval($wedding["id"]);


$stmt->close();


/*
|--------------------------------------------------------------------------
| KIỂM TRA KHÁCH CÓ THUỘC WEDDING NÀY KHÔNG
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        guest_name
     FROM guests
     WHERE id = ?
       AND wedding_id = ?
     LIMIT 1"
);


if (!$stmt) {

    invitation_fail(
        "Có lỗi hệ thống. Vui lòng thử lại sau.",
        "Database prepare failed: " . $conn->error
    );

}


$stmt->bind_param(
    "ii",
    $guest_id,
    $wedding_id
);


if (!$stmt->execute()) {

    invitation_fail(
        "Có lỗi hệ thống. Vui lòng thử lại sau.",
        "Database execute failed: " . $stmt->error
    );

}


$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    invitation_fail("Không tìm thấy khách mời hoặc bạn không có quyền tạo link.");

}


$guest = $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| KIỂM TRA LINK ĐÃ TỒN TẠI
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        token
     FROM invitation_links
     WHERE guest_id = ?
       AND wedding_id = ?
     LIMIT 1"
);


if (!$stmt) {

    invitation_fail(
        "Có lỗi hệ thống. Vui lòng thử lại sau.",
        "Database prepare failed: " . $conn->error
    );

}


$stmt->bind_param(
    "ii",
    $guest_id,
    $wedding_id
);


if (!$stmt->execute()) {

    invitation_fail(
        "Có lỗi hệ thống. Vui lòng thử lại sau.",
        "Database execute failed: " . $stmt->error
    );

}


$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| NẾU ĐÃ CÓ LINK
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 1) {

    $existing =
        $result->fetch_assoc();

    $token =
        $existing["token"];

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | TẠO URL
    |--------------------------------------------------------------------------
    */

    $invite_url = invitation_base_url() .
        "/invite.php?token=" . rawurlencode($token);


    header(
        "Location: guests.php?link=" .
        rawurlencode($invite_url)
    );

    exit;

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| TẠO TOKEN MỚI
|--------------------------------------------------------------------------
*/

do {

    $token =
        bin2hex(
            random_bytes(32)
        );


    $check =
        $conn->prepare(
            "SELECT id
             FROM invitation_links
             WHERE token = ?
             LIMIT 1"
        );


    if (!$check) {

        invitation_fail(
            "Không thể tạo link thiệp. Vui lòng thử lại sau.",
            "Token check prepare failed: " . $conn->error
        );

    }


    $check->bind_param(
        "s",
        $token
    );


    if (!$check->execute()) {

        $check_error =
            $check->error;

        $check->close();

        invitation_fail("Không thể tạo link thiệp. Vui lòng thử lại sau.", "Token check failed: " . $check_error);

    }


    $check_result =
        $check->get_result();


    $token_exists =
        $check_result->num_rows > 0;


    $check->close();


} while (
    $token_exists
);


/*
|--------------------------------------------------------------------------
| LƯU LINK
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "INSERT INTO invitation_links
    (
        wedding_id,
        guest_id,
        token
    )
    VALUES
    (
        ?,
        ?,
        ?
    )"
);


if (!$stmt) {

    invitation_fail("Không thể tạo link thiệp. Vui lòng thử lại sau.", "Invitation insert prepare failed: " . $conn->error);

}


$stmt->bind_param(
    "iis",
    $wedding_id,
    $guest_id,
    $token
);


/*
|--------------------------------------------------------------------------
| THỰC HIỆN INSERT
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    /*
    |--------------------------------------------------------------------------
    | TRƯỜNG HỢP LINK ĐÃ ĐƯỢC TẠO ĐỒNG THỜI
    |--------------------------------------------------------------------------
    */

    if ($stmt->errno == 1062) {

        $stmt->close();


        $stmt = $conn->prepare(
            "SELECT
                token
             FROM invitation_links
             WHERE guest_id = ?
               AND wedding_id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            invitation_fail(
                "Không thể tạo link thiệp. Vui lòng thử lại sau.",
                "Concurrent link lookup prepare failed: " . $conn->error
            );
        }

        $stmt->bind_param(
            "ii",
            $guest_id,
            $wedding_id
        );

        if (!$stmt->execute()) {
            $lookup_error = $stmt->error;
            $stmt->close();

            invitation_fail(
                "Không thể tạo link thiệp. Vui lòng thử lại sau.",
                "Concurrent link lookup execute failed: " . $lookup_error
            );
        }


        $result =
            $stmt->get_result();


        if ($result->num_rows === 1) {

            $existing =
                $result->fetch_assoc();

            $token =
                $existing["token"];

        }
        else {

            invitation_fail("Không thể tạo link thiệp. Vui lòng thử lại sau.");

        }


        $stmt->close();

    }
    else {

        $error =
            $stmt->error;

        $stmt->close();


        invitation_fail("Không thể tạo link thiệp. Vui lòng thử lại sau.", "Invitation insert failed: " . $error);

    }

}
else {

    $stmt->close();

}


/*
|--------------------------------------------------------------------------
| TẠO LINK THIỆP
|--------------------------------------------------------------------------
|
| Không hard-code /ThiepOnl.
| Tự lấy đường dẫn từ website hiện tại.
|
*/

$invite_url = invitation_base_url() .
        "/invite.php?token=" . rawurlencode($token);

/* QUAY LẠI TRANG KHÁCH MỜI*/

header(
    "Location: guests.php?link=" .
    rawurlencode($invite_url)
);

exit;

?>