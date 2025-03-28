<?php
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
}