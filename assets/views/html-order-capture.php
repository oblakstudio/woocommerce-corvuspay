<div class="wc-order-data-row wc-order-data-row-toggle corvuspay-partial-capture" style="display: none">
    <table class="wc-order-totals">
        <tr>
            <td class="label"><?php echo esc_html__( 'Authorization total:', 'corvuspay-woocommerce-integration' ); ?></td>
            <td class="total"
                id="authorization_total"><?php echo wc_price( $authorization_total, array( 'currency' => $order->get_currency() ) ); ?></td>
        </tr>
        <tr>
            <td class="label"><label
                        for="capture_amount"><?php echo esc_html__( 'Capture amount:', 'corvuspay-woocommerce-integration' ); ?>
                </label></td>
            <td class="total">
                <input type="text" id="capture_amount" name="capture_amount" class="text wc_input_price"
                       value="<?php echo $authorization_total ?>"/>
                <div class="clear"></div>
            </td>
        </tr>
    </table>
    <div class="clear"></div>
    <hr>
    <div class="capture-actions">
		<?php $amount = '<span class="capture-amount">' . wc_price( $authorization_total, array( 'currency' => $order->get_currency() ) ) . '</span>'; ?>
        <button type="button" class="button cancel-action cancel-action-capture"
                style="float: left"><?php echo esc_html__( 'Cancel', 'corvuspay-woocommerce-integration' ); ?></button>
        <button type="button" id="capture" class="button button-primary capture-action" disabled="disabled"
			<?php echo "data-order_id=" . esc_attr( $order->get_id() ); ?> >
			<?php printf( esc_html__( 'Capture %s', 'corvuspay-woocommerce-integration' ), $amount ); ?></button>
        <div class="clear"></div>
    </div>
</div>
<script type="text/javascript">
    if (window.corvuspay_wc_payment_gateway_admin_order_add_capture_events) {
        window.corvuspay_wc_payment_gateway_admin_order_add_capture_events();
    }
</script>
