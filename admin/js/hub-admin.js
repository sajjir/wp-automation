/**
 * UI Interaction Repeater Framework Javascript core Engine
 */
jQuery(document).ready(function($) {

    // Add Main Macro Rules Blocks
    $('#btn-add-new-rule').on('click', function(e) {
        e.preventDefault();
        var container = $('#rules-repeater-container');
        var count = container.find('.rule-row').length;
        var template = $('#rule-template').html();
        
        // Match indexing values
        var parsedHtml = template.replace(/{{RULE_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden');
        
        container.append($node);
    });

    // Remove Core Row Execution Elements
    $(document).on('click', '.remove-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف کامل این سناریوی اتوماسیون اطمینان دارید؟')) {
            $(this).closest('.rule-row').remove();
            reindexMasterStructure();
        }
    });

    // Handle Title Label Binding Updates
    $(document).on('input', '.txt-rule-name-input', function() {
        var val = $(this).val();
        $(this).closest('.rule-row').find('.lbl-rule-title').text(val ? val : 'بدون نام');
    });

    // Toggle contextual condition views dynamically based on matching selection matrix
    $(document).on('change', '.trigger-selector', function() {
        var scope = $(this).closest('.rule-row');
        var val = $(this).val();
        scope.find('.cond-box').addClass('hidden-box');
        scope.find('.cond-' + val).removeClass('hidden-box');
    });

    // Add nested conditional logic items inside rule arrays block
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

    // Dynamic Multi Action Grid Block Injections Architecture
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

    // Delay Module Visibilities switches
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
            // Updating internal dynamic naming properties loops can go here...
        });
    }
});