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
| CHỈ CHO PHÉP POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: templates.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA CSRF
|--------------------------------------------------------------------------
*/

if (
    !isset($_POST["csrf_token"]) ||
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_POST["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {
    header("Location: templates.php?error=invalid_request");
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY TEMPLATE ID
|--------------------------------------------------------------------------
*/

$template_id =
    intval(
        $_POST["template_id"] ?? 0
    );


/*
|--------------------------------------------------------------------------
| LẤY WEDDING ID ĐANG QUẢN LÝ
|--------------------------------------------------------------------------
|
| templates.php gửi wedding_id để Customer chọn mẫu
| cho đúng thiệp đang thao tác.
|
*/

$requested_wedding_id =
    intval(
        $_POST["wedding_id"] ?? 0
    );


$templates_url =
    "templates.php" .
    (
        $requested_wedding_id > 0
            ? "?wedding_id=" .
              $requested_wedding_id
            : ""
    );


/*
|--------------------------------------------------------------------------
| HÀM QUAY LẠI TRANG CHỌN MẪU
|--------------------------------------------------------------------------
*/

function redirectTemplates(
    string $base_url,
    string $error_code
): void {

    $separator =
        strpos(
            $base_url,
            "?"
        ) === false
            ? "?"
            : "&";


    header(
        "Location: " .
        $base_url .
        $separator .
        "error=" .
        urlencode($error_code)
    );

    exit;
}


if ($template_id <= 0) {

    redirectTemplates(
        $templates_url,
        "invalid_template"
    );
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA TEMPLATE
|--------------------------------------------------------------------------
|
| Chỉ cho phép chọn mẫu đang active.
|
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, template_name, folder
     FROM templates
     WHERE id = ?
       AND status = 'active'
     LIMIT 1"
);

if (!$stmt) {
    redirectTemplates($templates_url, "template_check");
}

$stmt->bind_param("i", $template_id);
$stmt->execute();

$result = $stmt->get_result();
$template = $result->fetch_assoc();

$stmt->close();

if (!$template) {
    redirectTemplates($templates_url, "template_unavailable");
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA FOLDER MẪU
|--------------------------------------------------------------------------
*/

$folder = trim($template["folder"] ?? "");

if (
    $folder === "" ||
    !preg_match('/^[a-zA-Z0-9_\/-]+$/', $folder)
) {
    redirectTemplates($templates_url, "invalid_folder");
}

$template_file = "../" . $folder . "/index.php";

if (!is_file($template_file)) {
    redirectTemplates($templates_url, "template_not_installed");
}

/*
|--------------------------------------------------------------------------
| TÌM WEDDING CỦA CUSTOMER
|--------------------------------------------------------------------------
|
| Luôn kiểm tra user_id để Customer chỉ thao tác
| trên wedding của chính mình.
|
|--------------------------------------------------------------------------
*/

if ($requested_wedding_id > 0) {

    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA ĐÚNG WEDDING ĐƯỢC CHỌN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "SELECT id
         FROM weddings
         WHERE id = ?
           AND user_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        redirectTemplates(
            $templates_url,
            "wedding_check"
        );
    }

    $stmt->bind_param(
        "ii",
        $requested_wedding_id,
        $user_id
    );

} else {

    /*
    |--------------------------------------------------------------------------
    | FALLBACK CHO LUỒNG CŨ
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "SELECT id
         FROM weddings
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        redirectTemplates(
            $templates_url,
            "wedding_check"
        );
    }

    $stmt->bind_param(
        "i",
        $user_id
    );
}


$stmt->execute();

$result =
    $stmt->get_result();

$wedding =
    $result->fetch_assoc();

$stmt->close();

/*
|--------------------------------------------------------------------------
| CẬP NHẬT / TẠO WEDDING
|--------------------------------------------------------------------------
*/

if ($wedding) {

    $wedding_id = intval($wedding["id"]);

    /*
    |----------------------------------------------------------------------
    | Cập nhật mẫu cho đúng wedding của Customer
    |----------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "UPDATE weddings
         SET template_id = ?
         WHERE id = ?
           AND user_id = ?"
    );

    if (!$stmt) {
        redirectTemplates($templates_url, "update_failed");
    }

    $stmt->bind_param(
        "iii",
        $template_id,
        $wedding_id,
        $user_id
    );

    if (!$stmt->execute()) {
        $stmt->close();

        redirectTemplates($templates_url, "update_failed");
    }

    $stmt->close();

} else {

    /*
    |----------------------------------------------------------------------
    | Tạo wedding_code duy nhất
    |----------------------------------------------------------------------
    */

    do {

        $wedding_code =
            "W" .
            strtoupper(
                bin2hex(
                    random_bytes(8)
                )
            );

        $check = $conn->prepare(
            "SELECT id
             FROM weddings
             WHERE wedding_code = ?
             LIMIT 1"
        );

        if (!$check) {
            redirectTemplates($templates_url, "code_check");
        }

        $check->bind_param(
            "s",
            $wedding_code
        );

        $check->execute();

        $check_result = $check->get_result();

        $code_exists = $check_result->num_rows > 0;

        $check->close();

    } while ($code_exists);

    /*
    |----------------------------------------------------------------------
    | Tạo wedding
    |----------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "INSERT INTO weddings
        (
            user_id,
            template_id,
            wedding_code,
            groom_name,
            bride_name
        )
        VALUES
        (
            ?,
            ?,
            ?,
            '',
            ''
        )"
    );

    if (!$stmt) {
        redirectTemplates($templates_url, "create_failed");
    }

    $stmt->bind_param(
        "iis",
        $user_id,
        $template_id,
        $wedding_code
    );

    if (!$stmt->execute()) {
        $stmt->close();

        redirectTemplates($templates_url, "create_failed");
    }

    $wedding_id = $conn->insert_id;

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| CHUYỂN SANG NHẬP THÔNG TIN THIỆP
|--------------------------------------------------------------------------
*/

header(
    "Location: wedding_info.php?id=" .
    intval($wedding_id)
);

exit;
?>
