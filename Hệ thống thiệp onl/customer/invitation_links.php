<?php

session_start();

require_once "../config/database.php";
require_once "../config/url_helper.php";


/*
|--------------------------------------------------------------------------
| KIỂM TRA ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "customer"
) {
    header("Location: ../auth/login.php");
    exit;
}


$user_id = intval(
    $_SESSION["user_id"]
);


/*
|--------------------------------------------------------------------------
| HÀM ESCAPE HTML
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| LẤY TÊN CUSTOMER
|--------------------------------------------------------------------------
*/

$full_name = "Khách hàng";


$stmt = $conn->prepare(
    "SELECT full_name
     FROM users
     WHERE id = ?
     LIMIT 1"
);


if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();


    if ($result->num_rows === 1) {

        $user =
            $result->fetch_assoc();


        if (
            !empty(
                $user["full_name"]
            )
        ) {
            $full_name =
                $user["full_name"];
        }

    }


    $stmt->close();

}


/*
|--------------------------------------------------------------------------
| LẤY WEDDING ID
|--------------------------------------------------------------------------
|
| ƯU TIÊN:
| - Nếu URL có wedding_id -> dùng đúng wedding đó.
| - Nếu URL không có wedding_id -> tự lấy wedding mới nhất của customer.
|
| Nhờ vậy truy cập trực tiếp:
| invitation_links.php
|
| vẫn hoạt động, không còn lỗi:
| "Không tìm thấy thiệp cần quản lý."
|--------------------------------------------------------------------------
*/

$wedding_id =
    intval(
        $_GET["wedding_id"]
        ?? 0
    );


if ($wedding_id <= 0) {

    $stmt = $conn->prepare(
        "SELECT id
         FROM weddings
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );


    if (!$stmt) {

        die(
            "Không thể tải thông tin thiệp: " .
            $conn->error
        );

    }


    $stmt->bind_param(
        "i",
        $user_id
    );


    if (!$stmt->execute()) {

        die(
            "Không thể tải thông tin thiệp: " .
            $stmt->error
        );

    }


    $result =
        $stmt->get_result();


    if ($result->num_rows === 1) {

        $fallback_wedding =
            $result->fetch_assoc();


        $wedding_id =
            intval(
                $fallback_wedding["id"]
            );

    }


    $stmt->close();

}


if ($wedding_id <= 0) {

    die(
        "Tài khoản của bạn chưa có thiệp để quản lý."
    );

}


/*
|--------------------------------------------------------------------------
| KIỂM TRA THIỆP + QUYỀN SỞ HỮU
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        groom_name,
        bride_name,
        wedding_slug

     FROM weddings

     WHERE
        id = ?
        AND user_id = ?

     LIMIT 1"
);


if (!$stmt) {

    die(
        "Không thể tải thông tin thiệp: " .
        $conn->error
    );

}


$stmt->bind_param(
    "ii",
    $wedding_id,
    $user_id
);


if (!$stmt->execute()) {

    die(
        "Không thể tải thông tin thiệp: " .
        $stmt->error
    );

}


$result =
    $stmt->get_result();


if (
    $result->num_rows !== 1
) {

    die(
        "Không tìm thấy thiệp hoặc bạn không có quyền truy cập."
    );

}


$wedding =
    $result->fetch_assoc();


$stmt->close();


if (empty($wedding["wedding_slug"])) {

    $generated_slug = ensure_unique_wedding_slug(
        $conn,
        intval($wedding["id"]),
        (string)($wedding["groom_name"] ?? ""),
        (string)($wedding["bride_name"] ?? "")
    );

    $slug_stmt = $conn->prepare(
        "UPDATE weddings SET wedding_slug = ? WHERE id = ?"
    );

    if ($slug_stmt) {
        $slug_stmt->bind_param(
            "si",
            $generated_slug,
            $wedding_id
        );
        $slug_stmt->execute();
        $slug_stmt->close();

        $wedding["wedding_slug"] = $generated_slug;
    }
}


/*
|--------------------------------------------------------------------------
| KHỞI TẠO BIẾN
|--------------------------------------------------------------------------
*/

$total_links = 0;

$opened_links = 0;

$unopened_links = 0;

$total_visits = 0;

$links = [];


/*
|--------------------------------------------------------------------------
| THỐNG KÊ LINK
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT

        COUNT(*) AS total_links,

        COALESCE(
            SUM(
                CASE
                    WHEN visit_count > 0
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS opened_links,

        COALESCE(
            SUM(
                CASE
                    WHEN visit_count = 0
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS unopened_links,

        COALESCE(
            SUM(visit_count),
            0
        ) AS total_visits

     FROM invitation_links

     WHERE wedding_id = ?"
);


if (!$stmt) {

    die(
        "Không thể tải thống kê link: " .
        $conn->error
    );

}


$stmt->bind_param(
    "i",
    $wedding_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$stats =
    $result->fetch_assoc();


if ($stats) {

    $total_links =
        intval(
            $stats["total_links"]
            ?? 0
        );


    $opened_links =
        intval(
            $stats["opened_links"]
            ?? 0
        );


    $unopened_links =
        intval(
            $stats["unopened_links"]
            ?? 0
        );


    $total_visits =
        intval(
            $stats["total_visits"]
            ?? 0
        );

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH LINK
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT

        il.id,
        il.wedding_id,
        il.guest_id,
        il.token,
        il.public_code,
        il.visit_count,
        il.last_visited,

        g.guest_name,
        g.phone,
        g.group_name,
        g.relationship

     FROM invitation_links il

     INNER JOIN guests g
        ON il.guest_id = g.id

     WHERE
        il.wedding_id = ?

     ORDER BY
        il.id DESC"
);


if (!$stmt) {

    die(
        "Không thể tải danh sách link: " .
        $conn->error
    );

}


$stmt->bind_param(
    "i",
    $wedding_id
);


$stmt->execute();


$result =
    $stmt->get_result();


while (
    $row =
    $result->fetch_assoc()
) {

    $links[] =
        $row;

}


$stmt->close();


foreach ($links as $index => $link_row) {

    if (empty($link_row["public_code"])) {

        $new_public_code = ensure_unique_public_code(
            $conn,
            intval($link_row["id"])
        );

        $code_stmt = $conn->prepare(
            "UPDATE invitation_links
             SET public_code = ?
             WHERE id = ?"
        );

        if ($code_stmt) {
            $link_id_for_update = intval($link_row["id"]);

            $code_stmt->bind_param(
                "si",
                $new_public_code,
                $link_id_for_update
            );

            if ($code_stmt->execute()) {
                $links[$index]["public_code"] =
                    $new_public_code;
            }

            $code_stmt->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| DỮ LIỆU PHÂN TÍCH THỐNG KÊ
|--------------------------------------------------------------------------
*/

$total_guests = 0;
$group_stats = [];
$top_links = [];
$recent_activity = [];

/*
| Tổng khách mời
*/
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_guests
     FROM guests
     WHERE wedding_id = ?"
);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $wedding_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $total_guests =
            intval(
                $row["total_guests"]
                ?? 0
            );

    }

    $stmt->close();

}


/*
| Khách mời theo nhóm
*/
$stmt = $conn->prepare(
    "SELECT
        COALESCE(
            NULLIF(
                TRIM(group_name),
                ''
            ),
            'Chưa phân nhóm'
        ) AS group_label,

        COUNT(*) AS total

     FROM guests

     WHERE wedding_id = ?

     GROUP BY
        COALESCE(
            NULLIF(
                TRIM(group_name),
                ''
            ),
            'Chưa phân nhóm'
        )

     ORDER BY total DESC, group_label ASC"
);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $wedding_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $group_stats[] =
            $row;

    }

    $stmt->close();

}


/*
| Top link được mở nhiều nhất
*/
$top_links = $links;

usort(
    $top_links,
    function ($a, $b) {

        $visit_compare =
            intval(
                $b["visit_count"]
            )
            <=>
            intval(
                $a["visit_count"]
            );

        if ($visit_compare !== 0) {

            return
                $visit_compare;

        }

        return
            strcmp(
                $a["guest_name"],
                $b["guest_name"]
            );

    }
);

$top_links =
    array_slice(
        $top_links,
        0,
        5
    );


/*
| Hoạt động mở thiệp gần đây
*/
foreach ($links as $activity_link) {

    if (
        intval(
            $activity_link["visit_count"]
        ) > 0
        &&
        !empty(
            $activity_link["last_visited"]
        )
    ) {

        $recent_activity[] =
            $activity_link;

    }

}

usort(
    $recent_activity,
    function ($a, $b) {

        return
            strtotime(
                $b["last_visited"]
            )
            <=>
            strtotime(
                $a["last_visited"]
            );

    }
);

$recent_activity =
    array_slice(
        $recent_activity,
        0,
        6
    );


/*
| Chỉ số hiệu quả
*/
$open_rate =
    $total_links > 0
        ? round(
            (
                $opened_links /
                $total_links
            ) * 100,
            1
        )
        : 0;

$average_visits =
    $total_links > 0
        ? round(
            $total_visits /
            $total_links,
            1
        )
        : 0;

$max_group_total = 0;

foreach ($group_stats as $group_stat) {

    $max_group_total =
        max(
            $max_group_total,
            intval(
                $group_stat["total"]
            )
        );

}


/*
|--------------------------------------------------------------------------
| TẠO BASE URL
|--------------------------------------------------------------------------
*/

$base_url = rtrim(
    detect_app_base_url(),
    "/"
);


/*
|--------------------------------------------------------------------------
| AVATAR
|--------------------------------------------------------------------------
*/

$avatar_letter =
    mb_strtoupper(
        mb_substr(
            $full_name,
            0,
            1,
            "UTF-8"
        ),
        "UTF-8"
    );

?>

<!DOCTYPE html>

<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Link thiệp - Thyu_nhun Weddings
    </title>


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
        href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        :root {

            --dark: #3D0B12;
            --wine: #B52A3A;
            --wine-dark: #9F1E2E;
            --wine-soft: #FCE8E7;

            --cream: #FFF8F6;
            --white: #ffffff;

            --text: #32191C;
            --text-soft: #877275;

            --border: #EEDDDD;

            --success: #658a79;
            --success-soft: #edf4ef;

            --warning: #b78945;
            --warning-soft: #FCF5E5;

            --shadow:
                0 10px 30px
                rgba(
                    66,
                    41,
                    42,
                    .06
                );

        }


        * {

            margin: 0;
            padding: 0;

            box-sizing:
                border-box;

        }


        body {

            min-height:
                100vh;

            background:
                var(--cream);

            color:
                var(--text);

            font-family:
                "Be Vietnam Pro",
                sans-serif;

        }


        button,
        input {

            font:
                inherit;

        }


        .topbar {

            min-height:
                82px;

            padding:
                0 42px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .94
                );

            border-bottom:
                1px solid
                var(--border);

        }


        .topbar-left {

            display:
                flex;

            align-items:
                center;

            gap:
                14px;

        }


        .back-dashboard {

            width:
                42px;

            height:
                42px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                12px;

            text-decoration:
                none;

            background:
                var(--wine-soft);

            color:
                var(--wine);

            transition:
                .2s ease;

        }


        .back-dashboard:hover {

            transform:
                translateX(-2px);

            background:
                var(--wine);

            color:
                white;

        }


        .page-heading {

            display:
                flex;

            flex-direction:
                column;

            gap:
                3px;

        }


        .page-heading strong {

            font-size:
                16px;

        }


        .page-heading span {

            font-size:
                12px;

            color:
                var(--text-soft);

        }


        .user-area {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

        }


        .user-text {

            text-align:
                right;

        }


        .user-text strong {

            display:
                block;

            font-size:
                14px;

        }


        .user-text span {

            display:
                block;

            margin-top:
                3px;

            font-size:
                12px;

            color:
                var(--text-soft);

        }


        .avatar {

            width:
                42px;

            height:
                42px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            background:
                linear-gradient(
                    135deg,
                    #b9877d,
                    #7d4b4b
                );

            color:
                white;

            font-weight:
                700;

        }


        .page {

            width:
                min(
                    1320px,
                    calc(
                        100% - 48px
                    )
                );

            margin:
                0 auto;

            padding:
                42px 0 70px;

        }


        .page-intro {

            display:
                flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap:
                30px;

            margin-bottom:
                30px;

        }


        .page-title h1 {

            font-family:
                "Playfair Display",
                serif;

            font-size:
                38px;

            font-weight:
                600;

        }


        .page-title p {

            margin-top:
                10px;

            font-size:
                14px;

            line-height:
                1.7;

            color:
                var(--text-soft);

        }


        .page-actions {

            display:
                flex;

            gap:
                12px;

        }


        .btn {

            min-height:
                44px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                9px;

            padding:
                0 18px;

            border-radius:
                12px;

            border:
                1px solid
                transparent;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                600;

            transition:
                .2s ease;

        }


        .btn-secondary {

            background:
                white;

            border-color:
                var(--border);

            color:
                var(--text);

        }


        .btn-primary {

            background:
                var(--wine);

            color:
                white;

        }


        .btn:hover {

            transform:
                translateY(-2px);

        }


        .btn-primary:hover {

            background:
                var(--wine-dark);

        }


        .stats-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                18px;

            margin-bottom:
                28px;

        }


        .stat-card {

            min-height:
                130px;

            padding:
                22px;

            display:
                flex;

            align-items:
                center;

            gap:
                16px;

            background:
                white;

            border:
                1px solid
                var(--border);

            border-radius:
                18px;

            box-shadow:
                var(--shadow);

        }


        .stat-icon {

            width:
                52px;

            height:
                52px;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                15px;

            font-size:
                19px;

            background:
                var(--wine-soft);

            color:
                var(--wine);

        }


        .stat-info span {

            display:
                block;

            font-size:
                12px;

            color:
                var(--text-soft);

        }


        .stat-info strong {

            display:
                block;

            margin-top:
                7px;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                27px;

        }


        .content-card {

            background:
                white;

            border:
                1px solid
                var(--border);

            border-radius:
                20px;

            box-shadow:
                var(--shadow);

            overflow:
                hidden;

        }


        .content-card-header {

            padding:
                25px 28px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            border-bottom:
                1px solid
                var(--border);

        }


        .content-card-title {

            display:
                flex;

            align-items:
                center;

            gap:
                14px;

        }


        .content-card-title-icon {

            width:
                45px;

            height:
                45px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                14px;

            background:
                var(--wine-soft);

            color:
                var(--wine);

        }


        .content-card-title h2 {

            font-family:
                "Playfair Display",
                serif;

            font-size:
                23px;

        }


        .content-card-title p {

            margin-top:
                5px;

            font-size:
                12px;

            color:
                var(--text-soft);

        }


        .search-box {

            position:
                relative;

            width:
                310px;

        }


        .search-box i {

            position:
                absolute;

            left:
                14px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                var(--text-soft);

        }


        .search-box input {

            width:
                100%;

            height:
                44px;

            padding:
                0 15px 0 40px;

            border:
                1px solid
                var(--border);

            border-radius:
                12px;

            outline:
                none;

        }


        .table-wrap {

            width:
                100%;

            overflow-x:
                auto;

        }


        table {

            width:
                100%;

            min-width:
                1000px;

            border-collapse:
                collapse;

        }


        thead {

            background:
                #fcfaf8;

        }


        th {

            padding:
                16px 20px;

            text-align:
                left;

            font-size:
                11px;

            text-transform:
                uppercase;

            color:
                var(--text-soft);

            border-bottom:
                1px solid
                var(--border);

        }


        td {

            padding:
                18px 20px;

            border-bottom:
                1px solid
                #f0ebe7;

            font-size:
                13px;

        }


        tbody tr:hover {

            background:
                #FFFAF9;

        }


        .guest-info {

            display:
                flex;

            flex-direction:
                column;

            gap:
                5px;

        }


        .guest-info span {

            font-size:
                11px;

            color:
                var(--text-soft);

        }


        .link-code {

            max-width:
                300px;

            display:
                flex;

            align-items:
                center;

            gap:
                8px;

        }


        .link-code span {

            flex:
                1;

            overflow:
                hidden;

            white-space:
                nowrap;

            text-overflow:
                ellipsis;

            padding:
                9px 11px;

            border-radius:
                9px;

            background:
                #f8f5f3;

            font-family:
                monospace;

            font-size:
                11px;

        }


        .copy-btn {

            width:
                36px;

            height:
                36px;

            border:
                1px solid
                var(--border);

            border-radius:
                9px;

            background:
                white;

            color:
                var(--wine);

            cursor:
                pointer;

        }


        .status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                7px 10px;

            border-radius:
                20px;

            font-size:
                11px;

            font-weight:
                600;

        }


        .opened {

            background:
                var(--success-soft);

            color:
                var(--success);

        }


        .unopened {

            background:
                var(--warning-soft);

            color:
                var(--warning);

        }


        .status-dot {

            width:
                7px;

            height:
                7px;

            border-radius:
                50%;

            background:
                currentColor;

        }


        .visit-count {

            font-size:
                18px;

            font-weight:
                700;

        }


        .last-visited {

            font-size:
                12px;

            color:
                var(--text-soft);

        }


        .icon-btn {

            width:
                38px;

            height:
                38px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                10px;

            background:
                var(--wine-soft);

            color:
                var(--wine);

            text-decoration:
                none;

        }


        .empty-state {

            min-height:
                420px;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            text-align:
                center;

            padding:
                40px;

        }


        .empty-icon {

            width:
                78px;

            height:
                78px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            margin-bottom:
                20px;

            border-radius:
                24px;

            background:
                var(--wine-soft);

            color:
                var(--wine);

            font-size:
                28px;

        }


        .empty-state p {

            max-width:
                450px;

            margin-top:
                12px;

            color:
                var(--text-soft);

            line-height:
                1.8;

        }


        .toast {

            position:
                fixed;

            right:
                25px;

            bottom:
                25px;

            z-index:
                9999;

            padding:
                14px 18px;

            border-radius:
                13px;

            background:
                #2f2526;

            color:
                white;

            opacity:
                0;

            transform:
                translateY(15px);

            pointer-events:
                none;

            transition:
                .25s ease;

        }


        .toast.show {

            opacity:
                1;

            transform:
                translateY(0);

        }


        @media (
            max-width: 1000px
        ) {

            .stats-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );

            }

        }


        @media (
            max-width: 760px
        ) {

            .topbar {

                padding:
                    16px 20px;

            }


            .user-text {

                display:
                    none;

            }


            .page {

                width:
                    calc(
                        100% - 30px
                    );

                padding-top:
                    30px;

            }


            .page-intro {

                flex-direction:
                    column;

            }


            .stats-grid {

                grid-template-columns:
                    1fr;

            }


            .content-card-header {

                flex-direction:
                    column;

                align-items:
                    stretch;

            }


            .search-box {

                width:
                    100%;

            }

        }

    
        /* =====================================================
           THYU_NHUN WEDDINGS — INVITATION LINKS MASTER STYLE
           Customer page: no sidebar, focused link management.
        ===================================================== */
        body { background:#FFF8F6 !important; color:#32191C !important; }
        .sidebar, aside.sidebar, .main-sidebar, .sidebar-overlay { display:none !important; }
        .main, main.main, .content, .page-content { width:100% !important; margin-left:0 !important; }
        .topbar, header.topbar {
            position:sticky; top:0; z-index:200; min-height:74px; height:auto;
            padding:14px 5vw !important; background:rgba(255,255,255,.94) !important;
            border-bottom:1px solid #ECD8D5 !important; box-shadow:none !important;
            backdrop-filter:blur(14px);
        }
        .page, .container, .content-wrap {
            width:min(1180px, calc(100% - 32px)) !important;
            margin-left:auto !important; margin-right:auto !important;
        }
        .page { padding:42px 0 52px !important; }
        .page-title, h1, .section-title h2 {
            font-family:"Cormorant Garamond", Georgia, serif !important;
            color:#32191C !important;
        }
        .stats-grid, .summary-grid { gap:18px !important; }
        .stat-card, .summary-card, .links-section, .section-card {
            background:#fff !important; border:1px solid #ECD8D5 !important;
            border-radius:20px !important; box-shadow:0 10px 35px rgba(61,48,48,.05) !important;
        }
        .stat-card, .summary-card { padding:22px !important; }
        .stat-number, .stat-value { color:#65131F !important; }
        .links-section, .section-card { padding:26px !important; }
        .search-box input {
            border:1px solid #ECD8D5 !important; background:#FFFCFB !important;
            border-radius:12px !important;
        }
        .search-box input:focus {
            border-color:#8F1D2C !important;
            box-shadow:0 0 0 4px rgba(143,29,44,.10) !important;
            outline:none !important;
        }
        .table-wrap {
            border:1px solid #ECD8D5 !important; border-radius:16px !important; overflow:auto !important;
        }
        table thead th { background:#f7f0ed !important; color:#6a5554 !important; }
        table tbody tr:hover { background:#FFF7F6 !important; }
        .link-code {
            background:#FFF7F6 !important; border:1px solid #ECD8D5 !important;
            border-radius:10px !important;
        }
        .copy-btn {
            border-radius:9px !important; background:#FCE8E7 !important;
            color:#65131F !important;
        }
        .copy-btn:hover { background:#65131F !important; color:#fff !important; }
        .status { border-radius:999px !important; }
        .status.opened { background:#eef7f1 !important; }
        .status.unopened { background:#f8eeeb !important; }
        .btn, .btn-primary, .open-btn, .action-btn {
            border-radius:12px !important;
        }
        .btn-primary, .open-btn.primary {
            background:linear-gradient(135deg,#8F1D2C,#65131F) !important;
            border-color:#8F1D2C !important; color:#fff !important;
            box-shadow:0 10px 22px rgba(143,29,44,.14);
        }
        .empty-state {
            background:#fff !important; border:1px dashed #DDB7BA !important;
            border-radius:18px !important;
        }
        @media(max-width:768px){
            .topbar, header.topbar { padding:12px 16px !important; }
            .page { padding:28px 0 38px !important; }
            .links-section, .section-card { padding:18px !important; border-radius:16px !important; }
            .stat-card, .summary-card { border-radius:16px !important; }
        }



        /* ==============================================================
           THỐNG KÊ & PHÂN TÍCH TRÊN TRANG LINK THIỆP
        ============================================================== */

        .analytics-section {
            margin-bottom: 28px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .analytics-card {
            padding: 26px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .analytics-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        .analytics-card-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .analytics-card-title-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: var(--wine-soft);
            color: var(--wine);
        }

        .analytics-card h2 {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .analytics-card p {
            font-size: 12px;
            color: var(--text-soft);
            line-height: 1.6;
        }

        .open-rate-layout {
            display: grid;
            grid-template-columns: 170px 1fr;
            align-items: center;
            gap: 24px;
        }

        .donut-chart {
            --open-rate: 0;
            width: 166px;
            height: 166px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background:
                conic-gradient(
                    var(--wine) calc(var(--open-rate) * 1%),
                    #efe6e1 0
                );
            position: relative;
        }

        .donut-chart::after {
            content: "";
            width: 116px;
            height: 116px;
            border-radius: 50%;
            background: #fff;
            position: absolute;
        }

        .donut-value {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .donut-value strong {
            display: block;
            font-family: "Playfair Display", serif;
            font-size: 28px;
            color: var(--text);
        }

        .donut-value span {
            font-size: 11px;
            color: var(--text-soft);
        }

        .analysis-list {
            display: grid;
            gap: 12px;
        }

        .analysis-item {
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            background: #FFFCFB;
        }

        .analysis-item-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: var(--text-soft);
        }

        .analysis-item-label i {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: var(--wine-soft);
            color: var(--wine);
        }

        .analysis-item strong {
            font-size: 17px;
            color: var(--text);
        }

        .group-list {
            display: grid;
            gap: 17px;
        }

        .group-row {
            display: grid;
            grid-template-columns: minmax(105px, 145px) 1fr 36px;
            align-items: center;
            gap: 14px;
        }

        .group-name {
            font-size: 13px;
            font-weight: 600;
        }

        .group-bar {
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #f0e9e5;
        }

        .group-bar span {
            display: block;
            height: 100%;
            min-width: 8px;
            border-radius: inherit;
            background: linear-gradient(90deg, #a96868, var(--wine));
        }

        .group-count {
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            color: var(--wine-dark);
        }

        .ranking-list,
        .activity-list {
            display: grid;
            gap: 11px;
        }

        .ranking-item,
        .activity-item {
            padding: 14px 15px;
            border: 1px solid var(--border);
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 13px;
            background: #FFFCFB;
        }

        .rank-number {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: var(--wine-soft);
            color: var(--wine-dark);
            font-weight: 700;
            font-size: 13px;
        }

        .ranking-info,
        .activity-info {
            min-width: 0;
            flex: 1;
        }

        .ranking-info strong,
        .activity-info strong {
            display: block;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ranking-info span,
        .activity-info span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: var(--text-soft);
        }

        .ranking-visits {
            text-align: right;
        }

        .ranking-visits strong {
            display: block;
            font-size: 17px;
            color: var(--wine-dark);
        }

        .ranking-visits span {
            font-size: 10px;
            color: var(--text-soft);
        }

        .activity-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--success-soft);
            color: var(--success);
        }

        .empty-mini {
            padding: 28px 18px;
            text-align: center;
            border: 1px dashed var(--border);
            border-radius: 14px;
            color: var(--text-soft);
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 1000px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .open-rate-layout {
                grid-template-columns: 1fr;
                justify-items: center;
            }

            .group-row {
                grid-template-columns: 95px 1fr 30px;
                gap: 9px;
            }
        }

    
        /* ==========================================================
           VIETNAMESE RED — GLOBAL CUSTOMER COLOR SYNC
           Shared with auth/login.php
        ========================================================== */
        :root {
            --vn-red: #8F1D2C;
            --vn-red-dark: #65131F;
            --vn-red-deep: #3D0B12;
            --vn-red-accent: #B52A3A;
            --vn-red-soft: #FCE8E7;
            --vn-red-light: #F3C7C5;
            --vn-bg: #FFF8F6;
            --vn-card: #FFFFFF;
            --vn-text: #32191C;
            --vn-muted: #7E6669;
            --vn-border: #ECD8D5;
            --vn-gold: #D4A73D;
        }

        ::selection {
            background: var(--vn-red-soft);
            color: var(--vn-red-dark);
        }

        a:hover {
            color: var(--vn-red);
        }

        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible {
            outline-color: var(--vn-red);
        }

    </style>

</head>


<body>


    <header class="topbar">


        <div class="topbar-left">

            <div class="page-heading">

                <strong>
                    Link thiệp
                </strong>

                <span>

                    <?= e(
                        $wedding["groom_name"]
                    ) ?>

                    &

                    <?= e(
                        $wedding["bride_name"]
                    ) ?>

                </span>

            </div>


        </div>


        <div class="user-area">


            <div class="user-text">

                <strong>
                    <?= e($full_name) ?>
                </strong>

                <span>
                    Khách hàng
                </span>

            </div>


            <div class="avatar">

                <?= e(
                    $avatar_letter
                ) ?>

            </div>


        </div>


    </header>



    <main class="page">


        <section class="page-intro">


            <div class="page-title">

                <h1>
                    Link thiệp của khách mời
                </h1>

                <p>
                    Quản lý và theo dõi các đường link thiệp riêng
                    của từng khách mời.
                </p>

            </div>


            <div class="page-actions">


                <a
                    href="guests.php?wedding_id=<?= $wedding_id ?>"
                    class="btn btn-secondary"
                >

                    <i class="fa-solid fa-users"></i>

                    Khách mời

                </a>


                <a
                    href="dashboard.php"
                    class="btn btn-primary"
                >

                    <i class="fa-solid fa-house"></i>

                    Trang chủ

                </a>


            </div>


        </section>



        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div class="stat-info">

                    <span>
                        Tổng khách mời
                    </span>

                    <strong>
                        <?= $total_guests ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-link"></i>
                </div>

                <div class="stat-info">

                    <span>
                        Tổng link đã tạo
                    </span>

                    <strong>
                        <?= $total_links ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-eye"></i>
                </div>

                <div class="stat-info">

                    <span>
                        Tổng lượt mở thiệp
                    </span>

                    <strong>
                        <?= $total_visits ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>

                <div class="stat-info">

                    <span>
                        Tỷ lệ khách đã mở thiệp
                    </span>

                    <strong>
                        <?= rtrim(rtrim(number_format($open_rate, 1, '.', ''), '0'), '.') ?>%
                    </strong>

                </div>

            </div>


        </section>



        <section class="analytics-section">


            <div class="analytics-grid">


                <article class="analytics-card">

                    <div class="analytics-card-header">

                        <div class="analytics-card-title">

                            <div class="analytics-card-title-icon">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>

                            <div>
                                <h2>Phân tích hiệu quả gửi thiệp</h2>
                                <p>Theo dõi mức độ tương tác với các link thiệp đã gửi.</p>
                            </div>

                        </div>

                    </div>


                    <div class="open-rate-layout">

                        <div
                            class="donut-chart"
                            style="--open-rate: <?= max(0, min(100, $open_rate)) ?>"
                        >

                            <div class="donut-value">
                                <strong>
                                    <?= rtrim(rtrim(number_format($open_rate, 1, '.', ''), '0'), '.') ?>%
                                </strong>
                                <span>Tỷ lệ đã mở</span>
                            </div>

                        </div>


                        <div class="analysis-list">

                            <div class="analysis-item">

                                <div class="analysis-item-label">
                                    <i class="fa-solid fa-envelope-open"></i>
                                    <span>Số khách đã mở</span>
                                </div>

                                <strong><?= $opened_links ?></strong>

                            </div>


                            <div class="analysis-item">

                                <div class="analysis-item-label">
                                    <i class="fa-regular fa-envelope"></i>
                                    <span>Số khách chưa mở</span>
                                </div>

                                <strong><?= $unopened_links ?></strong>

                            </div>


                            <div class="analysis-item">

                                <div class="analysis-item-label">
                                    <i class="fa-solid fa-chart-line"></i>
                                    <span>Trung bình lượt mở / link</span>
                                </div>

                                <strong><?= rtrim(rtrim(number_format($average_visits, 1, '.', ''), '0'), '.') ?></strong>

                            </div>

                        </div>

                    </div>

                </article>



                <article class="analytics-card">

                    <div class="analytics-card-header">

                        <div class="analytics-card-title">

                            <div class="analytics-card-title-icon">
                                <i class="fa-solid fa-user-group"></i>
                            </div>

                            <div>
                                <h2>Khách mời theo nhóm</h2>
                                <p>Phân bố khách mời dựa trên nhóm đã thiết lập.</p>
                            </div>

                        </div>

                    </div>


                    <?php if (count($group_stats) > 0): ?>

                        <div class="group-list">

                            <?php foreach ($group_stats as $group_stat): ?>

                                <?php

                                    $group_total =
                                        intval(
                                            $group_stat["total"]
                                        );

                                    $group_width =
                                        $max_group_total > 0
                                            ? round(
                                                (
                                                    $group_total /
                                                    $max_group_total
                                                ) * 100
                                            )
                                            : 0;

                                ?>

                                <div class="group-row">

                                    <div class="group-name">
                                        <?= e($group_stat["group_label"]) ?>
                                    </div>

                                    <div class="group-bar">
                                        <span style="width: <?= $group_width ?>%"></span>
                                    </div>

                                    <div class="group-count">
                                        <?= $group_total ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-mini">
                            Chưa có dữ liệu khách mời để phân tích nhóm.
                        </div>

                    <?php endif; ?>

                </article>


            </div>



            <div class="analytics-grid">


                <article class="analytics-card">

                    <div class="analytics-card-header">

                        <div class="analytics-card-title">

                            <div class="analytics-card-title-icon">
                                <i class="fa-solid fa-trophy"></i>
                            </div>

                            <div>
                                <h2>Top link được mở nhiều nhất</h2>
                                <p>Những khách mời có link thiệp được xem nhiều nhất.</p>
                            </div>

                        </div>

                    </div>


                    <?php if (count($top_links) > 0): ?>

                        <div class="ranking-list">

                            <?php foreach ($top_links as $index => $top_link): ?>

                                <div class="ranking-item">

                                    <div class="rank-number">
                                        #<?= $index + 1 ?>
                                    </div>


                                    <div class="ranking-info">

                                        <strong>
                                            <?= e($top_link["guest_name"]) ?>
                                        </strong>

                                        <span>
                                            Mở gần nhất:
                                            <?= !empty($top_link["last_visited"]) ? date("d/m/Y H:i", strtotime($top_link["last_visited"])) : "Chưa mở" ?>
                                        </span>

                                    </div>


                                    <div class="ranking-visits">

                                        <strong>
                                            <?= intval($top_link["visit_count"]) ?>
                                        </strong>

                                        <span>
                                            lượt mở
                                        </span>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-mini">
                            Chưa có link thiệp để xếp hạng.
                        </div>

                    <?php endif; ?>

                </article>



                <article class="analytics-card">

                    <div class="analytics-card-header">

                        <div class="analytics-card-title">

                            <div class="analytics-card-title-icon">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>

                            <div>
                                <h2>Hoạt động gần đây</h2>
                                <p>Những khách mời đã mở thiệp gần đây nhất.</p>
                            </div>

                        </div>

                    </div>


                    <?php if (count($recent_activity) > 0): ?>

                        <div class="activity-list">

                            <?php foreach ($recent_activity as $activity): ?>

                                <div class="activity-item">

                                    <div class="activity-icon">
                                        <i class="fa-solid fa-envelope-open-text"></i>
                                    </div>


                                    <div class="activity-info">

                                        <strong>
                                            <?= e($activity["guest_name"]) ?>
                                        </strong>

                                        <span>
                                            Đã mở thiệp ·
                                            <?= date("d/m/Y H:i", strtotime($activity["last_visited"])) ?>
                                        </span>

                                    </div>


                                    <div class="ranking-visits">

                                        <strong>
                                            <?= intval($activity["visit_count"]) ?>
                                        </strong>

                                        <span>
                                            lượt mở
                                        </span>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-mini">
                            Chưa có hoạt động mở thiệp nào.
                        </div>

                    <?php endif; ?>

                </article>


            </div>


        </section>



        <section class="content-card">


            <div class="content-card-header">


                <div class="content-card-title">


                    <div class="content-card-title-icon">

                        <i class="fa-solid fa-link"></i>

                    </div>


                    <div>

                        <h2>
                            Danh sách link thiệp
                        </h2>

                        <p>
                            Mỗi khách mời có một đường link riêng.
                        </p>

                    </div>


                </div>


                <div class="search-box">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Tìm tên khách mời..."
                    >

                </div>


            </div>



            <?php if (count($links) > 0): ?>


                <div class="table-wrap">


                    <table id="linksTable">


                        <thead>

                            <tr>

                                <th>
                                    Khách mời
                                </th>

                                <th>
                                    Link thiệp
                                </th>

                                <th>
                                    Trạng thái
                                </th>

                                <th>
                                    Lượt mở
                                </th>

                                <th>
                                    Lần mở gần nhất
                                </th>

                                <th>
                                    Thao tác
                                </th>

                            </tr>

                        </thead>



                        <tbody>


                            <?php foreach ($links as $link): ?>


                                <?php

                                $public_code =
                                    (string)(
                                        $link["public_code"]
                                        ?? ""
                                    );


                                $invite_url =
                                    build_public_invite_url(
                                        (string)(
                                            $wedding["wedding_slug"]
                                            ?? ""
                                        ),
                                        $public_code
                                    );


                                $visit_count =
                                    intval(
                                        $link["visit_count"]
                                        ?? 0
                                    );


                                $is_opened =
                                    $visit_count > 0;


                                $last_visited =
                                    $link["last_visited"];

                                ?>


                                <tr
                                    data-guest-name="<?= e(
                                        strtolower(
                                            $link["guest_name"]
                                        )
                                    ) ?>"
                                >


                                    <td>

                                        <div class="guest-info">

                                            <strong>

                                                <?= e(
                                                    $link["guest_name"]
                                                ) ?>

                                            </strong>


                                            <?php if (
                                                !empty(
                                                    $link["group_name"]
                                                )
                                            ): ?>

                                                <span>

                                                    <?= e(
                                                        $link["group_name"]
                                                    ) ?>

                                                </span>


                                            <?php elseif (
                                                !empty(
                                                    $link["relationship"]
                                                )
                                            ): ?>

                                                <span>

                                                    <?= e(
                                                        $link["relationship"]
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>


                                        </div>

                                    </td>



                                    <td>

                                        <div class="link-code">


                                            <span
                                                title="<?= e(
                                                    $invite_url
                                                ) ?>"
                                            >

                                                <?= e(
                                                    $invite_url
                                                ) ?>

                                            </span>


                                            <button
                                                type="button"
                                                class="copy-btn"
                                                data-link="<?= e(
                                                    $invite_url
                                                ) ?>"
                                                title="Sao chép link"
                                            >

                                                <i class="fa-regular fa-copy"></i>

                                            </button>


                                        </div>

                                    </td>



                                    <td>


                                        <?php if (
                                            $is_opened
                                        ): ?>

                                            <span class="status opened">

                                                <span
                                                    class="status-dot"
                                                ></span>

                                                Đã mở

                                            </span>


                                        <?php else: ?>

                                            <span class="status unopened">

                                                <span
                                                    class="status-dot"
                                                ></span>

                                                Chưa mở

                                            </span>

                                        <?php endif; ?>


                                    </td>



                                    <td>

                                        <span class="visit-count">

                                            <?= $visit_count ?>

                                        </span>

                                    </td>



                                    <td>

                                        <div class="last-visited">


                                            <?php if (
                                                !empty(
                                                    $last_visited
                                                )
                                            ): ?>


                                                <?= e(
                                                    date(
                                                        "d/m/Y H:i",
                                                        strtotime(
                                                            $last_visited
                                                        )
                                                    )
                                                ) ?>


                                            <?php else: ?>

                                                Chưa có lượt mở

                                            <?php endif; ?>


                                        </div>

                                    </td>



                                    <td>

                                        <a
                                            href="<?= e(
                                                $invite_url
                                            ) ?>"
                                            target="_blank"
                                            class="icon-btn"
                                            title="Mở thiệp"
                                        >

                                            <i
                                                class="fa-solid fa-arrow-up-right-from-square"
                                            ></i>

                                        </a>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>



            <?php else: ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-link-slash"></i>

                    </div>


                    <h2>
                        Chưa có link thiệp
                    </h2>


                    <p>
                        Hiện bạn chưa tạo link riêng cho khách mời.
                        Hãy thêm khách mời để hệ thống tạo link thiệp.
                    </p>


                    <a
                        href="guests.php?wedding_id=<?= $wedding_id ?>"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-users"></i>

                        Đi đến khách mời

                    </a>


                </div>


            <?php endif; ?>


        </section>


    </main>



    <div
        class="toast"
        id="toast"
    >
        <i class="fa-solid fa-check"></i>

        <span id="toastText">
            Đã sao chép link thiệp.
        </span>
    </div>



    <script>

        const copyButtons =
            document.querySelectorAll(
                ".copy-btn"
            );


        const toast =
            document.getElementById(
                "toast"
            );


        const toastText =
            document.getElementById(
                "toastText"
            );


        let toastTimer;


        function showToast(
            message
        ) {

            toastText.textContent =
                message;


            toast.classList.add(
                "show"
            );


            clearTimeout(
                toastTimer
            );


            toastTimer =
                setTimeout(
                    function () {

                        toast.classList.remove(
                            "show"
                        );

                    },
                    2500
                );

        }


        copyButtons.forEach(
            function (
                button
            ) {

                button.addEventListener(
                    "click",
                    async function () {

                        const link =
                            button.dataset.link;


                        try {

                            await navigator.clipboard.writeText(
                                link
                            );


                            showToast(
                                "Đã sao chép link thiệp."
                            );

                        }
                        catch (
                            error
                        ) {

                            const textarea =
                                document.createElement(
                                    "textarea"
                                );


                            textarea.value =
                                link;


                            textarea.style.position =
                                "fixed";


                            textarea.style.opacity =
                                "0";


                            document.body.appendChild(
                                textarea
                            );


                            textarea.select();


                            document.execCommand(
                                "copy"
                            );


                            textarea.remove();


                            showToast(
                                "Đã sao chép link thiệp."
                            );

                        }

                    }
                );

            }
        );


        const searchInput =
            document.getElementById(
                "searchInput"
            );


        const tableRows =
            document.querySelectorAll(
                "#linksTable tbody tr"
            );

        if (
            searchInput
        ) {

            searchInput.addEventListener(
                "input",
                function () {

                    const keyword =
                        this.value
                        .trim()
                        .toLowerCase();

                    tableRows.forEach(
                        function (
                            row
                        ) {

                            const guestName =
                                row.dataset.guestName
                                || "";

                            row.style.display =
                                guestName.includes(
                                    keyword
                                )
                                    ? ""
                                    : "none";

                        }
                    );

                }
            );

        }

    </script>

</body>

</html>