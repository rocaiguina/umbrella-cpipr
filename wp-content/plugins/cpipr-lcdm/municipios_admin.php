<?php

$GLOBALS['lcdm_lang'] = array(
    'en' => array(
        'Municipality'                      => 'Municipality',
        'Type of assistance'                => 'Type of assistance',
        'Category/program'                  => 'Category/program',
        'Total obligated/approved'          => 'Total obligated/approved',
        'Total disbursed'                   => 'Total disbursed',
        'Date of last payment'              => 'Date of last payment',
        'Individual Assistance'             => 'Individual Assistance',
        'Public Assistance'                 => 'Public Assistance',
        'Other Needs Assistance'            => 'Other Needs Assistance',
        'Housing Assistances'               => 'Housing Assistances',
        'Roads and Bridges'                 => 'Roads and Bridges',
        'Direct Administrative Cost'        => 'Direct Administrative Cost',
        'Buildings and Equipment'           => 'Buildings and Equipment',
        'Emergency Protective Measures'     => 'Emergency Protective Measures',
        'Parks Recreational Others'         => 'Parks Recreational Others',
        'Debris Removal'                    => 'Debris Removal',
        'Utilities'                         => 'Utilities'
    ),
    'es' => array(
        'Municipality'                      => 'Municipio',
        'Type of assistance'                => 'Tipo de asistencia',
        'Category/program'                  => 'Categoría/programa',
        'Total obligated/approved'          => 'Total obligado/aprobado',
        'Total disbursed'                   => 'Total desembolsado',
        'Date of last payment'              => 'Fecha del último pago',
        'Individual Assistance'             => 'Asistencia al individuo',
        'Public Assistance'                 => 'Asistencia pública',
        'Other Needs Assistance'            => 'Asistencia para otras necesidades',
        'Housing Assistances'               => 'Asistencia para vivienda',
        'Roads and Bridges'                 => 'Carreteras y puentes',
        'Direct Administrative Cost'        => 'Costos Administrativos Directos',
        'Buildings and Equipment'           => 'Edificios y equipo',
        'Emergency Protective Measures'     => 'Medidas de protección en emergencias',
        'Parks Recreational Others'         => 'Parques instalaciones recreativas y otros',
        'Debris Removal'                    => 'Recogido de escombros',
        'Utilities'                         => 'Servicio Básico'
    )
);

/* Add adming menu */
add_action('admin_menu', 'lcdm_municipios_setup_menu');

function lcdm_municipios_setup_menu () {
    add_menu_page('LCDM Municipios', 'LCDM Municipios', 'manage_options', 'lcdm-municipios', 'lcdm_all_municipios_admin_page');
    add_submenu_page('lcdm-municipios', 'All Municipios', 'All Municipios', 'manage_options', 'lcdm-municipios', 'lcdm_all_municipios_admin_page');
    add_submenu_page('lcdm-municipios', 'Import Municipios', 'Import Municipios', 'manage_options', 'lcdm-import-municipios', 'lcdm_import_municipios_admin_page');
}

function lcdm_all_municipios_admin_page () {
    global $wpdb;

    $page_title = '<h1 class="wp-heading-inline">Municipios</h1>';
    $page_title_action = '<a href="#" class="page-title-action">Import Data</a>';

    $table = '
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
            <tr>
                <th>Municipio</th>
                <th>Tipo de asistencia</th>
                <th>Desastre</th>
                <th>Categoria/programa</th>
                <th>Descripción del programa</th>
                <th>Total obligado/aprobado</th>
                <th>Fecha de obligación</th>
                <th>Total desembolsado</th>
                <th>Total pareo de fondos</th>
                <th>Fecha de último pago</th>
                <th>Fecha de actualización</th>
            </tr>
        </thead>
        <tbody>';

    $table_name = $wpdb->prefix . 'municipios';
    $data = $wpdb->get_results( 'SELECT * FROM ' . $table_name . ' ORDER BY municipio ASC' );
    foreach ($data as $value) {
        $row = '
            <tr>
                <td>' . $value->municipio . '</td>
                <td>' . $value->tipo_asistencia . '</td>
                <td>' . $value->desastre . '</td>
                <td>' . $value->categoria . '</td>
                <td>' . $value->descripcion_categoria . '</td>
                <td>' . $value->total_obligado . '</td>
                <td>' . $value->fecha_obligacion . '</td>
                <td>' . $value->total_desembolsado . '</td>
                <td>' . $value->total_pareo_fondos . '</td>
                <td>' . $value->fecha_ultimo_pago . '</td>
                <td>' . $value->fecha_actualizacion . '</td>
            </tr>
        ';
        $table .= $row;
    }

    $table .= '</tbody></table>';

    echo '<div class="wrap">' . $page_title . $page_title_action  . '<hr class="wp-header-end"/>' . $table . '</div>';
}

function lcdm_import_municipios_admin_page () {
    $message = lcdm_handle_import_municipios_post();

    $page_title = '<h2>Import Municipios</h2>';
    $import_form = '
        <p>Choose a CSV (.csv) file to upload, then click Upload file and import.</p>
        <form class="wp-upload-form" enctype="multipart/form-data" method="post">
            <p>
                <label for="upload">Choose a file from your computer: (Maximum size: 1GB)</label>
                <input id="upload" type="file" name="import_file" size="25"/>
            </p>
            <p class="submit">
                <input id="submit" class="button button-primary" type="submit" value="Upload file and import"/>
            </p>
        </form>
    ';

    echo '<div class="wrap">' . $page_title . $message . $import_form . '</div>';
}

function lcdm_handle_import_municipios_post () {
    global $wpdb;
     // First check if the file appears on the _FILES array
    if(isset($_FILES['import_file'])){
        $csv_path = $_FILES['import_file']['tmp_name'];

        $table_name = $wpdb->prefix . 'municipios';

        $truncated = $wpdb->query('TRUNCATE TABLE ' . $table_name);

        if ($truncated) {
            $imported = $wpdb->query('LOAD DATA LOCAL INFILE "' . $csv_path . '" INTO TABLE ' . $table_name . ' CHARACTER SET UTF8 FIELDS TERMINATED BY \',\' LINES TERMINATED BY \'\n\' IGNORE 1 LINES (municipio, tipo_asistencia, desastre, categoria, descripcion_categoria, total_obligado, fecha_obligacion, total_desembolsado, total_pareo_fondos, fecha_ultimo_pago, fecha_actualizacion)');
            if ($imported === 0) {
                $last_updated = get_option('lcdm_municipios_updated_at');

                if ($last_updated) {
                    update_option('lcdm_municipios_updated_at', date('Y-m-d'));
                } else {
                    add_option('lcdm_municipios_updated_at', date('Y-m-d'));
                }
                
                return '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
                        <p><strong>Successful data imported.</strong></p>
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>
                ';
            } else {
                return '
                    <div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
                        <p><strong>Error on import data.</strong></p>
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>
                ';
            }
        } else {
            return '
                <div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
                    <p><strong>Error on import data (truncate).</strong></p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            ';
        }
    }

    return '';
}

