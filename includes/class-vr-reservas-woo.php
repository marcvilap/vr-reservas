<?php
class VR_Reservas_Woo {

    public static function init() {
        add_action('woocommerce_before_calculate_totals', [self::class, 'aplicar_precio_personalizado'], 20, 1);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'guardar_datos_en_pedido'], 20, 4);
        add_action('woocommerce_order_status_completed', [self::class, 'confirmar_reserva'], 10, 1);
    }

    public static function aplicar_precio_personalizado($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['custom_price'])) {
                $cart_item['data']->set_price($cart_item['custom_price']);
            }
        }
    }

    public static function guardar_datos_en_pedido($item, $cart_item_key, $values, $order) {
        if (isset($values['reserva_id'])) {
            $item->add_meta_data('ID Reserva VR', $values['reserva_id']);
        }
    }

    public static function confirmar_reserva($order_id) {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item) {
            $reserva_id = $item->get_meta('ID Reserva VR');
            if ($reserva_id) {
                wp_update_post([
                    'ID' => $reserva_id,
                    'post_status' => 'publish'
                ]);
            }
        }
    }
}