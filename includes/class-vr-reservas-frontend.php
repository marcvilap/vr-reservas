<?php
class VR_Reservas_Frontend {

    public static function init() {
        add_shortcode('vr_reservar', [self::class, 'mostrar_formulario']);
        add_action('wp_enqueue_scripts', [self::class, 'cargar_estilos_y_scripts']);
        add_action('wp_ajax_vr_obtener_horas_disponibles', [self::class, 'ajax_horas_disponibles']);
        add_action('wp_ajax_nopriv_vr_obtener_horas_disponibles', [self::class, 'ajax_horas_disponibles']);
    }

    public static function get_boxes_disponibles() {
        $args = [
            'post_type' => 'vr_box',
            'posts_per_page' => -1,
            'meta_key' => '_vr_box_activo',
            'meta_value' => '1'
        ];

        $boxes = get_posts($args);
        $resultado = [];

        foreach ($boxes as $box) {
            $capacidad = intval(get_post_meta($box->ID, '_vr_box_capacidad', true));
            $resultado[$box->ID] = [
                'nombre' => get_the_title($box->ID),
                'capacidad' => $capacidad
            ];
        }

        return $resultado;
    }

    public static function mostrar_formulario() {
        ob_start();
        ?>
<form id="vr-reserva-form" method="post">
    <label for="juego">Juego:</label>
    <select name="juego" id="juego" required>
        <option value="">Selecciona un juego</option>
        <?php
                $juegos = get_posts(['post_type' => 'vr_juego', 'numberposts' => -1]);
                foreach ($juegos as $juego) {
                    echo '<option value="' . esc_attr($juego->ID) . '">' . esc_html($juego->post_title) . '</option>';
                }
                ?>
    </select>

    <label for="jugadores">Número de jugadores:</label>
    <input type="number" name="jugadores" id="jugadores" min="1" required>

    <label for="tipo">Tipo de partida:</label>
    <select name="tipo" id="tipo" required>
        <option value="compartida">Compartida</option>
        <option value="privada">Privada</option>
    </select>

    <div style="display:flex; gap:2rem">
        <div>
            <label>Fecha:</label>
            <div id="calendario-fecha"></div>
            <input type="hidden" name="fecha" id="fecha" />
        </div>
        <div style="width:100%">
            <label>Hora:</label>
            <div id="contenedor-horas" class="vr-horas-container"></div>
            <input type="hidden" name="hora" id="hora" />
        </div>
    </div>
    <div id="precio-estimado"></div>

    <button type="submit">Reservar ahora</button>
</form>
<?php
        return ob_get_clean();
    }

    public static function cargar_estilos_y_scripts() {
        wp_enqueue_style('vr-reservas-css', plugins_url('../assets/css/vr-reservas.css', __FILE__));
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css');

        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-es', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js', ['flatpickr'], null, true);
        wp_enqueue_script('vr-reservas-js', plugins_url('../assets/js/vr-reservas.js', __FILE__), ['jquery', 'flatpickr', 'flatpickr-es'], null, true);

        wp_localize_script('vr-reservas-js', 'vrReservasData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vr_reservas_nonce')
        ]);
    }

    public static function ajax_horas_disponibles() {
        check_ajax_referer('vr_reservas_nonce', 'nonce');

        $fecha      = sanitize_text_field($_POST['fecha']);
        $jugadores  = intval($_POST['jugadores']);
        $tipo       = sanitize_text_field($_POST['tipo']);
        $juego_id   = intval($_POST['juego']);

        $inicio = 10;
        $fin = 20;
        $horas_disponibles = [];

        $boxes_data = self::get_boxes_disponibles();
        $box_ids = array_keys($boxes_data);

        for ($hora = $inicio; $hora <= $fin; $hora++) {
            $franja = sprintf('%02d:00', $hora);

            $reservas = get_posts([
                'post_type' => 'vr_reserva',
                'post_status' => ['draft', 'publish'],
                'numberposts' => -1,
                'meta_query' => [
                    ['key' => 'fecha', 'value' => $fecha],
                    ['key' => 'hora', 'value' => $franja]
                ]
            ]);

            $ocupacion = [];
            $privadas = [];
            $juegos_por_box = [];

            foreach ($box_ids as $box_id) {
                $ocupacion[$box_id] = 0;
                $privadas[$box_id] = false;
                $juegos_por_box[$box_id] = [];
            }

            foreach ($reservas as $reserva) {
                $juego_actual = intval(get_post_meta($reserva->ID, 'juego_id', true));
                $tipo_reserva = get_post_meta($reserva->ID, 'tipo', true);
                $boxes = get_post_meta($reserva->ID, 'boxes_asignados', true);
                $jugadores_reserva = intval(get_post_meta($reserva->ID, 'jugadores', true));

                if (!is_array($boxes)) continue;

                if ($tipo_reserva === 'privada') {
                    foreach ($boxes as $box_id) {
                        if (!in_array($box_id, $box_ids)) continue;
                        $privadas[$box_id] = true;
                    }
                } else {
                    $jug_por_box = ceil($jugadores_reserva / count($boxes));
                    foreach ($boxes as $index => $box_id) {
                        if (!in_array($box_id, $box_ids)) continue;
                        $uso = ($index === array_key_last($boxes)) ? $jugadores_reserva - ($jug_por_box * $index) : $jug_por_box;
                        $ocupacion[$box_id] += $uso;
                        $juegos_por_box[$box_id][$juego_actual] = true;
                    }
                }
            }

            if ($tipo === 'privada') {
                $capacidad_libre = 0;
                foreach ($boxes_data as $box_id => $box_info) {
                    if (!$privadas[$box_id] && $ocupacion[$box_id] === 0) {
                        $capacidad_libre += $box_info['capacidad'];
                    }
                }

                if ($capacidad_libre >= $jugadores) {
                    $horas_disponibles[] = ['hora' => $franja, 'plazas' => $capacidad_libre];
                }
            } else {
                $capacidad_total = 0;

                foreach ($box_ids as $box_id) {
                    if ($privadas[$box_id]) continue;
                    $juegos_box = array_keys($juegos_por_box[$box_id]);
                    $compatible = empty($juegos_box) || in_array($juego_id, $juegos_box);
                    if ($compatible) {
                        $capacidad_total += $boxes_data[$box_id]['capacidad'] - $ocupacion[$box_id];
                    }
                }

                if ($capacidad_total >= $jugadores) {
                    $horas_disponibles[] = ['hora' => $franja, 'plazas' => $capacidad_total];
                }
            }
        }

        wp_send_json($horas_disponibles);
    }
}

VR_Reservas_Frontend::init();