<?php
/**
 * Plugin Name: Fuse Search
 * Description: Reemplazo del buscador de WordPress utilizando Fuse.js con generación de JSON por categoría y etiqueta.
 * Version: 1.0
 * Author: Equipo de la DGE Gobierno de Mendoza
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definir constantes
define( 'FUSE_SEARCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUSE_SEARCH_URL', plugin_dir_url( __FILE__ ) );
define( 'FUSE_SEARCH_JSON_DIR', FUSE_SEARCH_DIR . 'json/' );
define( 'FUSE_SEARCH_JSON_URL', FUSE_SEARCH_URL . 'json/' );

// Crear carpeta /json/ si no existe
register_activation_hook( __FILE__, function() {
    if ( ! file_exists( FUSE_SEARCH_JSON_DIR ) ) {
        mkdir( FUSE_SEARCH_JSON_DIR, 0755, true );
    }
});

// Cargar archivos
require_once FUSE_SEARCH_DIR . 'admin/admin-ui.php';
require_once FUSE_SEARCH_DIR . 'includes/generator.php';
require_once FUSE_SEARCH_DIR . 'includes/json-handler.php';
require_once FUSE_SEARCH_DIR . 'shortcodes/fuse-shortcode.php';

// Agregar menú al panel de administración
add_action( 'admin_menu', function() {
    add_menu_page(
        'Fuse Search',
        'Fuse Search',
        'manage_options',
        'fuse-search',
        'fuse_search_admin_ui',
        'dashicons-search',
        80
    );
});

// Cargar scripts y estilos del backend
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook === 'toplevel_page_fuse-search' ) {
        wp_enqueue_style( 'fuse-admin-css', FUSE_SEARCH_URL . 'assets/css/admin.css' );
        wp_enqueue_script( 'fuse-admin-js', FUSE_SEARCH_URL . 'assets/js/fuse-search.js', array('jquery'), null, true );
    }
});

