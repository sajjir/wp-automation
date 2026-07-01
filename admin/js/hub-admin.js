/**
 * UI Interaction Repeater Framework Javascript core Engine
 */
jQuery(document).ready(function($) {

    // --- RULES REPEATER LOGIC ---
    $('#btn-add-new-rule').on('click', function(e) {
        e.preventDefault();
        var container = $('#rules-repeater-container');
        var count = container.find('.rule-row').length;
        var template = $('#rule-template').html();
        
        var parsedHtml = template.replace(/{{RULE_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden');
        
        container.append($node);
    });

    $(document).on('click', '.remove-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف کامل این سناریوی اتوماسیون اطمینان دارید؟')) {
            $(this).closest('.rule-row').remove();
            reindexMasterStructure();
        }
    });

    $(document).on('input', '.txt-rule-name-input', function() {
        var val = $(this).val();
        $(this).closest('.rule-row').find('.lbl-rule-title').text(val ? val : 'بدون نام');
    });

    $(document).on('change', '.trigger-selector', function() {
        var scope = $(this).closest('.rule-row');
        var val = $(this).val();
        scope.find('.cond-box').addClass('hidden-box');
        scope.find('.cond-' + val).removeClass('hidden-box');
    });

    $(document).on('click', '.btn-add-condition', function(e) {
        e.preventDefault();
        var row = $(this).closest('.rule-row');
        var ruleIdx = row.attr('data-index');
        var container = row.find('.conditions-container-rows');
        var condIdx = container.find('.condition-item-block').length;

        var html = '<div class="condition-item-block" style="margin-bottom:5px;">' +
            '<select name="rules['+ruleIdx+'][conditions]['+condIdx+'][field]">' +
            '<option value="order_total">جمع کل سفارش</option><option value="billing_city">شهر صورتحساب</option><option value="user_role">نقش کاربری</option></select> ' +
            '<select name="rules['+ruleIdx+'][conditions]['+condIdx+'][operator]">' +
            '<option value="equals">برابر با</option><option value="greater_than">بزرگتر از</option><option value="contains">شامل عبارت</option></select> ' +
            '<input type="text" name="rules['+ruleIdx+'][conditions]['+condIdx+'][value]" placeholder="مقدار هدف" /> ' +
            '<button type="button" class="btn-remove-condition button-link" style="color:#a00;">❌</button></div>';
        
        container.append(html);
    });

    $(document).on('click', '.btn-remove-condition', function() {
        $(this).closest('.condition-item-block').remove();
    });

    $(document).on('click', '.btn-add-action-node', function(e) {
        e.preventDefault();
        var row = $(this).closest('.rule-row');
        var ruleIdx = row.attr('data-index');
        var container = row.find('.actions-holder-rows');
        var actIdx = container.find('.action-block-item').length;
        var uniqueActionId = 'act_' + Math.random().toString(36).substr(2, 9);

        var html = '<div class="action-block-item" style="background:#fff; border:1px solid #ddd; padding:10px; margin-bottom:10px; border-radius:3px;">' +
            '<div class="action-top-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:10px;">' +
            '<strong>🎬 اقدام خروجی (جدید)</strong>' +
            '<button type="button" class="btn-delete-action-node button-link" style="color:#a00;">حذف اقدام</button></div>' +
            '<input type="hidden" name="rules['+ruleIdx+'][actions]['+actIdx+'][id]" value="'+uniqueActionId+'" />' +
            '<table class="form-table" style="margin:0;">' +
            '<tr><th style="width:120px;">نوع اتصال/کانال</th><td>' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][type]">' +
            '<option value="sms">ارسال پیامک بیرونی</option><option value="telegram">ارسال پیام تلگرام</option>' +
            '<option value="n8n">اتصال وب هوک به n8n</option><option value="email">ارسال ایمیل (HTML)</option></select></td></tr>' +
            '<tr><th>متن پیام خروجی</th><td><textarea name="rules['+ruleIdx+'][actions]['+actIdx+'][message]" rows="3" style="width:100%;"></textarea></td></tr>' +
            '<tr><th>⏱ تاخیر در اجرا</th><td><label><input type="checkbox" name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][enabled]" class="chk-delay-toggle" value="1" /> فعالسازی مکانیزم تاخیر زمان‌بندی شده</label>' +
            '<div class="delay-values-wrapper hidden-box" style="margin-top:5px;">مقدار تاخیر: <input type="number" name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][value]" value="0" style="width:60px;" /> ' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][unit]"><option value="minutes">دقیقه</option><option value="hours">ساعت</option><option value="days">روز</option></select></div></td></tr>' +
            '</table></div>';

        container.append(html);
    });

    $(document).on('click', '.btn-delete-action-node', function() {
        $(this).closest('.action-block-item').remove();
    });

    $(document).on('change', '.chk-delay-toggle', function() {
        var block = $(this).closest('td').find('.delay-values-wrapper');
        if($(this).is(':checked')) {
            block.removeClass('hidden-box');
        } else {
            block.addClass('hidden-box');
        }
    });

    function reindexMasterStructure() {
        $('.rule-row').each(function(rIdx, row) {
            $(row).attr('data-index', rIdx);
        });
    }

    // --- WEBHOOKS (CONNECTIONS) REPEATER LOGIC ---
    $(document).on('change', '.webhook-type-selector', function() {
        var row = $(this).closest('.webhook-row');
        var val = $(this).val();
        
        // Hide all fields first
        row.find('.wh-field').addClass('hidden-box');
        
        // Show relevant fields based on type
        if( ['n8n', 'google_sheet', 'slack', 'discord'].includes(val) ) {
            row.find('.wh-n8n').removeClass('hidden-box'); // groups url inputs
        } else {
            row.find('.wh-' + val).removeClass('hidden-box');
        }
    });

    $('#btn-add-new-webhook').on('click', function(e) {
        e.preventDefault();
        var container = $('#webhooks-repeater-container');
        var count = container.find('.webhook-row').length;
        var template = $('#webhook-template').html();
        
        var parsedHtml = template.replace(/{{WH_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden');
        
        container.append($node);
    });

    $(document).on('click', '.remove-webhook-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف این کانال اتصال اطمینان دارید؟')) {
            $(this).closest('.webhook-row').remove();
        }
    });

});