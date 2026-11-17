<?php

session_start();

require_once "../config/database.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
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
| Chỉ nhận POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: templates.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY ID MẪU
|--------------------------------------------------------------------------
*/

$template_id = filter_input(
    INPUT_POST,
    "template_id",
    FILTER_VALIDATE_INT
);

if (!$template_id || $template_id <= 0) {
    header("Location: templates.php?status=invalid");
    exit;
}

/*
|--------------------------------------------------------------------------
| LẤY TRẠNG THÁI HIỆN TẠI
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, status
     FROM templates
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    header("Location: templates.php?status=error");
    exit;
}

$stmt->bind_param("i", $template_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    header("Location: templates.php?status=not_found");
    exit;
}

$template = $result->fetch_assoc();
$stmt->close();

/*
|--------------------------------------------------------------------------
| KIỂM TRA TRẠNG THÁI
|--------------------------------------------------------------------------
*/

$current_status = $template["status"];

if (
    $current_status !== "active" &&
    $current_status !== "inactive"
) {
    header("Location: templates.php?status=invalid_status");
    exit;
}

/*
|--------------------------------------------------------------------------
| ĐỔI TRẠNG THÁI
|--------------------------------------------------------------------------
*/

$new_status = (
    $current_status === "active"
)
    ? "inactive"
    : "active";

/*
|--------------------------------------------------------------------------
| CẬP NHẬT DATABASE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "UPDATE templates
     SET status = ?
     WHERE id = ?"
);

if (!$stmt) {
    header("Location: templates.php?status=error");
    exit;
}

$stmt->bind_param(
    "si",
    $new_status,
    $template_id
);

if (!$stmt->execute()) {
    $stmt->close();

    header("Location: templates.php?status=error");
    exit;
}

$changed = $stmt->affected_rows;
$stmt->close();

/*
|--------------------------------------------------------------------------
| QUAY LẠI DANH SÁCH MẪU
|--------------------------------------------------------------------------
*/

if ($changed < 0) {
    header("Location: templates.php?status=error");
    exit;
}

header(
    "Location: templates.php?status=updated"
);

exit;

?>
