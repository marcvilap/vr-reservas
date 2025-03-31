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

            $boxes_disponibles = VR_Reservas_Frontend::get_boxes_disponibles();
            $ocupacion = [];
            $privadas = [];
            $juegos_por_box = [];

            foreach ($boxes_disponibles as $box_id => $box_data) {
                $ocupacion[$box_id] = 0;
                $privadas[$box_id] = false;
                $juegos_por_box[$box_id] = [];
            }

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

                if (!is_array($boxes_reserva) || count($boxes_reserva) === 0) continue;

                if ($tipo_reserva === 'privada') {
                    foreach ($boxes_reserva as $box_id) {
                        if (!isset($boxes_disponibles[$box_id])) continue;
                        $privadas[$box_id] = true;
                    }
                } else {
                    $jug_por_box = ceil($jug_exist / count($boxes_reserva));
                    foreach ($boxes_reserva as $index => $box_id) {
                        if (!isset($boxes_disponibles[$box_id])) continue;
                        $uso = ($index === array_key_last($boxes_reserva)) ? $jug_exist - ($jug_por_box * $index) : $jug_por_box;
                        $ocupacion[$box_id] += $uso;
                        $juegos_por_box[$box_id][$juego_existente] = true;
                    }
                }
            }

            $asignados = [];
            $jugadores_a_asignar = $jugadores;

            if ($tipo === 'privada') {
                // buscar combinaciones óptimas de boxes
                $disponibles = [];
                foreach ($boxes_disponibles as $box_id => $box_data) {
                    if (!$privadas[$box_id] && $ocupacion[$box_id] === 0) {
                        $disponibles[] = [
                            'id' => $box_id,
                            'capacidad' => $box_data['capacidad']
                        ];
                    }
                }

                usort($disponibles, fn($a, $b) => $a['capacidad'] <=> $b['capacidad']);

                $combinaciones = function ($arr) use ($jugadores) {
                    $res = [];
                    $f = function ($prefix, $rest) use (&$res, &$f, $jugadores) {
                        for ($i = 0; $i < count($rest); $i++) {
                            $nuevo = array_merge($prefix, [$rest[$i]]);
                            $suma = array_sum(array_column($nuevo, 'capacidad'));
                            if ($suma >= $jugadores) {
                                $res[] = $nuevo;
                            }
                            $f($nuevo, array_slice($rest, $i + 1));
                        }
                    };
                    $f([], $arr);
                    return $res;
                };

                $posibles = $combinaciones($disponibles);
                usort($posibles, function ($a, $b) {
                    $sumaA = array_sum(array_column($a, 'capacidad'));
                    $sumaB = array_sum(array_column($b, 'capacidad'));
                    return count($a) <=> count($b) ?: $sumaA <=> $sumaB;
                });

                if (!empty($posibles)) {
                    $asignados = array_column($posibles[0], 'id');
                    $jugadores_a_asignar = 0; // éxito
                }

            } else {
                uasort($boxes_disponibles, fn($a, $b) => $b['capacidad'] <=> $a['capacidad']);
                foreach ($boxes_disponibles as $box_id => $box_data) {
                    if ($privadas[$box_id]) continue;

                    $espacio = $box_data['capacidad'] - $ocupacion[$box_id];
                    $juegos_actuales = array_keys($juegos_por_box[$box_id]);
                    $es_vacio = empty($juegos_actuales);
                    $mismo_juego = isset($juegos_por_box[$box_id][$juego_id]);

                    if (($es_vacio || $mismo_juego) && $espacio > 0) {
                        $usar = min($jugadores_a_asignar, $espacio);
                        $jugadores_a_asignar -= $usar;
                        if (!in_array($box_id, $asignados)) {
                            $asignados[] = $box_id;
                        }
                    }

                    if ($jugadores_a_asignar <= 0) break;
                }
            }

            if ($jugadores_a_asignar > 0) {
                wp_die('No hay boxes disponibles para esta reserva.');
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
                    'boxes_asignados' => $asignados
                ]
            ]);

            $precio_final = VR_Reservas_Woo::calcular_precio_reserva($jugadores, $tipo);
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

VR_Reservas_Handler::init();