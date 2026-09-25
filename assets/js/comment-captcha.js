(function ($) {
    'use strict';

    var challenge = '';
    var selectedId = 0;
    var pendingSubmit = false;

    function toast(message, type) {
        if (window.MimosaToast) window.MimosaToast(message, type);
    }

    function getModal() {
        var $modal = $('#mimosa-captcha-modal');
        if ($modal.length) return $modal;
        $modal = $('<div id="mimosa-captcha-modal" class="mimosa-captcha-modal" aria-hidden="true">' +
            '<div class="mimosa-captcha-modal__backdrop"></div>' +
            '<section class="mimosa-captcha-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mimosa-captcha-title">' +
            '<button class="mimosa-captcha-modal__close" type="button" aria-label="关闭">×</button>' +
            '<p class="mimosa-captcha-modal__eyebrow">BRILLIANCE CAPTCHA</p>' +
            '<h3 id="mimosa-captcha-title">选择对应发色的角色</h3>' +
            '<p class="mimosa-captcha-modal__hint">请从六位角色中选出发色为 <span class="mimosa-captcha-modal__hair"></span> 的一位。</p>' +
            '<div class="mimosa-captcha-grid"></div>' +
            '<div class="mimosa-captcha-modal__actions"></div>' +
            '</section></div>');
        $('body').append($modal);
        $modal.on('click', '.mimosa-captcha-modal__backdrop, .mimosa-captcha-modal__close', close);
        return $modal;
    }

    function close() {
        $('#mimosa-captcha-modal').removeClass('is-open').attr('aria-hidden', 'true');
    }

    function escapeText(value) {
        return $('<div>').text(value).html();
    }

    function showActions(mode) {
        var $actions = $('#mimosa-captcha-modal .mimosa-captcha-modal__actions');
        if (mode === 'retry') {
            $actions.html('<button type="button" class="mimosa-captcha-action js-captcha-retry mimosa-captcha-action--retry">再试一次</button><button type="button" class="mimosa-captcha-action mimosa-captcha-action--skip js-captcha-skip">跳过验证</button>');
        } else {
            $actions.empty();
        }
    }

    function open() {
        var $modal = getModal();
        selectedId = 0;
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $modal.find('.mimosa-captcha-grid').html('<div class="mimosa-captcha-loading">正在准备图片……</div>');
        showActions();

        $.post(mimosaCaptcha.ajaxUrl, { action: 'mimosa_get_comment_captcha', nonce: mimosaCaptcha.nonce })
            .done(function (response) {
                if (!response.success) {
                    toast(response.data || '验证码加载失败', 'error');
                    close();
                    return;
                }
                challenge = response.data.challenge;
                $modal.find('.mimosa-captcha-modal__hair').text(response.data.hair + '色');
                var cards = response.data.items.map(function (item) {
                    return '<button type="button" class="mimosa-captcha-card" data-id="' + item.id + '">' +
                        '<img src="' + escapeText(item.image) + '" alt="角色验证码图片" loading="lazy">' +
                        '<span class="mimosa-captcha-card__caption"><strong>' + escapeText(item.name) + '</strong>' + escapeText(item.work) + '</span></button>';
                });
                $modal.find('.mimosa-captcha-grid').html(cards.join(''));
            }).fail(function () {
                toast('验证码加载失败，请检查网络后重试', 'error');
                close();
            });
    }

    function reveal($selected, passed) {
        var $cards = $('#mimosa-captcha-modal .mimosa-captcha-card');
        $cards.addClass('is-revealed').prop('disabled', true);
        $selected.addClass(passed ? 'is-correct' : 'is-wrong');
    }

    function finish(ticket, message) {
        $('#comment-captcha-ticket').val(ticket);
        $('.js-comment-captcha').addClass('is-verified').find('span').text('验证通过');
        toast(message, 'success');
        setTimeout(close, 2800);
    }

    function verify($card) {
        selectedId = Number($card.data('id'));
        $.post(mimosaCaptcha.ajaxUrl, {
            action: 'mimosa_verify_comment_captcha', nonce: mimosaCaptcha.nonce,
            challenge: challenge, choice: selectedId
        }).done(function (response) {
            if (!response.success) {
                toast(response.data || '验证已失效，请重新开始', 'error');
                showActions('retry');
                return;
            }
            reveal($card, response.data.passed);
            if (response.data.passed) {
                finish(response.data.ticket, '验证通过！可以发送评论了');
            } else {
                toast('这位角色的发色不符合题目，再看看吧', 'error');
                showActions('retry');
            }
        }).fail(function () {
            toast('验证请求失败，请稍后重试', 'error');
        });
    }

    $(document).on('click', '.js-comment-captcha', function () {
        pendingSubmit = false;
        open();
    });

    $(document).on('click', '.mimosa-captcha-card', function () {
        if (selectedId) return;
        verify($(this));
    });

    $(document).on('click', '.js-captcha-retry', open);
    $(document).on('click', '.js-captcha-skip', function () {
        $.post(mimosaCaptcha.ajaxUrl, { action: 'mimosa_skip_comment_captcha', nonce: mimosaCaptcha.nonce })
            .done(function (response) {
                if (response.success) finish(response.data.ticket, '已跳过验证，可以发送评论了');
                else toast(response.data || '无法跳过验证', 'error');
            });
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') close();
    });

    window.MimosaCaptcha = {
        requireVerification: function () {
            pendingSubmit = true;
            open();
        }
    };
})(jQuery);
