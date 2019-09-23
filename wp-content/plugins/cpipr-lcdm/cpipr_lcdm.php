<?php
/*
 * Plugin Name: CPI
 * Description: Plugin that will create cpipr lcdm post types (video, infographics).
 * Version: 1.0
 * Author: Jorge Moreira 
*/

require_once dirname( __FILE__ ) . '/municipios_admin.php';

/* Install municipios table */
function lcdm_install_municipios_db_table () {
    global $wpdb;

    $table_name = $wpdb->prefix . 'municipios';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      municipio varchar(60) NOT NULL,
      tipo_asistencia varchar(60) NULL,
      desastre varchar(80) NULL,
      categoria varchar(80) NULL,
      descripcion_categoria varchar(80) NULL,
      total_obligado varchar(15) NULL,
      fecha_obligacion date NULL,
      total_desembolsado varchar(15) NULL,
      total_pareo_fondos varchar(15) NULL,
      fecha_ultimo_pago date NULL,
      fecha_actualizacion date NULL,
      lang varchar(8) NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'lcdm_install_municipios_db_table' );


/* Add filter for historias posts */

add_filter('img_caption_shortcode', 'lcdm_caption_shortcode', 20, 3);

function lcdm_caption_shortcode ($text, $atts, $content ) {
    $atts = shortcode_atts( array(
        'id' => '',
        'align' => 'alignnone',
        'width' => '',
        'credit' => '',
        'caption' => '',
    ), $atts );
    $atts = apply_filters( 'navis_image_layout_defaults', $atts );
    extract( $atts );

    if ( $id && ! $credit ) {
        $post_id = str_replace( 'attachment_', '', $id );
        $creditor = navis_get_media_credit( $post_id );
        $credit = ! empty( $creditor ) ? $creditor->to_string() : '';
    }

    if ( $id ) {
        $id = 'id="' . esc_attr($id) . '" ';
    }

    $out = sprintf( '<div %s class="wp-caption module image %s" style="max-width: %spx;"><div class="wp-caption-img-wrap">%s</div>', $id, $align, $width, do_shortcode( $content ) );
    if ( $credit ) {
        $out .= sprintf( '<p class="wp-media-credit">%s</p>', $credit );
    }
    if ( $caption ) {
        $out .= sprintf( '<p class="wp-caption-text">%s</p>', $caption );
    }
    $out .= "</div>";

    if (has_term('los-chavos-de-maria', 'series')) {
        $out = '</div>' . $out . '<div class="container">';
    }    

    return $out;
}

/* Add filter for gallery inside historias posts */
add_filter('post_gallery', 'lcdm_post_gallery', 20, 2);

function lcdm_post_gallery ($output, $attr) {
    if(is_page_template('lcdm-single.php')) {
        return '</div>' . $output . '<div class="container">';
    }
    return $output;
}

/**
 * Filter for adding embedded objects into lcdm posts
 */
function filter_lcdm_embeds( $content ) {
    if(is_page_template('lcdm-single.php')) {
        $content = preg_replace( "/<object/Si", '</div><div class="embed-container"><object', $content );
        $content = preg_replace( "/<\/object>/Si", '</object></div><div class="container">', $content );
        
        /**
         * Added iframe filtering, iframes are bad.
         */
        $content = preg_replace( "/<iframe.+?src=\"(.+?)\"/Si", '</div><div class="embed-container"><iframe src="\1" frameborder="0" allowfullscreen>', $content );
        $content = preg_replace( "/<\/iframe>/Si", '</iframe></div><div class="container">', $content );
        return $content;
    }
    return $content;
}
add_filter( 'the_content', 'filter_lcdm_embeds' );


//------------------------------------- Add Ajax Actions ---------------------------------------
// Ensures you can call the functions via AJAX when you want to use them.
// The .nopriv. part indicates that I want to make this function available for non logged-in users
// --------------------------------------------------------------------------------------------


/* Ajax action to get contracts from a puerto rico city */
add_action('wp_ajax_nopriv_pr_cities_contracts', 'ajax_pr_cities_contracts');
add_action('wp_ajax_pr_cities_contracts', 'ajax_pr_cities_contracts');

function ajax_pr_cities_contracts () {
    global $wpdb;

    $municipio = isset($_GET['city']) ? $_GET['city'] : '-1';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'es';

    $table_name = $wpdb->prefix . 'municipios';
    $query = $wpdb->prepare('SELECT * FROM ' . $table_name . ' WHERE municipio = %s', $municipio);
    $data = $wpdb->get_results($query);

    $translate = empty($GLOBALS['lcdm_lang'][$lang]) ? $GLOBALS['lcdm_lang']['es'] : $GLOBALS['lcdm_lang'][$lang];

    $response = array();

    foreach ( $data as $item ) {
        $row = array(
            'tipo_asistencia' => $item->tipo_asistencia,
            'categoria' => empty($translate[$item->categoria]) ? $item->categoria : $translate[$item->categoria],
            'total_obligado' => $item->total_obligado,
            'total_desembolsado' => $item->total_desembolsado,
            'fecha_ultimo_pago' => $item->fecha_ultimo_pago
        );
        array_push($response, $row);
    }

    wp_send_json( array('data' => $response) );
}

add_action('admin_post_nopriv_export_all_contracts', 'export_all_contracts');
add_action('admin_post_export_all_contracts', 'export_all_contracts');

function export_all_contracts () {
    global $wpdb;

    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'es';

    $table_name = $wpdb->prefix . 'municipios';
    $data = $wpdb->get_results('SELECT municipio, tipo_asistencia, categoria, total_obligado, total_desembolsado, fecha_ultimo_pago FROM ' . $table_name );

    $filename = 'CPI-recuperacion.csv';

    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
    header( 'Content-Description: File Transfer' );
    header( 'Content-type: text/csv; charset=utf-8' );
    header( "Content-Disposition: attachment; filename={$filename}" );
    header( 'Expires: 0' );
    header( 'Pragma: public' );
    
    $output_csv = @fopen( 'php://output', 'w' );

    $translate = empty($GLOBALS['lcdm_lang'][$lang]) ? $GLOBALS['lcdm_lang']['es'] : $GLOBALS['lcdm_lang'][$lang];

    $header = array(
        $translate['Municipality'],
        $translate['Type of assistance'],
        $translate['Category/program'],
        $translate['Total obligated/approved'],
        $translate['Total disbursed'],
        $translate['Date of last payment']
    );
    
    fputcsv( $output_csv, $header );

    // Put data
    foreach ( $data as $item ) {
        $row = array(
            $item->municipio,
            empty($translate[$item->tipo_asistencia]) ? $item->tipo_asistencia : $translate[$item->tipo_asistencia],
            empty($translate[$item->categoria]) ? $item->categoria : $translate[$item->categoria],
            $item->total_obligado,
            $item->total_desembolsado,
            $item->fecha_ultimo_pago
        );
        fputcsv( $output_csv, $row );
    }

    fclose($output_csv);
    die();
}

add_action('admin_post_nopriv_export_contracts_municipality', 'export_contracts_municipality');
add_action('admin_post_export_contracts_municipality', 'export_contracts_municipality');

function export_contracts_municipality () {
    global $wpdb;

    $municipio = isset($_GET['city']) ? $_GET['city'] : '-1';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'es';

    $table_name = $wpdb->prefix . 'municipios';
    $query = $wpdb->prepare('SELECT tipo_asistencia, categoria, total_obligado, total_desembolsado, fecha_ultimo_pago FROM ' . $table_name . ' WHERE municipio = %s', $municipio);
    $data = $wpdb->get_results($query);

    $filename = 'CPI-' . sanitize_title($municipio) . '.csv';

    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
    header( 'Content-Description: File Transfer' );
    header( 'Content-type: text/csv; charset=utf-8' );
    header( "Content-Disposition: attachment; filename={$filename}" );
    header( 'Expires: 0' );
    header( 'Pragma: public' );
    
    $output_csv = @fopen( 'php://output', 'w' );

    $translate = empty($GLOBALS['lcdm_lang'][$lang]) ? $GLOBALS['lcdm_lang']['es'] : $GLOBALS['lcdm_lang'][$lang];

    $header = array(
        $translate['Municipality'],
        $translate['Type of assistance'],
        $translate['Category/program'],
        $translate['Total obligated/approved'],
        $translate['Total disbursed'],
        $translate['Date of last payment']
    );
    
    fputcsv( $output_csv, $header );

    // Put data
    foreach ( $data as $item ) {
        $row = array(
            empty($translate[$item->tipo_asistencia]) ? $item->tipo_asistencia : $translate[$item->tipo_asistencia],
            empty($translate[$item->categoria]) ? $item->categoria : $translate[$item->categoria],
            $item->total_obligado,
            $item->total_desembolsado,
            $item->fecha_ultimo_pago
        );
        fputcsv( $output_csv, $row );
    }

    fclose($output_csv);
    die();
}

add_action('wp_ajax_nopriv_lcdm_historias', 'ajax_lcdm_historias');
add_action('wp_ajax_lcdm_historias', 'ajax_lcdm_historias');

function ajax_lcdm_historias () {
    $page = isset($_GET['page']) ? $_GET['page'] : '1';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'spanish';
    $q = isset($_GET['q']) ? $_GET['q'] : '';

    $query_terms = explode(',', $q);

    $conditions = array(
        'post_type' => 'post',
        'tax_query' => array(
            array(
                'taxonomy' => 'series',
                'field'    => 'slug',
                'terms'    => 'los-chavos-de-maria'
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => array('news', 'graphic', 'powerplayer'),
                'operator' => 'IN'
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => get_lcdm_language($lang),
            )
        ),
        's' => implode(' ', $query_terms),
        'order' => 'DESC',
        'posts_per_page' => 9,
        'paged' => $page,
        'post_status' => 'publish'
    );

    query_posts($conditions);

    if (have_posts()) {
        $row_count = 1;
        echo '<div class="row-fluid">';
        while(have_posts()) {
            the_post();
            get_template_part('partials/card-post');
            if ($row_count % 3 == 0) echo '</div><div class="row-fluid">';
            $row_count++;
        }
        echo "</div>";
    } else {
        echo "";
    }
    wp_reset_query();
    wp_die();
}

/*
 * Get full name of language
 */
function get_lcdm_language($lang) {
    $languages = array('es' => 'spanish', 'en' => 'english');
    return $languages[$lang];
}

add_action('wp_ajax_nopriv_lcdm_infographics', 'ajax_lcdm_infographics');
add_action('wp_ajax_lcdm_infographics', 'ajax_lcdm_infographics');

function ajax_lcdm_infographics () {
    $page = isset($_GET['page']) ? $_GET['page'] : '1';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'es';

    $conditions = array(
        'post_type' => 'post',
        'tax_query' => array(
            array(
                'taxonomy' => 'series',
                'field'    => 'slug',
                'terms'    => 'los-chavos-de-maria'
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => 'graphic',
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => get_lcdm_language($lang),
            )
        ),
        'order' => 'DESC',
        'posts_per_page' => 9,
        'paged' => $page,
        'post_status' => 'publish'
    );

    query_posts($conditions);

    if (have_posts()) {
        $row_count = 1;
        echo '<div class="row-fluid">';
        while(have_posts()) {
            the_post();
            get_template_part('partials/card-post');
            if ($row_count % 3 == 0) echo '</div><div class="row-fluid">';
            $row_count++;
        }
        echo "</div>";
    } else {
        echo "";
    }
    wp_reset_query();
    wp_die();
}

add_action('wp_ajax_nopriv_lcdm_power_players', 'ajax_lcdm_power_players');
add_action('wp_ajax_lcdm_power_players', 'ajax_lcdm_power_players');

function ajax_lcdm_power_players () {
    $page = isset($_GET['page']) ? $_GET['page'] : '1';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'spanish';

    $conditions = array(
        'post_type' => 'post',
        'tax_query' => array(
            array(
                'taxonomy' => 'series',
                'field'    => 'slug',
                'terms'    => 'los-chavos-de-maria'
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => 'powerplayer',
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => get_lcdm_language($lang),
            )
        ),
        'order' => 'DESC',
        'posts_per_page' => 9,
        'paged' => $page,
        'post_status' => 'publish'
    );

    query_posts($conditions);

    if (have_posts()) {
        $row_count = 1;
        echo '<div class="row-fluid">';
        while(have_posts()) {
            the_post();
            get_template_part('partials/card-post');
            if ($row_count % 3 == 0) echo '</div><div class="row-fluid">';
            $row_count++;
        }
        echo "</div>";
    } else {
        echo "";
    }
    wp_reset_query();
    wp_die();
}

add_action('wp_ajax_nopriv_lcdm_videos', 'ajax_lcdm_videos');
add_action('wp_ajax_lcdm_videos', 'ajax_lcdm_videos');

function ajax_lcdm_videos () {
    $page = isset($_GET['page']) ? $_GET['page'] : '1';
    $lang = isset($_GET['lang']) ? $_GET['lang'] : 'spanish';

    $conditions = array(
        'post_type' => 'post',
        'tax_query' => array(
            array(
                'taxonomy' => 'series',
                'field'    => 'slug',
                'terms'    => 'los-chavos-de-maria'
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => 'video',
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'slug',
                'terms'    => get_lcdm_language($lang),
            )
        ),
        'order' => 'DESC',
        'posts_per_page' => 9,
        'paged' => $page,
        'post_status' => 'publish'
    );

    query_posts($conditions);

    if (have_posts()) {
        $row_count = 1;
        echo '<div class="row-fluid">';
        while(have_posts()) {
            the_post();
            get_template_part('partials/card-video-post');
            if ($row_count % 3 == 0) echo '</div><div class="row-fluid">';
            $row_count++;
        }
        echo "</div>";
    } else {
        echo "";
    }
    wp_reset_query();
    wp_die();
}


/* Enqueue scripts for lcdm landing pages */
add_action('wp_enqueue_scripts', 'cpipr_lcdm_wp_enqueue_scripts');
function cpipr_lcdm_wp_enqueue_scripts () {
    $lcdm_pages = array(
        'en-los-chavos-de-maria',
        'en-lcdm-glosario',
        'en-lcdm-mapas-de-la-recuperacion',
        'en-lcdm-videos',
        'lcdm-glosario',
        'lcdm-mapas-de-la-recuperacion',
        'lcdm-videos'
    );

    if (is_page($lcdm_pages) || is_tax('series', 'los-chavos-de-maria') ) {
        wp_enqueue_style(
            'fancyBox',
            get_stylesheet_directory_uri() . '/lib/fancybox/dist/jquery.fancybox.css'
        );
        wp_enqueue_script(
            'fancyBox',
            get_stylesheet_directory_uri() . '/lib/fancybox/dist/jquery.fancybox.min.js',
            array('jquery'), '', false
        );

        wp_enqueue_style(
            'niceSelect',
            get_stylesheet_directory_uri() . '/lib/jquery-nice-select/css/nice-select.css'
        );
        wp_enqueue_script(
            'niceSelect',
            get_stylesheet_directory_uri() . '/lib/jquery-nice-select/js/jquery.nice-select.min.js',
            array('jquery'), '', false
        );

        wp_enqueue_script(
            'scrollTo',
            get_stylesheet_directory_uri() . '/lib/scrollTo/jquery.scrollTo.min.js',
            array('jquery'), '', false
        );

        wp_enqueue_style(
            'jsMapsCore',
            get_stylesheet_directory_uri() . '/lib/jsmaps/css/jsmaps.css'
        );
        wp_enqueue_script(
            'jsMapsCore',
            get_stylesheet_directory_uri() . '/lib/jsmaps/js/jsmaps-libs.js',
            array('jquery'), '', false
        );
        wp_enqueue_script(
            'jsMapsPanZoom',
            get_stylesheet_directory_uri() . '/lib/jsmaps/js/jsmaps-panzoom.js',
            array('jquery'), '', false
        );
        wp_enqueue_script(
            'jsMaps',
            get_stylesheet_directory_uri() . '/lib/jsmaps/js/jsmaps.min.js',
            array('jquery'), '', false
        );
        wp_enqueue_script(
            'jsMapsPuertoRico',
            get_stylesheet_directory_uri() . '/lib/jsmaps/maps/puertoRico.js',
            array('jquery'), '', false
        );

        wp_enqueue_script(
            'numeral',
            get_stylesheet_directory_uri() . '/lib/numeral/numeral.min.js',
            array('jquery'), '', false
        );
    }
}


add_filter('lcdm_language', 'lcdm_language_custom');
function lcdm_language_custom ($lang) {
    $lcdm_pages = array(
        'en-lcdm-documentos',
        'en-lcdm-glosario',
        'en-lcdm-graficas',
        'en-lcdm-historias',
        'en-lcdm-mapas-de-la-recuperacion',
        'en-lcdm-personajes-de-la-recuperacion',
        'en-lcdm-videos',
        'en-los-chavos-de-maria' 
    );

    if (is_page($lcdm_pages)) {
        return 'en';
    }
    return $lang;
}

/*
 * Add glosario javascripts 
 */
add_action('wp_footer', 'lcdm_glosario_js');
function lcdm_glosario_js () {
    $glosario_pages = array(
        'en-lcdm-glosario',
        'lcdm-glosario'
    );
    if (is_page($glosario_pages)) {
        wp_enqueue_script(
            'lcdm-glosario',
            get_stylesheet_directory_uri() . '/lib/lcdm/glosario.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Add graficas javascripts 
 */
add_action('wp_footer', 'lcdm_graficas_js');
function lcdm_graficas_js () {
    $graficas_pages = array(
        'en-lcdm-graficas',
        'lcdm-graficas'
    );
    if (is_page($graficas_pages)) {
        wp_enqueue_script(
            'lcdm-graficas',
            get_stylesheet_directory_uri() . '/lib/lcdm/graficas.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Add historias javascripts 
 */
add_action('wp_footer', 'lcdm_historias_js');
function lcdm_historias_js () {
    $historias_pages = array(
        'en-lcdm-historias',
        'lcdm-historias'
    );
    if (is_page($historias_pages)) {
        wp_enqueue_script(
            'lcdm-historias',
            get_stylesheet_directory_uri() . '/lib/lcdm/historias.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Add mapas_de_la_recuperacion javascripts 
 */
add_action('wp_footer', 'lcdm_mapas_de_la_recuperacion_js');
function lcdm_mapas_de_la_recuperacion_js () {
    global $post;
    $mapas_de_la_recuperacion_pages = array(
        'en-lcdm-mapas-de-la-recuperacion',
        'lcdm-mapas-de-la-recuperacion',
    );
    if (is_page($mapas_de_la_recuperacion_pages)) {
        wp_enqueue_script(
            'lcdm-mapas-de-la-recuperacion',
            get_stylesheet_directory_uri() . '/lib/lcdm/mapas_de_la_recuperacion.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Add personajes_de_la_recuperacion javascripts 
 */
add_action('wp_footer', 'lcdm_personajes_de_la_recuperacion_js');
function lcdm_personajes_de_la_recuperacion_js () {
    $personajes_de_la_recuperacion_pages = array(
        'en-lcdm-personajes-de-la-recuperacion',
        'lcdm-personajes-de-la-recuperacion'
    );
    if (is_page($personajes_de_la_recuperacion_pages)) {
        wp_enqueue_script(
            'lcdm-mapas-de-la-recuperacion',
            get_stylesheet_directory_uri() . '/lib/lcdm/personajes_de_la_recuperacion.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Add videos javascripts 
 */
add_action('wp_footer', 'lcdm_videos_js');
function lcdm_videos_js () {
    $videos_pages = array(
        'en-lcdm-videos',
        'lcdm-videos'
    );
    if (is_page($videos_pages)) {
        wp_enqueue_script(
            'lcdm-videos',
            get_stylesheet_directory_uri() . '/lib/lcdm/videos.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Add landing javascripts 
 */
add_action('wp_footer', 'lcdm_home_js');
function lcdm_home_js () {
    $landing_pages = array(
        'en-los-chavos-de-maria'
    );
    if (is_page($landing_pages) || is_tax('series', 'los-chavos-de-maria')) {
        wp_enqueue_script(
            'lcdm-home',
            get_stylesheet_directory_uri() . '/lib/lcdm/home.js',
            array('jquery'),
            false,
            true
        );
        wp_enqueue_script(
            'lcdm-mapas-de-la-recuperacion',
            get_stylesheet_directory_uri() . '/lib/lcdm/mapas_de_la_recuperacion.js',
            array('jquery'),
            false,
            true
        );
    }
}

/*
 * Redirect landing page /los-chavos-de-maria to /series/los-chavos-de-maria
 */

add_action('template_redirect', 'lcdm_landing_redirect');
function lcdm_landing_redirect () {
    if (is_page('los-chavos-de-maria')) {
        wp_redirect( get_term_link( 'los-chavos-de-maria', 'series' ) );
        die;
    }
    if (is_category('los-chavos-de-maria')) {
        wp_redirect( get_term_link( 'los-chavos-de-maria', 'series' ) );
        die;
    }
}

?>