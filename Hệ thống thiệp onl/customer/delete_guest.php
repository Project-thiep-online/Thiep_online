<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| 5C.3.6.3.1 - DELETE GUEST
|--------------------------------------------------------------------------
|
| File xử lý xóa khách mời bằng AJAX.
|
| Hỗ trợ:
| - Xóa 1 khách.
| - Xóa nhiều khách đã chọn.
| - Kiểm tra CSRF.
| - Kiểm tra quyền sở hữu Customer.
| - Xóa invitation_links liên quan trước.
| - Trả JSON để guests.php xóa dòng ngay trên giao diện.
|
| QUAN TRỌNG:
| guests.php gửi:
|
| guest_ids[]
|
| Vì vậy file này không sử dụng redirect HTML sau khi xóa.
|
*/


/*
|--------------------------------------------------------------------------
| HÀM KIỂM TRA AJAX
|--------------------------------------------------------------------------
*/

function isAjaxRequest(): bool
{
    return isset(
        $_SERVER["HTTP_X_REQUESTED_WITH"]
    ) && strtolower(
        $_SERVER["HTTP_X_REQUESTED_WITH"]
    ) === "xmlhttprequest";
}


/*
|--------------------------------------------------------------------------
| HÀM TRẢ JSON
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $extra = []
): void {

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


/*
|--------------------------------------------------------------------------
| KIỂM TRA ĐĂNG NHẬP CUSTOMER
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "customer"
) {

    if (isAjaxRequest()) {

        jsonResponse(
            false,
            "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại."
        );

    }

    header(
        "Location: ../auth/login.php"
    );

    exit;
}


$user_id =
    intval(
        $_SESSION["user_id"]
    );


/*
|--------------------------------------------------------------------------
| CHỈ CHO PHÉP POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    if (isAjaxRequest()) {

        jsonResponse(
            false,
            "Phương thức yêu cầu không hợp lệ."
        );

    }

    header(
        "Location: guests.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| KIỂM TRA CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !is_string($_POST["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {

    if (isAjaxRequest()) {

        jsonResponse(
            false,
            "Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại."
        );

    }

    die(
        "Yêu cầu không hợp lệ."
    );
}


/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH GUEST ID
|--------------------------------------------------------------------------
|
| Luồng mới từ guests.php:
|
| guest_ids[]
|
| Giữ hỗ trợ guest_id để tương thích nếu còn link/form cũ.
|
*/

$guest_ids = [];


if (
    isset($_POST["guest_ids"]) &&
    is_array($_POST["guest_ids"])
) {

    foreach ($_POST["guest_ids"] as $id) {

        $id =
            intval($id);

        if (
            $id > 0 &&
            !in_array(
                $id,
                $guest_ids,
                true
            )
        ) {

            $guest_ids[] =
                $id;

        }
    }

}


if (
    empty($guest_ids) &&
    isset($_POST["guest_id"])
) {

    $guest_id =
        intval(
            $_POST["guest_id"]
        );

    if ($guest_id > 0) {

        $guest_ids[] =
            $guest_id;

    }
}


/*
|--------------------------------------------------------------------------
| KIỂM TRA DANH SÁCH KHÁCH
|--------------------------------------------------------------------------
*/

if (
    empty($guest_ids)
) {

    jsonResponse(
        false,
        "Không tìm thấy khách mời cần xóa."
    );
}


/*
|--------------------------------------------------------------------------
| XÓA DỮ LIỆU
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | BẮT ĐẦU TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();


    /*
    |--------------------------------------------------------------------------
    | PREPARE CÁC CÂU LỆNH
    |--------------------------------------------------------------------------
    */

    $find_guest_stmt =
        $conn->prepare(
            "SELECT
                g.id,
                g.wedding_id
             FROM guests g
             INNER JOIN weddings w
                ON g.wedding_id = w.id
             WHERE g.id = ?
               AND w.user_id = ?
             LIMIT 1"
        );


    if (!$find_guest_stmt) {

        throw new Exception(
            "Không thể kiểm tra quyền xóa khách mời."
        );

    }


    $delete_links_stmt =
        $conn->prepare(
            "DELETE FROM invitation_links
             WHERE guest_id = ?
               AND wedding_id = ?"
        );


    if (!$delete_links_stmt) {

        throw new Exception(
            "Không thể chuẩn bị xóa link thiệp."
        );

    }


    $delete_guest_stmt =
        $conn->prepare(
            "DELETE FROM guests
             WHERE id = ?
               AND wedding_id = ?"
        );


    if (!$delete_guest_stmt) {

        throw new Exception(
            "Không thể chuẩn bị xóa khách mời."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | XÓA TỪNG KHÁCH
    |--------------------------------------------------------------------------
    */

    $deleted_count =
        0;


    foreach (
        $guest_ids as $guest_id
    ) {

        /*
        |--------------------------------------------------------------------------
        | KIỂM TRA QUYỀN SỞ HỮU
        |--------------------------------------------------------------------------
        */

        $find_guest_stmt->bind_param(
            "ii",
            $guest_id,
            $user_id
        );


        if (
            !$find_guest_stmt->execute()
        ) {

            throw new Exception(
                "Không thể kiểm tra khách mời."
            );

        }


        $result =
            $find_guest_stmt->get_result();


        $guest =
            $result->fetch_assoc();


        if (!$guest) {

            throw new Exception(
                "Không tìm thấy khách mời hoặc bạn không có quyền xóa."
            );

        }


        $wedding_id =
            intval(
                $guest["wedding_id"]
            );


        /*
        |--------------------------------------------------------------------------
        | XÓA LINK THIỆP RIÊNG
        |--------------------------------------------------------------------------
        */

        $delete_links_stmt->bind_param(
            "ii",
            $guest_id,
            $wedding_id
        );


        if (
            !$delete_links_stmt->execute()
        ) {

            throw new Exception(
                "Không thể xóa link thiệp của khách mời."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | XÓA KHÁCH MỜI
        |--------------------------------------------------------------------------
        */

        $delete_guest_stmt->bind_param(
            "ii",
            $guest_id,
            $wedding_id
        );


        if (
            !$delete_guest_stmt->execute()
        ) {

            throw new Exception(
                "Không thể xóa khách mời."
            );

        }


        if (
            $delete_guest_stmt->affected_rows !== 1
        ) {

            throw new Exception(
                "Khách mời không được xóa."
            );

        }


        $deleted_count++;

    }


    /*
    |--------------------------------------------------------------------------
    | HOÀN TẤT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | ĐÓNG STATEMENT
    |--------------------------------------------------------------------------
    */

    $find_guest_stmt->close();

    $delete_links_stmt->close();

    $delete_guest_stmt->close();


    /*
    |--------------------------------------------------------------------------
    | TRẢ KẾT QUẢ AJAX
    |--------------------------------------------------------------------------
    */

    $message =
        $deleted_count === 1
            ? "Đã xóa khách mời thành công."
            : "Đã xóa " .
              $deleted_count .
              " khách mời thành công.";


    jsonResponse(
        true,
        $message,
        [
            "deleted_count" =>
                $deleted_count,

            "deleted_ids" =>
                $guest_ids
        ]
    );


} catch (
    Throwable $error
) {

    /*
    |--------------------------------------------------------------------------
    | HOÀN TÁC NẾU CÓ LỖI
    |--------------------------------------------------------------------------
    */

    try {

        $conn->rollback();

    } catch (
        Throwable $rollback_error
    ) {

        /*
        | Không cần xử lý thêm ở đây.
        */

    }


    jsonResponse(
        false,
        "Có lỗi hệ thống. Vui lòng thử lại sau."
    );

}

?>
