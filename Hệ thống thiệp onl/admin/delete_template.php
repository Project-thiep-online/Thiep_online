<?php

session_start();

require_once "../config/database.php";


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
| CHỈ CHO PHÉP POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: templates.php?error=invalid_request");
    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY TEMPLATE ID
|--------------------------------------------------------------------------
*/

$template_id = intval(
    $_POST["template_id"] ?? 0
);

if ($template_id <= 0) {
    header("Location: templates.php?error=invalid_request");
    exit;
}


/*
|--------------------------------------------------------------------------
| KIỂM TRA TEMPLATE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id, template_name
     FROM templates
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    header("Location: templates.php?error=delete_failed");
    exit;
}

$stmt->bind_param(
    "i",
    $template_id
);

$stmt->execute();

$result = $stmt->get_result();

$template = $result->fetch_assoc();

$stmt->close();

if (!$template) {
    header("Location: templates.php?error=template_not_found");
    exit;
}


/*
|--------------------------------------------------------------------------
| KIỂM TRA MẪU CÓ ĐANG ĐƯỢC KHÁCH HÀNG SỬ DỤNG KHÔNG
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM weddings
     WHERE template_id = ?"
);

if (!$stmt) {
    header("Location: templates.php?error=delete_failed");
    exit;
}

$stmt->bind_param(
    "i",
    $template_id
);

$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$used_count = intval(
    $row["total"] ?? 0
);

$stmt->close();

if ($used_count > 0) {
    header("Location: templates.php?error=template_in_use");
    exit;
}


/*
|--------------------------------------------------------------------------
| XÓA MẪU KHỎI DATABASE
|--------------------------------------------------------------------------
|
| Chỉ xóa bản ghi trong bảng templates.
| Không tự động xóa thư mục source của mẫu để tránh mất code.
|
*/

$stmt = $conn->prepare(
    "DELETE FROM templates
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    header("Location: templates.php?error=delete_failed");
    exit;
}

$stmt->bind_param(
    "i",
    $template_id
);

if (!$stmt->execute()) {
    $stmt->close();

    header("Location: templates.php?error=delete_failed");
    exit;
}

$deleted = $stmt->affected_rows > 0;

$stmt->close();

$conn->close();

if (!$deleted) {
    header("Location: templates.php?error=delete_failed");
    exit;
}


header("Location: templates.php?success=template_deleted");
exit;
