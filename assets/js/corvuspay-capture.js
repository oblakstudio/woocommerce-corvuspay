(function ($) {
    'use strict';
    var amountToBeCaptured = $("#capture_amount").val();
    var totalAmount = $("#authorization_total bdi").text().replace(/[^0-9.]/g, '');
    (parseFloat(amountToBeCaptured) <= parseFloat(totalAmount))
        ? $("button.capture-action").removeAttr(
        "disabled"
        )
        : $("button.capture-action").attr(
        "disabled",
        "disabled"
        );

    var t, r, n, c, u, m;
    window.corvuspay_wc_payment_gateway_admin_order_add_capture_events = function () {
        return (
            (u = null != (r = window.woocommerce_admin) ? r : {}),
                (m = null != (n = window.woocommerce_admin_meta_boxes) ? n : {}),
                (t = null != (c = window.accounting) ? c : {}),
                $('#partial_complete').on('click', function () {
                    $(".wc-order-totals-items").hide();
                    $(".wc-order-bulk-actions > :not(.corvuspay-partial-capture)").hide();
                    $(".corvuspay-partial-capture").show();
                }),
                $('.cancel-action-capture').on('click', function () {
                    $(".wc-order-totals-items").show();
                    $(".wc-order-bulk-actions > :not(script)").show();
                    $(".corvuspay-partial-capture").hide();
                }),
                $('.capture').on('click', function () {
                    $("button.capture-action").removeAttr(
                        "disabled"
                    );
                }),
                $('.inside').on('click', '#capture', function () {
                    var confirmed = confirm(corvuspay_vars.confirm_description);
                    if (confirmed) {
                        $(this).prop("disabled", true);
                        $("#capture_amount").prop("disabled", true);
                        // get the order_id from the button tag
                        var order_id = $(this).data('order_id');
                        var order_amount = $("#capture_amount").val();

                        // send the data via ajax to the sever
                        $.ajax({
                            type: 'POST',
                            url: corvuspay_vars.ajax_url,
                            dataType: 'json',
                            data: {
                                action: 'corvuspay_complete_order',
                                order_id: order_id,
                                order_amount: order_amount
                            },
                            success: function (data, textStatus, XMLHttpRequest) {
                                if (data.error === 0) {
                                    window.location.href = window.location.href;
                                } else {
                                    alert(data.message);
                                    $(".cancel-action-capture").trigger("click");
                                }
                            },
                            error: function (XMLHttpRequest, textStatus, errorThrown) {
                                alert(errorThrown);
                            }
                        });
                    }

                }),
                $("#capture_amount").on("keyup change", function () {
                    var amountToBeCaptured = $("#capture_amount").val();
                    var totalAmount = $("#authorization_total bdi").text().replace(/[^0-9.]/g, '');
                    var valid = (amountToBeCaptured.match(/^\d+(?:\.\d+)?$/));

                    (valid && parseFloat(amountToBeCaptured) <= parseFloat(totalAmount) && parseFloat(amountToBeCaptured) > 0)
                        ? $("button.capture-action").removeAttr(
                        "disabled"
                        )
                        : $("button.capture-action").attr(
                        "disabled",
                        "disabled"
                        );

                    $("button .capture-amount .amount").text(
                        t.formatMoney(amountToBeCaptured, {
                            symbol: m.currency_format_symbol,
                            decimal: m.currency_format_decimal_sep,
                            thousand: m.currency_format_thousand_sep,
                            precision: m.currency_format_num_decimals,
                            format: m.currency_format,
                        })
                    )
                }))

    }
    window.corvuspay_wc_payment_gateway_admin_order_add_capture_events();

})(jQuery);