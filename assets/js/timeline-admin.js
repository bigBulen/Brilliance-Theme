jQuery(document).ready(function($) {
    $('.color-picker').wpColorPicker();

    const modal = $('#event-modal');
    const form = $('#event-form');
    let formDirty = false;
    let suppressDirty = false;

    function setFormDirty(dirty) {
        formDirty = dirty;
    }

    function markDirty() {
        if (!suppressDirty) {
            formDirty = true;
        }
    }

    function switchDateMode(mode) {
        $('#event-date-type').val(mode);
        $('input[name="date_type_radio"][value="' + mode + '"]').prop('checked', true);

        $('#date-panel-exact, #date-panel-month, #date-panel-range').each(function() {
            $(this).prop('hidden', true).find('input').prop('disabled', true);
        });

        if (mode === 'month') {
            $('#date-panel-month').prop('hidden', false).find('input').prop('disabled', false);
        } else if (mode === 'range') {
            $('#date-panel-range').prop('hidden', false).find('input').prop('disabled', false);
        } else {
            $('#date-panel-exact').prop('hidden', false).find('input').prop('disabled', false);
        }
    }

    function resetDateFields() {
        switchDateMode('exact');
        $('#event-date, #event-month, #event-date-start, #event-date-end').val('');
    }

    function populateDateFields(event) {
        const dateType = event.date_type || 'exact';
        switchDateMode(dateType);

        if (dateType === 'month') {
            $('#event-month').val(String(event.event_date).substring(0, 7));
            $('#event-date, #event-date-start, #event-date-end').val('');
        } else if (dateType === 'range') {
            $('#event-date-start').val(event.event_date);
            $('#event-date-end').val(event.event_date_end || '');
            $('#event-date, #event-month').val('');
        } else {
            $('#event-date').val(event.event_date);
            $('#event-month, #event-date-start, #event-date-end').val('');
        }
    }

    function collectDatePayload() {
        const dateType = $('#event-date-type').val();
        const payload = {
            date_type: dateType,
            event_date: '',
            event_month: '',
            event_date_start: '',
            event_date_end: ''
        };

        if (dateType === 'month') {
            payload.event_month = $('#event-month').val();
        } else if (dateType === 'range') {
            payload.event_date_start = $('#event-date-start').val();
            payload.event_date_end = $('#event-date-end').val();
        } else {
            payload.event_date = $('#event-date').val();
        }

        return payload;
    }

    function validateDatePayload(payload) {
        if (payload.date_type === 'month') {
            if (!payload.event_month) {
                alert('请选择事件月份');
                $('#event-month').focus();
                return false;
            }
        } else if (payload.date_type === 'range') {
            if (!payload.event_date_start || !payload.event_date_end) {
                alert('请填写开始日期和结束日期');
                $('#event-date-start').focus();
                return false;
            }
            if (payload.event_date_start > payload.event_date_end) {
                alert('开始日期不能晚于结束日期');
                $('#event-date-end').focus();
                return false;
            }
        } else if (!payload.event_date) {
            alert('请选择事件日期');
            $('#event-date').focus();
            return false;
        }

        return true;
    }

    function openModal() {
        modal.show();
        $('body').addClass('timeline-modal-open');
    }

    function closeModal(force) {
        if (!force && formDirty) {
            if (!confirm('有未保存的更改，确定要关闭吗？')) {
                return;
            }
        }
        modal.hide();
        $('body').removeClass('timeline-modal-open');
        setFormDirty(false);
    }

    function resetFormForNewEvent() {
        suppressDirty = true;
        form[0].reset();
        $('#event-id').val('');
        resetDateFields();
        $('#text-color').wpColorPicker('color', '#333333');
        $('#background-color').wpColorPicker('color', '#f8f9fa');
        $('#event-all-day').prop('checked', false);
        $('#event-time').prop('disabled', false);
        suppressDirty = false;
        setFormDirty(false);
    }

    switchDateMode('exact');

    $('#add-new-event').on('click', function() {
        $('#modal-title').text('添加新事件');
        resetFormForNewEvent();
        openModal();
    });

    $('.timeline-modal-close, #cancel-event').on('click', function() {
        closeModal(false);
    });

    modal.on('click', function(e) {
        if (e.target === this) {
            e.stopPropagation();
        }
    });

    $('.timeline-modal-content').on('click', function(e) {
        e.stopPropagation();
    });

    $('input[name="date_type_radio"]').on('change', function() {
        switchDateMode($(this).val());
        markDirty();
    });

    form.on('input change', 'input, textarea, select', function() {
        markDirty();
    });

    $('#event-all-day').on('change', function() {
        if ($(this).is(':checked')) {
            $('#event-time').val('').prop('disabled', true);
        } else {
            $('#event-time').prop('disabled', false);
        }
    });

    $(document).on('click', '.edit-event', function() {
        const eventId = $(this).data('id');
        const $btn = $(this);

        $.ajax({
            url: timeline_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'timeline_get_event',
                event_id: eventId,
                nonce: timeline_ajax.nonce
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('加载中...');
            },
            success: function(response) {
                if (response.success) {
                    const event = response.data;

                    suppressDirty = true;
                    $('#modal-title').text('编辑事件');
                    $('#event-id').val(event.id);
                    $('#event-title').val(event.title);
                    $('#event-description').val(event.description);
                    populateDateFields(event);
                    $('#event-time').val(event.event_time || '').prop('disabled', !event.event_time);
                    $('#event-all-day').prop('checked', !event.event_time);
                    $('#event-category').val(event.category);
                    $('#event-icon').val(event.icon);
                    $('#event-status').val(event.status);
                    $('#text-color').wpColorPicker('color', event.text_color);
                    $('#background-color').wpColorPicker('color', event.background_color);
                    suppressDirty = false;
                    setFormDirty(false);
                    openModal();
                } else {
                    alert('获取事件信息失败：' + response.data);
                }
            },
            error: function() {
                alert('请求失败，请重试');
            },
            complete: function() {
                $btn.prop('disabled', false).text('编辑');
            }
        });
    });

    $('#save-event').on('click', function() {
        const datePayload = collectDatePayload();
        const formData = {
            action: 'timeline_save_event',
            nonce: timeline_ajax.nonce,
            event_id: $('#event-id').val(),
            title: $('#event-title').val(),
            description: $('#event-description').val(),
            date_type: datePayload.date_type,
            event_date: datePayload.event_date,
            event_month: datePayload.event_month,
            event_date_start: datePayload.event_date_start,
            event_date_end: datePayload.event_date_end,
            event_time: $('#event-all-day').is(':checked') ? '' : $('#event-time').val(),
            category: $('#event-category').val(),
            text_color: $('#text-color').val(),
            background_color: $('#background-color').val(),
            icon: $('#event-icon').val(),
            status: $('#event-status').val()
        };

        if (!formData.title.trim()) {
            alert('请输入事件标题');
            $('#event-title').focus();
            return;
        }

        if (!validateDatePayload(datePayload)) {
            return;
        }

        $.ajax({
            url: timeline_ajax.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('#save-event').prop('disabled', true).text('保存中...');
            },
            success: function(response) {
                if (response.success) {
                    setFormDirty(false);
                    alert('保存成功');
                    closeModal(true);
                    location.reload();
                } else {
                    alert('保存失败：' + response.data);
                }
            },
            error: function() {
                alert('请求失败，请重试');
            },
            complete: function() {
                $('#save-event').prop('disabled', false).text('保存');
            }
        });
    });

    $(document).on('click', '.delete-event', function() {
        const eventId = $(this).data('id');
        const eventTitle = $(this).closest('tr').find('td:first strong').text();
        const $btn = $(this);

        if (!confirm('确定要删除事件 "' + eventTitle + '" 吗？此操作不可恢复。')) {
            return;
        }

        $.ajax({
            url: timeline_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'timeline_delete_event',
                event_id: eventId,
                nonce: timeline_ajax.nonce
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('删除中...');
            },
            success: function(response) {
                if (response.success) {
                    alert('删除成功');
                    location.reload();
                } else {
                    alert('删除失败：' + response.data);
                }
            },
            error: function() {
                alert('请求失败，请重试');
            },
            complete: function() {
                $btn.prop('disabled', false).text('删除');
            }
        });
    });

    $('#event-icon').on('input', function() {
        const iconClass = $(this).val();

        if ($(this).next('.icon-preview').length === 0) {
            $(this).after('<span class="icon-preview" style="margin-left: 10px;"></span>');
        }

        if (iconClass) {
            $(this).next('.icon-preview').html('<i class="' + iconClass + '"></i>');
        } else {
            $(this).next('.icon-preview').empty();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && modal.is(':visible')) {
            e.preventDefault();
            closeModal(false);
        }

        if (e.ctrlKey && e.keyCode === 83 && modal.is(':visible')) {
            e.preventDefault();
            $('#save-event').click();
        }
    });
});
