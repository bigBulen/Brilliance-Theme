(function ($) {
    'use strict';

    var images = [];
    var currentIndex = 0;
    var zoomed = false;
    var panX = 0, panY = 0;
    var imgDrag = null;
    var press = null;

    function getLightbox() {
        var $lightbox = $('#mimosa-lightbox');
        if ($lightbox.length) return $lightbox;

        $lightbox = $('<div id="mimosa-lightbox" class="mimosa-lightbox" aria-hidden="true">' +
            '<div class="mimosa-lightbox__overlay"></div>' +
            '<div class="mimosa-lightbox__container" role="dialog" aria-modal="true" aria-label="图片预览">' +
            '<img class="mimosa-lightbox__img" src="" alt="" draggable="false">' +
            '<button type="button" class="mimosa-lightbox__close" aria-label="关闭">×</button>' +
            '<button type="button" class="mimosa-lightbox__prev" aria-label="上一张">‹</button>' +
            '<button type="button" class="mimosa-lightbox__next" aria-label="下一张">›</button>' +
            '<button type="button" class="mimosa-lightbox__zoom" aria-label="放大或缩小"><span>＋</span></button>' +
            '<span class="mimosa-lightbox__counter"></span>' +
            '<div class="mimosa-lightbox__thumbs"></div>' +
            '</div></div>');
        $('body').append($lightbox);

        $lightbox.on('click', '.mimosa-lightbox__overlay, .mimosa-lightbox__close', closeLightbox);
        // 点击舞台空白处（非图片/按钮/缩略图）关闭
        $lightbox.on('click', '.mimosa-lightbox__container', function (e) {
            if ($(e.target).is('.mimosa-lightbox__container')) closeLightbox();
        });
        // 缩放按钮切缩放；点击图片切缩放（位移小视为点击），放大态下可拖拽平移查看细节
        $lightbox.on('click', '.mimosa-lightbox__zoom', toggleZoom);
        $lightbox.on('dragstart', '.mimosa-lightbox__img', function (e) { e.preventDefault(); });
        $lightbox.on('pointerdown', '.mimosa-lightbox__img', function (e) {
            press = { x: e.clientX, y: e.clientY };
            if (zoomed) {
                imgDrag = { startX: e.clientX, startY: e.clientY, panX: panX, panY: panY, moved: false };
                $(this).addClass('is-dragging');
                try { this.setPointerCapture(e.pointerId); } catch (err) {}
            }
        });
        $lightbox.on('pointermove', '.mimosa-lightbox__img', function (e) {
            if (!imgDrag) return;
            var dx = e.clientX - imgDrag.startX;
            var dy = e.clientY - imgDrag.startY;
            if (!imgDrag.moved && Math.abs(dx) + Math.abs(dy) < 4) return;
            imgDrag.moved = true;
            panX = imgDrag.panX + dx;
            panY = imgDrag.panY + dy;
            applyPan($(this));
        });
        $lightbox.on('pointerup pointercancel', '.mimosa-lightbox__img', function (e) {
            var wasDrag = false;
            if (imgDrag) {
                wasDrag = imgDrag.moved;
                imgDrag = null;
                $(this).removeClass('is-dragging');
                try { this.releasePointerCapture(e.pointerId); } catch (err) {}
                if (wasDrag) { press = null; return; }
            }
            if (press) {
                var dx = e.clientX - press.x;
                var dy = e.clientY - press.y;
                press = null;
                if (Math.abs(dx) + Math.abs(dy) < 6) toggleZoom();
            }
        });
        $lightbox.on('click', '.mimosa-lightbox__prev', function () {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            renderLightbox();
        });
        $lightbox.on('click', '.mimosa-lightbox__next', function () {
            currentIndex = (currentIndex + 1) % images.length;
            renderLightbox();
        });
        $lightbox.on('click', '.mimosa-lightbox__thumb', function () {
            currentIndex = $(this).index();
            renderLightbox();
        });
        return $lightbox;
    }

    function toggleZoom() {
        zoomed = !zoomed;
        var $img = $('#mimosa-lightbox .mimosa-lightbox__img');
        if (zoomed) {
            panX = 0;
            panY = 0;
            $img.addClass('is-zoomed').css('transform', 'translate(0px,0px) scale(2)');
        } else {
            $img.removeClass('is-zoomed').css('transform', '');
        }
        $('#mimosa-lightbox .mimosa-lightbox__zoom span').text(zoomed ? '－' : '＋');
    }

    function applyPan($img) {
        $img.css('transform', 'translate(' + panX + 'px,' + panY + 'px) scale(2)');
    }

    function renderThumbnails() {
        var $thumbs = $('#mimosa-lightbox .mimosa-lightbox__thumbs');
        $thumbs.empty();
        if (images.length <= 1) { $thumbs.hide(); return; }
        $thumbs.show();
        images.forEach(function (src, i) {
            var $t = $('<img class="mimosa-lightbox__thumb" src="' + src + '" alt="">');
            if (i === currentIndex) $t.addClass('is-active');
            $thumbs.append($t);
        });
    }

    function renderLightbox() {
        var $lightbox = getLightbox();
        var multiple = images.length > 1;
        zoomed = false;
        panX = 0;
        panY = 0;
        $lightbox.find('.mimosa-lightbox__img').attr('src', images[currentIndex]).removeClass('is-zoomed').css('transform', '');
        $lightbox.find('.mimosa-lightbox__zoom span').text('＋');
        $lightbox.find('.mimosa-lightbox__counter').text((currentIndex + 1) + ' / ' + images.length).toggle(multiple);
        $lightbox.find('.mimosa-lightbox__prev, .mimosa-lightbox__next').toggle(multiple);
        renderThumbnails();
        $lightbox.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('mimosa-lightbox-open');
    }

    function closeLightbox() {
        $('#mimosa-lightbox').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('mimosa-lightbox-open');
        zoomed = false;
        panX = 0;
        panY = 0;
    }

    function notify(message, type) {
        if (window.MimosaToast) window.MimosaToast(message, type || 'info');
    }

    function initLightbox() {
        var selector = '.article-single__content img, .page-single__content img, .shuoshuo-card__img, .shuoshuo-detail__images img, .comment-content img';
        $(document).on('click', selector, function (event) {
            event.preventDefault();
            event.stopPropagation();
            var $image = $(this);
            var $scope = $image.closest('.article-single__content, .page-single__content, .shuoshuo-card__images, .shuoshuo-detail__images, .comment-content');
            var $group = $scope.length ? $scope.find('img') : $image;
            images = $group.map(function () {
                return $(this).prop('currentSrc') || $(this).attr('src');
            }).get().filter(Boolean);
            var src = $image.prop('currentSrc') || $image.attr('src');
            currentIndex = images.indexOf(src);
            if (currentIndex < 0) {
                images = [src];
                currentIndex = 0;
            }
            renderLightbox();
        });

        $(document).on('keydown', function (event) {
            if (!$('#mimosa-lightbox').hasClass('is-open')) return;
            if (event.key === 'Escape') closeLightbox();
            if (event.key === 'ArrowLeft' && images.length > 1) $('#mimosa-lightbox .mimosa-lightbox__prev').trigger('click');
            if (event.key === 'ArrowRight' && images.length > 1) $('#mimosa-lightbox .mimosa-lightbox__next').trigger('click');
        });
    }

    function initEmotion() {
        var $keyboard = $('#mimosa-emotion-keyboard');
        if (!$keyboard.length) return;

        // 悬浮弹窗：定位到表情按钮附近（使用视口坐标，fixed 定位才正确）
        function positionKeyboard($btn) {
            var rect = $btn.get(0).getBoundingClientRect();
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var w = Math.min(480, vw - 16);
            var left = Math.max(8, Math.min(rect.left + rect.width / 2 - w / 2, vw - w - 8));
            var css = { position: 'fixed', left: left + 'px', width: w + 'px' };
            if (rect.top >= 240) {
                // 上方空间充足：弹窗在按钮正上方
                css.bottom = (vh - rect.top + 10) + 'px';
                css.top = 'auto';
            } else {
                // 上方空间不足：改为按钮下方
                css.top = (rect.bottom + 10) + 'px';
                css.bottom = 'auto';
            }
            $keyboard.css(css);
        }

        $(document).on('click', '.js-comment-emotion-btn', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var $btn = $(this);
            if (!$keyboard.is(':visible')) {
                positionKeyboard($btn);
            }
            $keyboard.stop(true, true).fadeToggle(150);
            $btn.toggleClass('is-active');
        });

        $(document).on('click', '.mimosa-emotion-tab', function () {
            var group = String($(this).data('group'));
            $('.mimosa-emotion-tab').removeClass('is-active');
            $(this).addClass('is-active');
            $('.mimosa-emotion-group').addClass('is-hidden');
            $('.mimosa-emotion-group[data-group="' + group + '"]').removeClass('is-hidden');
        });

        $(document).on('click', '.mimosa-emotion-item', function () {
            var $field = $('#comment-content');
            if (!$field.length) return;
            var text = $(this).data('type') === 'sticker'
                ? '!sticker[' + $(this).data('code') + ']'
                : String($(this).data('text') || '');
            var field = $field.get(0);
            var start = field.selectionStart || 0;
            var end = field.selectionEnd || 0;
            var value = $field.val();
            $field.val(value.slice(0, start) + text + value.slice(end));
            field.selectionStart = field.selectionEnd = start + text.length;
            $field.trigger('focus');
            $keyboard.fadeOut(100);
            $('.js-comment-emotion-btn').removeClass('is-active');
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('.comment-form-wrap').length) {
                $keyboard.fadeOut(100);
                $('.js-comment-emotion-btn').removeClass('is-active');
            }
        });

        // 打开时随滚动关闭，避免 fixed 定位与按钮错位
        $(window).on('scroll', function () {
            if ($keyboard.is(':visible')) {
                $keyboard.fadeOut(100);
                $('.js-comment-emotion-btn').removeClass('is-active');
            }
        });
    }

    function initReply() {
        $(document).on('click', '.js-comment-reply', function () {
            var commentId = $(this).data('comment-id');
            $('#comment-parent-id').val(commentId);
            $('#comment-reply-author').text($(this).data('comment-author'));
            // 填充原评论内容（斜体引用）
            var src = '';
            var $item = $('#comment-' + commentId);
            if ($item.length) {
                src = $item.find('.comment-content').first().text().trim();
            }
            $('#comment-reply-quote').text(src ? src : '').toggle(!!src);
            $('#comment-reply-info').slideDown(200);
            $('#comment-content').trigger('focus');
        });
        $(document).on('click', '#comment-reply-cancel', function () {
            $('#comment-parent-id').val('0');
            $('#comment-reply-info').slideUp(200);
        });
    }

    function initUpvotes() {
        $(document).on('click', '.js-comment-upvote', function () {
            var $button = $(this);
            if ($button.data('upvoted')) return;
            $.post(mimosaComments.ajaxUrl, {
                action: 'mimosa_upvote_comment', nonce: mimosaComments.nonce,
                comment_id: $button.data('comment-id')
            }).done(function (response) {
                if (!response.success) {
                    notify(response.data || '点赞失败，请稍后重试', 'error');
                    return;
                }
                $button.addClass('is-upvoted').data('upvoted', true);
                $button.find('.comment-upvote-count').text(response.data.count);
                notify('已为这条评论点赞', 'success');
            }).fail(function () {
                notify('点赞失败，请稍后重试', 'error');
            });
        });
    }

    function initPin() {
        if (!mimosaComments.isAdmin) return;
        $(document).on('click', '.js-comment-pin', function () {
            var $button = $(this);
            var pinned = $button.data('pinned') === true || $button.data('pinned') === 'true';
            $.post(mimosaComments.ajaxUrl, {
                action: 'mimosa_pin_comment', nonce: mimosaComments.nonce,
                comment_id: $button.data('comment-id'), pin_action: pinned ? 'unpin' : 'pin'
            }).done(function (response) {
                if (response.success) window.location.reload();
            });
        });
    }

    function initEdit() {
        $(document).on('click', '.js-comment-edit', function () {
            var $item = $('#comment-' + $(this).data('comment-id'));
            var $content = $item.find('.comment-content').first();
            if ($item.find('.comment-edit-form').length) return;
            var $editor = $('<div class="comment-edit-form"><textarea class="comment-edit-textarea"></textarea><div class="comment-edit-actions"><button type="button" class="comment-edit-save">保存</button><button type="button" class="comment-edit-cancel">取消</button></div></div>');
            $editor.data('comment-id', $(this).data('comment-id'));
            $editor.find('textarea').val($content.text().trim());
            $content.hide().after($editor);
            $editor.find('textarea').trigger('focus');
        });

        $(document).on('click', '.comment-edit-cancel', function () {
            var $editor = $(this).closest('.comment-edit-form');
            $editor.prev('.comment-content').show();
            $editor.remove();
        });

        $(document).on('click', '.comment-edit-save', function () {
            var $editor = $(this).closest('.comment-edit-form');
            var content = $editor.find('textarea').val().trim();
            if (!content) {
                notify('评论内容不能为空', 'error');
                return;
            }
            var $button = $(this).prop('disabled', true).text('保存中…');
            $.post(mimosaComments.ajaxUrl, {
                action: 'mimosa_edit_comment', nonce: mimosaComments.nonce,
                comment_id: $editor.data('comment-id'), comment: content, use_markdown: '1'
            }).done(function (response) {
                if (response.success) {
                    notify(response.data.message || '评论已更新', 'success');
                    setTimeout(function () { window.location.reload(); }, 650);
                } else {
                    notify(response.data || '更新失败', 'error');
                }
            }).fail(function () {
                notify('更新失败，请稍后重试', 'error');
            }).always(function () {
                $button.prop('disabled', false).text('保存');
            });
        });
    }

    function initSubmit() {
        $(document).on('submit', '#comment-form', function (event) {
            event.preventDefault();
            var $form = $(this);
            var content = $('#comment-content').val().trim();
            var required = $('#comment-author').prop('required');
            if (!content) {
                notify('评论内容不能为空', 'error');
                return;
            }
            if (required && (!$('#comment-author').val().trim() || !$('#comment-email').val().trim())) {
                notify('请填写昵称和邮箱', 'error');
                return;
            }
            if (mimosaComments.captchaEnabled && !$('#comment-captcha-ticket').val()) {
                notify('请先完成角色发色验证', 'info');
                if (window.MimosaCaptcha) window.MimosaCaptcha.requireVerification();
                return;
            }

            var $button = $form.find('.comment-submit-btn').prop('disabled', true);
            $.post(mimosaComments.ajaxUrl, {
                action: 'mimosa_submit_comment', nonce: mimosaComments.nonce,
                comment_post_ID: $form.find('[name="comment_post_ID"]').val(),
                comment_parent: $('#comment-parent-id').val(),
                author: $('#comment-author').val(), email: $('#comment-email').val(),
                url: $('#comment-url').val(), comment: content,
                captcha_ticket: $('#comment-captcha-ticket').val(),
                use_markdown: $('#comment-use-markdown').is(':checked') ? '1' : ''
            }).done(function (response) {
                if (response.success) {
                    notify(response.data.message || '评论已发布', 'success');
                    setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    $('#comment-captcha-ticket').val('');
                    $('.js-comment-captcha').removeClass('is-verified').find('span').text('验证后发送');
                    notify(response.data || '评论发布失败', 'error');
                }
            }).fail(function () {
                notify('评论发布失败，请稍后重试', 'error');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    }


    /* 评论折叠 */
    function initCommentFold() {
        $(document).on('click', '.js-comment-fold', function () {
            var $btn = $(this);
            var $content = $btn.prev('.comment-content');
            if (!$content.length) {
                $content = $btn.closest('.comment-main').find('.comment-content');
            }
            var isExpanded = $content.hasClass('is-expanded');
            if (isExpanded) {
                $content.removeClass('is-expanded');
                $btn.removeClass('is-expanded');
                $btn.find('span').text($btn.data('text-expand') || '展开');
            } else {
                $content.addClass('is-expanded');
                $btn.addClass('is-expanded');
                $btn.find('span').text($btn.data('text-collapse') || '收起');
            }
        });
    }

    // ── BBCode 帮助展开/收起
    function initBbcodeHelp() {
        $(document).on('click', '.js-bbcode-help-toggle', function () {
            var $list = $(this).next('.comment-bbcode-help__list');
            var willOpen = !$list.hasClass('is-open');
            $list.stop(true, true).slideToggle(180).toggleClass('is-open', willOpen);
            $(this).toggleClass('is-active', willOpen).attr('aria-expanded', String(willOpen));
        });
    }

    $(function () {
        initLightbox();
        initEmotion();
        initReply();
        initUpvotes();
        initPin();
        initEdit();
        initSubmit();
        initCommentFold();
        initBbcodeHelp();
    });
})(jQuery);
