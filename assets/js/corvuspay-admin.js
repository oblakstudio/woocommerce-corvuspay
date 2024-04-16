jQuery(function ($) {
	"use strict";

	var wc_corvuspay_admin = {
		init: function () {
			$(document.body).on("change", "#woocommerce_corvuspay_currency_routing", function () {
				var environment = $("#woocommerce_corvuspay_environment"),
					test_store_id = $("#woocommerce_corvuspay_test_store_id").parents("tr").eq(0),
					test_secret_key = $("#woocommerce_corvuspay_test_secret_key").parents("tr").eq(0),
					test_stores_settings = $("#woocommerce_corvuspay_test_stores_settings").parents("tr").eq(0),
					prod_store_id = $("#woocommerce_corvuspay_prod_store_id").parents("tr").eq(0),
					prod_secret_key = $("#woocommerce_corvuspay_prod_secret_key").parents("tr").eq(0),
					prod_stores_settings = $("#woocommerce_corvuspay_prod_stores_settings").parents("tr").eq(0);

				if ($(this).is(":checked")) {
					if (environment.val() === "test") {
						test_store_id.hide();
						test_secret_key.hide();
						test_stores_settings.show();
					} else if (environment.val() === "prod") {
						prod_store_id.hide();
						prod_secret_key.hide();
						prod_stores_settings.show();
					}
				} else {
					if (environment.val() === "test") {
						test_store_id.show();
						test_secret_key.show();
						test_stores_settings.hide();
					} else if (environment.val() === "prod") {
						prod_store_id.show();
						prod_secret_key.show();
						prod_stores_settings.hide();
					}
				}
			});
			$("#woocommerce_corvuspay_currency_routing").change();

			$(document.body).on("change", "#woocommerce_corvuspay_environment", function () {
				var currency_routing = $("#woocommerce_corvuspay_currency_routing"),
					test_store_id = $("#woocommerce_corvuspay_test_store_id").parents("tr").eq(0),
					test_secret_key = $("#woocommerce_corvuspay_test_secret_key").parents("tr").eq(0),
					test_stores_settings = $("#woocommerce_corvuspay_test_stores_settings").parents("tr").eq(0),
					test_certificate = $("#woocommerce_corvuspay_test_certificate").parents("tr").eq(0),
					test_certificate_password = $("#woocommerce_corvuspay_test_certificate_password").parents("tr").eq(0),
                    test_order_number_format = $("#woocommerce_corvuspay_test_order_number_format").parents("tr").eq(0),
					prod_store_id = $("#woocommerce_corvuspay_prod_store_id").parents("tr").eq(0),
					prod_secret_key = $("#woocommerce_corvuspay_prod_secret_key").parents("tr").eq(0),
					prod_stores_settings = $("#woocommerce_corvuspay_prod_stores_settings").parents("tr").eq(0),
					prod_certificate = $("#woocommerce_corvuspay_prod_certificate").parents("tr").eq(0),
					prod_certificate_password = $("#woocommerce_corvuspay_prod_certificate_password").parents("tr").eq(0),
                    prod_order_number_format = $("#woocommerce_corvuspay_prod_order_number_format").parents("tr").eq(0);

				if ($(this).val() === "test") {
					if (currency_routing.is(":checked") ) {
						test_store_id.hide();
						test_secret_key.hide();
						test_stores_settings.show();
					} else {
						test_store_id.show();
						test_secret_key.show();
						test_stores_settings.hide();
					}
					test_certificate.show();
					test_certificate_password.show();
                    test_order_number_format.show();
					prod_store_id.hide();
					prod_secret_key.hide();
					prod_certificate.hide();
					prod_certificate_password.hide();
                    prod_order_number_format.hide();
					prod_stores_settings.hide();
				} else {
					if (currency_routing.is(":checked") ) {
						prod_store_id.hide();
						prod_secret_key.hide();
						prod_stores_settings.show();
					} else {
						prod_store_id.show();
						prod_secret_key.show();
						prod_stores_settings.hide();
					}
					test_store_id.hide();
					test_secret_key.hide();
					test_stores_settings.hide();
					test_certificate.hide();
					test_certificate_password.hide();
                    test_order_number_format.hide();
					prod_certificate.show();
					prod_certificate_password.show();
                    prod_order_number_format.show();
				}
			});
			$("#woocommerce_corvuspay_environment").change();

			$(document.body).on("change", "#woocommerce_corvuspay_form_time_limit_enabled", function () {
				var form_time_limit_seconds = $("#woocommerce_corvuspay_form_time_limit_seconds").parents("tr").eq(0);

				if ($(this).is(":checked")) {
					form_time_limit_seconds.show();
				} else {
					form_time_limit_seconds.hide();
				}
			});
			$("#woocommerce_corvuspay_form_time_limit_enabled").change();

			$(document.body).on("change", "#woocommerce_corvuspay_form_installments", function () {
				var form_installments_map = $("#woocommerce_corvuspay_form_installments_map").parents("tr").eq(0);

				if ($(this).val() === "map") {
					form_installments_map.show();
				} else {
					form_installments_map.hide();
				}
			});
			$("#woocommerce_corvuspay_form_installments").change();

			$(document.body).on("change", "#woocommerce_corvuspay_form_pis_enabled", function () {
				var form_pis_creditor_reference = $("#woocommerce_corvuspay_form_pis_creditor_reference").parents("tr").eq(0);

				if ($(this).is(":checked")) {
					form_pis_creditor_reference.show();
				} else {
					form_pis_creditor_reference.hide();
				}
			});
			$("#woocommerce_corvuspay_form_pis_enabled").change();

            $('.hidden-setting').closest('tr').hide();
		}
	};

	wc_corvuspay_admin.init();
});
