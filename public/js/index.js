$(function () {
    marked.setOptions({
        renderer: new marked.Renderer(),
        gfm: true,
        tables: true,
        breaks: false,
        pedantic: false,
        sanitize: false,
        smartLists: true,
        smartypants: false
    });
    marked.setOptions({
        highlight: function (code, lang) {
            return hljs.highlightAuto(code, [lang]).value;
        }
    });

    $("#markdown").append(marked.parse($("#hide").text()));

    hljs.highlightAll();
    
    $("table").addClass("table table-bordered table-hover");

    var content = $("#content");
    var item = null;
    var left = null;
    $("#markdown").children().each(function () {
        if (item === null && this.tagName !== "H3") {
            content.append(this);
            return true;
        }
        if (this.tagName === "H3") {
            item = $('<div class="item"></div>');
            left = $('<div class="left"></div>');
            left.append(this);
            return true;
        } else if (this.tagName === "PRE" && $(this).children().first().hasClass("language-json")) {
            item.append(left);
            $(this).addClass("right");
            item.append(this);
            content.append(item);
        } else {
            left.append(this);
        }
    });
    $("#markdown").remove();

    var anchorContent = $('<div class="anchor-content" id="anchor-content"></div>');
    var a, li, i = 0;
    $("h2, h3").each(function () {
        i++;
        a = $('<a class="nav_item anchor-link" href="#' + $(this).attr("id") + '"></a>');
        a.addClass("item_" + this.tagName);
        a.text($(this).text());
        li = $("<li></li>");
        li.append(a);
        anchorContent.append(li);
    });

    var blogAnchor = $('<div class="anchor"></div>');
    blogAnchor.append(anchorContent);
    $("#catalog").prepend(blogAnchor);

});