<?php
// class-vr-reservas-admin.php

if (!defined('ABSPATH')) exit;

class VR_Reservas_Admin {

    public static function init() {
        add_action('init', [self::class, 'registrar_post_types']);

        // Mostrar metabox con detalles de la reserva
        add_action('add_meta_boxes', function () {
            add_meta_box('vr_datos_reserva', 'Datos de la reserva', [self::class, 'mostrar_datos_reserva'], 'vr_reserva', 'normal', 'high');
        });

        // Columnas personalizadas en el listado de reservas
        add_filter('manage_vr_reserva_posts_columns', [self::class, 'agregar_columnas_personalizadas']);
        add_action('manage_vr_reserva_posts_custom_column', [self::class, 'mostrar_columnas_personalizadas'], 10, 2);

        // Boxes dinámicos
        add_action('add_meta_boxes', [self::class, 'agregar_metabox_boxes']);
        add_action('save_post', [self::class, 'guardar_campos_boxes']);

        // Formulario editable para reservas
        add_action('add_meta_boxes', [self::class, 'agregar_metabox_reserva_editable']);
        add_action('save_post', [self::class, 'guardar_datos_reserva_editable']);
    }

    public static function registrar_post_types() {
        register_post_type('vr_reserva', [
            'label' => 'Reservas',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-tickets-alt'
        ]);

        register_post_type('vr_juego', [
            'label' => 'Juegos',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-vr'
        ]);

        register_post_type('vr_box', [
            'label' => 'Boxes',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-screenoptions'
        ]);
    }

    public static function crear_ejemplos() {
        if (!get_posts(['post_type' => 'vr_juego'])) {
            wp_insert_post(['post_type' => 'vr_juego', 'post_title' => 'Zombie Escape', 'post_status' => 'publish']);
            wp_insert_post(['post_type' => 'vr_juego', 'post_title' => 'Space Mission', 'post_status' => 'publish']);
        }

        if (!get_posts(['post_type' => 'vr_box'])) {
            wp_insert_post(['post_type' => 'vr_box', 'post_title' => 'Box A', 'post_status' => 'publish']);
            wp_insert_post(['post_type' => 'vr_box', 'post_title' => 'Box B', 'post_status' => 'publish']);
        }
    }

    public static function mostrar_datos_reserva($post) {
        $juego_id  = get_post_meta($post->ID, 'juego_id', true);
        $juego     = $juego_id ? get_the_title($juego_id) : '-';
        $jugadores = get_post_meta($post->ID, 'jugadores', true);
        $tipo      = get_post_meta($post->ID, 'tipo', true);
        $fecha     = get_post_meta($post->ID, 'fecha', true);
        $hora      = get_post_meta($post->ID, 'hora', true);
        $boxes     = get_post_meta($post->ID, 'boxes_asignados', true);

        echo "<p><strong>Juego:</strong> $juego</p>";
        echo "<p><strong>Jugadores:</strong> $jugadores</p>";
        echo "<p><strong>Tipo:</strong> $tipo</p>";
        echo "<p><strong>Fecha:</strong> $fecha</p>";
        echo "<p><strong>Hora:</strong> $hora</p>";

        if (is_array($boxes)) {
            echo "<p><strong>Boxes asignados:</strong> " . implode(', ', $boxes) . "</p>";
        }
    }

    public static function agregar_columnas_personalizadas($columns) {
        $columns['juego'] = 'Juego';
        $columns['fecha'] = 'Fecha';
        $columns['hora'] = 'Hora';
        $columns['tipo'] = 'Tipo';
        $columns['jugadores'] = 'Jugadores';
        $columns['boxes'] = 'Boxes';
        return $columns;
    }

    public static function mostrar_columnas_personalizadas($column, $post_id) {
        switch ($column) {
            case 'juego':
                $juego_id = get_post_meta($post_id, 'juego_id', true);
                echo $juego_id ? esc_html(get_the_title($juego_id)) : '-';
                break;
            case 'fecha':
                echo esc_html(get_post_meta($post_id, 'fecha', true));
                break;
            case 'hora':
                echo esc_html(get_post_meta($post_id, 'hora', true));
                break;
            case 'tipo':
                echo esc_html(get_post_meta($post_id, 'tipo', true));
                break;
            case 'jugadores':
                echo esc_html(get_post_meta($post_id, 'jugadores', true));
                break;
            case 'boxes':
                $boxes = get_post_meta($post_id, 'boxes_asignados', true);
                echo is_array($boxes) ? esc_html(implode(', ', $boxes)) : '-';
                break;
        }
    }

    public static function agregar_metabox_boxes() {
        add_meta_box('vr_box_info', 'Información del Box', [self::class, 'mostrar_metabox_boxes'], 'vr_box', 'normal', 'default');
    }

    public static function mostrar_metabox_boxes($post) {
        $capacidad = get_post_meta($post->ID, '_vr_box_capacidad', true);
        $activo = get_post_meta($post->ID, '_vr_box_activo', true);
        ?>
<label for="vr_box_capacidad">Capacidad máxima:</label>
<input type="number" name="vr_box_capacidad" id="vr_box_capacidad" value="<?= esc_attr($capacidad); ?>" min="1" />

<label for="vr_box_activo" style="display:block; margin-top:1rem;">¿Activo?</label>
<select name="vr_box_activo" id="vr_box_activo">
    <option value="1" <?= selected($activo, '1'); ?>>Sí</option>
    <option value="0" <?= selected($activo, '0'); ?>>No</option>
</select>
<?php
    }

    public static function guardar_campos_boxes($post_id) {
        if (isset($_POST['vr_box_capacidad'])) {
            update_post_meta($post_id, '_vr_box_capacidad', intval($_POST['vr_box_capacidad']));
        }
        if (isset($_POST['vr_box_activo'])) {
            update_post_meta($post_id, '_vr_box_activo', $_POST['vr_box_activo']);
        }
    }

    public static function agregar_metabox_reserva_editable() {
        add_meta_box('vr_editable_fields', 'Editar datos de la reserva', [self::class, 'mostrar_formulario_admin'], 'vr_reserva', 'normal', 'core');
    }

    public static function mostrar_formulario_admin($post) {
        $juego_id  = get_post_meta($post->ID, 'juego_id', true);
        $jugadores = get_post_meta($post->ID, 'jugadores', true);
        $tipo      = get_post_meta($post->ID, 'tipo', true);
        $fecha     = get_post_meta($post->ID, 'fecha', true);
        $hora      = get_post_meta($post->ID, 'hora', true);
        $boxes     = get_post_meta($post->ID, 'boxes_asignados', true);

        $juegos = get_posts(['post_type' => 'vr_juego', 'numberposts' => -1]);
        $all_boxes = get_posts(['post_type' => 'vr_box', 'numberposts' => -1]);
        $capacidad_total = array_sum(array_map(function($box) {
            return intval(get_post_meta($box->ID, '_vr_box_capacidad', true));
        }, $all_boxes));

        ?>
<label for="vr_juego_id">Juego:</label>
<select name="vr_juego_id" id="vr_juego_id">
    <?php foreach ($juegos as $j): ?>
    <option value="<?= esc_attr($j->ID) ?>" <?= selected($juego_id, $j->ID) ?>><?= esc_html($j->post_title) ?></option>
    <?php endforeach; ?>
</select>

<label for="vr_jugadores">Jugadores:</label>
<input type="number" name="vr_jugadores" id="vr_jugadores" value="<?= esc_attr($jugadores); ?>" min="1"
    max="<?= $capacidad_total ?>" />

<label for="vr_tipo">Tipo:</label>
<select name="vr_tipo" id="vr_tipo">
    <option value="compartida" <?= selected($tipo, 'compartida') ?>>Compartida</option>
    <option value="privada" <?= selected($tipo, 'privada') ?>>Privada</option>
</select>

<label for="vr_fecha">Fecha:</label>
<input type="date" name="vr_fecha" id="vr_fecha" value="<?= esc_attr($fecha); ?>" />

<label for="vr_hora">Hora:</label>
<input type="time" name="vr_hora" id="vr_hora" value="<?= esc_attr($hora); ?>" />

<label for="vr_boxes[]">Boxes asignados:</label>
<select name="vr_boxes[]" id="vr_boxes" multiple>
    <?php foreach ($all_boxes as $b): ?>
    <option value="<?= esc_attr($b->ID) ?>" <?= (is_array($boxes) && in_array($b->ID, $boxes)) ? 'selected' : '' ?>>
        <?= esc_html($b->post_title) ?></option>
    <?php endforeach; ?>
</select>
<?php
    }

    public static function guardar_datos_reserva_editable($post_id) {
        if (get_post_type($post_id) !== 'vr_reserva') return;

        if (isset($_POST['vr_juego_id'])) {
            update_post_meta($post_id, 'juego_id', intval($_POST['vr_juego_id']));
        }
        if (isset($_POST['vr_jugadores'])) {
            update_post_meta($post_id, 'jugadores', intval($_POST['vr_jugadores']));
        }
        if (isset($_POST['vr_tipo'])) {
            update_post_meta($post_id, 'tipo', sanitize_text_field($_POST['vr_tipo']));
        }
        if (isset($_POST['vr_fecha'])) {
            update_post_meta($post_id, 'fecha', sanitize_text_field($_POST['vr_fecha']));
        }
        if (isset($_POST['vr_hora'])) {
            update_post_meta($post_id, 'hora', sanitize_text_field($_POST['vr_hora']));
        }
        if (isset($_POST['vr_boxes'])) {
            update_post_meta($post_id, 'boxes_asignados', array_map('intval', $_POST['vr_boxes']));
        }
    }
}

VR_Reservas_Admin::init();