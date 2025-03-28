<?php
/**
 * Plugin Name: VR Reservas
 * Description: Sistema de reservas para un centro de realidad virtual integrado con WooCommerce.
 * Version: 1.0
 * Author: Tu nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constante para la ruta del plugin
define('VR_RESERVAS_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Incluir archivos principales del plugin
require_once VR_RESERVAS_PLUGIN_DIR . 'includes/class-vr-reservas-admin.php';
require_once VR_RESERVAS_PLUGIN_DIR . 'includes/class-vr-reservas-frontend.php';
require_once VR_RESERVAS_PLUGIN_DIR . 'includes/class-vr-reservas-woo.php';
require_once VR_RESERVAS_PLUGIN_DIR . 'includes/class-vr-reservas-handler.php'; // ✅ NUEVO

// Inicializar las clases principales
add_action('plugins_loaded', function () {
    VR_Reservas_Admin::init();
    VR_Reservas_Frontend::init();
    VR_Reservas_Woo::init();
    VR_Reservas_Handler::init(); // ✅ IMPORTANTE: asegúrate que la clase existe y está bien escrita
});