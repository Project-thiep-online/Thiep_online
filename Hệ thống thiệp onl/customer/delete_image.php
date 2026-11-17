<?php

session_start();

require_once "../config/database.php";


function isAjaxRequest()
{
    return isset($_SERVER["HTTP_X_REQUESTED_WITH"])
        && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"])
            === "xmlhttprequest";
}


function respond($success, $message, $statusCode = 200, $extra = [])
{
    if (isAjaxRequest()) {

        http_response_code($statusCode);

        header(
            "Content-Type: application/json; charset=utf-8"
        );

        echo json_encode(
            array_merge(
                [
                    "success" => $success,
                    "message" => $message
                ],
                $extra
            ),
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    if ($success) {

        $weddingId =
            intval($extra["wedding_id"] ?? 0);

        header(
            "Location: wedding_images.php?id=" .
            $weddingId
        );

        exit;
    }

    http_response_code(400);
        echo htmlspecialchars($message, ENT_QUOTES, "UTF-8");
        exit;
}


if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {
    respond(
        false,
        "Bạn chưa đăng nhập hoặc không có quyền truy cập.",
        401
    );
}


$user_id = intval($_SESSION["user_id"]);


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(
        false,
        "Yêu cầu không hợp lệ.",
        405
    );
}


if (
    !isset($_POST["csrf_token"]) ||
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {
    respond(
        false,
        "Yêu cầu không hợp lệ.",
        403
    );
}


$image_id = intval($_POST["image_id"] ?? 0);


if ($image_id <= 0) {
    respond(
        false,
        "Ảnh không hợp lệ.",
        400
    );
}


$stmt = $conn->prepare(
    "SELECT
        wi.id,
        wi.wedding_id,
        wi.image_path,
        wi.image_type
     FROM wedding_images wi
     INNER JOIN weddings w
        ON wi.wedding_id = w.id
     WHERE wi.id = ?
       AND w.user_id = ?
     LIMIT 1"
);


if (!$stmt) {
    respond(
        false,
        "Không thể chuẩn bị truy vấn.",
        500
    );
}


$stmt->bind_param(
    "ii",
    $image_id,
    $user_id
);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows !== 1) {
    respond(
        false,
        "Không tìm thấy ảnh hoặc bạn không có quyền xóa ảnh này.",
        404
    );
}


$image = $result->fetch_assoc();

$wedding_id =
    intval($image["wedding_id"]);


$file_path =
    "../" .
    ltrim(
        $image["image_path"],
        "/\\"
    );


if (file_exists($file_path)) {

    if (!unlink($file_path)) {
        respond(
            false,
            "Không thể xóa file ảnh.",
            500
        );
    }
}


$stmt = $conn->prepare(
    "DELETE FROM wedding_images
     WHERE id = ?
       AND wedding_id = ?"
);


if (!$stmt) {
    respond(
        false,
        "Không thể chuẩn bị truy vấn xóa.",
        500
    );
}


$stmt->bind_param(
    "ii",
    $image_id,
    $wedding_id
);


if (!$stmt->execute()) {
    respond(
        false,
        "Không thể xóa ảnh khỏi hệ thống.",
        500
    );
}


respond(
    true,
    "Đã xóa ảnh thành công.",
    200,
    [
        "image_id" => $image_id,
        "wedding_id" => $wedding_id,
        "image_type" => $image["image_type"]
    ]
);

?>
