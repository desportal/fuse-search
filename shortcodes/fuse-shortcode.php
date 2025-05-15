<?php
if (!defined('ABSPATH')) exit;

add_shortcode('fusejs_search', 'fusejs_search_shortcode');

function fusejs_search_shortcode($atts) {
    $atts = shortcode_atts([
        'cat'      => '',
        'cats'     => '',
        'etiq'     => '',
        'etiqs'    => '',
        'pag'      => '',
        'pags'     => '',
        'json'     => '',
        'layout'   => 'default',
        'redirect' => ''
    ], $atts);

    $json_files = [];

    foreach (['cat' => 'cat', 'cats' => 'cat', 'etiq' => 'etiq', 'etiqs' => 'etiq', 'pag' => 'pag', 'pags' => 'pag'] as $key => $prefix) {
        if (!empty($atts[$key])) {
            $slugs = array_map('sanitize_title', explode(',', $atts[$key]));
            foreach ($slugs as $slug) {
                $json_files[] = ['file' => $prefix . $slug . '.json', 'label' => $prefix . $slug];
            }
        }
    }

    // Cargar archivos tipo json="todo" → todo-1.json, todo-2.json, etc.
    if (!empty($atts['json']) && sanitize_title($atts['json']) === 'todo') {
        $todo_files = glob(FUSE_SEARCH_JSON_DIR . 'todo-*.json');
        foreach ($todo_files as $path) {
            $basename = basename($path);
            $json_files[] = ['file' => $basename, 'label' => 'global'];
        }
    }

    $layout = esc_attr($atts['layout']);
    $redirect = esc_url($atts['redirect']);

    $output = '<div class="fuse-search-wrapper" data-layout="' . $layout . '" data-redirect="' . $redirect . '">';

    if ($layout === 'barra') {
        $output .= '<div class="fuse-search-barra">';
        $output .= '<form onsubmit="return fuseSearchRedirect(event)">';
        $output .= '<input type="text" id="fuse-barra-input" placeholder="Buscar...">';
        $output .= '<button type="submit"><span role="img" aria-label="Buscar">🔍</span></button>';
        $output .= '</form>';
        $output .= '</div>';
    } else {
        $output .= '<div class="fuse-search-left">';
        $output .= '<input type="text" id="fuse-search-input" placeholder="Buscar..."><div id="fuse-filters"></div></div>';
        $output .= '<div class="fuse-search-right"><ul id="fuse-results"></ul></div>';
        $output .= '<div id="fuse-pagination"></div>';
    }

    foreach ($json_files as $item) {
        $file_url = esc_url(FUSE_SEARCH_JSON_URL . $item['file']);
        $label = esc_attr($item['label']);
        $output .= '<script class="fuse-json" type="application/json" data-src="' . $file_url . '" data-label="' . $label . '"></script>';
    }

    $output .= '</div>';

    return $output;
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('fuse-js', 'https://cdn.jsdelivr.net/npm/fuse.js@6.6.2/dist/fuse.min.js', [], null, true);
    wp_enqueue_script('fuse-search-frontend', FUSE_SEARCH_URL . 'assets/js/fuse-search.js', ['fuse-js'], null, true);
    wp_enqueue_style('fuse-search-style', FUSE_SEARCH_URL . 'assets/css/admin.css');
});
