jQuery(document).ready(function ($) {

    /**
     * ------------------------------------------------
     * 1. بارگذاری فیلدهای سفارشی بر اساس نوع آگهی
     * ------------------------------------------------
     */
    $('#listing_type').on('change', function () {
        var listingTypeID = $(this).val();
        var wrapper = $('#wpd-custom-fields-wrapper');
        var listingID = $('input[name="listing_id"]').val(); // برای حالت ویرایش

        if (listingTypeID === '') {
            wrapper.html('');
            return;
        }

        wrapper.html('<p class="wpd-loading-text">در حال بارگذاری فیلدها...</p>');

        $.ajax({
            url: wpd_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'wpd_load_custom_fields',
                nonce: wpd_ajax_obj.nonce,
                listing_type_id: listingTypeID,
                listing_id: listingID
            },
            success: function (response) {
                if (response.success) {
                    wrapper.html(response.data.html);
                    // START OF CHANGE: Initialize conditional logic for newly loaded fields
                    initConditionalLogic();
                    // END OF CHANGE
                } else {
                    wrapper.html('<p class="wpd-alert wpd-alert-danger">' + response.data.message + '</p>');
                }
            },
            error: function () {
                wrapper.html('<p class="wpd-alert wpd-alert-danger">خطا در برقراری ارتباط با سرور.</p>');
            }
        });
    });


    /**
     * ------------------------------------------------
     * 2. مدیریت تب‌ها در داشبورد کاربری
     * ------------------------------------------------
     */
    $('.wpd-dashboard-nav a').on('click', function (e) {
        e.preventDefault();
        var tab_id = $(this).attr('href');
        $('.wpd-dashboard-nav li').removeClass('active');
        $('.wpd-tab-content').removeClass('active');
        $(this).parent().addClass('active');
        $(tab_id).addClass('active');
    });

    if ($('.wpd-dashboard-nav').length) {
        $('.wpd-dashboard-nav li:first-child').addClass('active');
        $('.wpd-tab-content:first-of-type').addClass('active');
    }


    /**
     * ------------------------------------------------
     * 3. فیلتر کردن آگهی‌ها با AJAX
     * ------------------------------------------------
     */
    var filterForm = $('#wpd-filter-form');
    var resultsContainer = $('#wpd-listings-result-container');

    function submitFilterForm(paged) {
        paged = paged || 1;
        var formData = filterForm.serialize() + '&paged=' + paged;

        $.ajax({
            url: wpd_ajax_obj.ajax_url,
            type: 'POST',
            data: formData + '&action=wpd_filter_listings&nonce=' + wpd_ajax_obj.nonce,
            beforeSend: function () {
                resultsContainer.addClass('loading');
            },
            success: function (response) {
                if (response.success) {
                    resultsContainer.html(response.data.html);
                } else {
                    resultsContainer.html('<p class="wpd-alert wpd-alert-danger">خطایی رخ داد.</p>');
                }
            },
            error: function () {
                resultsContainer.html('<p class="wpd-alert wpd-alert-danger">خطا در ارتباط با سرور.</p>');
            },
            complete: function () {
                resultsContainer.removeClass('loading');
            }
        });
    }

    // ارسال فرم با کلیک روی دکمه یا تغییر فیلترها
    filterForm.on('submit', function (e) {
        e.preventDefault();
        submitFilterForm(1); // همیشه از صفحه اول شروع کن
    });

    // برای فیلتر آنی با تغییر select ها
    $('#filter-category, #filter-location').on('change', function () {
        submitFilterForm(1);
    });

    // مدیریت کلیک روی لینک‌های صفحه‌بندی
    resultsContainer.on('click', '.page-numbers a', function (e) {
        e.preventDefault();
        var url = new URL($(this).attr('href'));
        var paged = url.searchParams.get("paged") || 1;
        submitFilterForm(paged);
    });

    // START OF CHANGE: Conditional Logic Functions
    /**
     * ------------------------------------------------
     * 4. منطق شرطی برای فیلدهای فرم
     * ------------------------------------------------
     */
    function checkConditionalLogic() {
        $('.wpd-form-group[data-conditional-logic]').each(function () {
            var $dependentField = $(this);
            var logic;
            try {
                logic = JSON.parse($dependentField.attr('data-conditional-logic'));
            } catch (e) {
                console.error('Invalid conditional logic JSON:', $dependentField.attr('data-conditional-logic'));
                return;
            }

            if (!logic.target_field) return;

            var $targetField = $('[name="wpd_custom[' + logic.target_field + ']"], [name="wpd_custom[' + logic.target_field + '][]"]');
            var targetValue;

            if ($targetField.is(':radio') || $targetField.is(':checkbox')) {
                targetValue = $targetField.filter(':checked').val() || '';
            } else {
                targetValue = $targetField.val() || '';
            }

            var conditionMet = false;
            switch (logic.operator) {
                case 'is':
                    conditionMet = (targetValue == logic.value);
                    break;
                case 'is_not':
                    conditionMet = (targetValue != logic.value);
                    break;
                case 'is_empty':
                    conditionMet = (targetValue === '' || targetValue === null || targetValue.length === 0);
                    break;
                case 'is_not_empty':
                    conditionMet = (targetValue !== '' && targetValue !== null && targetValue.length > 0);
                    break;
            }

            var shouldShow = (logic.action === 'show') ? conditionMet : !conditionMet;

            if (shouldShow) {
                $dependentField.slideDown();
            } else {
                $dependentField.slideUp();
            }
        });
    }

    function initConditionalLogic() {
        // Run on page load for all fields
        checkConditionalLogic();

        // Attach event listeners to all potential target fields
        $('#wpd-custom-fields-wrapper').on('change keyup', 'input, select, textarea', function () {
            checkConditionalLogic();
        });
    }

    // Initial call
    initConditionalLogic();
    // END OF CHANGE

});
