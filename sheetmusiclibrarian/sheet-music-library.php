<?php
/**
 * Plugin Name: Sheet Music Librarian
 * Description: Manage and display sheet music pieces with instrument files, composer, season, notes, external links, and last updated info.
 * Version: 1.3.2
 * Author: Brad Salomons
 * License: GPL2+
 */

//////////////////////////////
// WORDPRESS REGISTRATIONS
//////////////////////////////

if (!defined('ABSPATH')) exit;

// Register Sheet Music Post Type

add_action('init', 'osm_register_sheet_music');
function osm_register_sheet_music() {
    $labels = [
        'name' => 'Sheet Music',
        'singular_name' => 'Sheet Music Piece',
        'add_new' => 'Add New Piece',
        'add_new_item' => 'Add New Music',
        'edit_item' => 'Edit Piece',
        'new_item' => 'New Piece',
        'view_item' => 'View Piece',
        'search_items' => 'Search Pieces',
        'not_found' => 'No pieces found',
        'menu_name' => 'Sheet Music'
    ];

    $args = [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-media-document',
        'capability_type' => ['sheet_music_piece', 'sheet_music_pieces'],
        'map_meta_cap' => true,
        'supports' => ['title'],
        'rewrite' => false,
    ];

    register_post_type('sheet_music', $args);
}

// Register Instrument Taxonomy

add_action('init', 'osm_register_instrument_taxonomy', 11);
function osm_register_instrument_taxonomy() {
    $labels = [
        'name' => 'Instruments',
        'singular_name' => 'Instrument',
        'search_items' => 'Search Instruments',
        'all_items' => 'All Instruments',
        'edit_item' => 'Edit Instrument',
        'update_item' => 'Update Instrument',
        'add_new_item' => 'Add New Instrument',
        'new_item_name' => 'New Instrument Name',
        'menu_name' => 'Instruments',
    ];

    $args = [
        'hierarchical' => true,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'instrument'],
        'capabilities' => [
            'manage_terms' => 'manage_sheet_music_instruments',
            'edit_terms'   => 'manage_sheet_music_instruments',
            'delete_terms' => 'manage_sheet_music_instruments',
            'assign_terms' => 'edit_sheet_music_pieces',
        ],
    ];

    register_taxonomy('instrument', ['sheet_music'], $args);
}

// Register Season Taxonomy

add_action('init', 'osm_register_season_taxonomy');
function osm_register_season_taxonomy() {
    $labels = [
        'name' => 'Seasons',
        'singular_name' => 'Season',
        'search_items' => 'Search Seasons',
        'all_items' => 'All Seasons',
        'edit_item' => 'Edit Season',
        'update_item' => 'Update Season',
        'add_new_item' => 'Add New Season',
        'new_item_name' => 'New Season Name',
        'menu_name' => 'Seasons',
    ];

    $args = [
        'hierarchical' => true, 
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'season'],
        'capabilities' => [
            'manage_terms' => 'edit_sheet_music_seasons',
            'edit_terms'   => 'edit_sheet_music_seasons',
            'delete_terms' => 'delete_sheet_music_seasons',
            'assign_terms' => 'edit_sheet_music_pieces',
        ],
    ];

    register_taxonomy('season', ['sheet_music'], $args);
}

//////////////////////////////
// PERMISSIONS
//////////////////////////////

// The plugin has its own capabilities so music access can be given without
// access to the rest of the site. Existing roles get the music equivalents of
// the post capabilities they already have, so nobody's access changes.

define('OSM_CAPS_VERSION', 1);

function osm_install_capabilities() {
    $equivalents = [
        'edit_posts'             => ['edit_sheet_music_pieces'],
        'edit_others_posts'      => ['edit_others_sheet_music_pieces', 'manage_sheet_music_files'],
        'edit_published_posts'   => ['edit_published_sheet_music_pieces'],
        'edit_private_posts'     => ['edit_private_sheet_music_pieces'],
        'publish_posts'          => ['publish_sheet_music_pieces'],
        'read_private_posts'     => ['read_private_sheet_music_pieces'],
        'delete_posts'           => ['delete_sheet_music_pieces'],
        'delete_others_posts'    => ['delete_others_sheet_music_pieces'],
        'delete_published_posts' => ['delete_published_sheet_music_pieces'],
        'delete_private_posts'   => ['delete_private_sheet_music_pieces'],
        'manage_categories'      => ['edit_sheet_music_seasons', 'delete_sheet_music_seasons', 'manage_sheet_music_instruments'],
    ];
    foreach (wp_roles()->role_objects as $role) {
        foreach ($equivalents as $existing => $caps) {
            if (!$role->has_cap($existing)) continue;
            foreach ($caps as $cap) $role->add_cap($cap);
        }
    }

    // Music Assistant: edits pieces and seasons, nothing else on the site.
    $assistant = get_role('music_assistant') ?: add_role('music_assistant', 'Music Assistant', ['read' => true]);
    foreach ([
        'upload_files',
        'edit_sheet_music_pieces',
        'edit_others_sheet_music_pieces',
        'edit_published_sheet_music_pieces',
        'publish_sheet_music_pieces',
        'edit_sheet_music_seasons',
    ] as $cap) {
        $assistant->add_cap($cap);
    }

    update_option('osm_caps_version', OSM_CAPS_VERSION);
}

register_activation_hook(__FILE__, 'osm_install_capabilities');

// Plugin updates don't run the activation hook, so also install on first load.
add_action('init', function() {
    if (intval(get_option('osm_caps_version')) < OSM_CAPS_VERSION) osm_install_capabilities();
}, 5);

// Someone who can work on sheet music but nothing else on the site.
function osm_is_music_only_user($user = null) {
    $user = $user ?: wp_get_current_user();
    return $user && $user->exists() && $user->has_cap('edit_sheet_music_pieces') && !$user->has_cap('edit_posts');
}

// Keep their admin uncluttered: land on Sheet Music, no Dashboard or Media menu.
add_filter('login_redirect', function($redirect_to, $requested, $user) {
    if ($user instanceof WP_User && osm_is_music_only_user($user) && (!$requested || $redirect_to === admin_url())) {
        return admin_url('edit.php?post_type=sheet_music');
    }
    return $redirect_to;
}, 10, 3);

add_action('load-index.php', function() {
    if (osm_is_music_only_user()) {
        wp_safe_redirect(admin_url('edit.php?post_type=sheet_music'));
        exit;
    }
});

add_action('admin_menu', function() {
    if (!osm_is_music_only_user()) return;
    remove_menu_page('index.php');
    remove_menu_page('upload.php');
}, 999);

// In the file picker, only show sheet music files and their own uploads.
add_filter('ajax_query_attachments_args', function($query) {
    if (!osm_is_music_only_user()) return $query;

    $own = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'author'         => get_current_user_id(),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $allowed = array_unique(array_merge(osm_all_sheet_music_attachment_ids(), $own));
    if (!empty($query['post__in'])) $allowed = array_intersect($allowed, array_map('intval', $query['post__in']));
    $query['post__in'] = $allowed ? array_values($allowed) : [0];
    return $query;
});

//////////////////////////////
// EDITOR
//////////////////////////////

add_action('add_meta_boxes', 'osm_add_combined_meta_box');
function osm_add_combined_meta_box() {
    add_meta_box(
        'osm_sheet_combined',
        'Sheet Music Info & Files',
        'osm_render_combined_meta_box',
        'sheet_music',
        'normal',
        'high'
    );
}

add_action('admin_enqueue_scripts', function($hook) {
    global $post_type;
    $screen    = get_current_screen();
    $is_piece  = in_array($hook, ['post.php', 'post-new.php']) && $post_type === 'sheet_music';
    $is_season = $hook === 'term.php' && $screen && $screen->taxonomy === 'season';
    if (!$is_piece && !$is_season) return;

    $css_file = plugin_dir_path(__FILE__) . 'admin-style.css';
    $css_url  = plugin_dir_url(__FILE__) . 'admin-style.css';
    $version  = file_exists($css_file) ? filemtime($css_file) : false;

    wp_enqueue_style('osm-admin-style', $css_url, [], $version);
    if ($is_piece) wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');
});

add_action('admin_head', function() {
    global $post_type;
    if ($post_type === 'sheet_music') {
        echo '<script>
            jQuery(function($){
                $("#instrumentdiv").removeClass("closed").addClass("closed");
            });
        </script>';
    }
});

add_action('instrument_edit_form_fields', 'osm_instrument_order_field');
function osm_instrument_order_field($term) {
    $order = get_term_meta($term->term_id, 'instrument_order', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="instrument_order">Sort Order</label></th>
        <td>
            <input type="number" name="instrument_order" id="instrument_order" value="<?php echo esc_attr($order ?: 0); ?>" style="width:60px;" />
            <?php
            // output nonce for verification
            wp_nonce_field('osm_instrument_order_' . $term->term_id, 'osm_instrument_order_nonce');
            ?>
            <p class="description">Enter a number to control the display order on the frontend (smaller numbers first).</p>
        </td>
    </tr>
    <?php
}


add_action('edited_instrument', 'osm_save_instrument_order');
add_action('created_instrument', 'osm_save_instrument_order');

function osm_save_instrument_order($term_id) {
    // Unsash and sanitize the nonce first
    $raw_nonce = isset($_POST['osm_instrument_order_nonce']) 
        ? sanitize_text_field( wp_unslash( $_POST['osm_instrument_order_nonce'] ) ) 
        : '';

    $nonce = sanitize_text_field($raw_nonce);

    // Verify nonce
    if ( ! $nonce || ! wp_verify_nonce($nonce, 'osm_instrument_order_' . $term_id) ) {
        return; // invalid nonce
    }

    // Save the order
    if ( isset($_POST['instrument_order']) ) {
        $order = intval( wp_unslash($_POST['instrument_order']) );
        update_term_meta( $term_id, 'instrument_order', $order );
    }
}

add_action('season_edit_form_fields', 'osm_season_order_field');
function osm_season_order_field($term) {
    $piece_ids = get_objects_in_term($term->term_id, 'season');
    $pieces = (!is_wp_error($piece_ids) && $piece_ids) ? get_posts([
        'post_type'      => 'sheet_music',
        'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
        'post__in'       => $piece_ids,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]) : [];
    $pieces = osm_sort_by_program_order($pieces, $term->slug);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label>Program Order</label></th>
        <td>
            <?php if (!$pieces) : ?>
                <p class="description">No pieces are in this season yet.</p>
            <?php else : ?>
                <ul id="osm-program-order" class="osm-program-order">
                    <?php foreach ($pieces as $piece) : ?>
                        <li>
                            <span class="osm-link-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
                            <?php echo esc_html($piece->post_title ?: '(no title)'); ?>
                            <?php if ($piece->post_status !== 'publish') : ?>
                                <em>(<?php echo esc_html(get_post_status_object($piece->post_status)->label); ?>)</em>
                            <?php endif; ?>
                            <input type="hidden" name="osm_piece_order[]" value="<?php echo esc_attr($piece->ID); ?>" />
                        </li>
                    <?php endforeach; ?>
                </ul>
                <script>
                jQuery(function($){
                    $('#osm-program-order').sortable({ handle: '.osm-link-drag-handle', axis: 'y' });
                });
                </script>
            <?php endif; ?>
            <?php wp_nonce_field('osm_season_order_' . $term->term_id, 'osm_season_order_nonce'); ?>
            <p class="description">Drag pieces into concert order. A Sheet Music Library showing only this season lists pieces in this order. Pieces added later appear at the end, alphabetically, until you place them.</p>
        </td>
    </tr>
    <?php
}

add_action('edited_season', 'osm_save_season_order');
function osm_save_season_order($term_id) {
    $nonce = isset($_POST['osm_season_order_nonce']) ? sanitize_text_field(wp_unslash($_POST['osm_season_order_nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'osm_season_order_' . $term_id)) return;

    $input = filter_input(INPUT_POST, 'osm_piece_order', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    $order = is_array($input) ? array_values(array_filter(array_map('intval', $input))) : [];
    if ($order === osm_get_program_order($term_id)) return;

    update_term_meta($term_id, 'osm_piece_order', $order);
    update_term_meta($term_id, 'osm_last_updated', time()); // reordering changes the season's listing
}

function osm_render_combined_meta_box($post) {
    wp_nonce_field('osm_save_sheet_combined', 'osm_sheet_combined_nonce');

    $composer = get_post_meta($post->ID, 'osm_composer', true);
    $notes    = get_post_meta($post->ID, 'osm_notes', true);

    echo '<p><label>Composer: <input type="text" name="osm_composer" value="'.esc_attr($composer).'" style="width:100%;" /></label></p>';
    echo '<p><label>Notes:<br><textarea name="osm_notes" rows="4" style="width:100%;">'.esc_textarea($notes).'</textarea></label></p>';

    $links = get_post_meta($post->ID, 'osm_links', true);
    if(!is_array($links)) $links = [];

    echo '<p><label>External Links (e.g. YouTube videos, reference recordings):</label> <span class="description">Drag <span class="dashicons dashicons-menu"></span> to reorder.</span></p>';
    echo '<div id="osm-links-container">';

    foreach($links as $i => $l){
        $link_title = isset($l['title']) ? $l['title'] : '';
        $link_url   = isset($l['url']) ? $l['url'] : '';

        echo '<div class="osm-link-row">';
        echo '  <div class="osm-link-inner">';
        echo '      <span class="osm-link-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>';
        echo '      <input type="text" class="osm-link-title" name="osm_links['.esc_html($i).'][title]" placeholder="Link title (e.g. YouTube Reference)" value="'.esc_attr($link_title).'" style="flex:1;" />';
        echo '      <input type="url" class="osm-link-url" name="osm_links['.esc_html($i).'][url]" placeholder="https://..." value="'.esc_attr($link_url).'" style="flex:2; margin-left:8px;" />';
        echo '      <button type="button" class="osm-remove-link button" style="margin-left:8px;">Remove</button>';
        echo '  </div>';
        echo '</div>';
    }

    echo '<div class="osm-link-row template" style="display:none;">';
    echo '  <div class="osm-link-inner">';
    echo '      <span class="osm-link-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>';
    echo '      <input type="text" class="osm-link-title" name="osm_links[__LINKINDEX__][title]" placeholder="Link title (e.g. YouTube Reference)" value="" style="flex:1;" />';
    echo '      <input type="url" class="osm-link-url" name="osm_links[__LINKINDEX__][url]" placeholder="https://..." value="" style="flex:2; margin-left:8px;" />';
    echo '      <button type="button" class="osm-remove-link button" style="margin-left:8px;">Remove</button>';
    echo '  </div>';
    echo '</div>';

    echo '</div>';
    echo '<div style="padding-top:8px; padding-bottom:8px;"><button type="button" id="osm-add-link" class="button">Add Link</button></div>';

    $files = get_post_meta($post->ID, 'osm_files', true);
    if(!is_array($files)) $files = [];

    echo '<hr><h4>Upload files and assign them to instruments.</h4>';
    echo '<div id="osm-files-container">';

    foreach($files as $i => $f){
        $attachment_id = intval($f['attachment_id']);
        $instrument_id = intval($f['instrument']);
        $attachment_url = $attachment_id ? wp_get_attachment_url($attachment_id) : '';

        echo '<div class="osm-file-row">';
        echo '  <input type="hidden" name="osm_files['.esc_html($i).'][attachment_id]" value="'.esc_attr($attachment_id).'" />';
        echo '  <div class="osm-file-inner">';
        echo '      <button type="button" class="button osm-upload-button">'.($attachment_url ? 'Change File' : 'Upload File').'</button>';
        echo '      <span class="osm-file-name" style="flex:1; margin-left:8px; color:#555;">'.($attachment_url ? esc_html(basename($attachment_url)) : '').'</span>';
        echo '      <select name="osm_files['.esc_html($i).'][instrument]" style="min-width:180px; margin-left:8px;">';
        echo '          <option value="">Select Instrument</option>';
        foreach(get_terms(['taxonomy'=>'instrument','hide_empty'=>false]) as $t){
            $selected = $t->term_id==$instrument_id ? 'selected' : '';
            echo '<option value="'.esc_html($t->term_id).'" '.esc_html($selected).'>'.esc_html($t->name).'</option>';
        }
        echo '      </select>';
        echo '      <button type="button" class="osm-remove-file button" style="margin-left:8px;">Remove</button>';
        echo '  </div>';
        echo '</div>';

    }

    echo '<div class="osm-file-row template" style="display:none;">';
    echo '  <input type="hidden" name="osm_files[__INDEX__][attachment_id]" value="" />';
    echo '  <div class="osm-file-inner">';
    echo '      <button type="button" class="button osm-upload-button">Upload File</button>';
    echo '      <span class="osm-file-name" style="flex:1; margin-left:8px; color:#555;"></span>';
    echo '      <select name="osm_files[__INDEX__][instrument]" style="min-width:180px; margin-left:8px;">';
    echo '          <option value="">Select Instrument</option>';
    foreach (get_terms(['taxonomy' => 'instrument', 'hide_empty' => false]) as $t) {
        echo '<option value="' .esc_html($t->term_id). '">' . esc_html($t->name) . '</option>';
    }
    echo '      </select>';
    echo '      <button type="button" class="osm-remove-file button" style="margin-left:8px;">Remove</button>';
    echo '  </div>';
    echo '</div>';

    echo '</div>';

    echo '<div style="padding-top:8px;"><button type="button" id="osm-add-file" class="button">Add File</button> ';
    echo '<button type="button" id="osm-bulk-upload" class="button">Bulk Upload</button></div>';

    // SHORTCODE HINTS (only useful to people who edit pages)
    $seasons = get_the_terms($post->ID, 'season');

    echo '<div class="osm-shortcode-box"'.(osm_is_music_only_user() ? ' hidden' : '').'>';
    echo '<strong>Embed this piece on the front end:</strong>';
    echo '<p>You can embed this sheet music using one of the following shortcodes:</p>';

    // All pieces
    echo '<code>[sheet_music_library]</code>';
    echo '<br><small>This version lists all published pieces.</small>';
    echo '<br><br>';

    // Season-specific shortcodes
    if (!empty($seasons) && !is_wp_error($seasons)) {
        echo '<p><strong>Season-specific shortcodes:</strong></p>';
        foreach ($seasons as $season) {
            echo '<code>[sheet_music_library season="' . esc_attr($season->slug) . '"]</code>';
            echo '<br><small>Displays only pieces from the <em>' . esc_html($season->name) . '</em> season.</small><br><br>';
        }
    } else {
        echo '<p><em>No seasons are currently associated with this piece.</em></p>';
    }

    // Single piece shortcode
    echo '<p><strong>Single piece shortcode:</strong></p>';
    echo '<code>[sheet_music_library id="' . esc_attr($post->ID) . '"]</code>';
    echo '<br><small>Displays this single piece only.</small>';

    // Last updated shortcode
    echo '<p><strong>Collection last updated:</strong></p>';
    echo '<code>[sheet_music_last_updated]</code>';
    echo '<br><small>Shows when the collection(s) on the same page last changed. Place it at the top of the page.</small>';

    echo '<p><small>In the block editor you can also use the <em>Sheet Music Library</em> and <em>Sheet Music Last Updated</em> blocks.</small></p>';

    echo '</div>';

    ?>
    <script>
    jQuery(document).ready(function($){
        var linksContainer = $('#osm-links-container');
        var linkTemplate = linksContainer.find('.template').clone().removeClass('template').show();
        var linkIndex = linksContainer.find('.osm-link-row').length;

        $('#osm-add-link').click(function(){
            var newRow = linkTemplate.clone().html(function(i, oldHTML){
                return oldHTML.replace(/__LINKINDEX__/g, linkIndex);
            });
            linksContainer.append(newRow);
            linkIndex++;
        });

        linksContainer.on('click', '.osm-remove-link', function(){
            $(this).closest('.osm-link-row').remove();
        });

        linksContainer.sortable({
            items: '.osm-link-row:not(.template)',
            handle: '.osm-link-drag-handle',
            axis: 'y',
            placeholder: 'osm-link-row-placeholder',
            forcePlaceholderSize: true
        });

        var container = $('#osm-files-container');
        var template = container.find('.template').clone().removeClass('template').show();
        var index = container.find('.osm-file-row').length;

        // Add new row
        $('#osm-add-file').click(function(){
            var newRow = template.clone().html(function(i, oldHTML){
                return oldHTML.replace(/__INDEX__/g, index);
            });
            container.append(newRow);
            index++;
        });

        // Remove row
        container.on('click', '.osm-remove-file', function(){
            $(this).closest('.osm-file-row').remove();
        });

        // Single file uploader
        container.on('click', '.osm-upload-button', function(e){
            e.preventDefault();
            var button = $(this);
            var row = button.closest('.osm-file-row');
            var hidden_input = row.find('input[type="hidden"]');
            var file_name_span = row.find('.osm-file-name');

            var file_frame = wp.media({
                title: 'Select or Upload File',
                button: { text: 'Use this file' },
                multiple: false
            });

            file_frame.on('select', function(){
                var attachment = file_frame.state().get('selection').first().toJSON();
                hidden_input.val(attachment.id);
                file_name_span.text(attachment.filename);
            });

            file_frame.open();
        });

        // Bulk files uploader
        $('#osm-bulk-upload').click(function(e){
            e.preventDefault();
            var bulk_frame = wp.media.frames.bulk_frame = wp.media({
                title: 'Select or Upload Files',
                button: { text: 'Use These Files' },
                multiple: true
            });

            bulk_frame.on('select', function(){
                var selection = bulk_frame.state().get('selection');
                selection.each(function(attachment){
                    attachment = attachment.toJSON();
                    var newRow = template.clone().html(function(i, oldHTML){
                        return oldHTML.replace(/__INDEX__/g, index);
                    });
                    newRow.find('input[type="hidden"]').val(attachment.id);
                    newRow.find('.osm-file-name').text(attachment.filename);
                    container.append(newRow);
                    index++;
                });
            });

            bulk_frame.open();
        });

    });
    </script>

    <?php
}

add_action('save_post', 'osm_save_sheet_combined');
function osm_save_sheet_combined($post_id) {

    $nonce = isset($_POST['osm_sheet_combined_nonce']) ? sanitize_text_field(wp_unslash($_POST['osm_sheet_combined_nonce'])) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'osm_save_sheet_combined' ) ) {
        return;
    }

    // Prevent autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) return;

    // Sanitize and save composer
    if (isset($_POST['osm_composer'])) {
        $composer = sanitize_text_field(wp_unslash($_POST['osm_composer']));
        update_post_meta($post_id, 'osm_composer', $composer);
    }

    // Sanitize and save notes
    if (isset($_POST['osm_notes'])) {
        $notes = sanitize_textarea_field(wp_unslash($_POST['osm_notes']));
        update_post_meta($post_id, 'osm_notes', $notes);
    }

    // Handle external links
    $raw_links = [];
    $input_links = filter_input(INPUT_POST, 'osm_links', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    if ( is_array( $input_links ) ) {
        $raw_links = wp_unslash( $input_links );
    }

    $clean_links = [];
    foreach ( $raw_links as $l ) {
        $url = isset( $l['url'] ) ? esc_url_raw( trim( $l['url'] ) ) : '';
        if ( ! $url ) continue;

        $title = isset( $l['title'] ) ? sanitize_text_field( $l['title'] ) : '';

        $clean_links[] = [
            'title' => $title,
            'url'   => $url,
        ];
    }

    update_post_meta( $post_id, 'osm_links', $clean_links );

    // Handle uploaded files
    $raw_files = []; // default

    $input_files = filter_input(INPUT_POST, 'osm_files', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

    if ( is_array( $input_files ) ) {
        $raw_files = wp_unslash( $input_files ); // unslash first
    }

    $clean_files = [];

    foreach ( $raw_files as $f ) {
        $attachment_id = isset( $f['attachment_id'] ) ? intval( $f['attachment_id'] ) : 0;
        $instrument    = isset( $f['instrument'] ) ? intval( $f['instrument'] ) : 0;

        if ( $attachment_id ) {
            $clean_files[] = [
                'attachment_id' => $attachment_id,
                'instrument'    => $instrument,
            ];
        }
    }

    update_post_meta( $post_id, 'osm_files', $clean_files );

    // Move this piece's files into the private folder.
    foreach ( $clean_files as $f ) {
        osm_protect_attachment( $f['attachment_id'] );
    }

    }



//////////////////////////////
// FRONT END
//////////////////////////////

// Detect a link's platform from its host so we can show a matching icon.
function osm_detect_link_type($url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) return 'generic';
    $host = strtolower(preg_replace('/^www\./', '', $host));

    $domains = [
        'youtube'    => ['youtube.com', 'youtu.be'],
        'vimeo'      => ['vimeo.com'],
        'spotify'    => ['spotify.com'],
        'soundcloud' => ['soundcloud.com'],
        'apple'      => ['music.apple.com'],
        'wikipedia'  => ['wikipedia.org'],
    ];

    foreach ($domains as $type => $hosts) {
        foreach ($hosts as $d) {
            if ($host === $d || substr($host, -strlen('.' . $d)) === '.' . $d) {
                return $type;
            }
        }
    }
    return 'generic';
}

// Small inline SVG icons, one per known platform, plus a generic link fallback.
function osm_link_icon_svg($type) {
    $icons = [
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="4" fill="#FF0000"/><path d="M10 8.5l7 3.5-7 3.5z" fill="#fff"/></svg>',
        'vimeo' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="1" width="22" height="22" rx="5" fill="#1AB7EA"/><path d="M9 7.5l8 4.5-8 4.5z" fill="#fff"/></svg>',
        'spotify' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1DB954"/><path d="M7 10c4-1.2 8-.8 10.5.9M6.8 13.2c3.3-1 6.6-.7 8.9.7M7 16c2.6-.7 5-.5 6.7.6" stroke="#fff" stroke-width="1.4" fill="none" stroke-linecap="round"/></svg>',
        'soundcloud' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="1" width="22" height="22" rx="5" fill="#FF7700"/><path d="M6 12.5v5h11.5a2.75 2.75 0 000-5.5c-.15-2.3-2-4-4.2-4-1.4 0-2.7.7-3.4 1.9-.5-.5-1.2-.8-1.9-.8-1.3 0-2 1-2 1.4z" fill="#fff"/></svg>',
        'apple' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="1" width="22" height="22" rx="5" fill="#111"/><path d="M14.5 6.2v7.6a2.2 2.2 0 11-1.3-2V8.8L10 9.6v5a2.2 2.2 0 11-1.3-2V8l5.8-1.8z" fill="#fff"/></svg>',
        'wikipedia' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#fff" stroke="#888" stroke-width="1.2"/><text x="12" y="17" text-anchor="middle" font-family="Georgia, \'Times New Roman\', serif" font-size="13" font-weight="700" fill="#000">W</text></svg>',
        'generic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 14.5l5-5M8.8 16.2l-2 2a2.8 2.8 0 01-4-4l2-2m10.4 0l2-2a2.8 2.8 0 00-4-4l-2 2" stroke="#0073aa" stroke-width="1.8" fill="none" stroke-linecap="round"/></svg>',
    ];
    return isset($icons[$type]) ? $icons[$type] : $icons['generic'];
}

add_action('init', function(){
    $css_file = plugin_dir_path(__FILE__) . 'style.css';
    $css_url  = plugin_dir_url(__FILE__) . 'style.css';
    $version  = file_exists($css_file) ? filemtime($css_file) : false;

    wp_register_style('osm-styles', $css_url, [], $version);

    $js_file = plugin_dir_path(__FILE__) . 'frontend.js';
    wp_register_script(
        'osm-frontend',
        plugin_dir_url(__FILE__) . 'frontend.js',
        [],
        file_exists($js_file) ? filemtime($js_file) : false,
        ['in_footer' => true, 'strategy' => 'defer']
    );
}, 9);

add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style('osm-styles');
});

// Split a comma-separated season attribute into clean slugs.
function osm_parse_season_slugs($season) {
    if (empty($season)) return [];
    return array_values(array_filter(array_map('sanitize_title', explode(',', $season))));
}

// IDs of every piece in any of the given seasons (empty array if none).
function osm_get_season_post_ids($slugs) {
    $season_post_ids = [];

    foreach ($slugs as $slug) {
        $season_term = get_term_by('slug', $slug, 'season');
        if ($season_term) {
            $linked_post_ids = get_objects_in_term($season_term->term_id, 'season');
            if (!is_wp_error($linked_post_ids) && !empty($linked_post_ids)) {
                $season_post_ids = array_merge($season_post_ids, $linked_post_ids);
            }
        }
    }

    return array_unique(array_map('intval', $season_post_ids));
}

// Saved concert order for a season (array of piece IDs).
function osm_get_program_order($term_id) {
    $order = get_term_meta($term_id, 'osm_piece_order', true);
    return is_array($order) ? array_map('intval', $order) : [];
}

// Sort posts into a season's program order; pieces not in it go last, alphabetically.
function osm_sort_by_program_order($posts, $slug) {
    $term = get_term_by('slug', $slug, 'season');
    $order = $term ? osm_get_program_order($term->term_id) : [];
    if (!$order) return $posts;

    $position = array_flip($order);
    $last = count($order);
    usort($posts, function($a, $b) use ($position, $last) {
        $pa = isset($position[$a->ID]) ? $position[$a->ID] : $last;
        $pb = isset($position[$b->ID]) ? $position[$b->ID] : $last;
        return ($pa <=> $pb) ?: strcasecmp($a->post_title, $b->post_title);
    });
    return $posts;
}

add_shortcode('sheet_music_library','osm_shortcode_optimized');
function osm_shortcode_optimized($atts){
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts([
        'id'         => 0,          // single piece by post ID
        'instrument' => 0,          // optional instrument filter
        'season'     => '',         // optional season filter by slug
        'orderby'    => 'program',  // 'program' (season's concert order, when one season is shown) or 'title'
    ], $atts, 'sheet_music_library');

    // The filters are plain read-only GET parameters, so no nonce is needed
    // (a nonce here goes stale behind page caching and silently breaks the filter).
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $selected_instrument = isset($_GET['osm_instrument_filter'])
        ? intval(wp_unslash($_GET['osm_instrument_filter']))
        : intval($atts['instrument']);
    $search = isset($_GET['osm_search'])
        ? sanitize_text_field(wp_unslash($_GET['osm_search']))
        : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $args = [
        'post_type'      => 'sheet_music',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ];

    // Single post by ID
    if (!empty($atts['id'])) {
        $args['p'] = intval($atts['id']);
    }

    // Filter by season using a meta/post workaround (avoids tax_query)
    $slugs = osm_parse_season_slugs($atts['season']);
    if ($slugs) {
        $season_post_ids = osm_get_season_post_ids($slugs);

        if ( ! empty( $season_post_ids ) ) {
            $args['post__in'] = $season_post_ids;
        } else {
            // No posts match the season, return early
            return '<p>No sheet music found for this season.</p>';
        }
    }

    // Download links only go out on real page views, never in feeds or to
    // anonymous REST API readers (which can bypass page-protection plugins).
    if (!osm_download_links_allowed()) {
        return '<p>Sheet music is available on the website.</p>';
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<p>No sheet music found.</p>';

    if ($atts['orderby'] === 'program' && count($slugs) === 1 && empty($atts['id'])) {
        $query->posts = osm_sort_by_program_order($query->posts, $slugs[0]);
    }

    wp_enqueue_script('osm-frontend');

    // Name used for the "download all parts" zip file.
    $zip_name = 'Sheet music';
    if (!empty($atts['id'])) {
        $zip_name = get_the_title(intval($atts['id']));
    } elseif ($slugs) {
        $season_names = [];
        foreach ($slugs as $slug) {
            $season_term = get_term_by('slug', $slug, 'season');
            if ($season_term) $season_names[] = $season_term->name;
        }
        if ($season_names) $zip_name = implode(', ', $season_names);
    }

    // INSTRUMENT FILTER FORM
    $instruments = get_terms([
        'taxonomy'   => 'instrument',
        'hide_empty' => false,
        'orderby'    => 'name',
    ]);
    if (is_wp_error($instruments)) $instruments = [];

    // Parent of each instrument, so the front-end script can match parents/children.
    $instrument_parents = [];
    foreach ($instruments as $inst) {
        $instrument_parents[intval($inst->term_id)] = intval($inst->parent);
    }

    $select_id = 'osm_instrument_filter_' . $instance;
    $search_id = 'osm_search_' . $instance;

    $form  = '<form method="get" class="osm-filter-form">';
    $form .= '<div class="osm-filter-selectinstrument">';

    if ($instruments) {
        $tree = [];
        foreach($instruments as $inst){
            $tree[ intval($inst->parent) ][] = $inst;
        }

        $render_options = function($parent_id, $tree, $selected_id = 0, $level = 0) use (&$render_options) {
            $out = '';
            if (empty($tree[$parent_id])) return $out;

            usort($tree[$parent_id], function($a, $b) {
                $oa = intval(get_term_meta($a->term_id, 'instrument_order', true));
                $ob = intval(get_term_meta($b->term_id, 'instrument_order', true));
                if ($oa === $ob) return strcasecmp($a->name, $b->name);
                return $oa <=> $ob;
            });

            foreach ($tree[$parent_id] as $inst) {
                $prefix = ($level > 0) ? str_repeat('&mdash; ', $level) : '';
                $selected = ($inst->term_id == $selected_id) ? 'selected' : '';
                $out .= '<option value="'.intval($inst->term_id).'" '.$selected.'>'.$prefix.esc_html($inst->name).'</option>';

                if ($level < 1) { // only one generation of children
                    $out .= $render_options($inst->term_id, $tree, $selected_id, $level + 1);
                }
            }

            return $out;
        };

        $form .= '<span class="osm-filter-field">';
        $form .= '<label for="'.esc_attr($select_id).'">Select Instrument: </label>';
        $form .= '<select name="osm_instrument_filter" id="'.esc_attr($select_id).'" class="osm-instrument-select">';
        $form .= '<option value="">All Instruments</option>';
        $form .= $render_options(0, $tree, $selected_instrument);
        $form .= '</select>';
        $form .= '</span> ';
    }

    $form .= '<span class="osm-filter-field">';
    $form .= '<label for="'.esc_attr($search_id).'">Search: </label>';
    $form .= '<input type="search" name="osm_search" id="'.esc_attr($search_id).'" class="osm-search" value="'.esc_attr($search).'" placeholder="Title or composer" />';
    $form .= '</span> ';
    $form .= '<button type="submit" class="button osm-filter-submit">Filter</button>';
    $form .= '</div>';
    $form .= '<div class="osm-download-all-wrap">';
    $form .= '<button type="button" class="button osm-download-all" hidden>Download all parts</button>';
    $form .= '<span class="osm-download-status" aria-live="polite"></span>';
    $form .= '</div>';
    $form .= '</form>';

    // Build full instrument ID list (selected + children + parents)
    $instrument_ids = [];
    if($selected_instrument){
        $instrument_ids[] = $selected_instrument;

        $children = get_term_children($selected_instrument, 'instrument');
        if($children && !is_wp_error($children))
            $instrument_ids = array_merge($instrument_ids, $children);

        // Correct parent loop
        $term = get_term($selected_instrument, 'instrument');
        $parent_id = ($term && !is_wp_error($term)) ? intval($term->parent) : 0;
        while($parent_id){
            $instrument_ids[] = $parent_id;
            $term = get_term($parent_id, 'instrument');
            $parent_id = ($term && !is_wp_error($term)) ? intval($term->parent) : 0;
        }
    }

    // Instrument name for a file, or 'Misc' when unassigned or the term was deleted.
    $instrument_name = function($term_id) {
        $term = $term_id ? get_term($term_id, 'instrument') : null;
        return ($term && !is_wp_error($term)) ? $term->name : 'Misc';
    };

    // SHEET MUSIC RECORDS
    // Everything is rendered; pieces and files outside the current filter are
    // marked hidden so the front-end script can re-filter without a reload.
    $pieces = '';
    $shown_pieces = 0;

    while($query->have_posts()){
        $query->the_post();
        $files = get_post_meta(get_the_ID(), 'osm_files', true);
        if(!$files) continue;

        $title    = get_the_title();
        $composer = get_post_meta(get_the_ID(), 'osm_composer', true);
        $notes    = get_post_meta(get_the_ID(), 'osm_notes', true);
        $links    = get_post_meta(get_the_ID(), 'osm_links', true);
        if(!is_array($links)) $links = [];
        $last_updated = get_the_modified_date('F j, Y');

        // Group & sort files by instrument order
        $files_grouped = [];
        foreach($files as $f) $files_grouped[$f['instrument']][] = $f;
        uasort($files_grouped, function($a, $b) use ($instrument_name){
            $order_a = intval(get_term_meta($a[0]['instrument'], 'instrument_order', true));
            $order_b = intval(get_term_meta($b[0]['instrument'], 'instrument_order', true));
            if($order_a === $order_b){
                return strcasecmp($instrument_name($a[0]['instrument']), $instrument_name($b[0]['instrument']));
            }
            return $order_a - $order_b;
        });

        $all_files_sorted = [];
        foreach($files_grouped as $fgroup){
            foreach($fgroup as $f){
                $all_files_sorted[] = $f;
            }
        }

        // File buttons
        $buttons = '';
        $shown_files = 0;
        foreach($all_files_sorted as $f){
            $path = get_attached_file($f['attachment_id']);
            if(!$path) continue;
            $url = osm_file_url($f['attachment_id']);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $shown = !$instrument_ids || in_array($f['instrument'], $instrument_ids);
            if ($shown) $shown_files++;
            $buttons .= '<a href="'.esc_url($url).'" class="button osm-file" data-instrument="'.intval($f['instrument']).'" data-ext="'.esc_attr($ext ? '.'.$ext : '').'" target="_blank" rel="nofollow"'.($shown ? '' : ' hidden').'>'.esc_html($instrument_name($f['instrument'])).'</a> ';
        }

        $matches_search = $search === '' || stripos($title . ' ' . $composer, $search) !== false;
        $piece_shown = $matches_search && (!$instrument_ids || $shown_files);
        if ($piece_shown) $shown_pieces++;

        $pieces .= '<div class="osm-piece" data-published="'.intval(get_post_time('U', true)).'" data-modified="'.intval(get_post_modified_time('U', true)).'"'.($piece_shown ? '' : ' hidden').'>';
        $pieces .= '<h3><span class="osm-piece-title">'.esc_html($title).'</span></h3>';
        if($composer) $pieces .= '<span class="osm-meta-composer">'.esc_html($composer).'</span><br>';

        $pieces .= '<div class="osm-file-buttons">'.$buttons.'</div>';

        if($notes) $pieces .= '<span class="osm-meta">Notes: '.esc_html($notes).'</span><br>';

        if($links){
            $pieces .= '<div class="osm-links"><ul class="osm-links-list">';
            foreach($links as $link){
                if(empty($link['url'])) continue;
                $label = !empty($link['title']) ? $link['title'] : $link['url'];
                $type  = osm_detect_link_type($link['url']);
                $icon  = osm_link_icon_svg($type);
                $pieces .= '<li>';
                $pieces .= '<span class="osm-link-icon">'.$icon.'</span>';
                $pieces .= '<a href="'.esc_url($link['url']).'" class="osm-link osm-link-'.esc_attr($type).'" target="_blank" rel="noopener noreferrer">';
                $pieces .= '<span class="osm-link-label">'.esc_html($label).'</span>';
                $pieces .= '</a></li>';
            }
            $pieces .= '</ul></div>';
        }

        $pieces .= '<span class="osm-meta-updated">Updated: '.esc_html($last_updated).'</span><br>';
        $pieces .= '</div>';
    }

    wp_reset_postdata();

    $output  = '<div class="osm-library" data-instrument-parents="'.esc_attr(wp_json_encode((object) $instrument_parents)).'" data-zip-name="'.esc_attr($zip_name).'">';
    $output .= $form;
    $output .= $pieces;
    $output .= '<p class="osm-no-results"'.($shown_pieces ? ' hidden' : '').'>No pieces match your selection.</p>';
    $output .= '</div>';

    return $output;
}

//////////////////////////////
// COLLECTION LAST UPDATED
//////////////////////////////

// Editing a piece doesn't change the modified date of the page showing it, and
// removing a piece from a collection doesn't touch any remaining piece. So we
// record our own "last changed" timestamps: one for the whole library (option)
// and one per season (term meta), bumped whenever a published piece changes.

function osm_touch_collection($post_id, $season_term_ids = null) {
    $now = time();
    update_option('osm_library_last_updated', $now, false);

    if ($season_term_ids === null) {
        $season_term_ids = wp_get_object_terms($post_id, 'season', ['fields' => 'ids']);
    }
    if (is_wp_error($season_term_ids)) return;

    foreach ($season_term_ids as $term_id) {
        update_term_meta(intval($term_id), 'osm_last_updated', $now);
    }
}

// Published, edited, unpublished or trashed.
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($post->post_type !== 'sheet_music') return;
    if ($new_status !== 'publish' && $old_status !== 'publish') return;
    osm_touch_collection($post->ID);
}, 10, 3);

// Seasons added to / removed from a published piece: both sides changed.
add_action('set_object_terms', function($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
    if ($taxonomy !== 'season') return;
    if (get_post_type($object_id) !== 'sheet_music' || get_post_status($object_id) !== 'publish') return;

    $changed_tt_ids = array_merge(array_diff($tt_ids, $old_tt_ids), array_diff($old_tt_ids, $tt_ids));
    if (!$changed_tt_ids) return;

    $term_ids = get_terms([
        'taxonomy'         => 'season',
        'term_taxonomy_id' => array_map('intval', $changed_tt_ids),
        'hide_empty'       => false,
        'fields'           => 'ids',
    ]);
    osm_touch_collection($object_id, $term_ids);
}, 10, 6);

// Permanently deleted while still published (skipping the trash).
add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) === 'sheet_music' && get_post_status($post_id) === 'publish') {
        osm_touch_collection($post_id);
    }
});

// Unix timestamp of the last change to a collection, or 0 if it has none.
// $season is a comma-separated slug list (empty = whole library); $id is a single piece.
function osm_get_collection_last_updated($season = '', $id = 0) {
    $id = intval($id);
    if ($id) {
        return get_post_status($id) === 'publish' ? intval(get_post_modified_time('U', true, $id)) : 0;
    }

    $stamps = [0];
    $query_args = [
        'post_type'      => 'sheet_music',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ];

    $slugs = osm_parse_season_slugs($season);
    if ($slugs) {
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, 'season');
            if ($term) $stamps[] = intval(get_term_meta($term->term_id, 'osm_last_updated', true));
        }
        $post_ids = osm_get_season_post_ids($slugs);
        if (!$post_ids) return max($stamps);
        $query_args['post__in'] = $post_ids;
    } else {
        $stamps[] = intval(get_option('osm_library_last_updated', 0));
    }

    // Fallback for pieces saved before timestamps were tracked.
    $latest = get_posts($query_args);
    if ($latest) $stamps[] = intval(get_post_modified_time('U', true, $latest[0]));

    return max($stamps);
}

// Every collection (block or shortcode) embedded in a piece of content.
function osm_find_collections_in_content($content) {
    $collections = [];

    if (has_blocks($content)) {
        $walk = function($blocks) use (&$walk, &$collections) {
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'sheet-music-librarian/library') {
                    $collections[] = [
                        'season' => isset($block['attrs']['season']) ? $block['attrs']['season'] : '',
                        'id'     => isset($block['attrs']['pieceId']) ? $block['attrs']['pieceId'] : 0,
                    ];
                }
                if (!empty($block['innerBlocks'])) $walk($block['innerBlocks']);
            }
        };
        $walk(parse_blocks($content));
    }

    if (has_shortcode($content, 'sheet_music_library')) {
        preg_match_all('/' . get_shortcode_regex(['sheet_music_library']) . '/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            if ($m[1] === '[' && $m[6] === ']') continue; // escaped [[shortcode]]
            $atts = shortcode_parse_atts($m[3]);
            if (!is_array($atts)) $atts = [];
            $collections[] = [
                'season' => isset($atts['season']) ? $atts['season'] : '',
                'id'     => isset($atts['id']) ? $atts['id'] : 0,
            ];
        }
    }

    return $collections;
}

// Markup for the "last updated" notice. With $match_page, it uses the
// collections found on $post_id (falling back to the whole library if none).
function osm_render_last_updated($match_page, $season, $label, $post_id, $wrapper_attributes = 'class="osm-last-updated"') {
    $collections = [['season' => $season, 'id' => 0]];

    if ($match_page) {
        $post = $post_id ? get_post($post_id) : null;
        $found = $post ? osm_find_collections_in_content($post->post_content) : [];
        if ($found) $collections = $found;
    }

    $timestamp = 0;
    foreach ($collections as $c) {
        $timestamp = max($timestamp, osm_get_collection_last_updated($c['season'], $c['id']));
    }
    if (!$timestamp) return '';

    return sprintf(
        '<p %s>%s <time datetime="%s">%s</time></p>',
        $wrapper_attributes,
        esc_html($label),
        esc_attr(wp_date('c', $timestamp)),
        esc_html(wp_date(get_option('date_format'), $timestamp))
    );
}

function osm_current_post_id() {
    return is_singular() ? get_queried_object_id() : get_the_ID();
}

add_shortcode('sheet_music_last_updated', 'osm_last_updated_shortcode');
function osm_last_updated_shortcode($atts) {
    $atts = shortcode_atts([
        'season' => '',  // empty = match the collections on this page
        'label'  => 'Music library last updated:',
    ], $atts, 'sheet_music_last_updated');

    return osm_render_last_updated(empty($atts['season']), $atts['season'], $atts['label'], osm_current_post_id());
}

//////////////////////////////
// FILE PROTECTION
//////////////////////////////

// Sheet music files live in a private uploads folder (one unguessable
// subfolder per file) and are only served through expiring, signed links that
// appear on the library page. Page access itself is left to whatever protects
// the page (e.g. a password plugin): only people who can see the page get links.

define('OSM_LINK_WINDOW', 14 * DAY_IN_SECONDS); // links last 14-28 days

// phpcs:disable WordPress.WP.AlternativeFunctions -- direct file moves inside uploads

function osm_private_dir() {
    $uploads = wp_upload_dir(null, false);
    return [
        'path' => wp_normalize_path(trailingslashit($uploads['basedir']) . 'sheet-music-private'),
        'url'  => trailingslashit($uploads['baseurl']) . 'sheet-music-private',
    ];
}

function osm_ensure_private_dir() {
    $dir = osm_private_dir()['path'];
    if (!wp_mkdir_p($dir)) return false;

    $files = [
        '.htaccess' => "# Sheet Music Librarian: files are served through the plugin, never directly.\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
        'index.php' => "<?php\n// Silence is golden.\n",
        'osm-access-test.txt' => 'osm-access-test',
    ];
    foreach ($files as $name => $contents) {
        if (!file_exists("$dir/$name")) file_put_contents("$dir/$name", $contents);
    }
    return true;
}

function osm_is_protected_file($path) {
    return strpos(wp_normalize_path($path), osm_private_dir()['path'] . '/') === 0;
}

// Move an attachment (and any generated preview images) into its own private
// subfolder. Returns true if the file is protected afterwards.
function osm_protect_attachment($attachment_id) {
    $attachment_id = intval($attachment_id);
    $file = $attachment_id ? get_attached_file($attachment_id, true) : '';
    if (!$file || !file_exists($file)) return false;

    if (osm_is_protected_file($file)) {
        update_post_meta($attachment_id, '_osm_protected', 1);
        return true;
    }
    if (!osm_ensure_private_dir()) return false;

    $new_dir = osm_private_dir()['path'] . '/' . bin2hex(random_bytes(12));
    if (!wp_mkdir_p($new_dir)) return false;

    $old_dir  = dirname($file);
    $new_file = $new_dir . '/' . basename($file);
    if (!rename($file, $new_file)) {
        rmdir($new_dir);
        return false;
    }

    // Generated sizes (PDF previews, image thumbnails) sit beside the original.
    $meta = wp_get_attachment_metadata($attachment_id);
    if (is_array($meta)) {
        $extra = !empty($meta['original_image']) ? [$meta['original_image']] : [];
        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            foreach ($meta['sizes'] as $size) {
                if (!empty($size['file'])) $extra[] = $size['file'];
            }
        }
        foreach (array_unique(array_map('basename', $extra)) as $name) {
            if (file_exists("$old_dir/$name")) rename("$old_dir/$name", "$new_dir/$name");
        }
        if (!empty($meta['file'])) {
            $meta['file'] = _wp_relative_upload_path($new_file);
            wp_update_attachment_metadata($attachment_id, $meta);
        }
    }

    update_attached_file($attachment_id, $new_file);
    update_post_meta($attachment_id, '_osm_protected', 1);
    return true;
}

// Remove a protected file's empty subfolder after the attachment is deleted.
add_action('delete_attachment', function($attachment_id) {
    $file = get_attached_file($attachment_id, true);
    if ($file && osm_is_protected_file($file)) {
        $dir = dirname($file);
        add_action('deleted_post', function() use ($dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) rmdir($dir);
        });
    }
});

// phpcs:enable WordPress.WP.AlternativeFunctions

// Every attachment used by any sheet music piece.
function osm_all_sheet_music_attachment_ids() {
    $ids = [];
    $piece_ids = get_posts([
        'post_type'      => 'sheet_music',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    foreach ($piece_ids as $piece_id) {
        $files = get_post_meta($piece_id, 'osm_files', true);
        if (!is_array($files)) continue;
        foreach ($files as $f) {
            if (!empty($f['attachment_id'])) $ids[] = intval($f['attachment_id']);
        }
    }
    return array_values(array_unique($ids));
}

// Signed links.

function osm_file_signature($attachment_id, $expires) {
    return substr(hash_hmac('sha256', intval($attachment_id) . '|' . intval($expires), wp_salt('auth') . 'osm_file'), 0, 32);
}

// Expiry is rounded to the window so a page's links stay identical for days
// at a time (friendly to caches) and always last at least one full window.
function osm_file_url($attachment_id) {
    $expires = (intdiv(time(), OSM_LINK_WINDOW) + 2) * OSM_LINK_WINDOW;
    return add_query_arg([
        'osm_file'    => intval($attachment_id),
        'osm_expires' => $expires,
        'osm_sig'     => osm_file_signature($attachment_id, $expires),
    ], home_url('/'));
}

function osm_download_links_allowed() {
    if (is_feed()) return false;
    if (wp_is_serving_rest_request() && !current_user_can('edit_posts')) return false;
    return true;
}

add_action('init', 'osm_serve_file', 99);
function osm_serve_file() {
    // Signed links carry their own authorization, so no nonce.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (!isset($_GET['osm_file'])) return;

    $attachment_id = absint(wp_unslash($_GET['osm_file']));
    $expires       = isset($_GET['osm_expires']) ? absint(wp_unslash($_GET['osm_expires'])) : 0;
    $signature     = isset($_GET['osm_sig']) ? sanitize_text_field(wp_unslash($_GET['osm_sig'])) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    header('X-Robots-Tag: noindex, nofollow, noarchive');

    if (!$attachment_id || $expires < time() || !hash_equals(osm_file_signature($attachment_id, $expires), $signature)) {
        wp_die(
            'This download link has expired. Please go back to the music library page and download the file again.',
            'Link expired',
            ['response' => 403]
        );
    }

    $file = get_post_type($attachment_id) === 'attachment' ? get_attached_file($attachment_id) : '';
    if (!$file || !is_readable($file)) {
        wp_die('Sorry, this file could not be found.', 'File not found', ['response' => 404]);
    }

    // Only show safe types inline; anything else downloads.
    $type   = get_post_mime_type($attachment_id) ?: 'application/octet-stream';
    $inline = preg_match('#^(application/pdf|image/(jpeg|png|gif|webp)|audio/)#', $type);
    $name   = basename($file);

    while (ob_get_level()) ob_end_clean();
    nocache_headers();
    header('Cache-Control: private, max-age=3600');
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');

    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($file); // phpcs:ignore WordPress.WP.AlternativeFunctions
    }
    exit;
}

// Close side doors to protected files.

// Pages showing a library are never cached, so links only reach people who can see the page.
add_action('template_redirect', function() {
    if (is_singular() && osm_find_collections_in_content(get_post_field('post_content', get_queried_object_id()))) {
        nocache_headers();
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    }
});

// Attachment pages for protected files would reveal the file's address (and newer
// WordPress redirects them straight to the file), so 404 them before that runs.
add_action('template_redirect', function() {
    if (is_attachment() && get_post_meta(get_queried_object_id(), '_osm_protected', true) && !current_user_can('upload_files')) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
}, 1);

// Hide protected files from the public media REST API.
add_filter('rest_attachment_query', function($args) {
    if (current_user_can('upload_files')) return $args;
    $args['meta_query'] = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
    $args['meta_query'][] = ['key' => '_osm_protected', 'compare' => 'NOT EXISTS'];
    return $args;
});

add_filter('rest_request_before_callbacks', function($response, $handler, $request) {
    if (is_wp_error($response) || current_user_can('upload_files')) return $response;
    if (preg_match('#^/wp/v2/media/(\d+)#', $request->get_route(), $m) && get_post_meta(intval($m[1]), '_osm_protected', true)) {
        return new WP_Error('rest_post_invalid_id', 'Invalid post ID.', ['status' => 404]);
    }
    return $response;
}, 10, 3);

// Whether the private folder can be opened directly: 'blocked', 'open' or 'unknown'.
function osm_check_direct_access($force = false) {
    $cached = get_transient('osm_direct_access');
    if ($cached && !$force) return $cached;

    osm_ensure_private_dir();
    $response = wp_remote_get(osm_private_dir()['url'] . '/osm-access-test.txt', [
        'timeout'     => 10,
        'redirection' => 0,
        'sslverify'   => apply_filters('https_local_ssl_verify', false),
    ]);

    if (is_wp_error($response)) {
        $status = 'unknown';
    } elseif (wp_remote_retrieve_response_code($response) === 200 && trim(wp_remote_retrieve_body($response)) === 'osm-access-test') {
        $status = 'open';
    } else {
        $status = 'blocked';
    }

    set_transient('osm_direct_access', $status, DAY_IN_SECONDS);
    return $status;
}

// Admin screen: Sheet Music > File Protection.

add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=sheet_music',
        'File Protection',
        'File Protection',
        'manage_sheet_music_files',
        'osm-file-protection',
        'osm_render_protection_page'
    );
});

function osm_protection_counts() {
    $ids = osm_all_sheet_music_attachment_ids();
    $unprotected = array_values(array_filter($ids, function($id) {
        $file = get_attached_file($id, true);
        return $file && file_exists($file) && !osm_is_protected_file($file);
    }));
    return ['total' => count($ids), 'unprotected' => $unprotected];
}

add_action('admin_post_osm_protect_files', function() {
    if (!current_user_can('manage_sheet_music_files')) wp_die('Sorry, you are not allowed to do that.');
    check_admin_referer('osm_protect_files');

    $moved = 0;
    $failed = 0;
    $started = time();
    foreach (osm_protection_counts()['unprotected'] as $attachment_id) {
        if (time() - $started > 20) break; // stay well inside PHP time limits; run again for the rest
        osm_protect_attachment($attachment_id) ? $moved++ : $failed++;
    }
    osm_check_direct_access(true);

    wp_safe_redirect(add_query_arg([
        'post_type' => 'sheet_music',
        'page'      => 'osm-file-protection',
        'moved'     => $moved,
        'failed'    => $failed,
    ], admin_url('edit.php')));
    exit;
});

add_action('admin_post_osm_check_access', function() {
    if (!current_user_can('manage_sheet_music_files')) wp_die('Sorry, you are not allowed to do that.');
    check_admin_referer('osm_check_access');
    osm_check_direct_access(true);
    wp_safe_redirect(admin_url('edit.php?post_type=sheet_music&page=osm-file-protection'));
    exit;
});

function osm_render_protection_page() {
    $counts = osm_protection_counts();
    $remaining = count($counts['unprotected']);
    $access = osm_check_direct_access();
    $uploads_path = wp_parse_url(osm_private_dir()['url'], PHP_URL_PATH);
    ?>
    <style>
        .osm-protection .osm-ok { color: #00a32a; }
        .osm-protection .osm-warn { color: #dba617; }
        .osm-protection .osm-code { background: #fff; border: 1px solid #ddd; padding: 10px 14px; max-width: 600px; }
    </style>
    <div class="wrap osm-protection">
        <h1>File Protection</h1>

        <?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only result counts ?>
        <?php if (isset($_GET['moved'])) : ?>
            <div class="notice notice-success"><p>
                Protected <?php echo intval($_GET['moved']); ?> file(s).
                <?php if (!empty($_GET['failed'])) : ?>
                    <?php echo intval($_GET['failed']); ?> could not be moved; check that the uploads folder is writable.
                <?php endif; ?>
            </p></div>
        <?php endif; ?>
        <?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

        <p>Sheet music files are kept in a private folder and downloaded through links that only appear on your music library page and expire after a few weeks. Members using the page notice no difference; links copied elsewhere, or picked up by search engines, stop working.</p>

        <h2>Files</h2>
        <?php if ($remaining) : ?>
            <p><strong><?php echo intval($remaining); ?> of <?php echo intval($counts['total']); ?></strong> sheet music files are still in the public uploads folder, where anyone with the address can open them.</p>
            <p>Moving them changes their direct addresses, so any old direct links (including ones search engines have indexed) stop working. Links on your library page update automatically.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="osm_protect_files" />
                <?php wp_nonce_field('osm_protect_files'); ?>
                <?php submit_button('Protect ' . $remaining . ' file' . ($remaining === 1 ? '' : 's'), 'primary', 'submit', false); ?>
            </form>
        <?php else : ?>
            <p><span class="dashicons dashicons-yes-alt osm-ok"></span> All <?php echo intval($counts['total']); ?> sheet music files are protected. New files are protected automatically when you save a piece.</p>
        <?php endif; ?>

        <h2>Direct access check</h2>
        <?php if ($access === 'blocked') : ?>
            <p><span class="dashicons dashicons-yes-alt osm-ok"></span> Your server blocks direct access to the private folder.</p>
        <?php elseif ($access === 'open') : ?>
            <p><span class="dashicons dashicons-warning osm-warn"></span> <strong>Your server allows direct access to the private folder.</strong> This is usual on Nginx servers, which ignore the folder's protection file. Files still have unguessable addresses and are hidden from search engines, but for full protection ask your host to add this rule to the site's Nginx configuration:</p>
            <pre class="osm-code">location ^~ <?php echo esc_html(trailingslashit($uploads_path)); ?> {
    deny all;
}</pre>
        <?php else : ?>
            <p><span class="dashicons dashicons-info osm-warn"></span> The check couldn't reach your site from the server (some hosts block this). To test manually, open <a href="<?php echo esc_url(osm_private_dir()['url'] . '/osm-access-test.txt'); ?>" target="_blank" rel="noopener">this test file</a> in a private browser window: an error page means you're protected.</p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="osm_check_access" />
            <?php wp_nonce_field('osm_check_access'); ?>
            <?php submit_button('Check again', 'secondary', 'submit', false); ?>
        </form>

        <h2>Already in Google?</h2>
        <p>Old addresses stop working once files are protected, and search engines drop them over the following weeks. To hide them sooner, use the Removals tool in <a href="https://search.google.com/search-console/removals" target="_blank" rel="noopener">Google Search Console</a>.</p>
    </div>
    <?php
}

// Nudge on the Sheet Music list screen when something needs attention.
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-sheet_music' || !current_user_can('manage_sheet_music_files')) return;

    $remaining = count(osm_protection_counts()['unprotected']);
    $open = get_transient('osm_direct_access') === 'open';
    if (!$remaining && !$open) return;

    $url = admin_url('edit.php?post_type=sheet_music&page=osm-file-protection');
    echo '<div class="notice notice-warning"><p>';
    if ($remaining) {
        echo esc_html($remaining . ' sheet music file' . ($remaining === 1 ? ' is' : 's are') . ' not protected yet.') . ' ';
    } else {
        echo 'Your server allows direct access to protected sheet music files. ';
    }
    echo '<a href="' . esc_url($url) . '">Review file protection</a></p></div>';
});

//////////////////////////////
// BLOCKS
//////////////////////////////

add_action('init', 'osm_register_blocks');
function osm_register_blocks() {
    $js_file = plugin_dir_path(__FILE__) . 'blocks/editor.js';
    wp_register_script(
        'osm-blocks-editor',
        plugin_dir_url(__FILE__) . 'blocks/editor.js',
        ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-server-side-render'],
        file_exists($js_file) ? filemtime($js_file) : false,
        true
    );

    register_block_type(plugin_dir_path(__FILE__) . 'blocks/library', [
        'render_callback' => 'osm_render_library_block',
    ]);
    register_block_type(plugin_dir_path(__FILE__) . 'blocks/last-updated', [
        'render_callback' => 'osm_render_last_updated_block',
    ]);
}

// Seasons and pieces for the block editor's dropdowns.
add_action('enqueue_block_editor_assets', function() {
    $seasons = get_terms(['taxonomy' => 'season', 'hide_empty' => false]);
    $pieces  = get_posts([
        'post_type'      => 'sheet_music',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $data = [
        'seasons' => is_wp_error($seasons) ? [] : array_map(function($t) {
            return ['slug' => $t->slug, 'name' => $t->name];
        }, $seasons),
        'pieces'  => array_map(function($p) {
            return ['id' => $p->ID, 'title' => $p->post_title];
        }, $pieces),
    ];

    wp_add_inline_script('osm-blocks-editor', 'window.osmBlockData = ' . wp_json_encode($data) . ';', 'before');
});

function osm_render_library_block($attributes) {
    $piece_id = intval($attributes['pieceId']);
    $output = osm_shortcode_optimized([
        'id'      => $piece_id,
        'season'  => $piece_id ? '' : $attributes['season'],
        'orderby' => $attributes['orderby'],
    ]);
    return '<div ' . get_block_wrapper_attributes() . '>' . $output . '</div>';
}

function osm_render_last_updated_block($attributes, $content, $block) {
    $post_id = !empty($block->context['postId']) ? $block->context['postId'] : osm_current_post_id();

    return osm_render_last_updated(
        !empty($attributes['matchPage']),
        $attributes['season'],
        $attributes['label'],
        $post_id,
        // The editor preview already wraps this in the block's own wrapper.
        wp_is_serving_rest_request() ? 'class="osm-last-updated"' : get_block_wrapper_attributes(['class' => 'osm-last-updated'])
    );
}
