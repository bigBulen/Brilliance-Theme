<?php
if (!defined('ABSPATH') || !current_user_can('manage_options')) exit;
$events = $this->get_admin_events();
$categories = $this->get_admin_categories();
?>
<div class="wrap">
    <h1>时间轴日历管理</h1>
    <div class="timeline-admin-container">
        <div class="timeline-admin-header"><button type="button" class="button button-primary" id="add-new-event">添加新事件</button></div>
        <div class="timeline-events-list">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>标题</th><th>日期类型</th><th>日期</th><th>时间</th><th>分类</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                    <?php if (!$events) : ?><tr><td colspan="7">暂无事件</td></tr><?php endif; ?>
                    <?php foreach ($events as $event) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($event->title); ?></strong></td>
                        <td><?php echo esc_html($event->date_type ?: 'exact'); ?></td>
                        <td><?php echo esc_html($event->event_date . ($event->event_date_end ? ' ~ ' . $event->event_date_end : '')); ?></td>
                        <td><?php echo esc_html($event->event_time ?: '全天'); ?></td>
                        <td><?php echo esc_html($event->category); ?></td>
                        <td><?php echo $event->status === 'published' ? '已发布' : '草稿'; ?></td>
                        <td><button type="button" class="button button-small edit-event" data-id="<?php echo (int) $event->id; ?>">编辑</button> <button type="button" class="button button-small delete-event" data-id="<?php echo (int) $event->id; ?>">删除</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="event-modal" class="timeline-modal" style="display:none">
        <div class="timeline-modal-content">
            <div class="timeline-modal-header"><h2 id="modal-title">添加事件</h2><button type="button" class="timeline-modal-close" aria-label="关闭">×</button></div>
            <div class="timeline-modal-body">
                <form id="event-form" novalidate>
                    <input type="hidden" id="event-id" name="event_id" value="">
                    <input type="hidden" id="event-date-type" name="date_type" value="exact">
                    <div class="form-group"><label for="event-title">事件标题 *</label><input type="text" id="event-title" name="title"></div>
                    <div class="form-group"><label for="event-description">事件描述</label><textarea id="event-description" name="description" rows="4"></textarea></div>
                    <div class="form-group"><span class="field-label">日期类型</span><div class="date-type-options">
                        <label class="date-type-option"><input type="radio" name="date_type_radio" value="exact" checked><span>精确日期</span></label>
                        <label class="date-type-option"><input type="radio" name="date_type_radio" value="month"><span>仅月份</span></label>
                        <label class="date-type-option"><input type="radio" name="date_type_radio" value="range"><span>日期范围</span></label>
                    </div></div>
                    <div id="date-panel-exact" class="date-mode-panel"><div class="form-group"><label for="event-date">事件日期 *</label><input type="date" id="event-date" name="event_date"></div></div>
                    <div id="date-panel-month" class="date-mode-panel" hidden><div class="form-group"><label for="event-month">事件月份 *</label><input type="month" id="event-month" name="event_month" disabled></div></div>
                    <div id="date-panel-range" class="date-mode-panel" hidden><div class="date-range-row"><div class="form-group"><label for="event-date-start">开始日期 *</label><input type="date" id="event-date-start" name="event_date_start" disabled></div><span class="date-range-separator">至</span><div class="form-group"><label for="event-date-end">结束日期 *</label><input type="date" id="event-date-end" name="event_date_end" disabled></div></div></div>
                    <div class="form-row"><div class="form-group"><label for="event-time">事件时间</label><div class="event-time-row"><input type="time" id="event-time" name="event_time"><label class="all-day-label"><input type="checkbox" id="event-all-day"> 全天</label></div></div></div>
                    <div class="form-row">
                        <div class="form-group"><label for="event-category">分类</label><input type="text" id="event-category" name="category" list="timeline-category-list"><datalist id="timeline-category-list"><?php foreach ($categories as $category) : ?><option value="<?php echo esc_attr($category); ?>"><?php endforeach; ?></datalist></div>
                        <div class="form-group"><label for="event-icon">图标</label><input type="text" id="event-icon" name="icon" placeholder="fas fa-calendar"></div>
                    </div>
                    <div class="form-row"><div class="form-group"><label for="text-color">文字颜色</label><input type="text" id="text-color" name="text_color" class="color-picker" value="#333333"></div><div class="form-group"><label for="background-color">背景颜色</label><input type="text" id="background-color" name="background_color" class="color-picker" value="#f8f9fa"></div></div>
                    <div class="form-group"><label for="event-status">状态</label><select id="event-status" name="status"><option value="published">已发布</option><option value="draft">草稿</option></select></div>
                </form>
            </div>
            <div class="timeline-modal-footer"><button type="button" class="button" id="cancel-event">取消</button><button type="button" class="button button-primary" id="save-event">保存</button></div>
        </div>
    </div>
</div>
