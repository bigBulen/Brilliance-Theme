;(function ($, window) {
    var headIndex = (function () {
        function headIndex(element, options) {
            this.settings = $.extend(true, $.fn.headIndex.default, options || {});
            this.element = element;
            this.init();
        }

        headIndex.prototype = {
            init: function () {
                this.articleWrap = $(this.settings.articleWrapSelector);
                this.headerList = this.articleWrap.find(':header');
                this.indexBox = $(this.settings.indexBoxSelector);
                this.indexScrollBox = $(this.settings.indexScrollBoxSelector);
                this.scrollBody = $(this.settings.scrollSelector);
                this.scrollWrap = $(this.settings.scrollWrap);
                this.manual = false;
                this.mouseHovered = false;

                if (this.indexBox.length === 0 || this.headerList.length === 0) {
                    return null;
                }

                this.initHeader();
                this.event();
            },

            initHeader: function () {
                for (var i = 0; i < this.headerList.length; i++, this.autoId++) {
                    this.headerList[i].id = this.headerList[i].id || "header-id-" + this.autoId;
                    this.headerList[i].topHeight = this.offsetTop(this.headerList[i]);
                    this.headerList[i].h = Number(this.headerList[i].tagName.charAt(1));
                }

                this.tempHtml = [];
                this.buildHtml(this.buildTree());

                var res = '<ul>' + this.tempHtml.join('') + '</ul>';
                this.indexBox.html(res);
            },

            event: function () {
                var that = this;
                var manualValTimer = null;
                this.indexBox.on('click.headindex', function (event) {
                    var target = $(event.target);
                    if (target.hasClass(that.settings.linkClass)) {
                        event.preventDefault();
                        var indexItem = target.parent('.' + that.settings.itemClass);

                        that.manual = true;
                        if (manualValTimer) {
                            clearTimeout(manualValTimer);
                            manualValTimer = null;
                        }
                        manualValTimer = setTimeout(function () {
                            that.manual = false;
                        }, 400);
                        that.current(indexItem);

                        that.scrollTo(event.target.getAttribute('href'))
                    }
                });

                this.indexScrollBox.on('mouseover', function (event) {
                    that.mouseHovered = true;
                });
                this.indexScrollBox.on('mouseleave', function (event) {
                    that.mouseHovered = false;
                });

                $(this.scrollWrap).scroll(function () {
                    if (that.manual) return;
                    that.updateCurrent();
                });

                that.updateCurrent();
            },

            updateTopHeight: function () {
                var length = this.headerList.length;
                var i;
                if (length === 0) return;

                if (this.headerList[0].topHeight === this.offsetTop(this.headerList[0])
                    && this.headerList[length - 1].topHeight === this.offsetTop(this.headerList[length - 1])) {
                    return;
                }

                if ((this.headerList[0].topHeight - this.offsetTop(this.headerList[0]))
                    === (this.headerList[length - 1].topHeight - this.offsetTop(this.headerList[length - 1]))) {

                    var hx = this.offsetTop(this.headerList[0]) - this.headerList[0].topHeight;
                    for (i = 0; i < this.headerList.length; i++, this.autoId++) {
                        this.headerList[i].topHeight += hx;
                    }
                    return;
                }

                for (i = 0; i < this.headerList.length; i++, this.autoId++) {
                    this.headerList[i].topHeight = this.offsetTop(this.headerList[i]);
                }
            },

            current: function (indexItem) {
                var subBox,
                    currentClass = 'current';

                if (indexItem.length === 0 || indexItem.hasClass(currentClass)) {
                    return;
                }
                var otherCurrent = this.indexBox.find('li.' + currentClass);
                if (otherCurrent.length > 0) {
                    otherCurrent.removeClass(currentClass);
                }
                this.indexBox.find('ul.open').removeClass('open');

                subBox = indexItem.children('.' + this.settings.subItemBoxClass);
                if (subBox.length > 0) {
                    subBox.addClass('open').slideDown();
                }
                var parentsBox = indexItem.parents('ul.' + this.settings.subItemBoxClass);
                if (parentsBox.length > 0) {
                    parentsBox.addClass('open').slideDown()
                }
                subBox = this.indexBox.find('ul.' + this.settings.subItemBoxClass).not('.open');
                if (subBox.length > 0) {
                    subBox.slideUp()
                }
                indexItem.addClass(currentClass);
                if (this.mouseHovered){
                    return;
                }
                var relativeOffsetTopToWrapper = indexItem.position().top;
                var indexScrollBoxScrollTop = this.indexScrollBox.scrollTop();
                var indexScrollBoxHeight = this.indexScrollBox.height();
                if (relativeOffsetTopToWrapper < 20 + indexItem.height()){
                    var target = indexScrollBoxScrollTop + relativeOffsetTopToWrapper - 20 - indexItem.height();
                    if (target < 30){
                        target = 0;
                    }
                    this.indexScrollBox.stop().animate({
                        scrollTop: target
                    }, 'normal');
                }
                if (relativeOffsetTopToWrapper > indexScrollBoxHeight - 10){
                    this.indexScrollBox.stop().animate({
                        scrollTop: indexScrollBoxScrollTop + relativeOffsetTopToWrapper - indexScrollBoxHeight + 10 + indexItem.height()
                    }, 'normal');
                }
            },

            buildHtml: function (tree) {
                if (tree === undefined || tree.length === 0) return;

                for (var i = 0; i < tree.length; i++) {
                    this.tempHtml.push("<li class='" + this.settings.itemClass + "'>"
                        + "<a no-pjax class='" + this.settings.linkClass + "' href='#" + tree[i].item.id + "'>"
                        + tree[i].item.innerText + "</a>");

                    if (tree[i].children.length !== 0) {
                        this.tempHtml.push("<ul class='" + this.settings.subItemBoxClass + "'>");
                        this.buildHtml(tree[i].children);
                        this.tempHtml.push("</ul>");
                    }
                    this.tempHtml.push("</li>")
                }
            },

            buildTree: function () {
                var current = null,
                    tempCur,
                    indexTree = [];

                for (var i = 0; i < this.headerList.length; i++) {
                    if (current == null) {
                        current = {item: this.headerList[i], parent: null, children: [],};
                        indexTree.push(current);
                        continue;
                    }
                    if (current.item.h < this.headerList[i].h) {
                        tempCur = {item: this.headerList[i], parent: current, children: [],};
                        current.children.push(tempCur);
                        current = tempCur;
                        continue;
                    }
                    if (current.item.h === this.headerList[i].h) {
                        tempCur = {item: this.headerList[i], parent: current.parent, children: [],};
                        ((current.parent && current.parent.children) || indexTree).push(tempCur);
                        current = tempCur;
                        continue;
                    }
                    while (current != null && current.item.h > this.headerList[i].h) {
                        current = current.parent;
                    }
                    if (current == null) {
                        current = {item: this.headerList[i], parent: null, children: [],};
                        indexTree.push(current);
                        continue;
                    }
                    i--;
                }

                return indexTree;
            },
            search: function (start, end, findValue) {
                if (this.headerList.length === 0) return null;

                if (end - start <= 1) {
                    if (this.headerList[end].topHeight < findValue) {
                        return this.headerList[end];
                    }
                    return this.headerList[start];
                }

                if (start < end) {
                    var middleIndex = parseInt((start + end) / 2);
                    var middleValue = this.headerList[middleIndex].topHeight;
                    if (findValue < middleValue) {
                        end = middleIndex;
                    } else if (findValue > middleValue) {
                        start = middleIndex
                    } else {
                        return this.headerList[middleIndex];
                    }
                    return this.search(start, end, findValue)
                }
            },

            offsetTop: function (elem) {
                return $(elem).offset().top - this.settings.offset;
            },
            scrollTo: function (eid) {
                this.scrollBody.stop().animate({
                    scrollTop: this.offsetTop(document.getElementById(eid.substr(1))) + 8
                }, 'normal');
            },
            updateCurrent: function () {
                var scrollTop = this.scrollBody.scrollTop();
                this.updateTopHeight();

                var find = this.search(0, this.headerList.length - 1, scrollTop);
                if (!find) {
                    return;
                }

                var indexItem = this.indexBox
                    .find('a[href="#' + find.id + '"]')
                    .parent('li.' + this.settings.itemClass);

                this.current(indexItem);
            },
            clean: function () {
                if (this.element) {
                    this.element.data("headIndex", null)
                }
            },
            ignoreScrollEvent: function (ignore) {
                if (ignore) {
                    this.manual = true;
                } else {
                    this.manual = false;
                    this.updateCurrent();
                }
            }
        };

        headIndex.prototype.autoId = 1;

        return headIndex;
    })();

    $.fn.headIndex = function (options) {
        return this.each(function () {
            var $this = $(this),
                instance = $this.data("headIndex");
            instance = new headIndex($this, options);
            $this.data("headIndex", instance);
            if ($.type(options) === "string") return instance[options]();
        });
    };

    $.fn.headIndex.default = {
        articleWrapSelector: ".article-wrap",
        indexBoxSelector: ".index-box",
        indexScrollBoxSelector: ".brilliance-toc-scroll",
        scrollSelector: 'body,html',
        scrollWrap: window,
        subItemBoxClass: "index-subItem-box",
        itemClass: "index-item",
        linkClass: "index-link",
        offset: 70,
    }
})(jQuery, window);
