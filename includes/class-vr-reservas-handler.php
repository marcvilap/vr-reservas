<?php
class VR_Reservas_Handler {

    public static function init() {
        add_action('init', [self::class, 'procesar_formulario']);
    }

    public static function procesar_formulario() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['juego'], $_POST['jugadores'], $_POST['tipo'], $_POST['fecha'], $_POST['hora'])) {

            $juego_id   = intval($_POST['juego']);
            $jugadores  = intval($_POST['jugadores']);
            $tipo       = sanitize_text_field($_POST['tipo']);
            $fecha      = sanitize_text_field($_POST['fecha']);
            $hora       = sanitize_text_field($_POST['hora']);

            $boxes = [];
            $capacidad = ['A' => 8, 'B' => 4];
            $ocupacion = ['A' => 0, 'B' => 0];
            $ocupacion_por_juego = ['A' => [], 'B' => []];
            $privadas = ['A' => false, 'B' => false];

            $reservas = get_posts([
                'post_type' => 'vr_reserva',
                'post_status' => ['draft', 'publish'],
                'numberposts' => -1,
                'meta_query' => [
                    ['key' => 'fecha', 'value' => $fecha],
                    ['key' => 'hora', 'value' => $hora]
                ]
            ]);

            foreach ($reservas as $reserva) {
                $tipo_reserva = get_post_meta($reserva->ID, 'tipo', true);
                $boxes_reserva = get_post_meta($reserva->ID, 'boxes_asignados', true);
                $jug_exist = intval(get_post_meta($reserva->ID, 'jugadores', true));
                $juego_existente = intval(get_post_meta($reserva->ID, 'juego_id', true));

                if (!is_array($boxes_reserva)) continue;

                foreach ($boxes_reserva as $box) {
                    if ($tipo_reserva === 'privada') {
                        $privadas[$box] = true;
                        $ocupacion[$box] = $capacidad[$box];
                    } else {
                        $ocupacion[$box] += $jug_exist;
                        if (!isset($ocupacion_por_juego[$box][$juego_existente])) {
                            $ocupacion_por_juego[$box][$juego_existente] = 0;
                        }
                        $ocupacion_por_juego[$box][$juego_existente] += $jug_exist;
                    }
                }
            }

            if ($tipo === 'privada') {
                if ($jugadores <= 4 && !$privadas['B'] && $ocupacion['B'] === 0) {
                    $boxes = ['B'];
                } elseif ($jugadores <= 8 && !$privadas['A'] && $ocupacion['A'] === 0) {
                    $boxes = ['A'];
                } elseif ($jugadores > 8 && !$privadas['A'] && !$privadas['B'] && $ocupacion['A'] === 0 && $ocupacion['B'] === 0) {
                    $boxes = ['A', 'B'];
                } else {
                    wp_die('No hay suficiente disponibilidad para una reserva privada.');
                }
            } else {
                $jugadores_restantes = $jugadores;

                // Primero rellenar los boxes con mismo juego
                foreach (["A", "B"] as $box) {
                    if ($privadas[$box]) continue;
                    $espacio = $capacidad[$box] - $ocupacion[$box];
                    $mismo_juego = isset($ocupacion_por_juego[$box][$juego_id]);

                    if ($mismo_juego && $espacio > 0) {
                        $usar = min($jugadores_restantes, $espacio);
                        if ($usar > 0) $boxes[] = $box;
                        $jugadores_restantes -= $usar;
                    }
                }

                // Luego rellenar boxes vacíos
                foreach (["A", "B"] as $box) {
                    if ($jugadores_restantes <= 0) break;
                    if ($privadas[$box]) continue;
                    if ($ocupacion[$box] === 0) {
                        $usar = min($jugadores_restantes, $capacidad[$box]);
                        if ($usar > 0) $boxes[] = $box;
                        $jugadores_restantes -= $usar;
                    }
                }

                if ($jugadores_restantes > 0) {
                    wp_die('No hay suficiente espacio en los boxes disponibles.');
                }
            }

            $reserva_id = wp_insert_post([
                'post_type' => 'vr_reserva',
                'post_status' => 'draft',
                'post_title' => 'Reserva del ' . $fecha . ' a las ' . $hora,
                'meta_input' => [
                    'juego_id'        => $juego_id,
                    'jugadores'       => $jugadores,
                    'tipo'            => $tipo,
                    'fecha'           => $fecha,
                    'hora'            => $hora,
                    'boxes_asignados' => $boxes
                ]
            ]);

            $timestamp = strtotime($fecha);
            $dia_semana = date('N', $timestamp);
            $tarifa = ($dia_semana >= 5 || $dia_semana == 7) ? 25 : 20;

            $jugadores_facturados = $jugadores;
            if ($tipo === 'privada') {
                if ($jugadores <= 4) $jugadores_facturados = 4;
                elseif ($jugadores <= 8) $jugadores_facturados = 8;
                else $jugadores_facturados = 12;
            }

            $precio_final = $tarifa * $jugadores_facturados;

            $producto_id = self::buscar_producto_reserva();
            if ($producto_id) {
                WC()->cart->add_to_cart($producto_id, 1, 0, [], [
                    'custom_price' => $precio_final,
                    'reserva_id'   => $reserva_id
                ]);

                wp_redirect(wc_get_checkout_url());
                exit;
            }
        }
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
}