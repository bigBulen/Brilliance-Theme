(function($) {
    'use strict';

    function scrollToCurrentMonth($timeline) {
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;
        const $currentMonthWrapper = $timeline.find('#timeline-month-' + currentYear + '-' + currentMonth);

        if (!$currentMonthWrapper.length || !$currentMonthWrapper.offset()) {
            return false;
        }

        const $yearGroup = $currentMonthWrapper.closest('.timeline-year-group');
        $yearGroup.removeClass('timeline-year-collapsed');
        $currentMonthWrapper.removeClass('timeline-month-collapsed');

        $('html, body').animate({ scrollTop: $currentMonthWrapper.offset().top - 100 }, 500);
        return true;
    }

    function scrollToCurrentMonthWithRetry($timeline, maxAttempts, delayMs) {
        let attempts = 0;

        function tryScroll() {
            if (scrollToCurrentMonth($timeline)) {
                return;
            }
            attempts++;
            if (attempts < maxAttempts) {
                setTimeout(tryScroll, delayMs);
            }
        }

        tryScroll();
    }

    function initTimeline($timeline) {
        if (!$timeline.length || $timeline.data('timeline-bound')) {
            return;
        }

        $timeline.data('timeline-bound', true);

        if (!$timeline.data('timeline-scrolled')) {
            $timeline.data('timeline-scrolled', true);
            scrollToCurrentMonthWithRetry($timeline, 10, 120);
        }
    }

    function bindTimelineInteractions() {
        $(document)
            .off('click.timeline-year')
            .on('click.timeline-year', '.argon-timeline-calendar .timeline-year-header', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.timeline-year-group').toggleClass('timeline-year-collapsed');
            })
            .off('click.timeline-month')
            .on('click.timeline-month', '.argon-timeline-calendar .timeline-month-header', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.timeline-month-wrapper').toggleClass('timeline-month-collapsed');
            });
    }

    function bootTimeline() {
        bindTimelineInteractions();
        initTimeline($('#argon-timeline-calendar'));
    }

    $(document).ready(bootTimeline);

    $(document).on('pjax:end pjax:complete pjax:success', bootTimeline);
})(jQuery);
