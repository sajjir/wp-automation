jQuery(document).ready(function($) {

    // ==========================================
    // 1. RULES LOGIC (سناریوها)
    // ==========================================
    $('#btn-add-new-rule').on('click', function(e) {
        e.preventDefault();
        var container = $('#rules-repeater-container');
        container.find('.hub-empty-state').fadeOut(200, function(){ $(this).remove(); });

        var count = container.find('.rule-row').length;
        var template = $('#rule-template').html();
        
        var parsedHtml = template.replace(/{{RULE_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden').hide();
        
        container.append($node);
        $node.slideDown(300);
    });

    $(document).on('click', '.remove-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف کامل این سناریو اطمینان دارید؟')) {
            $(this).closest('.rule-row').slideUp(300, function() {
                $(this).remove();
            });
        }
    });

    $(document).on('change', '.trigger-selector', function() {
        var scope = $(this).closest('.rule-row');
        var val = $(this).val();
        scope.find('.cond-box').hide();
        scope.find('.cond-' + val).fadeIn(200);
    });

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

    $(document).on('click', '.btn-add-action-node', function(e) {
        e.preventDefault();
        var row = $(this).closest('.rule-row');
        var ruleIdx = row.attr('data-index');
        var container = row.find('.actions-holder-rows');
        var actIdx = container.find('.hub-action-card').length;
        var uniqueActionId = 'act_' + Math.random().toString(36).substr(2, 9);

        // واکشی آپشن‌های کانال‌ها (الان دیگه disabled نیستن و درست میان)
        var existingSelect = $('.action-type-selector').closest('.hub-grid-2').find('.connection-selector').first();
        var webhookOptions = existingSelect.length > 0 ? existingSelect.html() : '<option value="">-- کانال پیشفرض --</option>';

        var html = '<div class="hub-action-card" style="display:none;">' +
            '<div class="hub-action-header"><div class="hub-action-title"><span class="dashicons dashicons-megaphone"></span> <input type="text" name="rules['+ruleIdx+'][actions]['+actIdx+'][name]" value="اقدام جدید" class="hub-action-title-input" /></div>' +
            '<div class="hub-action-controls"><button type="button" class="hub-btn hub-btn-warning btn-test-action" title="تست این اکشن روی یک سفارش واقعی">⚡ اقدام آنی</button><button type="button" class="hub-btn-icon-danger btn-delete-action-node" title="حذف اقدام"><span class="dashicons dashicons-trash"></span></button></div></div>' +
            '<input type="hidden" name="rules['+ruleIdx+'][actions]['+actIdx+'][id]" value="'+uniqueActionId+'" />' +
            '<div class="hub-grid-2"><div class="hub-form-group"><label>نوع اقدام</label>' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][type]" class="hub-select action-type-selector">' +
            '<option value="sms">ارسال پیامک (SMS)</option><option value="telegram">ارسال به تلگرام</option>' +
            '<option value="n8n">ارسال به n8n (وب‌هوک)</option><option value="email">ارسال ایمیل (SMTP)</option></select></div>' +
            '<div class="hub-form-group connection-group"><label>انتخاب کانال ارتباطی</label>' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][connection_id]" class="hub-select connection-selector">'+webhookOptions+'</select></div></div>' +
            '<div class="hub-grid-2 target-group"><div class="hub-form-group"><label>گیرنده پیام (Target)</label>' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][target_mode]" class="hub-select target-mode-selector">' +
            '<option value="customer">مشتری (صاحب سفارش)</option><option value="admin">مدیر سایت</option><option value="custom">شماره / آدرس دلخواه</option></select></div>' +
            '<div class="hub-form-group target-custom-box" style="display:none;"><label>مقدار گیرنده (شماره یا ایمیل)</label><input type="text" name="rules['+ruleIdx+'][actions]['+actIdx+'][target_value]" class="hub-input ltr-input" /></div></div>' +
            '<div class="hub-form-group"><label>محتوای پیام</label><textarea name="rules['+ruleIdx+'][actions]['+actIdx+'][message]" class="hub-textarea" rows="3"></textarea></div>' +
            '<div class="hub-delay-box"><label class="hub-checkbox-label"><input type="checkbox" name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][enabled]" class="chk-delay-toggle" value="1" /> اجرای با تاخیر</label>' +
            '<div class="delay-values-wrapper hidden-box" style="margin-top:10px;"><input type="number" name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][value]" value="0" class="hub-input-small" style="width:80px;" /> ' +
            '<select name="rules['+ruleIdx+'][actions]['+actIdx+'][delay][unit]" class="hub-select-small"><option value="minutes">دقیقه</option><option value="hours">ساعت</option><option value="days">روز</option></select></div></div>' +
            '</div>';

        var $node = $(html);
        container.append($node);
        $node.slideDown(200);
        
        filterConnectionOptions($node.find('.action-type-selector'));
    });

    $(document).on('click', '.btn-delete-action-node', function() {
        $(this).closest('.hub-action-card').slideUp(200, function() { $(this).remove(); });
    });

    $(document).on('change', '.chk-delay-toggle', function() {
        var block = $(this).closest('.hub-delay-box').find('.delay-values-wrapper');
        if($(this).is(':checked')) {
            block.slideDown(200).removeClass('hidden-box');
        } else {
            block.slideUp(200);
        }
    });

    $(document).on('change', '.target-mode-selector', function() {
        var card = $(this).closest('.hub-action-card');
        if($(this).val() === 'custom') {
            card.find('.target-custom-box').fadeIn(200);
        } else {
            card.find('.target-custom-box').hide();
        }
    });

    // ==========================================
    // 2. منطق فیلتر کانال‌ها (رفع باگ ذخیره نشدن و مخفی شدن گیرنده برای n8n)
    // ==========================================
    function filterConnectionOptions($actionTypeSelect) {
        var card = $actionTypeSelect.closest('.hub-action-card');
        var actionType = $actionTypeSelect.val();
        var $connSelect = card.find('.connection-selector');
        var $connGroup = card.find('.connection-group');
        var $targetGroup = card.find('.target-group');

        // مخفی کردن کادر گیرنده برای n8n
        if (['n8n', 'order_note', 'order_status'].includes(actionType)) {
            $targetGroup.slideUp(200);
        } else {
            $targetGroup.slideDown(200);
        }

        if (['order_note', 'order_status'].includes(actionType)) {
            $connGroup.hide();
        } else {
            $connGroup.show();
            var validProviders = [];
            if (actionType === 'sms') validProviders = ['melipayamak'];
            if (actionType === 'telegram') validProviders = ['telegram'];
            if (actionType === 'n8n') validProviders = ['n8n', 'google_sheet', 'slack', 'discord'];
            if (actionType === 'email') validProviders = ['email'];

            var isCurrentValid = false;
            var firstValidVal = "";

            $connSelect.find('option').each(function() {
                var pType = $(this).attr('data-provider');
                if (!pType) return; // گزینه پیشفرض (انتخاب کانال) است
                
                if (validProviders.includes(pType)) {
                    $(this).show(); // دیگه از disabled استفاده نمیشه تا توی POST بره!
                    if (firstValidVal === "") firstValidVal = $(this).val();
                    if ($(this).is(':selected')) isCurrentValid = true;
                } else {
                    $(this).hide();
                }
            });

            // فقط در صورتی مقدار سلکت را عوض کن که انتخاب فعلی کاربر نامعتبر باشه!
            if (!isCurrentValid && $connSelect.val() !== "") {
                $connSelect.val(firstValidVal);
            }
        }
    }

    $('.action-type-selector').each(function() {
        filterConnectionOptions($(this));
    });

    $(document).on('change', '.action-type-selector', function() {
        filterConnectionOptions($(this));
    });

    // ==========================================
    // 3. پاپ‌آپ تست آنی (Instant Action Modal)
    // ==========================================
    var currentActionCard = null;

    $(document).on('click', '.btn-test-action', function(e) {
        e.preventDefault();
        currentActionCard = $(this).closest('.hub-action-card');
        
        $('#hub-test-modal').css('display', 'flex').hide().fadeIn(200).addClass('active');
        fetchOrdersForTest('');
    });

    $('.hub-modal-close').on('click', function() {
        $('#hub-test-modal').removeClass('active').fadeOut(200);
    });

    var searchTimer;
    $('#hub-order-search').on('keyup', function() {
        var term = $(this).val();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            fetchOrdersForTest(term);
        }, 500);
    });

    function fetchOrdersForTest(term) {
        var $results = $('#hub-order-results');
        $results.html('<div class="hub-loading-spinner"><span class="dashicons dashicons-update-alt" style="animation: spin 2s linear infinite;"></span> در حال جستجوی سفارشات...</div>');

        $.post(hubAdmin.ajax_url, {
            action: 'hub_search_orders',
            nonce: hubAdmin.nonce,
            term: term
        }, function(response) {
            if(response.success) {
                $results.html(response.data);
            } else {
                $results.html('<p style="color:red;text-align:center;">' + response.data + '</p>');
            }
        });
    }

    $(document).on('click', '.btn-execute-test', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        
        if(!currentActionCard) return;

        var actionData = {
            type: currentActionCard.find('.action-type-selector').val(),
            connection_id: currentActionCard.find('.connection-selector').val(),
            target_mode: currentActionCard.find('.target-mode-selector').val(),
            target_value: currentActionCard.find('.target-custom-box input').val(),
            message: currentActionCard.find('textarea[name$="[message]"]').val()
        };

        if(!actionData.connection_id && !['order_note', 'order_status'].includes(actionData.type)) {
            alert('❌ لطفا ابتدا یک کانال ارتباطی انتخاب کرده و سناریو را ذخیره کنید.');
            return;
        }

        $btn.text('در حال ارسال...').attr('disabled', true);

        $.post(hubAdmin.ajax_url, {
            action: 'hub_test_action',
            nonce: hubAdmin.nonce,
            order_id: orderId,
            action_data: actionData
        }, function(response) {
            if(response.success) {
                alert('✅ ' + response.data);
                $('#hub-test-modal').removeClass('active').fadeOut(200);
            } else {
                alert('❌ خطا در ارسال: ' + response.data);
            }
            $btn.text('ارسال به این سفارش').attr('disabled', false);
        });
    });

    // ==========================================
    // 4. WEBHOOKS LOGIC (کانال‌ها)
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

    $('.webhook-type-selector').each(function() { toggleWebhookFields($(this)); });
    $(document).on('change', '.webhook-type-selector', function() { toggleWebhookFields($(this)); });

    $('#btn-add-new-webhook').on('click', function(e) {
        e.preventDefault();
        var container = $('#webhooks-repeater-container');
        container.find('.hub-empty-state').fadeOut(200, function(){ $(this).remove(); });

        var count = container.find('.webhook-row').length;
        var template = $('#webhook-template').html();
        var parsedHtml = template.replace(/{{WH_INDEX}}/g, count);
        var $node = $(parsedHtml).removeClass('template-hidden').hide();
        
        container.append($node);
        $node.slideDown(300);
        toggleWebhookFields($node.find('.webhook-type-selector'));
    });

    $(document).on('click', '.remove-webhook-row', function(e) {
        e.preventDefault();
        if(confirm('آیا از حذف این کانال ارتباطی اطمینان دارید؟')) {
            $(this).closest('.webhook-row').slideUp(300, function(){ $(this).remove(); });
        }
    });

    $("<style type='text/css'>@keyframes spin { 100% { transform:rotate(360deg); } }</style>").appendTo("head");
});