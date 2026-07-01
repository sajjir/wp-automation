jQuery(document).ready(function($) {

    // ==========================================
    // 1. RULES LOGIC (سناریوها)
    // ==========================================
    $('#btn-add-new-rule').on('click', function(e) {
        e.preventDefault();
        var container = $('#rules-repeater-container');
        // پاک کردن پیام خالی بودن (در صورت وجود)
        container.find('.hub-empty-state').remove();

        var count = container.find('.rule-row').length;
        var template = $('#rule-template').html();
        
        var parsedHtml = template.replace(/{{RULE_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden').hide();
        
        container.append($node);
        $node.fadeIn(300);
    });

    $(document).on('click', '.remove-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف کامل این سناریو اطمینان دارید؟')) {
            $(this).closest('.rule-row').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });

    // Toggle Sub-triggers
    $(document).on('change', '.trigger-selector', function() {
        var scope = $(this).closest('.rule-row');
        var val = $(this).val();
        scope.find('.cond-box').slideUp(200);
        setTimeout(function() {
            scope.find('.cond-' + val).slideDown(200);
        }, 200);
    });

    // Add/Remove Conditions
    $(document).on('click', '.btn-add-condition', function(e) {
        e.preventDefault();
        var row = $(this).closest('.rule-row');
        var ruleIdx = row.attr('data-index');
        var container = row.find('.conditions-container-rows');
        var condIdx = container.find('.hub-condition-row').length;

        var html = '<div class="hub-condition-row" style="display:none;">' +
            '<select name="rules['+ruleIdx+'][conditions]['+condIdx+'][field]" class="hub-select">' +
            '<option value="order_total">جمع کل سفارش</option><option value="billing_city">شهر صورتحساب</option><option value="user_role">نقش کاربری</option></select> ' +
            '<select name="rules['+ruleIdx+'][conditions]['+condIdx+'][operator]" class="hub-select">' +
            '<option value="equals">برابر با</option><option value="not_equals">مخالف با</option><option value="greater_than">بزرگتر از</option><option value="contains">شامل عبارت</option></select> ' +
            '<input type="text" name="rules['+ruleIdx+'][conditions]['+condIdx+'][value]" class="hub-input" placeholder="مقدار ارزیابی..." /> ' +
            '<button type="button" class="hub-btn-icon-danger btn-remove-condition"><span class="dashicons dashicons-no"></span></button></div>';
        
        var $node = $(html);
        container.append($node);
        $node.fadeIn(200);
    });

    $(document).on('click', '.btn-remove-condition', function() {
        $(this).closest('.hub-condition-row').fadeOut(200, function() { $(this).remove(); });
    });

    // Add/Remove Actions
    $(document).on('click', '.btn-add-action-node', function(e) {
        e.preventDefault();
        var row = $(this).closest('.rule-row');
        var ruleIdx = row.attr('data-index');
        var container = row.find('.actions-holder-rows');
        var actIdx = container.find('.hub-action-card').length;
        var uniqueActionId = 'act_' + Math.random().toString(36).substr(2, 9);

        // واکشی آپشن‌های دراپ‌داون کانال‌ها از روی اولین اکشن موجود یا استفاده از حالت پایه
        var webhookOptions = '<option value="">-- کانال پیشفرض --</option>';
        var existingSelect = container.find('select[name$="[connection_id]"]').first();
        if(existingSelect.length > 0) {
            webhookOptions = existingSelect.html(); // کپی گرفتن آپشن‌ها از ردیف‌های بالا
        }

        var html = '<div class="hub-action-card" style="display:none;">' +
            '<div class="hub-action-header">' +
            '<strong>اقدام #'+uniqueActionId+'</strong>' +
            '<button type="button" class="hub-btn-icon-danger btn-delete-action-node" title="حذف اقدام"><span class="dashicons dashicons-no"></span></button></div>' +
            '<input type="hidden" name="rules['+ruleIdx+'][actions]['+actIdx+'][id]" value="'+uniqueActionId+'" />' +
            '<div class="hub-grid-2"><div class="hub-form-group"><label>نوع اقدام</label>' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][type]" class="hub-select action-type-selector">' +
            '<option value="sms">ارسال پیامک (SMS)</option><option value="telegram">ارسال به تلگرام</option>' +
            '<option value="n8n">ارسال به n8n (وب‌هوک)</option><option value="email">ارسال ایمیل (SMTP)</option></select></div>' +
            '<div class="hub-form-group"><label>انتخاب کانال ارتباطی</label>' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][connection_id]" class="hub-select">'+webhookOptions+'</select></div></div>' +
            '<div class="hub-form-group"><label>محتوای پیام</label><textarea name="rules['+ruleIdx+'][actions]['+actIdx+'][message]" class="hub-textarea" rows="3"></textarea></div>' +
            '<div class="hub-delay-box"><label class="hub-checkbox-label"><input type="checkbox" name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][enabled]" class="chk-delay-toggle" value="1" /> اجرای با تاخیر</label>' +
            '<div class="delay-values-wrapper hidden-box"><input type="number" name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][value]" value="0" class="hub-input-small" /> ' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][unit]" class="hub-select-small"><option value="minutes">دقیقه</option><option value="hours">ساعت</option><option value="days">روز</option></select></div></div>' +
            '</div>';

        var $node = $(html);
        container.append($node);
        $node.fadeIn(200);
    });

    $(document).on('click', '.btn-delete-action-node', function() {
        $(this).closest('.hub-action-card').fadeOut(200, function() { $(this).remove(); });
    });

    $(document).on('change', '.chk-delay-toggle', function() {
        var block = $(this).closest('.hub-delay-box').find('.delay-values-wrapper');
        if($(this).is(':checked')) {
            block.slideDown(200).removeClass('hidden-box');
        } else {
            block.slideUp(200);
        }
    });

    // ==========================================
    // 2. WEBHOOKS LOGIC (اتصالات و کانال‌ها)
    // ==========================================
    function toggleWebhookFields($selectElement) {
        var row = $selectElement.closest('.webhook-row');
        var val = $selectElement.val();
        
        row.find('.wh-field').hide();
        
        if( ['n8n', 'google_sheet', 'slack', 'discord'].includes(val) ) {
            row.find('.wh-webhook-url').fadeIn(200);
        } else {
            row.find('.wh-' + val).fadeIn(200);
        }
    }

    // بررسی و نمایش فیلدهای صحیح در زمان لود صفحه برای آیتم‌های از قبل موجود
    $('.webhook-type-selector').each(function() {
        toggleWebhookFields($(this));
    });

    // بررسی و نمایش فیلدها در زمان تغییر دراپ‌داون
    $(document).on('change', '.webhook-type-selector', function() {
        toggleWebhookFields($(this));
    });

    $('#btn-add-new-webhook').on('click', function(e) {
        e.preventDefault();
        var container = $('#webhooks-repeater-container');
        container.find('.hub-empty-state').remove();

        var count = container.find('.webhook-row').length;
        var template = $('#webhook-template').html();
        
        var parsedHtml = template.replace(/{{WH_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden').hide();
        
        container.append($node);
        $node.fadeIn(300);
        
        // اطمینان از اینکه فیلدها برای المان جدید درست باز میشن
        toggleWebhookFields($node.find('.webhook-type-selector'));
    });

    $(document).on('click', '.remove-webhook-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف این کانال اتصال اطمینان دارید؟')) {
            $(this).closest('.webhook-row').fadeOut(300, function(){ $(this).remove(); });
        }
    });

});