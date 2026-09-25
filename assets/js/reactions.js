(function($) {
    'use strict';

    function getVisitorId() {
        var cookieKey = 'mimosa_reaction_visitor';
        var stored = localStorage.getItem(cookieKey);
        if (stored && /^[A-Za-z0-9_-]{16,80}$/.test(stored)) {
            return stored;
        }

        var match = document.cookie.match(new RegExp('(?:^|; )' + cookieKey.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&') + '=([^;]*)'));
        if (match && /^[A-Za-z0-9_-]{16,80}$/.test(decodeURIComponent(match[1]))) {
            stored = decodeURIComponent(match[1]);
            localStorage.setItem(cookieKey, stored);
            return stored;
        }

        var visitorId = 'rv_' + Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
        document.cookie = cookieKey + '=' + encodeURIComponent(visitorId) + '; path=/; max-age=31536000; samesite=lax';
        localStorage.setItem(cookieKey, visitorId);
        return visitorId;
    }

    function syncReactionCount($root, data) {
        var $list = $root.find('.mimosa-reactions__list');
        var counts = {};
        data.counts.forEach(function(item) {
            counts[item.emoji] = item.count;
        });

        $list.empty();
        data.counts.forEach(function(item) {
            var $pill = $('<span class="mimosa-reactions__pill"></span>');
            $pill.attr('data-emoji', item.emoji);
            $pill.append($('<span class="mimosa-reactions__emoji"></span>').text(item.emoji));
            $pill.append($('<span class="mimosa-reactions__count"></span>').text(item.count));
            $list.append($pill);
        });

        if (!data.counts.length) {
            $list.empty();
        }

        // 空提示：有 reaction 时移除，无 reaction 时显示
        var $empty = $root.find('.mimosa-reactions__empty');
        if (data.counts.length) {
            $empty.remove();
        } else {
            $empty.show();
        }
    }

    function closePopover($root) {
        var $trigger = $root.find('.js-mimosa-reaction-trigger');
        var $popover = $root.find('.mimosa-reactions__popover');
        $popover.attr('hidden', true);
        $trigger.attr('aria-expanded', 'false').removeClass('is-open');
    }

    function openPopover($root) {
        var $trigger = $root.find('.js-mimosa-reaction-trigger');
        var $popover = $root.find('.mimosa-reactions__popover');
        $popover.removeAttr('hidden');
        $trigger.attr('aria-expanded', 'true').addClass('is-open');
    }

    function initReactions() {
        $('.mimosa-reactions').each(function() {
            var $root = $(this);
            var endpoint = $root.data('endpoint');
            var postId = $root.data('post-id');

            closePopover($root);
            $root.find('.mimosa-reactions__popover').attr('hidden', true);

            $root.on('click', '.js-mimosa-reaction-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if ($root.find('.mimosa-reactions__popover').is('[hidden]')) {
                    openPopover($root);
                } else {
                    closePopover($root);
                }
            });

            $root.on('click', '.js-mimosa-reaction-choice', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var emoji = $(this).data('emoji');
                if (!emoji) return;

                closePopover($root);

                $.ajax({
                    url: endpoint,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        emoji: emoji,
                        visitorId: getVisitorId()
                    }
                }).done(function(response) {
                    if (response && response.counts) {
                        syncReactionCount($root, response);
                    }
                }).fail(function() {
                    if (window.MimosaToast) {
                        window.MimosaToast('表态失败，请稍后重试。', 'error', 2400);
                    }
                });
            });
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.mimosa-reactions').length) {
                $('.mimosa-reactions').each(function() {
                    closePopover($(this));
                });
            }
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.mimosa-reactions').each(function() {
                    closePopover($(this));
                });
            }
        });
    }

    $(function() {
        initReactions();
    });
})(jQuery);
