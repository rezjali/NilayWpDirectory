jQuery(document).ready(function ($) {
    // Check if the wpd_ajax_obj object exists before using it.
    if (typeof wpd_ajax_obj === 'undefined') {
        console.error('WPD AJAX object is not defined. Please check the `wp_localize_script` call.');
        return;
    }

    /**
     * ================================================================
     * 1. Modal Dialog for Confirmation and Alerts
     * ================================================================
     */
    function showWpdModal(title, message, isConfirm = false, onConfirm = null) {
        if ($('#wpd-custom-modal-overlay').length === 0) {
            $('body').append(`
                <div id="wpd-custom-modal-overlay" class="wpd-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000;">
                    <div class="wpd-modal-content" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; border-radius:8px; width:90%; max-width:400px;">
                        <div class="wpd-modal-header" style="border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                            <h4 id="wpd-modal-title" style="margin:0; font-size:1.2em;"></h4>
                            <button class="wpd-modal-close" style="background:none; border:none; font-size:1.5em; cursor:pointer;">×</button>
                        </div>
                        <div class="wpd-modal-body">
                            <p id="wpd-modal-message"></p>
                        </div>
                        <div class="wpd-modal-footer" style="text-align:left; margin-top:20px;">
                            <button id="wpd-modal-cancel-btn" class="wpd-button wpd-button-secondary" style="display:none; background:#aaa; margin-left:10px;"></button>
                            <button id="wpd-modal-confirm-btn" class="wpd-button"></button>
                        </div>
                    </div>
                </div>
            `);
        }

        const $modal = $('#wpd-custom-modal-overlay');
        const $confirmBtn = $('#wpd-modal-confirm-btn');
        const $cancelBtn = $('#wpd-modal-cancel-btn');

        $('#wpd-modal-title').text(title);
        $('#wpd-modal-message').html(message);
        $confirmBtn.off('click');
        $cancelBtn.off('click');

        if (isConfirm) {
            $confirmBtn.text("بله");
            $cancelBtn.text("خیر").show();

            $confirmBtn.on('click', function () {
                if (onConfirm) {
                    onConfirm();
                }
                $modal.hide();
            });
            $cancelBtn.on('click', function () {
                $modal.hide();
            });
        } else {
            $confirmBtn.text("باشه");
            $cancelBtn.hide();
            $confirmBtn.on('click', function () {
                $modal.hide();
            });
        }

        $('.wpd-modal-close').on('click', function () {
            $modal.hide();
        });

        $modal.show();
    }

    /**
     * ================================================================
     * 2. Initialize Frontend Components (Datepicker, Map, Gallery, File Uploader)
     * ================================================================
     */
    function initializeFrontendComponents(container) {
        // Datepicker
        container.find('.wpd-date-picker:not(.hasDatepicker)').each(function () {
            $(this).datepicker({
                dateFormat: 'yy-mm-dd'
            });
        });

        // Single File/Image Uploader
        container.find('.wpd-upload-file-button:not([data-initialized])').each(function () {
            var $button = $(this);
            $button.attr('data-initialized', 'true');
            $button.on('click', function (e) {
                e.preventDefault();
                var input = $button.siblings('input[type="hidden"]');
                var preview = $button.siblings('.file-preview');
                var frame = wp.media({
                    title: 'انتخاب فایل',
                    multiple: false
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    input.val(attachment.id);
                    preview.html('<p>فایل فعلی: <a href="' + attachment.url + '" target="_blank">' + attachment.filename + '</a> <a href="#" class="wpd-remove-file">(حذف)</a></p>');
                });
                frame.open();
            });
        });

        container.on('click', '.wpd-remove-file', function (e) {
            e.preventDefault();
            var wrapper = $(this).closest('.wpd-file-field-wrapper');
            wrapper.find('input[type="hidden"]').val('');
            wrapper.find('.file-preview').html('');
        });

        // Gallery Uploader
        container.find('.wpd-upload-gallery-button:not([data-initialized])').each(function () {
            var $button = $(this);
            $button.attr('data-initialized', 'true');
            $button.on('click', function (e) {
                e.preventDefault();
                var input = $button.siblings('input[type="hidden"]');
                var preview = $button.siblings('.gallery-preview');
                var image_ids = input.val() ? input.val().split(',').map(Number).filter(Boolean) : [];
                var frame = wp.media({
                    title: 'انتخاب تصاویر گالری',
                    button: { text: 'استفاده از این تصاویر' },
                    multiple: 'add'
                });
                frame.on('open', function () {
                    var selection = frame.state().get('selection');
                    image_ids.forEach(function (id) {
                        var attachment = wp.media.attachment(id);
                        attachment.fetch();
                        selection.add(attachment ? [attachment] : []);
                    });
                });
                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    var new_ids = [];
                    preview.empty();
                    selection.each(function (attachment) {
                        new_ids.push(attachment.id);
                        if (attachment.attributes.sizes && attachment.attributes.sizes.thumbnail) {
                            preview.append('<div class="image-container"><img src="' + attachment.attributes.sizes.thumbnail.url + '"><span class="remove-image" data-id="' + attachment.id + '">×</span></div>');
                        }
                    });
                    input.val(new_ids.join(','));
                });
                frame.open();
            });
        });

        // Gallery Image Remover
        container.off('click', '.remove-image').on('click', '.remove-image', function () {
            var id_to_remove = $(this).data('id');
            var wrapper = $(this).closest('.wpd-gallery-field-wrapper');
            var input = wrapper.find('input[type="hidden"]');
            var image_ids = input.val().split(',').map(Number).filter(Boolean);
            var new_ids = image_ids.filter(id => id !== id_to_remove);
            input.val(new_ids.join(','));
            $(this).parent().remove();
        });

        // Map Picker
        container.find('.wpd-map-field-wrapper:not([data-initialized])').each(function () {
            var $wrapper = $(this);
            $wrapper.attr('data-initialized', 'true');
            var input = $wrapper.find('input[type="text"]');
            var mapContainer = $wrapper.find('.map-preview')[0];
            var latlng = input.val() ? input.val().split(',') : [32.4279, 53.6880];
            var map = L.map(mapContainer).setView(latlng, 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            var marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                input.val(e.target.getLatLng().lat.toFixed(6) + ',' + e.target.getLatLng().lng.toFixed(6));
            });
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                input.val(e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6));
            });
            setTimeout(function () { map.invalidateSize() }, 200);
        });

        // Product Price Calculation
        function calculateTotalPrice() {
            let productsTotal = 0;
            $('.wpd-product-field-wrapper').each(function () {
                const $wrapper = $(this);
                const isSelected = $wrapper.find('.wpd-product-select').is(':checked');
                if (!isSelected) {
                    return;
                }
                const pricingMode = $wrapper.data('pricing-mode');
                let price = 0;
                let quantity = 1;
                if ($wrapper.find('.wpd-product-quantity').length) {
                    quantity = parseInt($wrapper.find('.wpd-product-quantity').val()) || 1;
                }
                if (pricingMode === 'fixed') {
                    price = parseFloat($wrapper.data('fixed-price')) || 0;
                } else { // user_defined
                    price = parseFloat($wrapper.find('.wpd-product-user-price').val()) || 0;
                }
                productsTotal += price * quantity;
            });
            $('#wpd-products-total-cost').text(productsTotal.toLocaleString());
            const baseCost = parseFloat($('#wpd-base-cost').data('base-cost')) || 0;
            const finalTotal = baseCost + productsTotal;
            $('#wpd-final-total-cost').text(finalTotal.toLocaleString());
        }
        container.on('change keyup', '.wpd-product-select, .wpd-product-quantity, .wpd-product-user-price', calculateTotalPrice);
        calculateTotalPrice();
        container.on('change', '.wpd-product-select', function () {
            $(this).closest('.wpd-product-field-wrapper').find('.wpd-product-details').slideToggle($(this).is(':checked'));
        });
        $('.wpd-product-field-wrapper input[type="checkbox"]').each(function () {
            if (!$(this).is(':checked')) {
                $(this).closest('.wpd-product-field-wrapper').find('.wpd-product-details').hide();
            }
        });

        // Repeater Logic
        container.on('click', '.wpd-repeater-remove-row-btn', function (e) {
            e.preventDefault();
            const $rowToRemove = $(this).closest('.wpd-repeater-row');
            if (confirm('آیا از حذف این ردیف مطمئن هستید؟')) {
                $rowToRemove.remove();
            }
        });
        container.on('click', '.wpd-repeater-add-row-btn', function (e) {
            e.preventDefault();
            var template = $(this).siblings('.wpd-repeater-template');
            var container = $(this).siblings('.wpd-repeater-rows-container');
            var newIndex = container.children('.wpd-repeater-row').length;
            var newRowHtml = template.html().replace(/__INDEX__/g, newIndex);
            var newRow = $(newRowHtml).appendTo(container);
            initializeFrontendComponents(newRow);
        });
    }

    /**
     * ================================================================
     * 3. Conditional Logic for Form Fields
     * ================================================================
     */
    function checkConditionalLogic(container) {
        container.find('[data-conditional-logic]').each(function () {
            var $dependentField = $(this);
            var logic;
            try {
                logic = JSON.parse($dependentField.attr('data-conditional-logic'));
            } catch (e) {
                console.error('Invalid conditional logic JSON:', $dependentField.attr('data-conditional-logic'));
                return;
            }
            if (!logic.enabled || !logic.target_field) return;
            var $targetField = $('[name="wpd_custom[' + logic.target_field + ']"], [name="wpd_custom[' + logic.target_field + '][]"]');
            var targetValue;
            if ($targetField.is(':radio') || $targetField.is(':checkbox')) {
                targetValue = $targetField.filter(':checked').val() || '';
            } else {
                targetValue = $targetField.val() || '';
            }
            var conditionMet = false;
            switch (logic.operator) {
                case 'is': conditionMet = (targetValue == logic.value); break;
                case 'is_not': conditionMet = (targetValue != logic.value); break;
                case 'is_empty': conditionMet = (targetValue === '' || targetValue === null || targetValue.length === 0); break;
                case 'is_not_empty': conditionMet = (targetValue !== '' && targetValue !== null && targetValue.length > 0); break;
            }
            var shouldShow = (logic.action === 'show') ? conditionMet : !conditionMet;
            if (shouldShow) {
                $dependentField.slideDown('fast');
            } else {
                $dependentField.slideUp('fast');
            }
        });
    }

    function initConditionalLogic(container) {
        checkConditionalLogic(container);
        container.on('change keyup', 'input, select, textarea', function () {
            checkConditionalLogic(container);
        });
    }

    // Initial call for existing fields
    initializeFrontendComponents($('body'));
    initConditionalLogic($('.wpd-container'));

    // Re-initialize after AJAX load
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (settings.data && settings.data.includes('action=wpd_load_custom_fields')) {
            initializeFrontendComponents($('#wpd-custom-fields-wrapper'));
            initConditionalLogic($('#wpd-custom-fields-wrapper'));
        }
    });

    /**
     * ================================================================
     * 4. User Dashboard Tab Management
     * ================================================================
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
        var urlHash = window.location.hash;
        if (urlHash && $('.wpd-dashboard-nav a[href="' + urlHash + '"]').length) {
            $('.wpd-dashboard-nav a[href="' + urlHash + '"]').trigger('click');
        } else {
            $('.wpd-dashboard-nav li:first-child a').trigger('click');
        }
    }

    $(document).on('click', '.wpd-delete-listing-btn', function (e) {
        e.preventDefault();
        const deleteUrl = $(this).attr('href');
        if (confirm(wpd_ajax_obj.modal_messages.delete_confirm)) {
            window.location.href = deleteUrl;
        }
    });

    /**
     * ================================================================
     * 5. AJAX Filtering for Archive Page
     * ================================================================
     */
    var filterForm = $('#wpd-filter-form');
    var resultsContainer = $('#wpd-listings-result-container');

    function submitFilterForm(paged) {
        paged = paged || 1;
        var formData = filterForm.serialize();

        $.ajax({
            url: wpd_ajax_obj.ajax_url,
            type: 'POST',
            data: formData + '&paged=' + paged,
            beforeSend: function () {
                resultsContainer.addClass('loading');
            },
            success: function (response) {
                if (response.success) {
                    resultsContainer.html(response.data.html);
                } else {
                    resultsContainer.html('<p class="wpd-alert wpd-alert-danger">' + (response.data.message || 'خطایی رخ داد.') + '</p>');
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

    $('#filter-listing-type').on('change', function () {
        var typeId = $(this).val();
        var filterFormContainer = $('#wpd-filter-form-dynamic-fields');

        if (typeId === '') {
            filterFormContainer.html('');
            submitFilterForm(1);
            return;
        }

        filterFormContainer.html('<p class="wpd-loading-text">در حال بارگذاری فیلترها...</p>');

        $.ajax({
            url: wpd_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'wpd_load_filter_form',
                nonce: wpd_ajax_obj.nonce,
                listing_type_id: typeId
            },
            success: function (response) {
                if (response.success) {
                    filterFormContainer.html(response.data.html);
                    submitFilterForm(1);
                } else {
                    filterFormContainer.html('<p class="wpd-alert wpd-alert-danger">' + response.data.message + '</p>');
                }
            },
            error: function () {
                filterFormContainer.html('<p class="wpd-alert wpd-alert-danger">خطا در برقراری ارتباط.</p>');
            }
        });
    });

    filterForm.on('submit', function (e) {
        e.preventDefault();
        submitFilterForm(1);
    });

    resultsContainer.on('click', '.page-numbers a', function (e) {
        e.preventDefault();
        var url = new URL($(this).attr('href'));
        var paged = url.searchParams.get("paged") || 1;
        submitFilterForm(paged);
    });
});
