var banlist_init = function(token, my_boards, inMod) {
    inMod = !inMod;

    var time = function() { return Date.now()/1000|0; };
    var expired = function(ban) { return ban.expires && ban.expires != 0 && ban.expires < time(); };

    var columns = [
        {name: "<input type='checkbox' id='select-all' title='" + _("Select all") + "'>", cls: "minimal", show: inMod, fmt: function(f) {
            return f.access ? "<input type='checkbox' class='unban' name='ban_" + f.id + "' value='unban'>" : "";
        } },
        {name: _("IP address"), cls: "minimal", fmt: function(f) {
            var $cell = $("<span>");
            if (inMod && f.single_addr && !f.masked) {
                $cell.append($("<a>").attr("href", "?/IP/" + f.mask).text(f.mask));
            } else {
                $cell.text(f.mask);
            }
            if (inMod && !f.masked && f.country && /^[a-z]{2}$/.test(f.country.code)) {
                $cell.append(" ", $("<img>", {
                    "class": "flag mod-ip-flag",
                    src: ($("#banlist").attr("data-flag-uri") || configRoot + "static/flags/%s.png").replace("%s", f.country.code),
                    alt: f.country.name,
                    title: f.country.name,
                    width: 16,
                    height: 11
                }).css("vertical-align", "middle"));
            }
            return $cell.html();
        } },
        {name: _("Reason"), fmt: function(f) {
            var add = "", suf = "";
            if (f.seen == 1) add += " <i class='fa fa-check' title='" + _("Seen") + "'></i>";
            if (f.message) {
                add += " <i class='fa fa-comment' title='" + _("Message for which user was banned is included") + "'></i>";
                suf = "<div class='ban-message'>" + f.message + "</div>";
            }
            return (f.reason || "-") + add + suf;
        } },
        {name: _("Board"), cls: "minimal", fmt: function(f) {
            if (f.board) return "/" + f.board + "/";
            else return "<em>" + _("all") + "</em>";
        } },
        {name: _("Set"), cls: "minimal", fmt: function(f) {
            return "<span title='" + strftime(window.post_date, new Date((f.created|0)*1000), datelocale) + "'>" + ago(f.created) + _(" ago") + "</span>";
        } },
        {name: _("Expires"), cls: "minimal", fmt: function(f) {
            if (!f.expires || f.expires == 0) return "<em>" + _("never") + "</em>";
            return strftime(window.post_date, new Date((f.expires|0)*1000), datelocale) +
                ((f.expires < time()) ? "" : " <small>" + _("in ") + until(f.expires|0) + "</small>");
        } },
        {name: _("Staff"), cls: "minimal", fmt: function(f) {
            if (!f.username) return "<em>" + _("system") + "</em>";
            if (inMod && f.username != '?' && !f.vstaff) {
                return "<a href='?/new_PM/" + f.username + "'>" + f.username + "</a>";
            }
            return f.username;
        } },
        {name: "", cls: "minimal", show: inMod, fmt: function(f) {
            var actions = "<a href='?/edit_ban/" + f.id + "'>[" + _("Edit") + "]</a>";
            if (f.access) actions += " <a href='?/bans/" + f.id + "/unban'>[" + _("Unban") + "]</a>";
            return actions;
        } }
    ].filter(function(column) { return column.show !== false; });

    $.getJSON(inMod ? ("?/bans.json/"+token) : token, function(bans) {
        var $table = $("#banlist");
        var $head = $("<tr>");
        columns.forEach(function(column) {
            $("<th>").addClass(column.cls || "").html(column.name).appendTo($head);
        });
        $("<thead>").append($head).appendTo($table);

        var $body = $("<tbody>").appendTo($table);
        bans.forEach(function(ban) {
            var $row = $("<tr>").data("ban", ban).toggleClass("expired", !!expired(ban));
            columns.forEach(function(column) {
                $("<td>").addClass(column.cls || "").html(column.fmt(ban)).appendTo($row);
            });
            $body.append($row);
        });

        var matches = function(e) {
            if ($("#only_mine").prop("checked") && ($.inArray(e.board, my_boards) === -1)) return false;
            if ($("#only_not_expired").prop("checked") && expired(e)) return false;

            var terms = $("#search").val().split(" ").filter(Boolean);
            return terms.every(function(t) {
                var fields = ["mask", "reason", "board", "username", "message"];
                var ma = t.match(/^(mask|reason|board|staff|message):/);
                if (ma) {
                    fields = [ma[1] == "staff" ? "username" : ma[1]];
                    t = t.slice(ma[0].length);
                }
                return fields.some(function(f) {
                    return e[f] && String(e[f]).indexOf(t) !== -1;
                });
            });
        };

        var filter = function() {
            var shown = 0;
            $body.children().each(function() {
                var visible = matches($(this).data("ban"));
                $(this).toggle(visible);
                if (visible) shown++;
                else $(this).find("input.unban").prop("checked", false);
            });
            $("#banlist-count").text(shown + " / " + bans.length);
        };

        $("#only_mine, #only_not_expired").on("change", filter);
        $("#search").on("input", filter);
        filter();

        $("#select-all").on("change", function() {
            $body.find("tr:visible input.unban").prop("checked", $(this).prop("checked"));
        });
    });
};
