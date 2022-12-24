let Nsfw = {};
window.Nsfw = Nsfw;

Nsfw.click = () => {
    if (Nsfw.enabled) {
        localStorage.removeItem('styling.nsfw');
        Nsfw.turn_off();
    } else {
        localStorage.setItem('styling.nsfw', 'true');
        Nsfw.turn_on();
    }
};

Nsfw.handle = () => {
    if (Nsfw.enabled) {
        Nsfw.turn_on();

    } else {
        Nsfw.turn_off();
    }
};

Nsfw.enabled = localStorage.getItem('styling.nsfw') === 'true';

Nsfw.turn_on = function () {
    Nsfw.enabled = true;
    $('head').append('<style type="text/css" id="nsfw-style">' +
        '.post-image, .thread-image{opacity:0.05}' +
        '.post-image:hover, .thread-image:hover{opacity:1}' +
        '</style>');
};

Nsfw.turn_off = function () {
    Nsfw.enabled = false;
    $('#nsfw-style').remove();
};

$(document).ready(function () {
    Nsfw.handle();
    let nsfw_button = $("<a href='javascript:void(0)' title='" + _("NSFW Mode") + "'>&nbsp;[" + _("NSFW") + "]</a> &nbsp;").css("float", "right").css('margin', '0 5px');

    if ($(".boardlist.compact-boardlist").length) {
        nsfw_button.addClass("cb-item cb-fa").html("<i class='fa fa-gear'></i>");
    }
    if ($(".boardlist:first").length) {
        nsfw_button.appendTo($(".boardlist:first"));
    } else {
        nsfw_button.prependTo($(document.body));
    }

    nsfw_button.on("click", Nsfw.click);


});
