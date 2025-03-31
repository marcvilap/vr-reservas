<?php
class VR_Reservas_Woo {

    public static function init() {
        add_action('woocommerce_before_calculate_totals', [self::class, 'aplicar_precio_personalizado'], 20, 1);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'guardar_datos_en_pedido'], 20, 4);
        add_action('woocommerce_order_status_completed', [self::class, 'confirmar_reserva'], 10, 1);
    }

    // Esta función ya no se usará al quitar custom_price, pero la dejamos por si se reutiliza más adelante
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

    public static function calcular_precio_reserva($jugadores, $tipo_partida) {
        // Esta función sigue devolviendo el precio base del producto
        $producto_id = self::buscar_producto_reserva();
        if ($producto_id) {
            $producto = wc_get_product($producto_id);
            return $producto->get_price();
        }
        return 0;
    }

    private static function buscar_producto_reserva() {
        $productos = wc_get_products(['limit' => -1, 'status' => 'publish']);
        foreach ($productos as $p) {
            if (stripos($p->get_name(), 'reserva') !== false) {
                return $p->get_id();
            }
        }
        return false;
    }

    public static function get_boxes_disponibles() {
        $args = [
            'post_type' => 'vr_box',
            'posts_per_page' => -1,
            'meta_key' => '_vr_box_activo',
            'meta_value' => '1'
        ];

        $posts = get_posts($args);
        $resultado = [];

        foreach ($posts as $box) {
            $capacidad = intval(get_post_meta($box->ID, '_vr_box_capacidad', true));
            $resultado[] = [
                'id' => $box->ID,
                'nombre' => get_the_title($box->ID),
                'capacidad' => $capacidad
            ];
        }

        return $resultado;
    }
}

VR_Reservas_Woo::init();