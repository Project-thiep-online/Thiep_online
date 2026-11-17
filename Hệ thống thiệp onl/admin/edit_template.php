<?php

session_start();

require_once "../config/database.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/*
|--------------------------------------------------------------------------
| KIỂM TRA ADMIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY ID MẪU
|--------------------------------------------------------------------------
*/

$template_id = intval(
    $_GET["id"] ?? 0
);

if ($template_id <= 0) {
    header("Location: templates.php?status=error");
    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN MẪU
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT *
     FROM templates
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $template_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: templates.php?status=error");
    exit;
}

$template = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| THÔNG BÁO
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| XỬ LÝ CẬP NHẬT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $template_name = trim(
        $_POST["template_name"] ?? ""
    );

    $description = trim(
        $_POST["description"] ?? ""
    );

    $folder = trim(
        $_POST["folder"] ?? ""
    );

    $status = $_POST["status"] ?? "active";


    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA DỮ LIỆU
    |--------------------------------------------------------------------------
    */

    if (
        $template_name === "" ||
        $folder === ""
    ) {

        $error =
            "Vui lòng nhập tên mẫu và thư mục.";

    } elseif (
        !in_array(
            $status,
            ["active", "inactive"],
            true
        )
    ) {

        $error =
            "Trạng thái không hợp lệ.";

    } elseif (
        !preg_match(
            '/^[a-zA-Z0-9_\/-]+$/',
            $folder
        )
    ) {

        $error =
            "Đường dẫn thư mục không hợp lệ.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | KIỂM TRA FOLDER TRÙNG
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare(
            "SELECT id
             FROM templates
             WHERE folder = ?
             AND id != ?
             LIMIT 1"
        );

        $check->bind_param(
            "si",
            $folder,
            $template_id
        );

        $check->execute();

        $check_result =
            $check->get_result();


        if ($check_result->num_rows > 0) {

            $error =
                "Thư mục này đang được sử dụng bởi một mẫu khác.";

        } elseif (!is_dir("../" . $folder)) {

            $error =
                "Không tìm thấy thư mục mẫu: " . $folder;

        } else {


            /*
            |--------------------------------------------------------------------------
            | CẬP NHẬT
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "UPDATE templates
                 SET
                    template_name = ?,
                    description = ?,
                    folder = ?,
                    status = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "ssssi",
                $template_name,
                $description,
                $folder,
                $status,
                $template_id
            );


            if ($stmt->execute()) {

                $success =
                    "Đã cập nhật mẫu thiệp thành công.";

                /*
                | Cập nhật dữ liệu hiển thị
                */

                $template["template_name"] =
                    $template_name;

                $template["description"] =
                    $description;

                $template["folder"] =
                    $folder;

                $template["status"] =
                    $status;

            } else {

                $error =
                    "Không thể cập nhật mẫu: " .
                    $stmt->error;

            }

        }

    }

}

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
    Sửa mẫu thiệp - thyu_nhun Weddings
</title>


<!-- FONT -->

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


<!-- ICON -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =====================================================
   RESET
===================================================== */

* {

    box-sizing:
        border-box;

    margin:
        0;

    padding:
        0;

}


:root {

    --dark:
        #3D0B12;

    --wine:
        #8F1D2C;

    --wine-dark:
        #65131F;

    --cream:
        #FFF8F6;

    --white:
        #ffffff;

    --gold:
        #D4A73D;

    --text:
        #32191C;

    --muted:
        #7E6669;

    --border:
        #ECD8D5;

}


/* =====================================================
   BODY
===================================================== */

body {

    font-family:
        "Be Vietnam Pro",
        Arial,
        sans-serif;

    background:
        var(--cream);

    color:
        var(--text);

    min-height:
        100vh;

}


/* =====================================================
   HEADER
===================================================== */

.home-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;

    padding: 8px 13px;

    background: #f8f1ed;
    color: #8b4a4a;

    border: 1px solid #eadbd4;
    border-radius: 7px;

    text-decoration: none;

    font-size: 11px;
    font-weight: 600;

    transition: .2s;
}

.home-btn:hover {
    background: #8b4a4a;
    color: white;
}

.topbar {

    height:
        70px;

    background:
        white;

    border-bottom:
        1px solid
        var(--border);

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        0 32px;

}


/* =====================================================
   LEFT HEADER
===================================================== */

.topbar-left {

    display:
        flex;

    align-items:
        center;

    gap:
        14px;

}


.home-btn {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        8px 13px;

    background:
        #f8f1ed;

    color:
        var(--wine);

    border:
        1px solid
        #eadbd4;

    border-radius:
        7px;

    text-decoration:
        none;

    font-size:
        11px;

    font-weight:
        600;

    transition:
        .2s;

}


.home-btn:hover {

    background:
        var(--wine);

    color:
        white;

}


.page-title {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    font-size:
        13px;

    font-weight:
        600;

}


.page-title i {

    color:
        var(--wine);

}


/* =====================================================
   USER
===================================================== */

.user-area {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

}


.user-info {

    text-align:
        right;

}


.user-info strong {

    display:
        block;

    font-size:
        11px;

}


.user-info span {

    display:
        block;

    color:
        var(--muted);

    font-size:
        9px;

    margin-top:
        2px;

}


.avatar {

    width:
        38px;

    height:
        38px;

    border-radius:
        50%;

    background:
        linear-gradient(
            135deg,
            #ead8cf,
            #b98778
        );

    color:
        white;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


/* =====================================================
   CONTENT
===================================================== */

.content {

    width:
        100%;

    max-width:
        850px;

    margin:
        0 auto;

    padding:
        35px 20px 50px;

}


/* =====================================================
   PAGE HEADING
===================================================== */

.heading {

    text-align:
        center;

    margin-bottom:
        25px;

}


.heading-icon {

    width:
        50px;

    height:
        50px;

    margin:
        0 auto 12px;

    border-radius:
        50%;

    background:
        #f5e4df;

    color:
        var(--wine);

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        19px;

}


.heading h1 {

    font-family:
        "Playfair Display",
        serif;

    font-size:
        28px;

    color:
        var(--text);

    margin-bottom:
        5px;

}


.heading p {

    color:
        var(--muted);

    font-size:
        11px;

}


/* =====================================================
   FORM BOX
===================================================== */

.form-box {

    background:
        white;

    border:
        1px solid
        var(--border);

    border-radius:
        15px;

    padding:
        30px;

    box-shadow:
        0 8px 30px
        rgba(50,30,25,.06);

}


/* =====================================================
   TEMPLATE ID
===================================================== */

.template-id {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    background:
        #faf6f3;

    border:
        1px solid
        #eee3dd;

    border-radius:
        9px;

    padding:
        12px 15px;

    margin-bottom:
        23px;

}


.template-id span {

    color:
        var(--muted);

    font-size:
        10px;

}


.template-id strong {

    color:
        var(--wine);

    font-size:
        12px;

}


/* =====================================================
   ALERT
===================================================== */

.alert {

    padding:
        13px 15px;

    border-radius:
        8px;

    margin-bottom:
        18px;

    font-size:
        10px;

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

}


.error {

    background:
        #fae7e6;

    color:
        #a04b4b;

    border:
        1px solid
        #efd0ce;

}


.success {

    background:
        #e7f4ea;

    color:
        #47865b;

    border:
        1px solid
        #d1e7d6;

}


/* =====================================================
   FORM GROUP
===================================================== */

.form-group {

    margin-bottom:
        19px;

}


.form-group label {

    display:
        block;

    font-size:
        11px;

    font-weight:
        600;

    color:
        #514a48;

    margin-bottom:
        7px;

}


.required {

    color:
        var(--wine);

}


input,
textarea,
select {

    width:
        100%;

    border:
        1px solid
        #e2dad6;

    border-radius:
        8px;

    background:
        white;

    padding:
        11px 13px;

    color:
        var(--text);

    font-family:
        "Be Vietnam Pro",
        Arial,
        sans-serif;

    font-size:
        11px;

    outline:
        none;

    transition:
        .2s;

}


input {

    height:
        42px;

}


textarea {

    min-height:
        105px;

    resize:
        vertical;

    line-height:
        1.6;

}


select {

    height:
        42px;

    cursor:
        pointer;

}


input:focus,
textarea:focus,
select:focus {

    border-color:
        var(--wine);

    box-shadow:
        0 0 0 3px
        rgba(150,80,80,.08);

}


.hint {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        6px;

    margin-top:
        7px;

    color:
        var(--muted);

    font-size:
        9px;

    line-height:
        1.5;

}


.hint i {

    color:
        var(--gold);

    margin-top:
        2px;

}


/* =====================================================
   STATUS
===================================================== */

.status-preview {

    margin-top:
        8px;

}


.status-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        9px;

}


.status-active {

    background:
        #e5f3e8;

    color:
        #3d7d4e;

}


.status-inactive {

    background:
        #eeeeee;

    color:
        #777;

}


/* =====================================================
   BUTTONS
===================================================== */

.form-actions {

    display:
        flex;

    gap:
        10px;

    margin-top:
        25px;

    padding-top:
        20px;

    border-top:
        1px solid
        var(--border);

}


.btn {

    height:
        42px;

    border-radius:
        8px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        7px;

    text-decoration:
        none;

    font-family:
        "Be Vietnam Pro",
        sans-serif;

    font-size:
        11px;

    font-weight:
        600;

    cursor:
        pointer;

    transition:
        .2s;

}


.btn-save {

    flex:
        1;

    border:
        none;

    background:
        var(--wine);

    color:
        white;

}


.btn-save:hover {

    background:
        var(--wine-dark);

}


.btn-cancel {

    padding:
        0 20px;

    border:
        1px solid
        #dfd5d0;

    background:
        white;

    color:
        #756c68;

}


.btn-cancel:hover {

    background:
        #f8f1ed;

    color:
        var(--wine);

}


/* =====================================================
   FOOTER LINK
===================================================== */

.back-link {

    display:
        flex;

    justify-content:
        center;

    margin-top:
        18px;

    color:
        var(--wine);

    text-decoration:
        none;

    font-size:
        10px;

}


.back-link:hover {

    text-decoration:
        underline;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 650px) {

    .topbar {

        padding:
            0 15px;

    }

    .user-info {

        display:
            none;

    }

    .home-btn {

        padding:
            8px 10px;

    }

    .home-btn span {

        display:
            none;

    }

    .content {

        padding:
            25px 15px;

    }

    .form-box {

        padding:
            22px 18px;

    }

    .form-actions {

        flex-direction:
            column;

    }

    .btn-cancel {

        width:
            100%;

    }

}


/* ==========================================================
   ADMIN — VIETNAMESE RED + GOLD FINAL COLOR SYNC
   Shared visual identity with Customer system
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

::selection{
    background:var(--vn-red-soft);
    color:var(--vn-red-dark);
}

/* Admin navigation */
.sidebar{
    background:linear-gradient(180deg,var(--vn-red-deep) 0%,var(--vn-red-dark) 100%) !important;
}
.brand-mark,
.brand-icon,
.brand-symbol{
    background:linear-gradient(145deg,var(--vn-red-accent),var(--vn-red-dark)) !important;
    border:1px solid rgba(242,213,138,.38) !important;
    color:var(--vn-gold-light) !important;
}
.menu-item.active,
.menu-item:hover{
    background:linear-gradient(90deg,rgba(181,42,58,.92),rgba(143,29,44,.74)) !important;
    color:#fff !important;
}
.menu-item.active i,
.menu-item:hover i{
    color:var(--vn-gold-light) !important;
}
.menu-title{
    color:rgba(242,213,138,.62) !important;
}

/* Shared buttons and interactive elements */
.btn-primary,
.primary-btn,
.save-btn,
.submit-btn,
.add-btn,
.action-btn.primary{
    background:linear-gradient(135deg,var(--vn-red),var(--vn-red-dark)) !important;
    border-color:var(--vn-red) !important;
    color:#fff !important;
}
.btn-primary:hover,
.primary-btn:hover,
.save-btn:hover,
.submit-btn:hover,
.add-btn:hover,
.action-btn.primary:hover{
    background:linear-gradient(135deg,var(--vn-red-accent),var(--vn-red)) !important;
}
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible{
    outline:2px solid var(--vn-red) !important;
    outline-offset:2px;
}

/* Cards, forms, tables */
.card,
.panel,
.stat-card,
.table-card,
.content-card,
.form-card,
.settings-card{
    border-color:var(--vn-border) !important;
}
input:focus,
select:focus,
textarea:focus{
    border-color:var(--vn-red) !important;
    box-shadow:0 0 0 3px rgba(143,29,44,.10) !important;
}
.badge,
.status-badge,
.chip{
    border-color:rgba(143,29,44,.18);
}
a:hover{
    color:var(--vn-red);
}

/* Gold accent used sparingly for premium admin identity */
.brand-sub,
.brand-area span,
.logo-subtitle{
    color:var(--vn-gold-light) !important;
}


/* SUBPAGE LAYOUT: only return-home navigation, no full sidebar */
.sidebar{display:none!important;}
.main{margin-left:0!important;width:100%!important;}
@media(max-width:900px){.main{width:100%!important;margin-left:0!important;}.topbar{padding-left:18px!important;padding-right:18px!important;}}
</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="topbar">

    <a href="dashboard.php" class="home-btn">
        <i class="fa-solid fa-house"></i>
        Trang chủ
    </a>

    <div class="topbar-left">


        <!-- TRANG CHỦ -->

        <a
            href="dashboard.php"
            class="home-btn"
        >

            <i class="fa-solid fa-house"></i>

            <span>
                Trang chủ
            </span>

        </a>


        <!-- TIÊU ĐỀ -->

        <div class="page-title">

            <i class="fa-solid fa-palette"></i>

            <span>
                Quản lý mẫu thiệp
            </span>

        </div>


    </div>



    <div class="user-area">


        <div class="user-info">

            <strong>
                Xin chào,
                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "Admin"
                ) ?>
            </strong>

            <span>
                Quản trị viên
            </span>

        </div>


        <div class="avatar">

            <i class="fa-solid fa-user"></i>

        </div>


    </div>


</header>



<!-- =====================================================
     CONTENT
===================================================== -->

<main class="content">


    <div class="heading">


        <div class="heading-icon">

            <i class="fa-solid fa-pen"></i>

        </div>


        <h1>
            Sửa mẫu thiệp
        </h1>


        <p>
            Cập nhật thông tin và trạng thái mẫu thiệp
        </p>


    </div>



    <div class="form-box">


        <!-- ID -->

        <div class="template-id">

            <span>
                Mã mẫu thiệp
            </span>

            <strong>
                #<?= intval($template["id"]) ?>
            </strong>

        </div>



        <!-- ALERT -->

        <?php if ($error !== ""): ?>

            <div class="alert error">

                <i
                    class="fa-solid fa-circle-exclamation"
                ></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>



        <?php if ($success !== ""): ?>

            <div class="alert success">

                <i
                    class="fa-solid fa-circle-check"
                ></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>



        <!-- FORM -->

        <form
            method="POST"
            autocomplete="off"
        >


            <!-- TÊN -->

            <div class="form-group">

                <label>

                    Tên mẫu

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="template_name"
                    value="<?= htmlspecialchars(
                        $template["template_name"]
                    ) ?>"
                    placeholder="Nhập tên mẫu thiệp"
                    required
                >

            </div>



            <!-- MÔ TẢ -->

            <div class="form-group">

                <label>
                    Mô tả
                </label>


                <textarea
                    name="description"
                    placeholder="Nhập mô tả cho mẫu thiệp..."
                ><?= htmlspecialchars(
                    $template["description"] ?? ""
                ) ?></textarea>


            </div>



            <!-- FOLDER -->

            <div class="form-group">

                <label>

                    Tên thư mục

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="folder"
                    value="<?= htmlspecialchars(
                        $template["folder"]
                    ) ?>"
                    placeholder="templates/mau01"
                    required
                >


                <div class="hint">

                    <i
                        class="fa-solid fa-circle-info"
                    ></i>

                    <span>
                        Chỉ sử dụng chữ cái, số,
                        dấu gạch ngang (-),
                        gạch dưới (_) và dấu /.
                        Ví dụ: templates/mau01
                    </span>

                </div>


            </div>



            <!-- STATUS -->

            <div class="form-group">

                <label>
                    Trạng thái
                </label>


                <select
                    name="status"
                    id="status"
                >

                    <option
                        value="active"
                        <?= $template["status"] === "active"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Đang hoạt động
                    </option>


                    <option
                        value="inactive"
                        <?= $template["status"] === "inactive"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Đang tắt
                    </option>

                </select>


                <div
                    class="status-preview"
                    id="statusPreview"
                >

                    <?php if (
                        $template["status"] === "active"
                    ): ?>

                        <span
                            class="status-badge status-active"
                        >

                            <i
                                class="fa-solid fa-circle-check"
                            ></i>

                            Mẫu đang được hiển thị
                            cho khách hàng

                        </span>

                    <?php else: ?>

                        <span
                            class="status-badge status-inactive"
                        >

                            <i
                                class="fa-solid fa-circle-pause"
                            ></i>

                            Mẫu đang bị tắt

                        </span>

                    <?php endif; ?>

                </div>


            </div>



            <!-- BUTTON -->

            <div class="form-actions">


                <a
                    href="templates.php"
                    class="btn btn-cancel"
                >

                    <i
                        class="fa-solid fa-arrow-left"
                    ></i>

                    Hủy

                </a>


                <button
                    type="submit"
                    class="btn btn-save"
                >

                    <i
                        class="fa-solid fa-floppy-disk"
                    ></i>

                    Lưu thay đổi

                </button>


            </div>


        </form>


    </div>



    <!-- BACK -->

    <a
        href="templates.php"
        class="back-link"
    >

        <i
            class="fa-solid fa-arrow-left"
        ></i>

        &nbsp;
        Quay lại quản lý mẫu thiệp

    </a>


</main>



<script>

/*
|--------------------------------------------------------------------------
| CẬP NHẬT PREVIEW TRẠNG THÁI
|--------------------------------------------------------------------------
*/

const statusSelect =
    document.getElementById("status");

const statusPreview =
    document.getElementById("statusPreview");


statusSelect.addEventListener(
    "change",
    function () {

        if (this.value === "active") {

            statusPreview.innerHTML = `

                <span
                    class="status-badge status-active"
                >

                    <i
                        class="fa-solid fa-circle-check"
                    ></i>

                    Mẫu đang được hiển thị
                    cho khách hàng

                </span>

            `;

        } else {

            statusPreview.innerHTML = `

                <span
                    class="status-badge status-inactive"
                >

                    <i
                        class="fa-solid fa-circle-pause"
                    ></i>

                    Mẫu đang bị tắt

                </span>

            `;

        }

    }
);

</script>


</body>

</html>