/**
 * Brilliance 短代码交互 JavaScript
 */

(function($) {
    'use strict';

    // ── 折叠块交互
    function initCollapseBlocks() {
        $(document).on('click', '.collapse-block-title', function() {
            const $block = $(this).closest('.collapse-block');
            const $body = $block.find('.collapse-block-body');
            
            $block.toggleClass('collapsed');
            $body.slideToggle(300);
        });
    }

    // ── Spoiler 和 Black 交互
    function initHiddenText() {
        $(document).on('mouseenter focusin', '.brilliance-spoiler, .brilliance-black', function() {
            $(this).addClass('revealed');
        });

        $(document).on('mouseleave focusout', '.brilliance-spoiler, .brilliance-black', function() {
            $(this).removeClass('revealed');
        });

        $(document).on('click', '.brilliance-spoiler, .brilliance-black', function() {
            const $hiddenText = $(this);
            $hiddenText.addClass('revealed');
            window.setTimeout(function() {
                if (!$hiddenText.is(':hover') && !$hiddenText.is(':focus')) {
                    $hiddenText.removeClass('revealed');
                }
            }, 80);
        });
    }

    // ── Checkbox 交互（可选：允许用户勾选）
    function initCheckboxes() {
        $('.shortcode-todo input[type="checkbox"]').on('change', function() {
            const $label = $(this).siblings('label');
            if ($(this).is(':checked')) {
                $label.css('text-decoration', 'line-through');
                $label.css('opacity', '0.6');
            } else {
                $label.css('text-decoration', 'none');
                $label.css('opacity', '1');
            }
        });
        
        // 初始化已选中的样式
        $('.shortcode-todo input[type="checkbox"]:checked').each(function() {
            $(this).siblings('label').css({
                'text-decoration': 'line-through',
                'opacity': '0.6'
            });
        });
    }

    // ── 初始化所有交互
    $(document).ready(function() {
        initCollapseBlocks();
        initHiddenText();
        initCheckboxes();
    });

})(jQuery);
