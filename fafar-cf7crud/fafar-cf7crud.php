<?php
/*
Plugin name: FAFAR Contact Form 7 CRUD
Plugin URI: hhttps://github.com/giovanecf
Description: Save and manage Contact Form 7 messages. Never lose important data. FAFAR Contact Form 7 CRUD plugin is an add-on for the Contact Form 7 plugin.
Author: Geovani Figueiredo
Author URI: https://github.com/giovanecf
Text Domain: fafar-cf7crud
Domain Path: /languages/
Version: 1.2.7
*/

/**  This protect the plugin file from direct access */
if ( ! defined( 'WPINC' ) ) die;

if ( ! defined( 'ABSPATH' ) ) exit;


add_action('wp_enqueue_scripts', 'callback_for_setting_up_scripts');


register_activation_hook( __FILE__, 'fafar_cf7crud_on_activate' );


add_action( 'upgrader_process_complete', 'fafar_cf7crud_upgrade_function', 10, 2 );


register_deactivation_hook( __FILE__, 'fafar_cf7crud_on_deactivate' );


add_filter('wpcf7_form_elements', 'fafar_cf7crud_adding_hidden_fields');


add_filter('wpcf7_form_tag', 'fafar_cf7crud_populate_form_field');


add_action( 'wpcf7_before_send_mail', 'fafar_cf7crud_before_send_mail_handler' );


function callback_for_setting_up_scripts() {

    wp_register_style('fafar-cf7crud', plugins_url( 'css/main.css', __FILE__ ) );

    wp_enqueue_style( 'fafar-cf7crud' );

    wp_register_script( 'fafar-cf7crud', plugins_url( 'js/main.js', __FILE__ ) );

    wp_enqueue_script( 'fafar-cf7crud' );

}


function fafar_cf7crud_create_table(){

    global $wpdb;
    $cfdb       = apply_filters( 'fafar_cf7crud_database', $wpdb );
    $table_name = $cfdb->prefix . 'fafar_cf7crud_submissions';

    if( $cfdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

        $charset_collate = $cfdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            submission_id CHAR(32) NOT NULL,
            form_id bigint(20) NOT NULL,
            submission_data longtext NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (submission_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    $upload_dir    = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir['basedir'].'/fafar_cf7crud_uploads';
    if ( ! file_exists( $fafar_cf7crud_dirname ) ) {
        wp_mkdir_p( $fafar_cf7crud_dirname );
        $fp = fopen( $fafar_cf7crud_dirname.'/index.php', 'w');
        fwrite($fp, "<?php \n\t // Silence is golden.");
        fclose( $fp );
    }
    add_option( 'fafar_cf7crud_view_install_date', date('Y-m-d G:i:s'), '', 'yes');

}


function fafar_cf7crud_on_activate( $network_wide ){

    global $wpdb;
    if ( is_multisite() && $network_wide ) {
        // Get all blogs in the network and activate plugin on each one
        $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            fafar_cf7crud_create_table();
            restore_current_blog();
        }
    } else {
        fafar_cf7crud_create_table();
    }

}


function fafar_cf7crud_upgrade_function( $upgrader_object, $options ) {

    $upload_dir    = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir['basedir'].'/fafar_cf7crud_uploads';

    if ( file_exists( $fafar_cf7crud_dirname.'/index.php' ) ) return;
        
    if ( file_exists( $fafar_cf7crud_dirname ) ) {
        $fp = fopen( $fafar_cf7crud_dirname.'/index.php', 'w');
        fwrite($fp, "<?php \n\t // Silence is golden.");
        fclose( $fp );
    }

}


function fafar_cf7crud_on_deactivate() {

	// Remove custom capability from all roles
	global $wp_roles;

	foreach( array_keys( $wp_roles->roles ) as $role ) {
		$wp_roles->remove_cap( $role, 'fafar_cf7crud_access' );
	}
}


function fafar_cf7crud_before_send_mail_handler( $contact_form ) {

    $submission   = WPCF7_Submission::get_instance();

    if( $submission->get_posted_data( 'fafar-cf7crud-submission-id' ) !== null ) {

        fafar_cf7crud_before_send_mail_update( $contact_form );
        
    } else {
        
        fafar_cf7crud_before_send_mail_create( $contact_form );

    }

}

function fafar_cf7crud_before_send_mail_create( $contact_form ) {

    //error_log("FAFAR: A");

    /**
     * CREATE SUBMISSION ROUTINE
     * **/

    global $wpdb;
    $cfdb                  = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados
    $table_name            = $cfdb->prefix . 'fafar_cf7crud_submissions';
    $upload_dir            = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir[ 'basedir' ] . '/fafar_cf7crud_uploads';
    $bytes                 = random_bytes(5);
    $unique_hash              = time().bin2hex($bytes);

    $submission   = WPCF7_Submission::get_instance();
    $contact_form = $submission->get_contact_form();
    $tags_names   = array();
    $strict_keys  = apply_filters('fafar_cf7crud_strict_keys', false);  

    if ( $submission ) {

        $allowed_tags = array();
        $bl   = array( '\"', "\'", '/', '\\', '"', "'" );
        $wl   = array( '&quot;', '&#039;', '&#047;', '&#092;', '&quot;', '&#039;' );

        if( $strict_keys ){
            $tags  = $contact_form->scan_form_tags();
            foreach( $tags as $tag ){
                if( ! empty($tag->name) ) $tags_names[] = $tag->name;
            }
            $allowed_tags = $tags_names;
        }

        $not_allowed_tags = apply_filters( 'fafar_cf7crud_not_allowed_tags', array( 'g-recaptcha-response' ) );
        $allowed_tags     = apply_filters( 'fafar_cf7crud_allowed_tags', $allowed_tags );
        $data             = $submission->get_posted_data();
        $files            = $submission->uploaded_files();
        $uploaded_files   = array();


        foreach ( $_FILES as $file_key => $file ) {
            array_push( $uploaded_files, $file_key );
        }
        foreach ( $files as $file_key => $file ) {
            $file = is_array( $file ) ? reset( $file ) : $file;
            if( empty($file) ) continue;
            copy( $file, $fafar_cf7crud_dirname . '/' . $unique_hash . '-' . $file_key . '-' . basename( $file ) );
        }

        $form_data = array();
        
        foreach ( $data as $key => $d ) {
            
            if( $strict_keys && !in_array( $key, $allowed_tags ) ) continue;

            if( $key == 'fafar-cf7crud-submission-id' ) continue;

            if( str_contains( $key, 'fafar-cf7crud-input-file-hidden-' ) ) continue;

            if ( !in_array( $key, $not_allowed_tags ) && !in_array( $key, $uploaded_files )  ) {

                $tmpD = $d;

                if ( ! is_array( $d ) ) {
                    $tmpD = str_replace( $bl, $wl, $tmpD );
                } else {

                    $tmpD = array_map( function($item) use($bl, $wl) {
                               return str_replace( $bl, $wl, $item ); 
                            }, $tmpD);
                }

                $key = sanitize_text_field( $key );
                $form_data[ $key ] = $tmpD;
            }
            if ( in_array( $key, $uploaded_files ) ) {

                $file = is_array( $files[ $key ] ) ? reset( $files[ $key ] ) : $files[ $key ];
                
                $file_name = empty( $file ) ? '' : $unique_hash . '-' . $key . '-' . basename( $file ); 
                
                $key = sanitize_text_field( $key );


                $form_data[ $key . 'fafarcf7crudfile' ] = $file_name;

                if( $file_name == '' ) {

                    $form_data[ $key . 'fafarcf7crudfile' ] = 
                        $submission->get_posted_data( 'fafar-cf7crud-input-file-hidden-' . $key ) ? 
                            $submission->get_posted_data( 'fafar-cf7crud-input-file-hidden-' . $key ) : "";
    
                }
            }
        }

        /* fafar_cf7crud before save data. */
        $form_data = apply_filters( 'fafar_cf7crud_before_save_data', $form_data );

        do_action( 'fafar_cf7crud_before_save', $form_data );

        $form_id         = $contact_form->id();
        $submission_data = serialize( $form_data );
        $created_at      = current_time( 'Y-m-d H:i:s' );

        $cfdb->insert( $table_name, array(
            'submission_id'   => $unique_hash,
            'form_id'         => $form_id,
            'submission_data' => $submission_data,
            'created_at'      => $created_at
        ) );

        /* fafar_cf7crud after save data */
        $insert_id = $cfdb->insert_id;
        do_action( 'fafar_cf7crud_after_save_data', $insert_id );
    }

}

function fafar_cf7crud_before_send_mail_update( $contact_form ) {

    /**
     * UPDATE SUBMISSION ROUTINE
     * **/

    global $wpdb;
    $cfdb                  = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados
    $table_name            = $cfdb->prefix . 'fafar_cf7crud_submissions';
    $upload_dir            = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir[ 'basedir' ] . '/fafar_cf7crud_uploads';
    $bytes                 = random_bytes(5);
    $unique_hash           = time().bin2hex($bytes);

    $submission   = WPCF7_Submission::get_instance();
    $contact_form = $submission->get_contact_form();
    $tags_names   = array();
    $strict_keys  = apply_filters('fafar_cf7crud_strict_keys', false);  

    if ( $submission ) {

        $allowed_tags = array();
        $bl   = array( '\"', "\'", '/', '\\', '"', "'" );
        $wl   = array( '&quot;', '&#039;', '&#047;', '&#092;', '&quot;', '&#039;' );

        if( $strict_keys ){
            $tags  = $contact_form->scan_form_tags();
            foreach( $tags as $tag ){
                if( ! empty($tag->name) ) $tags_names[] = $tag->name;
            }
            $allowed_tags = $tags_names;
        }

        $not_allowed_tags = apply_filters( 'fafar_cf7crud_not_allowed_tags', array( 'g-recaptcha-response' ) );
        $allowed_tags     = apply_filters( 'fafar_cf7crud_allowed_tags', $allowed_tags );
        $data             = $submission->get_posted_data();
        $files            = $submission->uploaded_files();
        $uploaded_files   = array();

        $unique_hash = $submission->get_posted_data( "fafar-cf7crud-submission-id" ) ?
                            $submission->get_posted_data( "fafar-cf7crud-submission-id" ) : $unique_hash;

        foreach ( $_FILES as $file_key => $file ) {

            array_push( $uploaded_files, $file_key );

        }
        foreach ( $files as $file_key => $file ) {

            $file = is_array( $file ) ? reset( $file ) : $file;
            if( empty( $file ) ) continue;
            copy( $file, $fafar_cf7crud_dirname . '/' . $unique_hash . '-' . $file_key . '-' . basename( $file ) );

        }

        $form_data = array();
        
        foreach ( $data as $key => $d ) {
            
            if( $strict_keys && !in_array( $key, $allowed_tags ) ) continue;

            if( $key == 'fafar-cf7crud-submission-id' ) continue;

            if( str_contains( $key, 'fafar-cf7crud-input-file-hidden-' ) ) continue;

            if ( !in_array( $key, $not_allowed_tags ) && !in_array( $key, $uploaded_files )  ) {

                $tmpD = $d;

                if ( ! is_array( $d ) ) {
                    $tmpD = str_replace( $bl, $wl, $tmpD );
                } else {

                    $tmpD = array_map( function($item) use($bl, $wl) {
                               return str_replace( $bl, $wl, $item ); 
                            }, $tmpD);
                }

                $key = sanitize_text_field( $key );
                $form_data[ $key ] = $tmpD;
            }
            if ( in_array( $key, $uploaded_files ) ) {

                $file = is_array( $files[ $key ] ) ? reset( $files[ $key ] ) : $files[ $key ];
                
                $file_name = empty( $file ) ? '' : $unique_hash . '-' . $key . '-' . basename( $file ); 
                
                $key = sanitize_text_field( $key );
                
                $form_data[ $key . 'fafarcf7crudfile' ] = $file_name;

                if( $file_name == '' ) {

                    $form_data[ $key . 'fafarcf7crudfile' ] = 
                        $submission->get_posted_data( 'fafar-cf7crud-input-file-hidden-' . $key ) ? 
                            $submission->get_posted_data( 'fafar-cf7crud-input-file-hidden-' . $key ) : "";

                }

            }
        }

        /* fafar_cf7crud before save data. */
        $form_data = apply_filters( 'fafar_cf7crud_before_update_data', $form_data );

        do_action( 'fafar_cf7crud_before_update', $form_data );

        $form_id         = $contact_form->id();
        $submission_data = serialize( $form_data );
        $created_at      = current_time( 'Y-m-d H:i:s' );

        $wpdb->update(
            $table_name,
            array(
                'submission_data' => $submission_data
            ),
            array(
                'submission_id' => $submission->get_posted_data( "fafar-cf7crud-submission-id" )
            )
        );

        /* fafar_cf7crud after save data */
        do_action( 'fafar_cf7crud_after_update_data', $submission->get_posted_data( "fafar-cf7crud-submission-id" ) );
    }
}


function fafar_cf7crud_get_file_attrs() {

    global $wpdb;

    $cfdb = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados

    $assistido = $wpdb->get_results("SELECT * FROM `" . $cfdb->prefix . 'fafar_cf7crud_submissions' . "` WHERE `submission_id` = '" . $_GET['id'] . "'" );

    $file_attrs = array();

	if ( !$assistido[0] )
        return $file_attrs;
	
	$form_data = unserialize( $assistido[0]->submission_data );

	foreach ( $form_data as $chave => $data ) {

        if ( strpos( $chave, 'fafarcf7crudfile' ) !== false ) {

            $file_attrs[ $chave ] = $data;

        }

    }

    return $file_attrs;
    
}

function fafar_cf7crud_get_input_value( $tag_name ) {

    global $wpdb;

    $cfdb = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados

    $assistido = $wpdb->get_results( "SELECT * FROM `" . $cfdb->prefix . 'fafar_cf7crud_submissions' . "` WHERE `submission_id` = '" . $_GET['id'] . "'" );

	if ( !$assistido[0] ) 
        return "";
	
	$form_data = unserialize( $assistido[0]->submission_data );

	foreach ( $form_data as $chave => $data ) {

        if( $chave === $tag_name ) {
            
            if( is_array( $data ) && !empty( $data ) ) return $data[0];
            else return $data;

        }

    }
}

function fafar_cf7crud_populate_form_field($tag) {
    
    if( is_admin() ) return $tag;

    if( !isset( $_GET['id'] ) ) return $tag;

    if( $tag['basetype'] == 'radio' ) {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] );

        foreach ($tag['values'] as $key => $value) {

            if ($value == $input_value) {

                $tag['options'][] = 'default:' . ($key + 1);
                break;
            }
        }

    } else if( $tag['basetype'] == 'file' ) {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] . 'fafarcf7crudfile' );

        $tag['raw_values'] = (array) $input_value;

    } else {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] );

        $tag['values'] = (array) $input_value;

    }

    return $tag;
}

function fafar_cf7crud_get_input_file_attr_value( $key_attr, $file_attrs ) {

    foreach ( $file_attrs as $key => $value) {

        if( $key_attr == $key ) return $value;

    }

    return '';
}

function fafar_cf7crud_get_custom_input_file( $input_file_str, $file_attrs ) {

    // Get name attr from stock file input
    preg_match( '/name="[\S]+"/', $input_file_str, $matches );
    $name_attr = str_replace( 'name="' , '', $matches[0] );
    $name_attr = str_replace( '"' , '', $name_attr );

    // Set attr as database saved
    $key_attr_with_file_db_sufix = $name_attr . 'fafarcf7crudfile';

    // Get current attr value: String | ""
    $value_attr = fafar_cf7crud_get_input_file_attr_value( $key_attr_with_file_db_sufix, $file_attrs );

    // Building fafar cf7crud file input with custom label and data attr
    $custom_input_file  = "<div class='fafar-cf7crud-input-document-container'>";
    $custom_input_file .= "<button type='button' class='fafar-cf7crud-input-document-button' data-file-input-button='" . $name_attr . "'>";
    $custom_input_file .= "<span class='dashicons dashicons-upload'></span>";
    $custom_input_file .= "Arquivo";
    $custom_input_file .= "</button>";
    $custom_input_file .= "<span class='fafar-cf7crud-input-document-name' data-file-input-label='" . $name_attr . "'>";
    $custom_input_file .= ( $value_attr ) ? $value_attr : "Selecione um arquivo";
    $custom_input_file .= "</span>";
    $custom_input_file .= "</div>";

    // Setting value attr of stock file input
    $input_file_str = preg_replace( '/\/?>/', ' value="' . $value_attr . '" />', $input_file_str );

    // Setting custom class
    $input_file_str = preg_replace( '/class=\"/', ' class="fafar-cf7crud-stock-file-input ', $input_file_str );

    // Building a hidden input to store the file names
    $input_hidden_to_store_file_path = 
        "<input class='wpcf7-form-control wpcf7-hidden' name='fafar-cf7crud-input-file-hidden-" . $name_attr . "' value='" . ( ( $value_attr ) ? $value_attr : "" ) . "' type='hidden' />";


    return $input_file_str . $custom_input_file . $input_hidden_to_store_file_path;
}

function fafar_cf7crud_adding_hidden_fields($content) {

    if( is_admin() ) 
        return $content;

    $file_attrs = array();

    if( isset( $_GET['id'] ) )
        $file_attrs = fafar_cf7crud_get_file_attrs();

    // Creating a pattern
    $startPattern = '/<input[^>]*';
    $type = 'type="file"';
    $endPattern = '[^>]*\/?>/';
        
    $pattern = $startPattern . $type . $endPattern;
        
    // Perform the regex match
    if ( preg_match_all( $pattern, $content, $input_file_matches ) ) {
        // If has at least one
        foreach( $input_file_matches[0] as $input_file_match ) {

            // Add's a custom file input, after the original file input(hidden by css)
            $content = str_replace( $input_file_match, fafar_cf7crud_get_custom_input_file( $input_file_match, $file_attrs ), $content );
                
        }
        
    }

    // Adding Hidden Submission ID Field
    if( isset( $_GET['id'] ) )
        $content .= "<input class='wpcf7-form-control wpcf7-hidden' value='" . $_GET['id'] . "' type='hidden' name='fafar-cf7crud-submission-id' />";

	return $content;
}


