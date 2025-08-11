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
                    // START OF CHANGE: Removed datepicker initialization from frontend
                    // initJalaliDatepicker();
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

});
