$(document).ready(function () {
    $(".report tr.brief").click(function () {
        $(this).next("tr").toggleClass("h");
        $(this).find(".arrow").toggleClass("up");
    });
    $('body').keydown(function (e) {
        if (e.ctrlKey && e.keyCode == 37) {	// Ctrl+Left
            el = $('.paginator:first .prev');
            if (el.length)
                location.href = el.attr('href');
        }
        if (e.ctrlKey && e.keyCode == 39) {	// Ctrl+Right
            el = $('.paginator:first .next');
            if (el.length)
                location.href = el.attr('href');
        }
    });
});
