<?php
/**
 * Plugin Name: Phoenix Topic Generator (Content Ideation Engine)
 * Description: Dynamically monitors custom publications and links for new technologies, techniques, and trends using Gemini.
 * Version: 1.2.6
 * Author: Nate Balcom
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', 'phoenix_ideation_init_settings' );
function phoenix_ideation_init_settings() {
    register_setting( 'phoenix_ideation_group', 'phoenix_ideation_sources' );
    register_setting( 'phoenix_ideation_group', 'phoenix_ideation_keywords' );
}

add_action( 'admin_menu', 'phoenix_ideation_register_menu' );
function phoenix_ideation_register_menu() {
    $page_hook = add_menu_page('Content Ideation', 'Phoenix Ideas', 'edit_posts', 'phoenix-ideas', 'phoenix_ideation_render_panel', 'dashicons-lightbulb', 6);
    
    // Intercept database actions BEFORE page headers render to prevent URL string pollution
    add_action( 'load-' . $page_hook, 'phoenix_ideation_handle_backend_actions' );
}

add_action( 'admin_enqueue_scripts', 'phoenix_ideation_enqueue_admin_assets' );
function phoenix_ideation_enqueue_admin_assets( $hook ) {
    if ( 'toplevel_page_phoenix-ideas' !== $hook ) {
        return;
    }
    wp_enqueue_script(
        'phoenix-ideation-admin-logic',
        plugin_dir_url( __FILE__ ) . 'assets/admin.js',
        array( 'jquery' ),
        '1.1.0',
        true
    );
}

/**
 * High-Performance Action Interceptor (PRG Pattern)
 */
function phoenix_ideation_handle_backend_actions() {
    // 1. Safe Processing Loop for Deleting Feeds
    if ( isset( $_GET['action'] ) && 'delete_source' === $_GET['action'] && isset( $_GET['index'] ) ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'phoenix_delete_source_' . $_GET['index'] ) ) {
            $saved_sources = get_option( 'phoenix_ideation_sources', array() );
            $index_to_delete = intval( $_GET['index'] );
            
            if ( isset( $saved_sources[$index_to_delete] ) ) {
                unset( $saved_sources[$index_to_delete] );
                update_option( 'phoenix_ideation_sources', array_values( $saved_sources ) );
            }
            
            // Redirect cleanly to drop the query strings out of the browser history
            wp_redirect( admin_url( 'admin.php?page=phoenix-ideas&phoenix_updated=1' ) );
            exit;
        }
    }

    // 2. Safe Processing Loop for Saving / Updating Form Entities
    if ( isset( $_POST['phoenix_save_sources'] ) ) {
        if ( check_admin_referer( 'phoenix_update_sources_action', 'phoenix_source_nonce' ) ) {
            $saved_sources = get_option( 'phoenix_ideation_sources', array() );
            
            if ( isset( $_POST['edit_item_index'] ) && $_POST['edit_item_index'] !== '' ) {
                // Execute Update Mode
                $index = intval( $_POST['edit_item_index'] );
                if ( isset( $saved_sources[$index] ) && ! empty( $_POST['new_feed_url'] ) ) {
                    $saved_sources[$index]['name'] = sanitize_text_field( $_POST['new_source_name'] );
                    $saved_sources[$index]['url']  = esc_url_raw( $_POST['new_feed_url'] );
                    update_option( 'phoenix_ideation_sources', $saved_sources );
                }
            } else {
                // Execute Creation Mode
                if ( ! empty( $_POST['new_feed_url'] ) ) {
                    $new_name = sanitize_text_field( $_POST['new_source_name'] );
                    $new_url = esc_url_raw( $_POST['new_feed_url'] );
                    $saved_sources[] = array( 'name' => $new_name ? $new_name : $new_url, 'url' => $new_url );
                    update_option( 'phoenix_ideation_sources', $saved_sources );
                }
            }

            if ( isset( $_POST['focus_keywords'] ) ) {
                update_option( 'phoenix_ideation_keywords', sanitize_text_field( $_POST['focus_keywords'] ) );
            }

            wp_redirect( admin_url( 'admin.php?page=phoenix-ideas&phoenix_updated=1' ) );
            exit;
        }
    }
}

/**
 * Administration Interface Panel Presentation
 */
function phoenix_ideation_render_panel() {
    $saved_sources = get_option( 'phoenix_ideation_sources', array() );
    $saved_keywords = get_option( 'phoenix_ideation_keywords', 'AEO, Automation, Photoshop, UX Architecture, LLM' );

    // Check if an entity is currently targeted for modification
    $is_editing = false;
    $edit_name  = '';
    $edit_url   = '';
    $edit_index = '';

    if ( isset( $_GET['action'] ) && 'edit_source' === $_GET['action'] && isset( $_GET['index'] ) ) {
        $edit_index = intval( $_GET['index'] );
        if ( isset( $saved_sources[$edit_index] ) ) {
            $is_editing = true;
            $edit_name  = $saved_sources[$edit_index]['name'];
            $edit_url   = $saved_sources[$edit_index]['url'];
        }
    }

    if ( isset( $_GET['phoenix_updated'] ) ) {
        echo '<div class="updated"><p>Phoenix Monitoring Station Updated Successfully.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Phoenix Content Ideation Panel</h1>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-ideas' ) ); ?>">
            <?php wp_nonce_field( 'phoenix_update_sources_action', 'phoenix_source_nonce' ); ?>
            
            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Left Hand Feed Manifest Column -->
                <div style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
                    <h2>Monitored Publications & Feeds</h2>
                    <table class="wp-list-table widefat fixed striped" style="margin-bottom: 15px;">
                        <thead>
                            <tr>
                                <th>Publication Name</th>
                                <th>URL</th>
                                <th style="width: 110px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $saved_sources ) ) : ?>
                                <tr><td colspan="3" style="color: #999; font-style: italic;">No custom feeds registered yet.</td></tr>
                            <?php else : foreach ( $saved_sources as $index => $source ) : ?>
                                <tr style="<?php echo ( $is_editing && $edit_index === $index ) ? 'background-color: #f0f6fa;' : ''; ?>">
                                    <td><strong><?php echo esc_html( $source['name'] ); ?></strong></td>
                                    <td><code><?php echo esc_html( $source['url'] ); ?></code></td>
                                    <td style="text-align: center;">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-ideas&action=edit_source&index=' . $index ) ); ?>" class="dashicons-before dashicons-edit" title="Edit Stream" style="text-decoration: none; margin-right: 8px;"></a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=phoenix-ideas&action=delete_source&index=' . $index ), 'phoenix_delete_source_' . $index ) ); ?>" class="dashicons-before dashicons-trash" title="Delete Stream" style="color: #a00; text-decoration: none;"></a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Dynamic Stream Modification Interface Box -->
                    <div style="background: #f9f9f9; padding: 12px; border: 1px dashed #ccd0d4;">
                        <h3 style="margin-top:0;"><?php echo $is_editing ? '⚙️ Edit Target Stream' : '➕ Add New Target Stream'; ?></h3>
                        <input type="hidden" name="edit_item_index" value="<?php echo $is_editing ? esc_attr( $edit_index ) : ''; ?>" />
                        
                        <div style="display:flex; gap:10px; margin-bottom:8px;">
                            <input type="text" name="new_source_name" placeholder="e.g. TechCrunch" value="<?php echo esc_attr($edit_name); ?>" style="flex:1;" required />
                            <input type="url" name="new_feed_url" placeholder="https://site.com/feed/" value="<?php echo esc_attr($edit_url); ?>" style="flex:2;" required />
                        </div>
                        
                        <?php if ( $is_editing ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-ideas' ) ); ?>" class="button button-link" style="color:#666; float:right;">Cancel Edit</a>
                        <?php endif; ?>
                        <div style="clear:both;"></div>
                    </div>
                </div>
                
                <!-- Right Hand Focus Management Column -->
                <div style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ccd0d4; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h2>Technology & Technique Targets</h2>
                        <textarea name="focus_keywords" style="width: 100%; font-family: monospace;" rows="6"><?php echo esc_textarea( $saved_keywords ); ?></textarea>
                    </div>
                    <p style="margin-bottom:0; text-align:right;">
                        <input type="submit" name="phoenix_save_sources" class="button button-primary button-large" value="<?php echo $is_editing ? 'Update Channel Configurations' : 'Save System Changes'; ?>" />
                    </p>
                </div>
            </div>
        </form>
        
        <div style="margin-top: 30px; text-align: center;">
            <button id="phoenix-scan-now-btn" class="button button-secondary button-large" <?php echo empty($saved_sources) ? 'disabled' : ''; ?>>Scan Active Channels & Brainstorm Topics</button>
        </div>
        <div id="phoenix-ideas-output-container" style="margin-top:25px; padding:20px; background:#fff; border:1px solid #ccd0d4; min-height:150px;">
            <p style="color:#666; font-style:italic; text-align: center;">Ready to scan your active publication streams...</p>
        </div>
    </div>
    <?php
}

// Handle processing logic for asynchronous AJAX requests
add_action( 'wp_ajax_phoenix_execute_trend_scan', 'phoenix_ajax_handle_trend_scan' );
function phoenix_ajax_handle_trend_scan() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'phoenix_update_sources_action' ) ) { 
        wp_send_json_error( 'Security token expired.' ); 
    }
    
    $saved_sources = get_option( 'phoenix_ideation_sources', array() );
    $saved_keywords = get_option( 'phoenix_ideation_keywords', 'AEO, Automation, Photoshop, UX Architecture, LLM' );
    if ( empty( $saved_sources ) ) { 
        wp_send_json_error( 'Your Monitored Publications registry is empty.' ); 
    }

    $found_headlines = array();
    include_once( ABSPATH . WPINC . '/feed.php' );
    foreach ( $saved_sources as $source ) {
        $rss = fetch_feed( $source['url'] );
        if ( ! is_wp_error( $rss ) ) {
            $maxitems = $rss->get_item_quantity( 10 );
            $rss_items = $rss->get_items( 0, $maxitems );
            foreach ( $rss_items as $item ) { 
                $found_headlines[] = '[' . $source['name'] . '] ' . $item->get_title(); 
            }
        }
    }

    // Remember to paste your actual AIza... key here!
    $api_key = 'YOUR-API-KEY-HERE';
    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=" . $api_key;

    $prompt = "Identify trends from these headlines:\n" . implode( "\n", array_slice( $found_headlines, 0, 40 ) ) . "\nFocus keys: {$saved_keywords}. Brainstorm 3 deep-dive article ideas with a Title, Hook, and Outline. Format cleanly using HTML tags (<h3>, <p>, <ul>, <li>). Do not use markdown backticks.";

    $response = wp_remote_post( $api_url, array(
        'timeout' => 120, // Headroom matches javascript pipeline tolerance parameters
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array( 'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ) ) )
    ) );

    if ( is_wp_error( $response ) ) { 
        wp_send_json_error( $response->get_error_message() ); 
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    $body_text     = wp_remote_retrieve_body( $response );
    $body_data     = json_decode( $body_text, true );

    if ( 200 !== $response_code ) {
        $error_details = $body_data['error']['message'] ?? 'Gateway Rejection Status ' . $response_code;
        wp_send_json_error( 'Google API Gateway Error: ' . $error_details );
    }
    
    if ( empty( $body_data['candidates'][0]['content']['parts'][0]['text'] ) ) {
        wp_send_json_error( 'Gemini returned an empty payload structure. Ensure your monitored feeds are returning active data arrays.' );
    }

    $html_output = $body_data['candidates'][0]['content']['parts'][0]['text'];
    wp_send_json_success( $html_output );
}
