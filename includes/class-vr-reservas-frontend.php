<?php
class VR_Reservas_Frontend {

    public static function init() {
        add_shortcode('vr_reservar', [self::class, 'mostrar_formulario']);
        add_action('wp_enqueue_scripts', [self::class, 'cargar_estilos_y_scripts']);
        add_action('wp_ajax_vr_obtener_horas_disponibles', [self::class, 'ajax_horas_disponibles']);
        add_action('wp_ajax_nopriv_vr_obtener_horas_disponibles', [self::class, 'ajax_horas_disponibles']);
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
    <input type="number" name="jugadores" id="jugadores" min="1" max="12" required>

    <label for="tipo">Tipo de partida:</label>
    <select name="tipo" id="tipo" required>
        <option value="compartida">Compartida</option>
        <option value="privada">Privada</option>
    </select>

    <label>Fecha:</label>
    <div id="calendario-fecha"></div>
    <input type="hidden" name="fecha" id="fecha" />

    <label>Hora:</label>
    <div id="contenedor-horas" class="vr-horas-container"></div>
    <input type="hidden" name="hora" id="hora" />

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

            $capacidad = ['A' => 8, 'B' => 4];
            $ocupacion = ['A' => 0, 'B' => 0];
            $ocupacion_por_juego = ['A' => [], 'B' => []];
            $privadas = ['A' => false, 'B' => false];

            foreach ($reservas as $reserva) {
                $juego_actual = intval(get_post_meta($reserva->ID, 'juego_id', true));
                $tipo_reserva = get_post_meta($reserva->ID, 'tipo', true);
                $boxes = get_post_meta($reserva->ID, 'boxes_asignados', true);
                $jugadores_reserva = intval(get_post_meta($reserva->ID, 'jugadores', true));

                if (!is_array($boxes)) continue;

                foreach ($boxes as $box) {
                    if ($tipo_reserva === 'privada') {
                        $privadas[$box] = true;
                    }
                    $ocupacion[$box] += $jugadores_reserva;
                    if (!isset($ocupacion_por_juego[$box][$juego_actual])) {
                        $ocupacion_por_juego[$box][$juego_actual] = 0;
                    }
                    $ocupacion_por_juego[$box][$juego_actual] += $jugadores_reserva;
                }
            }

            if ($tipo === 'privada') {
                if ($jugadores <= 4 && !$privadas['B'] && $ocupacion['B'] === 0) {
                    $horas_disponibles[] = $franja;
                } elseif ($jugadores <= 8 && !$privadas['A'] && $ocupacion['A'] === 0) {
                    $horas_disponibles[] = $franja;
                } elseif ($jugadores > 8 && !$privadas['A'] && !$privadas['B'] && $ocupacion['A'] === 0 && $ocupacion['B'] === 0) {
                    $horas_disponibles[] = $franja;
                }
            } else {
                $jugadores_restantes = $jugadores;

                // Intentar rellenar primero boxes con mismo juego
                foreach (["A", "B"] as $box) {
                    if ($privadas[$box]) continue;

                    $espacio = $capacidad[$box] - $ocupacion[$box];
                    $juego_ya_en_box = isset($ocupacion_por_juego[$box][$juego_id]);

                    if ($juego_ya_en_box && $espacio > 0) {
                        $usar = min($jugadores_restantes, $espacio);
                        $jugadores_restantes -= $usar;
                    }
                }

                // Intentar usar boxes vacíos
                foreach (["A", "B"] as $box) {
                    if ($jugadores_restantes <= 0) break;
                    if ($privadas[$box]) continue;
                    if ($ocupacion[$box] === 0) {
                        $espacio = $capacidad[$box];
                        $usar = min($jugadores_restantes, $espacio);
                        $jugadores_restantes -= $usar;
                    }
                }

                if ($jugadores_restantes <= 0) {
                    $horas_disponibles[] = $franja;
                }
            }
        }

        wp_send_json($horas_disponibles);
    }
}