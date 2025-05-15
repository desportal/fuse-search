<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Hook para eliminar archivo JSON
add_action( 'admin_init', function() {
    if ( isset($_POST['delete_json']) && ! empty($_POST['delete_file']) ) {
        $filename = sanitize_file_name($_POST['delete_file']);
        $filepath = FUSE_SEARCH_JSON_DIR . $filename;

        if ( file_exists($filepath) && strpos($filename, '.json') !== false ) {
            unlink($filepath);
        }
    }
});

