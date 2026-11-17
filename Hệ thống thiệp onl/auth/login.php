<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {

                header("Location: ../admin/dashboard.php");
                exit;

            }

            if ($user["role"] === "customer") {

                header("Location: ../customer/dashboard.php");
                exit;

            }

        } else {

            $error = "Mật khẩu không đúng!";

        }

    } else {

        $error = "Tên đăng nhập không tồn tại!";

    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Đăng nhập - thyu_nhun Weddings</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* ===== VIETNAMESE RED WEDDING PALETTE ===== */
            --wine: #830b1b;          /* Đỏ cưới chủ đạo */
            --wine-dark: #65131F;     /* Đỏ đậm */
            --wine-deep: #3a090f;     /* Đỏ rất đậm */
            --wine-soft: #D95B64;     /* Đỏ nhấn */
            --wine-light: #F3C7C5;    /* Đỏ pastel */
            --cream: #FFF8F6;         /* Kem trắng hơi hồng */
            --cream-deep: #F8EAE7;
            --ivory: #FFFCFB;
            --text: #32191C;
            --muted: #7E6669;
            --line: #ECD8D5;
            --gold: #D4A73D;          /* Vàng gold */
            --gold-light: #F2D58A;
            --danger-bg: #FFF0F1;
            --danger: #9f1e3a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "DM Sans", Arial, sans-serif;
            color: var(--text);
            background: var(--cream);
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(360px, 43%) 1fr;
            overflow: hidden;
        }

        /* ===== BRANDING ===== */
        .branding {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px clamp(36px, 7vw, 100px);
            color: #fffaf7;
            background:
                radial-gradient(circle at 8% 18%, rgba(255,255,255,.08), transparent 30%),
                linear-gradient(145deg, #26070D 0%, #53131C 52%, #8F1D2C 100%);
            overflow: hidden;
        }

        .branding::before {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            border: 1px solid rgba(242, 213, 138, .24);
            border-radius: 50%;
            top: -170px;
            right: -170px;
        }

        .branding::after {
            content: "♡";
            position: absolute;
            right: -34px;
            bottom: -115px;
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: 430px;
            line-height: 1;
            font-weight: 500;
            color: rgba(255,255,255,.035);
            pointer-events: none;
        }

        .brand-content {
            position: relative;
            z-index: 1;
            max-width: 520px;
        }

        .brand-mark {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            margin-bottom: 26px;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 19px;
            background: rgba(255,255,255,.09);
            box-shadow: 0 18px 40px rgba(0,0,0,.16);
        }

        .brand-mark svg {
            width: 29px;
            height: 29px;
            stroke: var(--gold-light);
        }

        .brand-name {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: clamp(42px, 4.2vw, 64px);
            line-height: .88;
            letter-spacing: .01em;
            font-weight: 600;
        }

        .brand-name span {
            display: block;
            margin-top: 10px;
            font-family: "DM Sans", Arial, sans-serif;
            font-size: 11px;
            line-height: 1;
            letter-spacing: .34em;
            text-transform: uppercase;
            color: rgba(255,250,247,.56);
            font-weight: 600;
        }

        .brand-line {
            width: 64px;
            height: 1px;
            margin: 42px 0 27px;
            background: rgba(242, 213, 138, .72);
        }

        .brand-quote {
            max-width: 440px;
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: clamp(29px, 2.8vw, 43px);
            line-height: 1.12;
            font-weight: 500;
            color: #FFF7F3;
        }

        .brand-subtext {
            max-width: 420px;
            margin-top: 20px;
            font-size: 14px;
            line-height: 1.8;
            color: rgba(255,250,247,.60);
        }

        .brand-footer {
            position: absolute;
            left: clamp(36px, 7vw, 100px);
            bottom: 34px;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,250,247,.38);
            font-size: 11px;
            letter-spacing: .08em;
        }

        .brand-footer::before {
            content: "";
            width: 34px;
            height: 1px;
            background: currentColor;
        }

        /* ===== FORM AREA ===== */
        .login-area {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 42px 28px;
            background:
                radial-gradient(circle at 100% 0%, rgba(143,29,44,.10), transparent 27%),
                radial-gradient(circle at 0% 100%, rgba(217,91,100,.05), transparent 24%),
                var(--cream);
            overflow: hidden;
        }

        .login-area::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            border: 1px solid rgba(143,29,44,.15);
            border-radius: 50%;
            top: -120px;
            right: -90px;
            pointer-events: none;
        }

        .login-card {
            position: relative;
            z-index: 1;
            width: min(100%, 500px);
            padding: 48px 48px 30px;
            border: 1px solid rgba(236,216,213,.96);
            border-radius: 28px;
            background: rgba(255,252,251,.90);
            box-shadow: 0 25px 70px rgba(91, 18, 31, .10);
            backdrop-filter: blur(10px);
        }

        .login-eyebrow {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 13px;
            font-size: 10px;
            letter-spacing: .22em;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--wine);
        }

        .login-eyebrow::before {
            content: "";
            width: 28px;
            height: 1px;
            background: var(--wine-soft);
        }

        .login-card h1 {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: 47px;
            line-height: 1;
            font-weight: 600;
            color: var(--text);
        }

        .login-intro {
            margin: 13px 0 31px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--muted);
        }

        .error-message {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 13px 15px;
            border: 1px solid #F0C9CD;
            border-radius: 12px;
            background: var(--danger-bg);
            color: var(--danger);
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            width: 18px;
            height: 18px;
            transform: translateY(-50%);
            color: #92767A;
            pointer-events: none;
        }

        .input-wrap input {
            width: 100%;
            height: 52px;
            padding: 0 48px 0 46px;
            border: 1px solid var(--line);
            border-radius: 13px;
            outline: none;
            background: #fff;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            transition: .2s ease;
        }

        .input-wrap input::placeholder {
            color: #B7A0A2;
        }

        .input-wrap input:focus {
            border-color: var(--wine);
            box-shadow: 0 0 0 4px rgba(143,29,44,.10);
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            transform: translateY(-50%);
            border: none;
            border-radius: 9px;
            background: transparent;
            color: #94777A;
            cursor: pointer;
            transition: .2s ease;
        }

        .toggle-password:hover {
            background: #FFF0F1;
            color: var(--wine);
        }

        .toggle-password svg {
            width: 18px;
            height: 18px;
        }

        .btn-login {
            width: 100%;
            height: 53px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 7px;
            border: none;
            border-radius: 13px;
            background: linear-gradient(135deg, #B52A3A 0%, #8F1D2C 48%, #65131F 100%);
            color: #fff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(143,29,44,.24);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(143,29,44,.32);
        }

        .btn-login svg {
            width: 18px;
            height: 18px;
        }

        .account-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-top: 19px;
            padding: 0 3px;
            font-size: 12px;
            line-height: 1.6;
            color: #8B7073;
        }

        .account-note svg {
            flex: 0 0 auto;
            width: 16px;
            height: 16px;
            margin-top: 1px;
            color: var(--wine-soft);
        }

        /* ===== SUPPORT ===== */
        .support-box {
            margin-top: 29px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
        }

        .support-title {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: 24px;
            font-weight: 600;
            color: var(--text);
        }

        .support-desc {
            margin-top: 5px;
            font-size: 12px;
            line-height: 1.6;
            color: var(--muted);
        }

        .support-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px;
            margin-top: 16px;
        }

        .support-item {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fffaf7;
            color: inherit;
            text-decoration: none;
            transition: .2s ease;
        }

        .support-item:hover {
            transform: translateY(-2px);
            border-color: #D9A9AE;
            box-shadow: 0 10px 22px rgba(143,29,44,.08);
        }

        .support-icon {
            flex: 0 0 auto;
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: #FCE8E7;
            color: var(--wine);
        }

        .support-icon svg {
            width: 18px;
            height: 18px;
        }

        .support-text {
            min-width: 0;
        }

        .support-label {
            display: block;
            margin-bottom: 2px;
            font-size: 10px;
            color: var(--muted);
        }

        .support-value {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
        }

        .copyright {
            margin-top: 22px;
            text-align: center;
            font-size: 10px;
            color: #A98F92;
        }

        @media (max-width: 900px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .branding {
                min-height: 270px;
                padding: 42px 30px 55px;
            }

            .brand-content {
                max-width: 650px;
            }

            .brand-mark {
                width: 50px;
                height: 50px;
                margin-bottom: 18px;
            }

            .brand-name {
                font-size: 46px;
            }

            .brand-line,
            .brand-quote,
            .brand-subtext {
                display: none;
            }

            .brand-footer {
                left: 30px;
                bottom: 22px;
            }

            .login-area {
                min-height: auto;
                padding: 36px 22px 50px;
            }
        }

        @media (max-width: 560px) {
            .login-card {
                padding: 34px 22px 25px;
                border-radius: 22px;
            }

            .login-card h1 {
                font-size: 40px;
            }

            .support-links {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="login-page">

    <aside class="branding">
        <div class="brand-content">

            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"/>
                </svg>
            </div>

            <div class="brand-name">
                thyu_nhun
                <span>Weddings</span>
            </div>

            <div class="brand-line"></div>

            <h2 class="brand-quote">Every love story deserves a beautiful beginning.</h2>

            <p class="brand-subtext">
                Mỗi câu chuyện tình yêu
đều xứng đáng có một khởi đầu đẹp.
            </p>

        </div>

        <div class="brand-footer">Wedding Invitation System</div>
    </aside>


    <main class="login-area">

        <section class="login-card">

            <div class="login-eyebrow">Customer & Admin Portal</div>

            <h1>Chào mừng trở lại</h1>

            <p class="login-intro">
                Đăng nhập để tiếp tục quản lý câu chuyện cưới của bạn.
            </p>

            <?php if ($error !== ""): ?>
                <div class="error-message" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 8v5"></path>
                        <path d="M12 16h.01"></path>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">

                <div class="form-group">
                    <label for="username">Tên đăng nhập</label>

                    <div class="input-wrap">

                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="12" cy="8" r="3.5"></circle>
                            <path d="M5 20c.8-3.4 3.2-5.3 7-5.3s6.2 1.9 7 5.3"></path>
                        </svg>

                        <input
                            id="username"
                            type="text"
                            name="username"
                            placeholder="Nhập tên đăng nhập"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            autocomplete="username"
                            required
                        >

                    </div>
                </div>


                <div class="form-group">
                    <label for="password">Mật khẩu</label>

                    <div class="input-wrap">

                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                        </svg>

                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Nhập mật khẩu"
                            autocomplete="current-password"
                            required
                        >

                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Hiện mật khẩu">
                            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="2.5"></circle>
                            </svg>
                        </button>

                    </div>
                </div>


                <button type="submit" class="btn-login">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 21s-7-4.6-9.4-8.5C.7 9.4 2.1 5.6 5.5 4.8c2-.5 4.1.3 5.3 2 1.2-1.7 3.3-2.5 5.3-2 3.4.8 4.8 4.6 2.9 7.7C19 16.4 12 21 12 21Z"></path>
                    </svg>
                    ĐĂNG NHẬP
                </button>

            </form>


            <div class="account-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                </svg>

                <span>
                    Thông tin đăng nhập được cung cấp riêng bởi quản trị viên hệ thống.
                </span>
            </div>


            <div class="support-box">

                <h3 class="support-title">Cần hỗ trợ?</h3>

                <p class="support-desc">
                    Nếu gặp vấn đề khi đăng nhập hoặc cần hỗ trợ về thiệp cưới,
                    hãy liên hệ với chúng tôi.
                </p>

                <div class="support-links">

                    <!-- Thay số điện thoại tại href và phần support-value nếu cần -->
                    <a class="support-item" href="tel:0968117006">

                        <span class="support-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .3 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.9.6 2.9.7A2 2 0 0 1 22 16.9Z"></path>
                            </svg>
                        </span>

                        <span class="support-text">
                            <span class="support-label">Điện thoại</span>
                            <span class="support-value">0968 117 006</span>
                        </span>

                    </a>


                    <!-- Thay link Facebook tại href nếu cần -->
                    <a
                        class="support-item"
                        href="https://www.facebook.com/nhung.nnthuy/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >

                        <span class="support-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M14 8h3V4h-3c-3 0-5 2-5 5v3H6v4h3v6h4v-6h3l1-4h-4V9c0-.6.4-1 1-1Z"></path>
                            </svg>
                        </span>

                        <span class="support-text">
                            <span class="support-label">Facebook</span>
                            <span class="support-value">Liên hệ qua Facebook</span>
                        </span>

                    </a>

                </div>

            </div>


            <p class="copyright">
                © <?php echo date("Y"); ?> thyu_nhun Weddings
            </p>

        </section>

    </main>

</div>


<script>
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");

    togglePassword.addEventListener("click", function () {
        const isPassword = passwordInput.type === "password";

        passwordInput.type = isPassword ? "text" : "password";
        togglePassword.setAttribute(
            "aria-label",
            isPassword ? "Ẩn mật khẩu" : "Hiện mật khẩu"
        );
    });
</script>

</body>
</html>
