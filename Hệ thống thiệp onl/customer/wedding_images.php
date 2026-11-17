<?php

session_start();

require_once "../config/database.php";

/*CACHE*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/*KIỂM TRA ĐĂNG NHẬP*/
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "customer"
) {
    header("Location: ../auth/login.php");
    exit;
}


$user_id = intval($_SESSION["user_id"]);

/*
|--------------------------------------------------------------------------
| THÔNG TIN HIỂN THỊ TÀI KHOẢN
|--------------------------------------------------------------------------
| Một số phiên đăng nhập chỉ lưu user_id và role, vì vậy cần fallback
| an toàn để tránh lỗi Undefined variable ở phần header.
*/
$full_name = trim((string) (
    $_SESSION["full_name"]
    ?? $_SESSION["name"]
    ?? $_SESSION["username"]
    ?? "Khách hàng"
));

if ($full_name === "") {
    $full_name = "Khách hàng";
}

$avatar_letter = function_exists("mb_substr")
    ? mb_strtoupper(mb_substr($full_name, 0, 1, "UTF-8"), "UTF-8")
    : strtoupper(substr($full_name, 0, 1));

if ($avatar_letter === "") {
    $avatar_letter = "K";
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["csrf_token"])) {

    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));

}

$csrf_token = $_SESSION["csrf_token"];


/*
|--------------------------------------------------------------------------
| LẤY WEDDING ID
|--------------------------------------------------------------------------
*/

$wedding_id = intval($_GET["id"] ?? 0);

if ($wedding_id <= 0) {
    die("Thiệp cưới không hợp lệ.");
}


/*
|--------------------------------------------------------------------------
| KIỂM TRA WEDDING THUỘC CUSTOMER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT *
     FROM weddings
     WHERE id = ?
     AND user_id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $wedding_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Không tìm thấy thiệp hoặc bạn không có quyền truy cập.");
}

$wedding = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| THÔNG BÁO
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| HÀM KIỂM TRA ẢNH
|--------------------------------------------------------------------------
*/

function validateImageUpload($file)
{
    /*
    |----------------------------------------------------------------------
    | Kiểm tra file
    |----------------------------------------------------------------------
    */

    if (
        !isset($file["error"]) ||
        $file["error"] !== UPLOAD_ERR_OK
    ) {
        return [
            "success" => false,
            "error" => "File tải lên không hợp lệ."
        ];
    }


    /*
    |----------------------------------------------------------------------
    | Kiểm tra kích thước
    |----------------------------------------------------------------------
    */

    $max_size = 5 * 1024 * 1024;

    if ($file["size"] > $max_size) {

        return [
            "success" => false,
            "error" => "Ảnh không được vượt quá 5MB."
        ];

    }


    /*
    |----------------------------------------------------------------------
    | Kiểm tra MIME thật
    |----------------------------------------------------------------------
    */

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mime = $finfo->file(
        $file["tmp_name"]
    );


    /*
    |----------------------------------------------------------------------
    | MIME được phép
    |----------------------------------------------------------------------
    */

    $allowed = [

        "image/jpeg" => "jpg",

        "image/png" => "png",

        "image/webp" => "webp"

    ];


    if (!isset($allowed[$mime])) {

        return [
            "success" => false,
            "error" => "Chỉ cho phép ảnh JPG, PNG hoặc WebP."
        ];

    }


    /*
    |----------------------------------------------------------------------
    | Kiểm tra file thực sự là ảnh
    |----------------------------------------------------------------------
    */

    if (
        @getimagesize($file["tmp_name"]) === false
    ) {

        return [
            "success" => false,
            "error" => "File không phải là ảnh hợp lệ."
        ];

    }


    return [

        "success" => true,

        "mime" => $mime,

        "extension" => $allowed[$mime]

    ];
}


/*
|--------------------------------------------------------------------------
| HÀM UPLOAD ẢNH ĐƠN
|--------------------------------------------------------------------------
| Dùng cho ảnh cô dâu và ảnh chú rể.
| Mỗi loại chỉ lưu 1 ảnh cho mỗi wedding.
| Tải ảnh mới thành công sẽ thay thế ảnh cũ.
|--------------------------------------------------------------------------
*/
function uploadSingleWeddingImage(
    $conn,
    $file,
    $wedding_id,
    $image_type,
    $prefix
) {
    $validation = validateImageUpload($file);

    if (!$validation["success"]) {
        return [
            "success" => false,
            "message" => $validation["error"]
        ];
    }

    $upload_dir = "../uploads/weddings/" . $wedding_id . "/";

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return [
                "success" => false,
                "message" => "Không thể tạo thư mục upload."
            ];
        }
    }

    $new_filename =
        $prefix . "_" .
        bin2hex(random_bytes(16)) . "." .
        $validation["extension"];

    $new_file_path = $upload_dir . $new_filename;

    $new_db_path =
        "uploads/weddings/" .
        $wedding_id . "/" .
        $new_filename;

    if (!move_uploaded_file($file["tmp_name"], $new_file_path)) {
        return [
            "success" => false,
            "message" => "Không thể tải ảnh lên."
        ];
    }

    $old_image = null;

    $stmt = $conn->prepare(
        "SELECT id, image_path
         FROM wedding_images
         WHERE wedding_id = ?
           AND image_type = ?
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        if (file_exists($new_file_path)) {
            unlink($new_file_path);
        }

        return [
            "success" => false,
            "message" => "Không thể chuẩn bị truy vấn."
        ];
    }

    $stmt->bind_param("is", $wedding_id, $image_type);
    $stmt->execute();
    $old_result = $stmt->get_result();

    if ($old_result->num_rows === 1) {
        $old_image = $old_result->fetch_assoc();
    }

    $image_order = 0;

    $stmt = $conn->prepare(
        "INSERT INTO wedding_images
        (
            wedding_id,
            image_path,
            image_type,
            image_order
        )
        VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        if (file_exists($new_file_path)) {
            unlink($new_file_path);
        }

        return [
            "success" => false,
            "message" => "Không thể chuẩn bị lưu ảnh."
        ];
    }

    $stmt->bind_param(
        "issi",
        $wedding_id,
        $new_db_path,
        $image_type,
        $image_order
    );

    if (!$stmt->execute()) {
        if (file_exists($new_file_path)) {
            unlink($new_file_path);
        }

        return [
            "success" => false,
            "message" => "Không thể lưu ảnh vào hệ thống."
        ];
    }

    if ($old_image !== null) {
        $old_file = "../" . $old_image["image_path"];

        if (file_exists($old_file)) {
            unlink($old_file);
        }

        $delete_old = $conn->prepare(
            "DELETE FROM wedding_images
             WHERE id = ?
               AND wedding_id = ?"
        );

        if ($delete_old) {
            $delete_old->bind_param(
                "ii",
                $old_image["id"],
                $wedding_id
            );
            $delete_old->execute();
        }
    }

    return [
        "success" => true,
        "message" => "Tải ảnh thành công."
    ];
}


/*
|--------------------------------------------------------------------------
| XỬ LÝ POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |----------------------------------------------------------------------
    | KIỂM TRA CSRF
    |----------------------------------------------------------------------
    */

    $csrf_valid =
        isset($_POST["csrf_token"]) &&
        hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        );

    if (!$csrf_valid) {

        http_response_code(400);
        $error = "Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.";

    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD ẢNH CÔ DÂU
    |--------------------------------------------------------------------------
    */

    if ($csrf_valid && isset($_POST["upload_bride"])) {

        if (!isset($_FILES["bride_image"])) {
            $error = "Vui lòng chọn ảnh cô dâu.";
        } else {
            $upload = uploadSingleWeddingImage(
                $conn,
                $_FILES["bride_image"],
                $wedding_id,
                "bride",
                "bride"
            );

            if ($upload["success"]) {
                $success = "Đã cập nhật ảnh cô dâu thành công.";
            } else {
                $error = $upload["message"];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD ẢNH CHÚ RỂ
    |--------------------------------------------------------------------------
    */

    if ($csrf_valid && isset($_POST["upload_groom"])) {

        if (!isset($_FILES["groom_image"])) {
            $error = "Vui lòng chọn ảnh chú rể.";
        } else {
            $upload = uploadSingleWeddingImage(
                $conn,
                $_FILES["groom_image"],
                $wedding_id,
                "groom",
                "groom"
            );

            if ($upload["success"]) {
                $success = "Đã cập nhật ảnh chú rể thành công.";
            } else {
                $error = $upload["message"];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD COVER
    |--------------------------------------------------------------------------
    */

    if (
        $csrf_valid &&
        isset($_POST["upload_cover"])
    ) {


        if (
            !isset($_FILES["cover_image"])
        ) {

            $error =
                "Vui lòng chọn ảnh cover.";

        } else {


            $file =
                $_FILES["cover_image"];


            /*
            |------------------------------------------------------------------
            | Kiểm tra ảnh mới
            |------------------------------------------------------------------
            */

            $validation =
                validateImageUpload($file);


            if (!$validation["success"]) {

                $error =
                    $validation["error"];

            } else {


                /*
                |----------------------------------------------------------------
                | Thư mục upload
                |----------------------------------------------------------------
                */

                $upload_dir =
                    "../uploads/weddings/" .
                    $wedding_id .
                    "/";


                if (
                    !is_dir($upload_dir)
                ) {

                    if (
                        !mkdir(
                            $upload_dir,
                            0755,
                            true
                        )
                    ) {

                        $error =
                            "Không thể tạo thư mục upload.";

                    }

                }


                /*
                |----------------------------------------------------------------
                | Nếu tạo thư mục thành công
                |----------------------------------------------------------------
                */

                if ($error === "") {


                    /*
                    |------------------------------------------------------------
                    | Tên file mới
                    |------------------------------------------------------------
                    */

                    $new_filename =
                        "cover_" .
                        bin2hex(
                            random_bytes(16)
                        ) .
                        "." .
                        $validation["extension"];


                    $new_file_path =
                        $upload_dir .
                        $new_filename;


                    /*
                    |------------------------------------------------------------
                    | Đường dẫn lưu DB
                    |------------------------------------------------------------
                    */

                    $new_db_path =
                        "uploads/weddings/" .
                        $wedding_id .
                        "/" .
                        $new_filename;


                    /*
                    |------------------------------------------------------------
                    | Upload cover mới
                    |------------------------------------------------------------
                    */

                    if (
                        move_uploaded_file(
                            $file["tmp_name"],
                            $new_file_path
                        )
                    ) {


                        /*
                        |--------------------------------------------------------
                        | Lấy cover cũ
                        |--------------------------------------------------------
                        */

                        $old_cover = null;


                        $stmt =
                            $conn->prepare(
                                "SELECT
                                    id,
                                    image_path
                                 FROM wedding_images
                                 WHERE wedding_id = ?
                                 AND image_type = 'cover'
                                 ORDER BY id DESC
                                 LIMIT 1"
                            );


                        $stmt->bind_param(
                            "i",
                            $wedding_id
                        );


                        $stmt->execute();


                        $old_result =
                            $stmt->get_result();


                        if (
                            $old_result->num_rows === 1
                        ) {

                            $old_cover =
                                $old_result->fetch_assoc();

                        }


                        /*
                        |--------------------------------------------------------
                        | Thêm cover mới vào database
                        |--------------------------------------------------------
                        */

                        $stmt =
                            $conn->prepare(
                                "INSERT INTO wedding_images
                                (
                                    wedding_id,
                                    image_path,
                                    image_type,
                                    image_order
                                )
                                VALUES (?, ?, 'cover', 0)"
                            );


                        $stmt->bind_param(
                            "is",
                            $wedding_id,
                            $new_db_path
                        );


                        if (
                            $stmt->execute()
                        ) {


                            /*
                            |----------------------------------------------------
                            | Xóa cover cũ sau khi cover mới thành công
                            |----------------------------------------------------
                            */

                            if (
                                $old_cover !== null
                            ) {


                                $old_file =
                                    "../" .
                                    $old_cover["image_path"];


                                if (
                                    file_exists(
                                        $old_file
                                    )
                                ) {

                                    unlink(
                                        $old_file
                                    );

                                }


                                /*
                                |------------------------------------------------
                                | Xóa DB cover cũ
                                |------------------------------------------------
                                */

                                $delete_old =
                                    $conn->prepare(
                                        "DELETE FROM wedding_images
                                         WHERE id = ?
                                         AND wedding_id = ?"
                                    );


                                $delete_old->bind_param(
                                    "ii",
                                    $old_cover["id"],
                                    $wedding_id
                                );


                                $delete_old->execute();

                            }


                            $success =
                                "Đã cập nhật ảnh cover thành công.";

                        } else {


                            /*
                            |----------------------------------------------------
                            | Nếu DB lỗi → xóa file mới
                            |----------------------------------------------------
                            */

                            if (
                                file_exists(
                                    $new_file_path
                                )
                            ) {

                                unlink(
                                    $new_file_path
                                );

                            }


                            $error =
                                "Không thể lưu ảnh cover vào hệ thống.";

                        }

                    } else {

                        $error =
                            "Không thể tải ảnh cover lên.";

                    }

                }

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | [4B - QR CODE] UPLOAD QR
    |--------------------------------------------------------------------------
    | Mỗi wedding chỉ giữ 1 ảnh QR.
    | QR dùng chung hệ thống wedding_images với image_type = 'qr'.
    |--------------------------------------------------------------------------
    */

    if ($csrf_valid && isset($_POST["upload_qr"])) {

        if (!isset($_FILES["qr_image"])) {
            $error = "Vui lòng chọn ảnh QR Code.";
        } else {

            $file = $_FILES["qr_image"];
            $validation = validateImageUpload($file);

            if (!$validation["success"]) {
                $error = $validation["error"];
            } else {

                $upload_dir =
                    "../uploads/weddings/" .
                    $wedding_id .
                    "/";

                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = "Không thể tạo thư mục upload.";
                    }
                }

                if ($error === "") {

                    $new_filename =
                        "qr_" .
                        bin2hex(random_bytes(16)) .
                        "." .
                        $validation["extension"];

                    $new_file_path =
                        $upload_dir .
                        $new_filename;

                    $new_db_path =
                        "uploads/weddings/" .
                        $wedding_id .
                        "/" .
                        $new_filename;

                    if (move_uploaded_file($file["tmp_name"], $new_file_path)) {

                        /* [4B - QR CODE] Lấy QR cũ nếu có */
                        $old_qr = null;

                        $stmt = $conn->prepare(
                            "SELECT id, image_path
                             FROM wedding_images
                             WHERE wedding_id = ?
                             AND image_type = 'qr'
                             ORDER BY id DESC
                             LIMIT 1"
                        );

                        $stmt->bind_param("i", $wedding_id);
                        $stmt->execute();
                        $old_result = $stmt->get_result();

                        if ($old_result->num_rows === 1) {
                            $old_qr = $old_result->fetch_assoc();
                        }

                        /* [4B - QR CODE] Lưu QR mới */
                        $stmt = $conn->prepare(
                            "INSERT INTO wedding_images
                            (
                                wedding_id,
                                image_path,
                                image_type,
                                image_order
                            )
                            VALUES (?, ?, 'qr', 0)"
                        );

                        $stmt->bind_param(
                            "is",
                            $wedding_id,
                            $new_db_path
                        );

                        if ($stmt->execute()) {

                            /* [4B - QR CODE] Xóa QR cũ sau khi QR mới đã lưu */
                            if ($old_qr !== null) {

                                $old_file = "../" . $old_qr["image_path"];

                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }

                                $delete_old = $conn->prepare(
                                    "DELETE FROM wedding_images
                                     WHERE id = ?
                                     AND wedding_id = ?
                                     AND image_type = 'qr'"
                                );

                                $delete_old->bind_param(
                                    "ii",
                                    $old_qr["id"],
                                    $wedding_id
                                );

                                $delete_old->execute();
                            }

                            $success = "Đã cập nhật QR Code thành công.";

                        } else {

                            if (file_exists($new_file_path)) {
                                unlink($new_file_path);
                            }

                            $error = "Không thể lưu QR Code vào hệ thống.";
                        }

                    } else {
                        $error = "Không thể tải ảnh QR Code lên.";
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD ALBUM
    |--------------------------------------------------------------------------
    */

    if (
        $csrf_valid &&
        isset($_POST["upload_album"])
    ) {


        if (
            !isset($_FILES["album_images"]) ||
            !isset($_FILES["album_images"]["name"])
        ) {

            $error =
                "Vui lòng chọn ảnh album.";

        } else {


            $files =
                $_FILES["album_images"];


            /*
            |------------------------------------------------------------------
            | Đếm file
            |------------------------------------------------------------------
            */

            $total =
                count($files["name"]);


            $max_album_upload = 20;


            if (
                $total > $max_album_upload
            ) {

                $error =
                    "Mỗi lần chỉ được tải tối đa 20 ảnh.";

            } else {


                /*
                |----------------------------------------------------------------
                | Thư mục album
                |----------------------------------------------------------------
                */

                $upload_dir =
                    "../uploads/weddings/" .
                    $wedding_id .
                    "/";


                if (
                    !is_dir($upload_dir)
                ) {

                    if (
                        !mkdir(
                            $upload_dir,
                            0755,
                            true
                        )
                    ) {

                        $error =
                            "Không thể tạo thư mục upload.";

                    }

                }


                /*
                |----------------------------------------------------------------
                | Danh sách ảnh cần lưu
                |----------------------------------------------------------------
                */

                $uploaded_count = 0;

                $failed_count = 0;


                /*
                |----------------------------------------------------------------
                | Lấy thứ tự album hiện tại
                |----------------------------------------------------------------
                */

                $stmt =
                    $conn->prepare(
                        "SELECT
                            COALESCE(
                                MAX(image_order),
                                0
                            )
                         FROM wedding_images
                         WHERE wedding_id = ?
                         AND image_type = 'album'"
                    );


                $stmt->bind_param(
                    "i",
                    $wedding_id
                );


                $stmt->execute();


                $order_result =
                    $stmt->get_result();


                $row =
                    $order_result->fetch_row();


                $image_order =
                    intval($row[0]);


                /*
                |----------------------------------------------------------------
                | Upload từng ảnh
                |----------------------------------------------------------------
                */

                for (
                    $i = 0;
                    $i < $total;
                    $i++
                ) {


                    /*
                    |----------------------------------------------------------------
                    | Bỏ qua file không được upload
                    |----------------------------------------------------------------
                    */

                    if (
                        $files["error"][$i] !==
                        UPLOAD_ERR_OK
                    ) {

                        $failed_count++;

                        continue;

                    }


                    $file = [

                        "name" =>
                            $files["name"][$i],

                        "type" =>
                            $files["type"][$i],

                        "tmp_name" =>
                            $files["tmp_name"][$i],

                        "error" =>
                            $files["error"][$i],

                        "size" =>
                            $files["size"][$i]

                    ];


                    /*
                    |----------------------------------------------------------------
                    | Kiểm tra ảnh
                    |----------------------------------------------------------------
                    */

                    $validation =
                        validateImageUpload(
                            $file
                        );


                    if (
                        !$validation["success"]
                    ) {

                        $failed_count++;

                        continue;

                    }


                    /*
                    |----------------------------------------------------------------
                    | Tên file random
                    |----------------------------------------------------------------
                    */

                    $new_filename =
                        "album_" .
                        bin2hex(
                            random_bytes(16)
                        ) .
                        "." .
                        $validation["extension"];


                    $new_file_path =
                        $upload_dir .
                        $new_filename;


                    $new_db_path =
                        "uploads/weddings/" .
                        $wedding_id .
                        "/" .
                        $new_filename;


                    /*
                    |----------------------------------------------------------------
                    | Upload
                    |----------------------------------------------------------------
                    */

                    if (
                        move_uploaded_file(
                            $file["tmp_name"],
                            $new_file_path
                        )
                    ) {


                        /*
                        |------------------------------------------------------------
                        | Tăng order
                        |------------------------------------------------------------
                        */

                        $image_order++;


                        /*
                        |------------------------------------------------------------
                        | Lưu database
                        |------------------------------------------------------------
                        */

                        $stmt =
                            $conn->prepare(
                                "INSERT INTO wedding_images
                                (
                                    wedding_id,
                                    image_path,
                                    image_type,
                                    image_order
                                )
                                VALUES (?, ?, 'album', ?)"
                            );


                        $stmt->bind_param(
                            "isi",
                            $wedding_id,
                            $new_db_path,
                            $image_order
                        );


                        if (
                            $stmt->execute()
                        ) {

                            $uploaded_count++;

                        } else {


                            /*
                            |--------------------------------------------------------
                            | DB lỗi → xóa file
                            |--------------------------------------------------------
                            */

                            if (
                                file_exists(
                                    $new_file_path
                                )
                            ) {

                                unlink(
                                    $new_file_path
                                );

                            }


                            $failed_count++;

                        }

                    } else {

                        $failed_count++;

                    }

                }


                /*
                |----------------------------------------------------------------
                | Thông báo
                |----------------------------------------------------------------
                */

                if (
                    $uploaded_count > 0
                ) {

                    $success =
                        "Đã tải lên " .
                        $uploaded_count .
                        " ảnh album.";

                    if (
                        $failed_count > 0
                    ) {

                        $success .=
                            " Có " .
                            $failed_count .
                            " ảnh không thể tải lên.";

                    }

                } else {

                    $error =
                        "Không có ảnh nào được tải lên thành công.";

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| LẤY ẢNH CÔ DÂU
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
       AND image_type = 'bride'
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param("i", $wedding_id);
$stmt->execute();
$bride_result = $stmt->get_result();

$bride_image = null;

if ($bride_result->num_rows === 1) {
    $bride_image = $bride_result->fetch_assoc();
}


/*
|--------------------------------------------------------------------------
| LẤY ẢNH CHÚ RỂ
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
       AND image_type = 'groom'
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param("i", $wedding_id);
$stmt->execute();
$groom_result = $stmt->get_result();

$groom_image = null;

if ($groom_result->num_rows === 1) {
    $groom_image = $groom_result->fetch_assoc();
}


/*
|--------------------------------------------------------------------------
| LẤY ẢNH COVER
|--------------------------------------------------------------------------
*/

$stmt =
    $conn->prepare(
        "SELECT
            id,
            image_path,
            image_type,
            image_order,
            created_at
         FROM wedding_images
         WHERE wedding_id = ?
         AND image_type = 'cover'
         ORDER BY id DESC
         LIMIT 1"
    );


$stmt->bind_param(
    "i",
    $wedding_id
);


$stmt->execute();


$cover_result =
    $stmt->get_result();


$cover_image = null;


if (
    $cover_result->num_rows === 1
) {

    $cover_image =
        $cover_result->fetch_assoc();

}


/*LẤY ALBUM */
$stmt =
    $conn->prepare(
        "SELECT
            id,
            image_path,
            image_type,
            image_order,
            created_at
        FROM wedding_images
        WHERE wedding_id = ?
        AND image_type = 'album'
        ORDER BY image_order ASC, id DESC"
    );

$stmt->bind_param(
    "i",
    $wedding_id
);

$stmt->execute();

$album_result =
    $stmt->get_result();

$album_images = [];

while (
    $image =
    $album_result->fetch_assoc()
) {

    $album_images[] =
        $image;

}


/*
|--------------------------------------------------------------------------
| [4B - QR CODE] LẤY QR HIỆN TẠI
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
     AND image_type = 'qr'
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $wedding_id
);

$stmt->execute();

$qr_result = $stmt->get_result();

$qr_image = null;

if ($qr_result->num_rows === 1) {
    $qr_image = $qr_result->fetch_assoc();
}

?>

<?php

$brand_name = $brand_name ?? "thy_nhun";
$copyright_text = $copyright_text ?? "thy_nhun Weddings";

$contact_phone = $contact_phone ?? "";
$contact_facebook = $contact_facebook ?? "";

?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#8F1D2C">
<title>Ảnh cưới | thyu_nhun Weddings</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<!--
    ========================================================================
    🔤 FONT THƯƠNG HIỆU
    ========================================================================
    -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >
<!-- [CUSTOMER DESIGN SYSTEM] Đổi concept toàn bộ customer: ưu tiên sửa file này. -->

<style>
:root{
    --bg:#fff9f8;
    --surface:#ffffff;
    --surface-soft:#fff3f1;
    --primary:#8f1d2c;
    --primary-dark:#65131f;
    --primary-soft:#f8e3e4;
    --text:#32191c;
    --muted:#7e6669;
    --border:#ecd8d5;
    --success:#287a52;
    --success-bg:#eaf7ef;
    --danger:#b53a46;
    --danger-bg:#fff0f1;
    --shadow:0 12px 35px rgba(61,11,18,.08);
    --radius:22px;
    --font-display:
                "Cormorant Garamond",
                Georgia,
                serif;

}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    min-height:100vh;
    background:
      radial-gradient(circle at top left,rgba(143,29,44,.055),transparent 28rem),
      var(--bg);
    color:var(--text);
    font-family:"DM Sans","Be Vietnam Pro",Arial,sans-serif;
}
a{text-decoration:none;color:inherit}
button,input{font:inherit}
button{cursor:pointer}

/* ===== HEADER ===== */
.topbar{
    position:sticky;top:0;z-index:100;
    width:100%;min-height:82px;
    display:flex;align-items:center;justify-content:space-between;
    gap:24px;padding:14px clamp(20px,5vw,72px);
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(18px);
    border-bottom:1px solid rgba(236,216,213,.9);
    box-shadow:0 5px 24px rgba(61,11,18,.035);
}
.brand{display:inline-flex;align-items:center;gap:13px;min-width:0}
.brand-icon{
    width:48px;height:48px;border-radius:16px;
    display:grid;place-items:center;flex:0 0 auto;
    color:#fff;background:linear-gradient(145deg,var(--primary),var(--primary-dark));
    box-shadow:0 8px 18px rgba(143,29,44,.2);
}
.brand-logo-svg{width:31px;height:31px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.brand-name{display:flex;flex-direction:column;gap:2px;line-height:1.1;min-width:0}
.brand-name strong{
    font-family:"Cormorant Garamond","Playfair Display",serif;
    font-size:1.45rem;font-weight:700;letter-spacing:.01em;color:var(--primary-dark)
}
.brand-name span{
    font-size:.62rem;font-weight:700;letter-spacing:.18em;color:var(--muted)
}
.user-area{display:flex;align-items:center;gap:12px;flex:0 0 auto}
.user-info{display:flex;flex-direction:column;align-items:flex-end;gap:2px;line-height:1.2}
.user-info strong{font-size:.9rem;font-weight:700;color:var(--text)}
.user-info span{font-size:.75rem;color:var(--muted)}
.avatar{
    width:42px;height:42px;border-radius:50%;display:grid;place-items:center;
    color:#fff;font-weight:700;
    background:linear-gradient(145deg,#b83b4c,var(--primary-dark));
    box-shadow:0 6px 16px rgba(143,29,44,.18)
}

/* ===== PAGE ===== */
.cw-page{min-height:calc(100vh - 82px)}
.cw-content{width:min(1180px,calc(100% - 40px));margin:0 auto;padding:38px 0 64px}
.cw-back{
    display:inline-flex;align-items:center;gap:9px;
    color:var(--muted);font-size:.9rem;font-weight:700;margin-bottom:24px;
    transition:.2s ease
}
.cw-back:hover{color:var(--primary);transform:translateX(-3px)}
.cw-heading{margin-bottom:28px}
.cw-heading h1{
    font-family:"Cormorant Garamond","Playfair Display",serif;
    font-size:clamp(2.1rem,4vw,3.2rem);line-height:1;color:var(--text)
}
.cw-heading p{margin-top:10px;color:var(--muted);line-height:1.65}

/* ===== SUMMARY ===== */
.cw-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:26px}
.cw-summary-card{
    min-height:108px;padding:20px;border:1px solid var(--border);border-radius:var(--radius);
    background:var(--surface);box-shadow:0 8px 25px rgba(61,11,18,.035);
    display:flex;align-items:center;gap:15px
}
.cw-summary-icon{
    width:48px;height:48px;border-radius:15px;display:grid;place-items:center;
    background:var(--primary-soft);color:var(--primary);font-size:1.15rem
}
.cw-summary-text{display:flex;flex-direction:column;gap:4px}
.cw-summary-text strong{font-size:1.55rem;line-height:1}
.cw-summary-text span{font-size:.84rem;color:var(--muted)}

/* ===== SECTIONS ===== */
.cw-section{
    margin-top:22px;padding:clamp(18px,3vw,28px);
    background:var(--surface);border:1px solid var(--border);
    border-radius:28px;box-shadow:var(--shadow)
}
.cw-section-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}
.cw-section-title{display:flex;align-items:center;gap:12px}
.cw-section-icon{
    width:44px;height:44px;border-radius:14px;display:grid;place-items:center;
    color:var(--primary);background:var(--primary-soft)
}
.cw-section-title h2{
    font-family:"Cormorant Garamond","Playfair Display",serif;
    font-size:1.7rem;line-height:1.1
}
.cw-section-title p{margin-top:3px;color:var(--muted);font-size:.87rem}
.cw-count{
    padding:8px 12px;border-radius:999px;background:var(--surface-soft);
    color:var(--primary);font-size:.8rem;font-weight:700
}

/* ===== PHOTO CARDS ===== */
.cw-photo-pair{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
.cw-photo-card{
    overflow:hidden;border:1px solid var(--border);border-radius:22px;
    background:linear-gradient(180deg,#fff,#fffafa)
}
.cw-photo-card-head{
    display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px 12px
}
.cw-photo-card-title{display:flex;align-items:center;gap:10px;font-weight:700}
.cw-photo-card-icon{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:var(--primary-soft);color:var(--primary)}
.cw-photo-card-status{font-size:.76rem;font-weight:700;color:var(--muted)}
.cw-photo-card-preview{
    aspect-ratio:16/10;margin:0 14px;border-radius:17px;overflow:hidden;
    background:var(--surface-soft);position:relative
}
.cw-photo-card-preview img{width:100%;height:100%;object-fit:cover;display:block}
.cw-photo-placeholder{height:100%;display:grid;place-items:center;color:var(--muted);text-align:center;padding:20px}
.cw-photo-card-actions{display:flex;gap:10px;padding:14px}
.cw-photo-action,.cw-btn{
    min-height:42px;border:1px solid var(--border);border-radius:12px;padding:0 15px;
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    font-size:.86rem;font-weight:700;background:#fff;color:var(--text);transition:.2s ease
}
.cw-photo-action:hover,.cw-btn:hover{transform:translateY(-1px);border-color:#d9aaa9}
.cw-btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}
.cw-btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark)}
.cw-btn-danger{color:var(--danger);background:var(--danger-bg);border-color:#f1c9cd}
.cw-photo-delete{color:var(--danger)}
.is-disabled{opacity:.55;pointer-events:none}

/* ===== UPLOAD ===== */
.cw-upload{margin-top:18px;border:1.5px dashed #dcb9b8;border-radius:20px;background:var(--surface-soft);padding:20px}
.cw-upload-head{display:flex;align-items:flex-start;gap:14px}
.cw-upload-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:#fff;color:var(--primary);flex:0 0 auto}
.cw-upload-info h3{font-size:1rem;margin-bottom:4px}
.cw-upload-info p{font-size:.85rem;color:var(--muted);line-height:1.55}
.cw-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
.cw-hidden-file{display:none}

/* ===== ALBUM ===== */
.cw-album-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
.cw-album-item{
    position:relative;overflow:hidden;border-radius:18px;background:var(--surface-soft);
    aspect-ratio:1/1;border:1px solid var(--border)
}
.cw-album-image{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s ease}
.cw-album-item:hover .cw-album-image{transform:scale(1.045)}
.cw-album-number{
    position:absolute;top:10px;left:10px;width:30px;height:30px;border-radius:50%;
    display:grid;place-items:center;background:rgba(255,255,255,.9);font-size:.75rem;font-weight:700;color:var(--primary)
}
.cw-album-footer{
    position:absolute;left:0;right:0;bottom:0;padding:12px;
    display:flex;justify-content:flex-end;gap:8px;
    background:linear-gradient(transparent,rgba(0,0,0,.62))
}
.cw-album-delete,.cw-photo-view{
    width:34px;height:34px;border:0;border-radius:10px;display:grid;place-items:center;background:#fff;color:var(--text)
}
.cw-album-delete{color:var(--danger)}
.cw-empty{padding:44px 20px;text-align:center;color:var(--muted);border:1.5px dashed var(--border);border-radius:18px}

/* ===== ALERTS ===== */
.cw-message,.success,.error{
    padding:14px 16px;border-radius:15px;margin-bottom:20px;font-size:.9rem;line-height:1.5
}
.success{background:var(--success-bg);color:var(--success);border:1px solid #cce9d8}
.error{background:var(--danger-bg);color:var(--danger);border:1px solid #f1c9cd}

/* ===== MODAL ===== */
.cw-image-modal{position:fixed;inset:0;z-index:1000;background:rgba(20,10,12,.72);display:none;align-items:center;justify-content:center;padding:24px}
.cw-image-modal.is-open{display:flex}
.cw-image-modal-box{max-width:min(100%,900px);max-height:90vh;position:relative}
.cw-image-modal-box img{max-width:100%;max-height:90vh;border-radius:18px;display:block}
.cw-image-modal-close{position:absolute;right:-10px;top:-10px;width:38px;height:38px;border:0;border-radius:50%;background:#fff;color:var(--text);font-size:1.1rem}


@media (max-width:980px){
    .cw-summary{grid-template-columns:1fr}
    .cw-album-grid{grid-template-columns:repeat(3,1fr)}
}
@media (max-width:720px){
    .topbar{min-height:70px;padding:10px 16px}
    .brand-icon{width:43px;height:43px;border-radius:14px}
    .brand-name strong{font-size:1.25rem}
    .brand-name span{display:none}
    .user-info{display:none}
    .cw-content{width:min(100% - 28px,1180px);padding:26px 0 44px}
    .cw-photo-pair{grid-template-columns:1fr}
    .cw-album-grid{grid-template-columns:repeat(2,1fr)}
    .cw-section{border-radius:22px;padding:16px}
}
@media (max-width:420px){
    .cw-heading h1{font-size:2rem}
    .cw-section-head{align-items:flex-start;flex-direction:column}
    .cw-actions,.cw-photo-card-actions{flex-direction:column}
    .cw-actions .cw-btn,.cw-photo-card-actions .cw-photo-action{width:100%}
}

/* ==========================================================
   FINAL CUSTOMER SYNC — VIETNAMESE RED + GOLD
   Đồng bộ wedding_images.php với toàn bộ Customer/Admin system
========================================================== */
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

body{
    background:
        radial-gradient(circle at top right,rgba(212,167,61,.08),transparent 23rem),
        radial-gradient(circle at left 18%,rgba(143,29,44,.055),transparent 30rem),
        var(--vn-bg);
    color:var(--vn-text);
}

.topbar{
    background:rgba(255,255,255,.94);
    border-bottom-color:var(--vn-border);
}

.brand-icon{
    color:var(--vn-gold-light);
    background:linear-gradient(145deg,var(--vn-red-accent),var(--vn-red-dark));
    border:1px solid rgba(242,213,138,.36);
    box-shadow:0 10px 24px rgba(61,11,18,.20);
    position:relative;
    overflow:hidden;
}
.brand-icon::after{
    content:"";
    position:absolute;
    inset:6px;
    border:1px solid rgba(242,213,138,.16);
    border-radius:12px;
    pointer-events:none;
}
.brand-logo-svg{position:relative;z-index:1}

.brand-name strong{color:var(--vn-red-dark)}
.brand-name span{color:var(--vn-gold)}
.avatar{
    background:linear-gradient(145deg,var(--vn-red-accent),var(--vn-red-dark));
    border:1px solid rgba(212,167,61,.18);
}

.cw-heading{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:20px;
}
.cw-heading h1{color:var(--vn-text)}
.cw-heading p{max-width:760px}
.cw-back{
    margin-bottom:3px;
    padding:10px 14px;
    border:1px solid var(--vn-border);
    border-radius:12px;
    background:var(--vn-card);
    white-space:nowrap;
}
.cw-back:hover{
    border-color:rgba(143,29,44,.28);
    color:var(--vn-red);
    box-shadow:0 8px 20px rgba(61,11,18,.06);
}

.cw-summary{
    grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
}
.cw-summary-card,
.cw-section,
.cw-photo-card{
    background:var(--vn-card);
    border-color:var(--vn-border);
}
.cw-summary-card{
    box-shadow:0 10px 28px rgba(61,11,18,.045);
}
.cw-summary-icon,
.cw-section-icon,
.cw-photo-card-icon{
    color:var(--vn-red);
    background:var(--vn-red-soft);
}
.cw-summary-text strong{color:var(--vn-red-dark)}
.cw-count{
    color:var(--vn-red);
    background:var(--vn-red-soft);
    border:1px solid rgba(143,29,44,.10);
}

.cw-photo-card{
    background:linear-gradient(180deg,#fff 0%,#FFF9F8 100%);
}
.cw-photo-card-head{
    display:grid;
    grid-template-columns:auto 1fr auto;
    align-items:start;
}
.cw-photo-card-title h2{
    margin:0;
    font-family:"Cormorant Garamond","Playfair Display",serif;
    font-size:1.45rem;
    line-height:1.1;
}
.cw-photo-card-title p{
    margin:4px 0 0;
    color:var(--vn-muted);
    font-size:.82rem;
    font-weight:400;
}
.cw-photo-card-status{
    padding:7px 10px;
    border-radius:999px;
    background:var(--vn-red-soft);
    color:var(--vn-red);
    border:1px solid rgba(143,29,44,.10);
}

.cw-photo-card-preview{
    border:1px solid rgba(236,216,213,.75);
}
.cw-photo-placeholder{
    background:linear-gradient(145deg,#FFF8F6,#FCE8E7);
}
.cw-photo-placeholder i{
    display:block;
    font-size:1.7rem;
    margin-bottom:8px;
    color:var(--vn-red);
}

.cw-photo-card-actions{
    align-items:center;
    flex-wrap:wrap;
}
.cw-photo-action,
.cw-btn{
    border-color:var(--vn-border);
}
.cw-photo-action:hover,
.cw-btn:hover{
    border-color:rgba(143,29,44,.35);
}
.cw-photo-select{
    color:#fff;
    background:linear-gradient(135deg,var(--vn-red),var(--vn-red-dark));
    border-color:var(--vn-red);
}
.cw-photo-select:hover{
    color:#fff;
    background:linear-gradient(135deg,var(--vn-red-accent),var(--vn-red));
}
.cw-btn-primary{
    background:linear-gradient(135deg,var(--vn-red),var(--vn-red-dark));
}
.cw-btn-primary:hover{
    background:linear-gradient(135deg,var(--vn-red-accent),var(--vn-red));
}
.cw-btn-danger{
    color:#B53A46;
}

.cw-photo-delete{
    display:flex;
    justify-content:flex-end;
    padding:0 14px 14px;
}

.cw-upload{
    border-color:rgba(143,29,44,.28);
    background:linear-gradient(135deg,#FFF8F6,#FCE8E7);
}
.cw-upload-icon{
    color:var(--vn-red);
    border:1px solid var(--vn-border);
}
.cw-upload-info span{
    display:block;
    margin-top:4px;
    color:var(--vn-muted);
    font-size:.84rem;
}

.cw-album-grid{margin-top:20px}
.cw-album-number{
    width:auto;
    min-width:30px;
    padding:0 9px;
    border-radius:999px;
}
.cw-album-footer{
    align-items:flex-end;
    justify-content:space-between;
}
.cw-album-delete,
.cw-photo-view{
    border:1px solid var(--vn-border);
}
.cw-empty{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
    background:linear-gradient(135deg,#FFF8F6,#FFFFFF);
}
.cw-empty i{
    font-size:2rem;
    color:var(--vn-red);
}

.success{
    background:#EEF8F1;
    color:#287A52;
    border-color:#CDE9D8;
}
.error{
    background:#FFF0F1;
    color:#B53A46;
    border-color:#F1C9CD;
}

.cw-footer{
    background:#fff;
}
.cw-footer-brand{
    color:var(--vn-red-dark);
}

.cw-image-modal{
    background:rgba(61,11,18,.78);
}
.cw-image-modal-close{
    border:1px solid var(--vn-border);
}

@media (max-width:720px){
    .cw-heading{
        display:block;
    }
    .cw-back{
        margin-top:16px;
    }
    .cw-photo-card-head{
        grid-template-columns:auto 1fr;
    }
    .cw-photo-card-status{
        grid-column:2;
        justify-self:start;
    }
}

@media (max-width:420px){
    .cw-photo-card-head{
        grid-template-columns:1fr;
    }
    .cw-photo-card-icon{
        margin-bottom:2px;
    }
    .cw-photo-card-status{
        grid-column:1;
    }
}

 
        /*
        ====================================================================
        FOOTER
        ====================================================================
        */

        footer {

            margin-top:
                auto;

            padding:
                32px 25px;

            border-top:
                1px solid
                var(--theme-border);

            background: white;

            text-align:
                center;

        }


        .footer-brand {

            font-family:
                var(--font-display);

            color:
                var(--theme-primary-dark);

            font-size:
                23px;

            font-weight:
                600;

        }


        .footer-tagline {

            margin-top:
                4px;

            color:
                var(--theme-muted);

            font-size:
                8px;

            letter-spacing:
                2px;

            text-transform:
                uppercase;

        }


        .footer-contact {

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            flex-wrap:
                wrap;

            gap:
                8px 20px;

            margin:
                17px 0;

        }


        .footer-contact a {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            color:
                var(--theme-muted);

            font-size:
                10px;

            text-decoration:
                none;

            transition:
                var(--transition);

        }


        .footer-contact a:hover {

            color:
                var(--theme-primary);

        }


        .footer-copy {

            padding-top:
                15px;

            border-top:
                1px solid
                var(--theme-border);

            color:
                #a59693;

            font-size:
                9px;

        }


        .footer-copy strong {

            color:
                var(--theme-primary);

        }


        /*
        ====================================================================
        RESPONSIVE TABLET
        ====================================================================
        */

        @media (
            max-width: 800px
        ) {

            .topbar {

                padding:
                    0 20px;

            }


            .content {

                padding:
                    30px 20px 45px;

            }


            .form-card {

                padding:
                    25px;

            }

        }



    /* =====================================================================
       📭 18. EMPTY STATE
       ===================================================================== */

    .empty-state {

        background:
            white;

        border:
            1px solid
            var(--card-border);

        border-radius:
            var(--radius-large);

        padding:
            70px 30px;

        text-align:
            center;

        box-shadow:
            var(--shadow-card);
    }


    .empty-icon {

        width:
            65px;

        height:
            65px;

        margin:
            0 auto 17px;

        border-radius:
            18px;

        display:
            flex;

        align-items:
            center;

        justify-content:
            center;

        background:
            #f3e5df;

        font-size:
            27px;
    }


    .empty-state h2 {

        font-family:
            var(--font-display);

        font-size:
            28px;

        font-weight:
            500;

        margin-bottom:
            7px;
    }


    .empty-state p {

        color:
            var(--text-secondary);

        font-size:
            11px;

        margin-bottom:
            17px;
    }


</style>

</head>
<body>
<div class="cw-page">

<!-- =========================================================
     [4A.4 - HEADER]
     Chỉ có trang chủ và Settings mới có menu đầy đủ.
     Trang này chỉ có thương hiệu + thông tin khách hàng.
========================================================= -->

<header class="topbar">
    <a href="dashboard.php" class="brand" aria-label="Về trang chủ thyu_nhun Weddings">
        <div class="brand-icon">
            <svg class="brand-logo-svg" viewBox="0 0 64 64" aria-hidden="true">
                <path d="M17 19c-5 0-9 4-9 9 0 11 12.5 19 24 28 11.5-9 24-17 24-28 0-5-4-9-9-9-6 0-11 4.5-15 10-4-5.5-9-10-15-10Z"/>
                <path d="M22 18c4 1 7.2 4.1 10 8.2 2.8-4.1 6-7.2 10-8.2"/>
                <circle cx="32" cy="10.5" r="1.6" fill="currentColor" stroke="none"/>
            </svg>
        </div>

        <div class="brand-name">
            <strong>thyu_nhun Weddings</strong>
            <span>WEDDING INVITATION</span>
        </div>
    </a>

    <div class="user-area">
        <div class="user-info">
            <strong>
                Xin chào,
                <?= htmlspecialchars($full_name, ENT_QUOTES, "UTF-8") ?>
            </strong>
            <span>Khách hàng</span>
        </div>

        <div class="avatar" aria-label="Tài khoản">
            <?= htmlspecialchars($avatar_letter, ENT_QUOTES, "UTF-8") ?>
        </div>
    </div>
</header>


<main class="cw-content">
    <!-- [PAGE HEADING] -->
    <div class="cw-heading">
        <div>
            <h1>Ảnh cưới</h1>
            <p>Quản lý ảnh riêng của cô dâu, chú rể, ảnh bìa, QR Code và album ảnh chung của hai bạn.</p>
        </div>
        <!-- [BACK HOME] -->
        <a href="dashboard.php" class="cw-back"><i class="fa-solid fa-arrow-left"></i> Về trang chủ</a>
    </div>

    <!-- [MESSAGE] -->
    <?php if ($success !== ""): ?><div class="cw-message success"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>
    <?php if ($error !== ""): ?><div class="cw-message error"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>

    <!-- [SUMMARY] -->
    <div class="cw-summary">
        <div class="cw-summary-card"><div class="cw-summary-icon"><i class="fa-solid fa-person-dress"></i></div><div class="cw-summary-text"><span>Ảnh cô dâu</span><strong><?= $bride_image !== null ? "1" : "0" ?></strong></div></div>
        <div class="cw-summary-card"><div class="cw-summary-icon"><i class="fa-solid fa-user-tie"></i></div><div class="cw-summary-text"><span>Ảnh chú rể</span><strong><?= $groom_image !== null ? "1" : "0" ?></strong></div></div>
        <div class="cw-summary-card"><div class="cw-summary-icon"><i class="fa-solid fa-image"></i></div><div class="cw-summary-text"><span>Ảnh bìa</span><strong><?= $cover_image !== null ? "1" : "0" ?></strong></div></div>
        <div class="cw-summary-card"><div class="cw-summary-icon"><i class="fa-solid fa-images"></i></div><div class="cw-summary-text"><span>Album ảnh cưới</span><strong><?= count($album_images) ?></strong></div></div>
        <div class="cw-summary-card"><div class="cw-summary-icon"><i class="fa-solid fa-qrcode"></i></div><div class="cw-summary-text"><span>QR Code</span><strong><?= $qr_image !== null ? "1" : "0" ?></strong></div></div>
    </div>

    <!-- =====================================================
         QUẢN LÝ ẢNH DẠNG CARD
    ====================================================== -->

    <div class="cw-photo-pair">

        <!-- ẢNH CÔ DÂU -->
        <section class="cw-photo-card">
            <div class="cw-photo-card-head">
                <div class="cw-photo-card-icon"><i class="fa-solid fa-person-dress"></i></div>
                <div class="cw-photo-card-title">
                    <h2>Ảnh cô dâu</h2>
                    <p>Ảnh riêng của cô dâu.</p>
                </div>
                <span class="cw-photo-card-status"><?= $bride_image !== null ? "Đã có ảnh" : "Chưa có ảnh" ?></span>
            </div>

            <div class="cw-photo-card-preview">
                <?php if ($bride_image !== null): ?>
                    <img src="../<?= htmlspecialchars($bride_image["image_path"]) ?>" alt="Ảnh cô dâu">
                <?php else: ?>
                    <div class="cw-photo-placeholder">
                        <i class="fa-regular fa-image"></i>
                        <span>Chưa có ảnh cô dâu</span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="cw-card-upload-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input id="bride_file" type="file" name="bride_image" class="cw-hidden-file" accept="image/jpeg,image/png,image/webp" required>
                <div class="cw-photo-card-actions">
                    <?php if ($bride_image !== null): ?>
                        <button type="button" class="cw-photo-action cw-photo-view" data-modal="modal_bride"><i class="fa-regular fa-eye"></i> Xem ảnh</button>
                    <?php else: ?>
                        <span class="cw-photo-action cw-photo-view is-disabled"><i class="fa-regular fa-eye"></i> Xem ảnh</span>
                    <?php endif; ?>
                    <label for="bride_file" class="cw-photo-action cw-photo-select"><i class="fa-solid fa-cloud-arrow-up"></i> <?= $bride_image !== null ? "Thay ảnh" : "Chọn ảnh" ?></label>
                </div>
                <button type="submit" name="upload_bride" class="cw-hidden-submit" hidden></button>
            </form>

            <?php if ($bride_image !== null): ?>
            <div class="cw-photo-delete">
                <form method="POST" action="delete_image.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="image_id" value="<?= intval($bride_image["id"]) ?>">
                    <button type="submit" class="cw-btn cw-btn-danger"><i class="fa-solid fa-trash"></i> Xóa ảnh</button>
                </form>
            </div>
            <?php endif; ?>
        </section>

        <!-- ẢNH CHÚ RỂ -->
        <section class="cw-photo-card">
            <div class="cw-photo-card-head">
                <div class="cw-photo-card-icon"><i class="fa-solid fa-user-tie"></i></div>
                <div class="cw-photo-card-title">
                    <h2>Ảnh chú rể</h2>
                    <p>Ảnh riêng của chú rể.</p>
                </div>
                <span class="cw-photo-card-status"><?= $groom_image !== null ? "Đã có ảnh" : "Chưa có ảnh" ?></span>
            </div>

            <div class="cw-photo-card-preview">
                <?php if ($groom_image !== null): ?>
                    <img src="../<?= htmlspecialchars($groom_image["image_path"]) ?>" alt="Ảnh chú rể">
                <?php else: ?>
                    <div class="cw-photo-placeholder">
                        <i class="fa-regular fa-image"></i>
                        <span>Chưa có ảnh chú rể</span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="cw-card-upload-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input id="groom_file" type="file" name="groom_image" class="cw-hidden-file" accept="image/jpeg,image/png,image/webp" required>
                <div class="cw-photo-card-actions">
                    <?php if ($groom_image !== null): ?>
                        <button type="button" class="cw-photo-action cw-photo-view" data-modal="modal_groom"><i class="fa-regular fa-eye"></i> Xem ảnh</button>
                    <?php else: ?>
                        <span class="cw-photo-action cw-photo-view is-disabled"><i class="fa-regular fa-eye"></i> Xem ảnh</span>
                    <?php endif; ?>
                    <label for="groom_file" class="cw-photo-action cw-photo-select"><i class="fa-solid fa-cloud-arrow-up"></i> <?= $groom_image !== null ? "Thay ảnh" : "Chọn ảnh" ?></label>
                </div>
                <button type="submit" name="upload_groom" class="cw-hidden-submit" hidden></button>
            </form>

            <?php if ($groom_image !== null): ?>
            <div class="cw-photo-delete">
                <form method="POST" action="delete_image.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="image_id" value="<?= intval($groom_image["id"]) ?>">
                    <button type="submit" class="cw-btn cw-btn-danger"><i class="fa-solid fa-trash"></i> Xóa ảnh</button>
                </form>
            </div>
            <?php endif; ?>
        </section>

    </div>

    <!-- ẢNH BÌA -->
    <section class="cw-photo-card" style="margin-bottom:20px;">
        <div class="cw-photo-card-head">
            <div class="cw-photo-card-icon"><i class="fa-solid fa-image"></i></div>
            <div class="cw-photo-card-title">
                <h2>Ảnh bìa thiệp</h2>
                <p>Ảnh đại diện hoặc ảnh xuất hiện ở phần đầu thiệp.</p>
            </div>
            <span class="cw-photo-card-status"><?= $cover_image !== null ? "Đã có ảnh" : "Chưa có ảnh" ?></span>
        </div>

        <div class="cw-photo-card-preview">
            <?php if ($cover_image !== null): ?>
                <img src="../<?= htmlspecialchars($cover_image["image_path"]) ?>" alt="Ảnh bìa thiệp">
            <?php else: ?>
                <div class="cw-photo-placeholder"><i class="fa-regular fa-image"></i><span>Chưa có ảnh bìa</span></div>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" class="cw-card-upload-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input id="cover_file" type="file" name="cover_image" class="cw-hidden-file" accept="image/jpeg,image/png,image/webp" required>
            <div class="cw-photo-card-actions">
                <?php if ($cover_image !== null): ?>
                    <button type="button" class="cw-photo-action cw-photo-view" data-modal="modal_cover"><i class="fa-regular fa-eye"></i> Xem ảnh</button>
                <?php else: ?>
                    <span class="cw-photo-action cw-photo-view is-disabled"><i class="fa-regular fa-eye"></i> Xem ảnh</span>
                <?php endif; ?>
                <label for="cover_file" class="cw-photo-action cw-photo-select"><i class="fa-solid fa-cloud-arrow-up"></i> <?= $cover_image !== null ? "Thay ảnh" : "Chọn ảnh" ?></label>
            </div>
            <button type="submit" name="upload_cover" class="cw-hidden-submit" hidden></button>
        </form>

        <?php if ($cover_image !== null): ?>
        <div class="cw-photo-delete">
            <form method="POST" action="delete_image.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="image_id" value="<?= intval($cover_image["id"]) ?>">
                <button type="submit" class="cw-btn cw-btn-danger"><i class="fa-solid fa-trash"></i> Xóa ảnh</button>
            </form>
        </div>
        <?php endif; ?>
    </section>

<section class="cw-section">
        <div class="cw-section-head"><div class="cw-section-title"><div class="cw-section-icon"><i class="fa-solid fa-images"></i></div><div><h2>Album ảnh cưới</h2><p>Thêm nhiều ảnh cùng lúc vào album.</p></div></div><span class="cw-count"><?= count($album_images) ?> ảnh</span></div>
        <div class="cw-upload">
            <div class="cw-upload-head"><div class="cw-upload-icon"><i class="fa-solid fa-photo-film"></i></div><div class="cw-upload-info"><strong>Thêm ảnh vào album</strong><span>Có thể chọn tối đa 20 ảnh mỗi lần. Mỗi ảnh tối đa 5MB.</span></div></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="file" name="album_images[]" class="cw-file" accept="image/jpeg,image/png,image/webp" multiple required>
                <div class="cw-actions"><button type="submit" name="upload_album" class="cw-btn cw-btn-primary"><i class="fa-solid fa-cloud-arrow-up"></i> Tải ảnh album</button></div>
            </form>
        </div>
        <?php if (count($album_images) > 0): ?>
        <div class="cw-album-grid">
            <?php foreach ($album_images as $index => $image): ?>
            <article class="cw-album-item">
                <div class="cw-album-image"><img src="../<?= htmlspecialchars($image["image_path"]) ?>" alt="Ảnh album <?= $index + 1 ?>" loading="lazy"></div>
                <div class="cw-album-footer"><span class="cw-album-number">Ảnh <?= $index + 1 ?></span><form method="POST" action="delete_image.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><input type="hidden" name="image_id" value="<?= intval($image["id"]) ?>"><button type="submit" class="cw-album-delete" title="Xóa ảnh"><i class="fa-solid fa-trash"></i></button></form></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="cw-empty" style="margin-top:20px;"><i class="fa-regular fa-images"></i><strong>Album chưa có ảnh</strong><span>Hãy tải những khoảnh khắc đẹp của hai bạn lên đây.</span></div>
        <?php endif; ?>
    </section>

    <!-- QR CODE -->
    <section class="cw-photo-card" style="margin-bottom:20px;">
        <div class="cw-photo-card-head">
            <div class="cw-photo-card-icon"><i class="fa-solid fa-qrcode"></i></div>
            <div class="cw-photo-card-title">
                <h2>QR Code</h2>
                <p>Mẫu thiệp nào hỗ trợ QR sẽ sử dụng ảnh này.</p>
            </div>
            <span class="cw-photo-card-status"><?= $qr_image !== null ? "Đã có QR" : "Chưa có QR" ?></span>
        </div>

        <div class="cw-photo-card-preview">
            <?php if ($qr_image !== null): ?>
                <img src="../<?= htmlspecialchars($qr_image["image_path"]) ?>" alt="QR Code">
            <?php else: ?>
                <div class="cw-photo-placeholder"><i class="fa-solid fa-qrcode"></i><span>Chưa có QR Code</span></div>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" class="cw-card-upload-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input id="qr_file" type="file" name="qr_image" class="cw-hidden-file" accept="image/jpeg,image/png,image/webp" required>
            <div class="cw-photo-card-actions">
                <?php if ($qr_image !== null): ?>
                    <button type="button" class="cw-photo-action cw-photo-view" data-modal="modal_qr"><i class="fa-regular fa-eye"></i> Xem ảnh</button>
                <?php else: ?>
                    <span class="cw-photo-action cw-photo-view is-disabled"><i class="fa-regular fa-eye"></i> Xem ảnh</span>
                <?php endif; ?>
                <label for="qr_file" class="cw-photo-action cw-photo-select"><i class="fa-solid fa-cloud-arrow-up"></i> <?= $qr_image !== null ? "Thay QR" : "Chọn QR" ?></label>
            </div>
            <button type="submit" name="upload_qr" class="cw-hidden-submit" hidden></button>
        </form>

        <?php if ($qr_image !== null): ?>
        <div class="cw-photo-delete">
            <form method="POST" action="delete_image.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="image_id" value="<?= intval($qr_image["id"]) ?>">
                <button type="submit" class="cw-btn cw-btn-danger"><i class="fa-solid fa-trash"></i> Xóa QR Code</button>
            </form>
        </div>
        <?php endif; ?>
    </section>

    <!-- MODAL XEM ẢNH -->
    <?php if ($bride_image !== null): ?>
    <div id="modal_bride" class="cw-image-modal"><div class="cw-image-modal-box"><button type="button" class="cw-image-modal-close">&times;</button><img src="../<?= htmlspecialchars($bride_image["image_path"]) ?>" alt="Ảnh cô dâu"></div></div>
    <?php endif; ?>

    <?php if ($groom_image !== null): ?>
    <div id="modal_groom" class="cw-image-modal"><div class="cw-image-modal-box"><button type="button" class="cw-image-modal-close">&times;</button><img src="../<?= htmlspecialchars($groom_image["image_path"]) ?>" alt="Ảnh chú rể"></div></div>
    <?php endif; ?>

    <?php if ($cover_image !== null): ?>
    <div id="modal_cover" class="cw-image-modal"><div class="cw-image-modal-box"><button type="button" class="cw-image-modal-close">&times;</button><img src="../<?= htmlspecialchars($cover_image["image_path"]) ?>" alt="Ảnh bìa"></div></div>
    <?php endif; ?>

    <?php if (count($album_images) > 0): ?>
    <div id="modal_album" class="cw-image-modal"><div class="cw-image-modal-box"><button type="button" class="cw-image-modal-close">&times;</button><div class="cw-album-modal-grid"><?php foreach ($album_images as $image): ?><img src="../<?= htmlspecialchars($image["image_path"]) ?>" alt="Ảnh album"><?php endforeach; ?></div></div></div>
    <?php endif; ?>

    <?php if ($qr_image !== null): ?>
    <div id="modal_qr" class="cw-image-modal"><div class="cw-image-modal-box"><button type="button" class="cw-image-modal-close">&times;</button><img src="../<?= htmlspecialchars($qr_image["image_path"]) ?>" alt="QR Code"></div></div>
    <?php endif; ?>


</main>

<!-- =========================================================
     [4A.4 - FOOTER]
     Thông tin liên hệ
========================================================= -->

 <footer>


        <div class="footer-brand">

            thyu_nhun Weddings

        </div>


        <div class="footer-tagline">

            WEDDING INVITATION

        </div>


        <!--
        ====================================================================
        THÔNG TIN LIÊN HỆ
        ====================================================================
        -->

        <div class="footer-contact">


            <!--
            THAY SỐ ĐIỆN THOẠI TẠI ĐÂY
            -->

            <a
                href="tel:0900000000"
            >

                <i class="fa-solid fa-phone"></i>

                0968 117 006

            </a>



            <!--
            THAY LINK FACEBOOK TẠI ĐÂY
            -->

            <a
                href="https://facebook.com/"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="fa-brands fa-facebook-f"></i>

                Facebook

            </a>



            <!--
            WEBSITE
            -->

            <a
                href="https://thyu_nhun.id.vn"
                target="_blank"
                rel="noopener noreferrer"
            >

                <i class="fa-solid fa-globe"></i>

                thyu_nhun.id.vn

            </a>


        </div>



        <div class="footer-copy">

            © <?= date("Y") ?>

            <strong>
                thyu_nhun Weddings
            </strong>

            · Copyright by thyu_nhun.id.vn

        </div>


    </footer>


</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    document.addEventListener("submit", async function (event) {

        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;

        const action = (form.getAttribute("action") || "").trim();

        if (!action || !action.endsWith("delete_image.php")) return;

        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const imageIdInput = form.querySelector('input[name="image_id"]');

        if (!button || !imageIdInput) return;

        let message = "Bạn có chắc muốn xóa ảnh này không?";

        if (form.closest(".cw-photo-card")) {
            const title = form.closest(".cw-photo-card")?.querySelector("h2")?.textContent?.trim() || "";

            if (title.includes("Cô dâu")) {
                message = "Bạn có chắc muốn xóa ảnh cô dâu này không?";
            } else if (title.includes("Chú rể")) {
                message = "Bạn có chắc muốn xóa ảnh chú rể này không?";
            } else if (title.includes("QR")) {
                message = "Bạn có chắc muốn xóa QR Code này không?";
            }
        }

        if (form.closest(".cw-album-item")) {
            message = "Bạn có chắc muốn xóa ảnh này khỏi album không?";
        }

        if (!confirm(message)) return;

        const oldButtonHtml = button.innerHTML;
        const oldDisabled = button.disabled;

        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        try {
            const response = await fetch(form.action || "delete_image.php", {
                method: "POST",
                body: new FormData(form),
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                }
            });

            const raw = await response.text();

            let data = null;

            try {
                data = JSON.parse(raw);
            } catch (parseError) {
                throw new Error(
                    raw
                        ? "Máy chủ trả về dữ liệu không hợp lệ: " + raw.substring(0, 200)
                        : "Máy chủ không trả về dữ liệu."
                );
            }

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || "Không thể xóa ảnh."
                );
            }

            window.location.reload();

        } catch (error) {

            console.error("Delete image error:", error);

            alert(
                error.message ||
                "Có lỗi xảy ra khi xóa ảnh."
            );

            button.disabled = oldDisabled;
            button.innerHTML = oldButtonHtml;
        }

    });

});
</script>


<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".cw-hidden-file").forEach(function (input) {
        input.addEventListener("change", function () {
            if (this.files && this.files.length > 0) {
                this.closest("form").querySelector(".cw-hidden-submit").click();
            }
        });
    });

    document.querySelectorAll("[data-modal]").forEach(function (button) {
        button.addEventListener("click", function () {
            const modal = document.getElementById(this.dataset.modal);
            if (modal) modal.classList.add("is-open");
        });
    });

    document.querySelectorAll(".cw-image-modal-close").forEach(function (button) {
        button.addEventListener("click", function () {
            this.closest(".cw-image-modal").classList.remove("is-open");
        });
    });

    document.querySelectorAll(".cw-image-modal").forEach(function (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) modal.classList.remove("is-open");
        });
    });

});
</script>

</body>
</html>
