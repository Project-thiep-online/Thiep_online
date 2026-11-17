document.addEventListener("DOMContentLoaded", function () {

    const openCard = document.getElementById("openCard");
    const welcome = document.getElementById("welcome");
    const card = document.getElementById("card");

    if (!openCard || !welcome || !card) {
        console.error(
            "Không tìm thấy openCard, welcome hoặc card."
        );
        return;
    }


    // Khóa cuộn khi đang ở màn hình mở thiệp
    document.body.style.overflow = "hidden";


    openCard.addEventListener("click", function () {

        // Không cho bấm nhiều lần
        if (openCard.disabled) return;

        openCard.disabled = true;


        // Hiệu ứng hai cánh cửa mở
        welcome.classList.add("open");


        // Chờ cửa mở hoàn toàn
        setTimeout(function () {

            // Hiện nội dung thiệp
            card.style.display = "block";


            // Ẩn màn hình mở thiệp
            welcome.classList.add("hide");


            // Cho phép cuộn
            document.body.style.overflowX = "hidden";
            document.body.style.overflowY = "auto";


            // Đưa về đầu trang
            window.scrollTo({
                top: 0,
                left: 0,
                behavior: "instant"
            });

        }, 1800);

    });

});