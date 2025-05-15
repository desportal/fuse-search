<?php
if (!defined('ABSPATH')) exit;

function fuse_search_admin_ui() {
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'categorias';

    $tabs = [
        'categorias' => '🗂️ Categorías',
        'etiquetas'  => '🏷️ Etiquetas',
        'paginas'    => '📄 Páginas',
        'global'     => '🌐 Global',
        'shortcodes' => '⚙️ Shortcodes',
        'verificacion' => '✅ Verificación'
    ];

    echo '<div class="wrap"><h1>Fuse Search</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($tabs as $key => $label) {
        $active = ($tab === $key) ? ' nav-tab-active' : '';
        echo '<a href="?page=fuse-search&tab=' . $key . '" class="nav-tab' . $active . '">' . esc_html($label) . '</a>';
    }
    echo '</h2>';

    switch ($tab) {
        case 'categorias': fuse_search_tab_categorias(); break;
        case 'etiquetas':  fuse_search_tab_etiquetas(); break;
        case 'paginas':    fuse_search_tab_paginas(); break;
        case 'global':     fuse_search_tab_global(); break;
        case 'verificacion': fuse_search_tab_verificacion(); break;
        default:           fuse_search_tab_shortcodes(); break;
    }

    echo '</div>';
}

function fuse_search_tab_verificacion() {
    if (!current_user_can('manage_options')) {
        echo '<p>No tienes permiso para acceder a esta sección.</p>';
        return;
    }

    echo '<h3>Verificación de Archivos JSON</h3>';
    echo '<p>Comparación entre los archivos generados y el contenido real en la base de datos.</p>';
    fuse_verify_all_json();
}

function fuse_search_tab_categorias() {
    $categories = get_categories(['hide_empty' => false]);

    echo '<h3>Generar JSON por Categoría</h3>';
    echo '<form method="post">';
    echo '<select name="fuse_categoria">';
    foreach ($categories as $cat) {
        echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
    }
    echo '</select>';
    submit_button('Crear JSON', 'primary', 'crear_json_categoria');

    echo '<hr><h3>Archivos JSON de Categorías</h3>';
    fuse_search_list_json_files('cat');
}

function fuse_search_tab_etiquetas() {
    $tags = get_tags(['hide_empty' => false]);

    echo '<h3>Generar JSON por Etiqueta</h3>';
    echo '<form method="post">';
    echo '<select name="fuse_etiqueta">';
    foreach ($tags as $tag) {
        echo '<option value="' . esc_attr($tag->term_id) . '">' . esc_html($tag->name) . '</option>';
    }
    echo '</select>';
    submit_button('Crear JSON', 'primary', 'crear_json_etiqueta');

    echo '<hr><h3>Archivos JSON de Etiquetas</h3>';
    fuse_search_list_json_files('etiq');
}

function fuse_search_tab_paginas() {
    echo '<h3>Generar JSON a partir de un archivo de títulos de páginas</h3>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<label for="fuse_paginas_json_name">Nombre del archivo JSON (sin espacios):</label><br>';
    echo '<input type="text" name="fuse_paginas_json_name" required pattern="[a-zA-Z0-9_-]+" style="width: 300px; margin-bottom: 10px;"><br><br>';

    echo '<label for="txt_paginas">Cargar archivo TXT de títulos:</label><br>';
    echo '<input type="file" id="txt_paginas" accept=".txt" style="margin-bottom:10px;"><br>';
    echo '<button type="button" id="cargar_txt_paginas" class="button">Cargar páginas desde archivo</button><br><br>';

    echo '<div id="preview_paginas" style="margin: 20px 0; display: flex; flex-wrap: wrap; gap: 10px;"></div>';
    echo '<div id="contador_paginas" style="margin-bottom: 20px; font-weight: bold;"></div>';

    echo '<input type="hidden" name="fuse_paginas_ids" id="fuse_paginas_ids">';
    submit_button('Crear JSON', 'primary', 'crear_json_paginas');

    echo '<hr><h3>Archivos JSON de Páginas</h3>';
    fuse_search_list_json_files('pag');

    echo <<<JS
<script>
document.addEventListener("DOMContentLoaded", function () {
    const cargarBtn = document.getElementById("cargar_txt_paginas");
    const fileInput = document.getElementById("txt_paginas");
    const preview = document.getElementById("preview_paginas");
    const contador = document.getElementById("contador_paginas");
    const hiddenInput = document.getElementById("fuse_paginas_ids");

    cargarBtn.addEventListener("click", function () {
        const file = fileInput.files[0];
        if (!file) return alert("Por favor selecciona un archivo .txt primero.");

        const reader = new FileReader();
        reader.onload = async function (e) {
            const content = e.target.result;
            const lineas = content.split(/\\r?\\n/).map(line => line.trim()).filter(line => line.length > 0);

            preview.innerHTML = '';
            contador.innerHTML = '';
            hiddenInput.value = '';

            const response = await fetch(ajaxurl + "?action=fuse_search_get_paginas");
            const paginas = await response.json();

            let encontrados = 0;
            let noEncontrados = 0;
            const idsEncontrados = [];

            lineas.forEach(linea => {
                const cleanLinea = linea.replace(/\\s+/g, ' ').toLowerCase();

                let match = null;
                paginas.forEach(pagina => {
                    const cleanTitulo = pagina.title.replace(/\\s+/g, ' ').toLowerCase();
                    if (cleanLinea === cleanTitulo) {
                        match = pagina;
                    }
                });

                const div = document.createElement('div');
                div.textContent = linea;
                div.style.padding = "6px 12px";
                div.style.border = "1px solid #0073aa";
                div.style.borderRadius = "20px";
                div.style.background = "#f1f8ff";
                div.style.fontSize = "14px";
                div.style.color = "#0073aa";

                if (match) {
                    encontrados++;
                    idsEncontrados.push(match.ID);
                } else {
                    noEncontrados++;
                    div.style.borderColor = "#cc0000";
                    div.style.color = "#cc0000";
                    div.style.background = "#ffecec";
                }

                preview.appendChild(div);
            });

            contador.innerHTML = `${encontrados} encontrados, ${noEncontrados} no encontrados.`;
            hiddenInput.value = idsEncontrados.join(",");
        };

        reader.readAsText(file);
    });
});
</script>
JS;
}

function fuse_search_tab_shortcodes() {
    $files = glob(FUSE_SEARCH_JSON_DIR . '*.json');
    if (empty($files)) {
        echo '<p>No hay archivos JSON generados aún.</p>';
        return;
    }

    $printed_todo = false;

    echo '<h3>Shortcodes Disponibles</h3>';

    foreach ($files as $file) {
        $name = basename($file, '.json');

        if (strpos($name, 'cat') === 0) {
            $slug = str_replace('cat', '', $name);
            echo '<p><code>[fusejs_search cat="' . esc_html($slug) . '"]</code></p>';
        } elseif (strpos($name, 'etiq') === 0) {
            $slug = str_replace('etiq', '', $name);
            echo '<p><code>[fusejs_search etiq="' . esc_html($slug) . '"]</code></p>';
        } elseif (strpos($name, 'pag') === 0) {
            $slug = str_replace('pag', '', $name);
            echo '<p><code>[fusejs_search pag="' . esc_html($slug) . '"]</code></p>';
        } elseif (strpos($name, 'todo-') === 0 && !$printed_todo) {
            echo '<p><code>[fusejs_search json="todo"]</code></p>';
            $printed_todo = true;
        }
    }

    echo '<hr><h3>Filtros Combinados</h3>';
    echo '<p>Ejemplo:</p>';
    echo '<code>[fusejs_search cats="noticias,ventas" etiqs="recursos" pags="institucional,servicios"]</code>';

    echo '<hr><h3>Filtros Dinámicos Disponibles</h3>';
    echo '<form>';
    foreach ($files as $file) {
        $name = basename($file, '.json');
        if (strpos($name, 'cat') === 0 || strpos($name, 'etiq') === 0 || strpos($name, 'pag') === 0) {
            $label = ucfirst(str_replace(['cat', 'etiq', 'pag', '.json'], '', $name));
            echo '<label><input type="checkbox" disabled> ' . esc_html($label) . '</label><br>';
        }
    }
    echo '</form>';
}

function fuse_search_list_json_files($prefix) {
    $dir = FUSE_SEARCH_JSON_DIR;
    $files = glob($dir . $prefix . '*.json');

    if (empty($files)) {
        echo '<p>No hay archivos generados aún.</p>';
        return;
    }

    echo '<ul>';
    foreach ($files as $file) {
        $filename = basename($file);
        $url = FUSE_SEARCH_JSON_URL . $filename;
        echo '<li><a href="' . esc_url($url) . '" target="_blank">' . esc_html($filename) . '</a> ';
        echo '<form method="post" style="display:inline;"><input type="hidden" name="delete_file" value="' . esc_attr($filename) . '">';
        submit_button('Eliminar', 'delete', 'delete_json', false);
        echo '</form></li>';
    }
    echo '</ul>';
}

function fuse_search_tab_global() {
    echo '<h3>Generar JSON con TODO el sitio (entradas + páginas)</h3>';
    echo '<form method="post">';
    echo '<p>Este proceso incluirá todas las entradas y páginas publicadas. Si hay demasiados elementos, se dividirán automáticamente en varios archivos (<code>todo-1.json</code>, <code>todo-2.json</code>, etc.).</p>';
    submit_button('Crear JSON Global', 'primary', 'crear_json_global');
    echo '</form>';

    if (!get_option('fuse_global_json_batch_index')) {
        echo '<p style="color:green; font-weight:bold; margin-top:15px;">✅ Generación completada: todos los archivos JSON globales han sido generados correctamente.</p>';
    }

    echo '<hr><h3>Archivos JSON Globales</h3>';
    fuse_search_list_json_files('todo');
}

