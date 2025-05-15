<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', function () {
    if (isset($_POST['crear_json_categoria']) && !empty($_POST['fuse_categoria'])) {
        $cat_id = intval($_POST['fuse_categoria']);
        fuse_generate_json_for_category($cat_id);
    }

    if (isset($_POST['crear_json_etiqueta']) && !empty($_POST['fuse_etiqueta'])) {
        $tag_id = intval($_POST['fuse_etiqueta']);
        fuse_generate_json_for_tag($tag_id);
    }

    if (isset($_POST['crear_json_paginas']) && !empty($_POST['fuse_paginas_ids']) && !empty($_POST['fuse_paginas_json_name'])) {
        $page_ids = array_map('intval', explode(',', $_POST['fuse_paginas_ids']));
        $name = sanitize_title($_POST['fuse_paginas_json_name']);
        fuse_generate_json_for_pages($page_ids, $name);
    }

    if (isset($_POST['crear_json_global'])) {
        fuse_generate_json_for_global();
    }
});

function fuse_generate_json_for_category($cat_id) {
    $category = get_category($cat_id);
    if (!$category) return;

    $slug = sanitize_title($category->name);
    $filename = 'cat' . $slug . '.json';

    $args = [
        'post_type' => 'post',
        'cat' => $cat_id,
        'posts_per_page' => -1
    ];

    $posts = get_posts($args);
    $data = [];

    foreach ($posts as $post) {
        setup_postdata($post);
        $data[] = fuse_format_post_data($post);
    }
    wp_reset_postdata();

    file_put_contents(FUSE_SEARCH_JSON_DIR . $filename, wp_json_encode($data));
}

function fuse_generate_json_for_tag($tag_id) {
    $tag = get_tag($tag_id);
    if (!$tag) return;

    $slug = sanitize_title($tag->name);
    $filename = 'etiq' . $slug . '.json';

    $args = [
        'post_type' => 'post',
        'tag_id' => $tag_id,
        'posts_per_page' => -1
    ];

    $posts = get_posts($args);
    $data = [];

    foreach ($posts as $post) {
        setup_postdata($post);
        $data[] = fuse_format_post_data($post);
    }
    wp_reset_postdata();

    file_put_contents(FUSE_SEARCH_JSON_DIR . $filename, wp_json_encode($data));
}

function fuse_generate_json_for_pages($ids, $name) {
    if (empty($ids) || empty($name)) return;

    $args = [
        'post_type' => 'page',
        'post__in' => $ids,
        'orderby' => 'post__in',
        'posts_per_page' => -1
    ];

    $pages = get_posts($args);
    $data = [];

    foreach ($pages as $post) {
        setup_postdata($post);
        $data[] = fuse_format_post_data($post);
    }
    wp_reset_postdata();

    $filename = 'pag' . $name . '.json';
    file_put_contents(FUSE_SEARCH_JSON_DIR . $filename, wp_json_encode($data));
}

function fuse_generate_json_for_global() {
    delete_option('fuse_global_json_batch_index');
    fuse_delete_existing_global_json();
    fuse_generate_json_for_global_batch();
}

add_action('fuse_global_json_continue_batch', 'fuse_generate_json_for_global_batch');

function fuse_generate_json_for_global_batch() {
    $batch_index = get_option('fuse_global_json_batch_index', 1);
    $posts_per_file = 300;
    $files_per_batch = 10;
    $offset = ($batch_index - 1) * $files_per_batch * $posts_per_file;
    $total_to_fetch = $posts_per_file * $files_per_batch;

    $query = new WP_Query([
        'post_type'      => ['post', 'page'],
        'post_status'    => 'publish',
        'posts_per_page' => $total_to_fetch,
        'offset'         => $offset,
        'orderby'        => 'date',
        'order'          => 'DESC'
    ]);

    if (!$query->have_posts()) {
    delete_option('fuse_global_json_batch_index');
    error_log('[FuseSearch] Generación global completada.');
    return;
    }


    $posts = $query->posts;
    wp_reset_postdata();

    $chunks = array_chunk($posts, $posts_per_file);
    $file_number = ($batch_index - 1) * $files_per_batch + 1;

    foreach ($chunks as $chunk) {
        $data = [];
        foreach ($chunk as $post) {
            $data[] = fuse_format_post_data($post);
        }
        $filename = 'todo-' . $file_number . '.json';
        file_put_contents(FUSE_SEARCH_JSON_DIR . $filename, wp_json_encode($data));
        $file_number++;
    }

    update_option('fuse_global_json_batch_index', $batch_index + 1);
    wp_schedule_single_event(time() + 60, 'fuse_global_json_continue_batch');/*pausa de 1 minuto entre lotes de 10 json*/
}

function fuse_delete_existing_global_json() {
    $dir = FUSE_SEARCH_JSON_DIR;
    $files = glob($dir . 'todo-*.json');
    foreach ($files as $file) {
        unlink($file);
    }
}


function fuse_format_post_data($post) {
    return [
        'title' => get_the_title($post),
        'link' => get_permalink($post),
        'excerpt' => get_the_excerpt($post),
        'date' => get_the_date('Y-m-d', $post),
        'image' => get_the_post_thumbnail_url($post, 'medium')
    ];
}

add_action('wp_ajax_fuse_search_get_paginas', function () {
    if (!current_user_can('edit_pages')) {
        wp_send_json_error('No autorizado', 403);
    }

    $pages = get_pages([
        'post_status' => 'publish',
        'sort_column' => 'post_title',
        'sort_order' => 'asc'
    ]);

    $result = [];
    foreach ($pages as $page) {
        $result[] = [
            'ID' => $page->ID,
            'title' => $page->post_title
        ];
    }

    wp_send_json($result);
});

function fuse_verify_all_json() {
    $dir = FUSE_SEARCH_JSON_DIR;
    $files = glob($dir . '*.json');
    if (empty($files)) {
        echo '<p>No hay archivos JSON para verificar.</p>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Archivo</th><th>Esperados (DB)</th><th>En JSON</th><th>Diferencia</th></tr></thead><tbody>';

    $total_global_json = 0;
    $expected_global_count = 0;

    foreach ($files as $file) {
        $filename = basename($file);
        $prefix = substr($filename, 0, 3);
        $slug = str_replace([$prefix, '.json'], '', $filename);

        $expected = [];
        $json_count = 0;
        $json = json_decode(file_get_contents($file), true);
        if (is_array($json)) {
            $json_count = count($json);
        }

        if (strpos($filename, 'todo-') === 0) {
            $total_global_json += $json_count;
            continue;
        }

        if ($prefix === 'cat') {
            $term = get_term_by('slug', $slug, 'category');
            if ($term) {
                $expected = get_posts([
                    'post_type' => 'post',
                    'cat' => $term->term_id,
                    'posts_per_page' => -1
                ]);
            }
        }

        if ($prefix === 'eti') {
            $term = get_term_by('slug', $slug, 'post_tag');
            if ($term) {
                $expected = get_posts([
                    'post_type' => 'post',
                    'tag_id' => $term->term_id,
                    'posts_per_page' => -1
                ]);
            }
        }

        if ($prefix === 'pag') {
            $expected = get_pages(['post_status' => 'publish']);
        }

        if ($prefix === 'con') {
            $expected = get_posts([
                'post_type' => ['post', 'page'],
                'post_status' => 'publish',
                'numberposts' => -1
            ]);
        }

        $expected_count = is_array($expected) ? count($expected) : 0;
        $diff = $expected_count - $json_count;

        echo '<tr>';
        echo '<td>' . esc_html($filename) . '</td>';
        echo '<td>' . esc_html($expected_count) . '</td>';
        echo '<td>' . esc_html($json_count) . '</td>';
        echo '<td>' . esc_html($diff) . '</td>';
        echo '</tr>';
    }

    $expected_global_count = count(get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'numberposts' => -1
    ]));

    $diff_total = $expected_global_count - $total_global_json;

    echo '<tr style="background:#eef;">';
    echo '<td><strong>todo-*.json (combinados)</strong></td>';
    echo '<td><strong>' . esc_html($expected_global_count) . '</strong></td>';
    echo '<td><strong>' . esc_html($total_global_json) . '</strong></td>';
    echo '<td><strong>' . esc_html($diff_total) . '</strong></td>';
    echo '</tr>';

    echo '</tbody></table>';
}

