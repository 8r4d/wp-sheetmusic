<?php
/**
 * Plugin Name: Sheet Music Librarian
 * Description: Manage and display sheet music pieces with instrument files, composer, season, notes, and last updated info.
 * Version: 1.0.0
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
        'capability_type' => 'post',
        'supports' => ['title'],
        'rewrite' => false,
    ];

    register_post_type('sheet_music', $args);
}

// Register Instrument Taxonomy

add_action('init', 'osm_register_instrument_taxonomy');
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
    ];

    register_taxonomy('season', ['sheet_music'], $args);
}

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
    if (in_array($hook, ['post.php', 'post-new.php'])) {
        $css_file = plugin_dir_path(__FILE__) . 'admin-style.css';
        $css_url  = plugin_dir_url(__FILE__) . 'admin-style.css';
        $version  = file_exists($css_file) ? filemtime($css_file) : false;

        wp_enqueue_style('osm-admin-style', $css_url, [], $version);
        wp_enqueue_media(); 
    }
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

add_action('admin_enqueue_scripts', function($hook) {
    if (in_array($hook, ['post.php', 'post-new.php'])) {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }
});

add_action('instrument_edit_form_fields', 'osm_instrument_order_field');
function osm_instrument_order_field($term){
    $order = get_term_meta($term->term_id, 'instrument_order', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="instrument_order">Sort Order</label></th>
        <td>
            <input type="number" name="instrument_order" id="instrument_order" value="<?php echo esc_attr($order ?: 0); ?>" style="width:60px;" />
            <p class="description">Enter a number to control the display order on the frontend (smaller numbers first).</p>
        </td>
    </tr>
    <?php
}

add_action('edited_instrument', 'osm_save_instrument_order');
function osm_save_instrument_order($term_id){
    // Verify nonce from the edit form
    if ( ! isset( $_POST['_wpnonce'] ) || ! check_admin_referer( 'edit-tag_' . $term_id ) ) {
        return;
    }

    if ( isset($_POST['instrument_order']) ) {
        $order = intval( wp_unslash($_POST['instrument_order']) );
        update_term_meta($term_id, 'instrument_order', $order);
    }
}


function osm_render_combined_meta_box($post) {
    wp_nonce_field('osm_save_sheet_combined', 'osm_sheet_combined_nonce');

    $composer = get_post_meta($post->ID, 'osm_composer', true);
    $season   = get_post_meta($post->ID, 'osm_season', true);
    $notes    = get_post_meta($post->ID, 'osm_notes', true);

    echo '<p><label>Composer: <input type="text" name="osm_composer" value="'.esc_attr($composer).'" style="width:100%;" /></label></p>';
    echo '<p><label>Notes:<br><textarea name="osm_notes" rows="4" style="width:100%;">'.esc_textarea($notes).'</textarea></label></p>';

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

    // SHORTCODE HINTS
    $seasons = get_the_terms($post->ID, 'season');

    echo '<div class="osm-shortcode-box">';
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

    echo '</div>';

    ?>
    <script>
    jQuery(document).ready(function($){
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

    // Save selected seasons (taxonomy terms)
    if (isset($_POST['osm_season']) && is_array($_POST['osm_season'])) {
        $season_ids = array_map('intval', wp_unslash($_POST['osm_season']));
        wp_set_post_terms($post_id, $season_ids, 'season');
    }

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

    }



//////////////////////////////
// FRONT END
//////////////////////////////

add_action('wp_enqueue_scripts', function(){
    $css_file = plugin_dir_path(__FILE__) . 'style.css';
    $css_url  = plugin_dir_url(__FILE__) . 'style.css';
    $version  = file_exists($css_file) ? filemtime($css_file) : false;

    wp_enqueue_style('osm-styles', $css_url, [], $version);
});

add_shortcode('sheet_music_library','osm_shortcode_optimized');
function osm_shortcode_optimized($atts){
    $atts = shortcode_atts([
        'id'         => 0,   // single piece by post ID
        'instrument' => 0,   // optional instrument filter
        'season'     => '',  // optional season filter by slug
    ], $atts, 'sheet_music_library');

    $selected_instrument = isset($_GET['osm_instrument_filter']) 
        ? intval($_GET['osm_instrument_filter']) 
        : intval($atts['instrument']);

    // Build a transient cache key
    $cache_key = 'osm_sheet_music_' . md5(serialize($atts) . '_' . $selected_instrument);
    $output = get_transient($cache_key);
    if ($output !== false) return $output;

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
    if ( ! empty( $atts['season'] ) ) {
        $slugs = array_map( 'sanitize_title', explode( ',', $atts['season'] ) );
        $season_post_ids = [];

        foreach ( $slugs as $slug ) {
            $season_term = get_term_by( 'slug', $slug, 'season' );
            if ( $season_term ) {
                $linked_post_ids = get_objects_in_term( $season_term->term_id, 'season' );
                if ( ! is_wp_error( $linked_post_ids ) && ! empty( $linked_post_ids ) ) {
                    $season_post_ids = array_merge( $season_post_ids, $linked_post_ids );
                }
            }
        }

        $season_post_ids = array_unique( $season_post_ids );

        if ( ! empty( $season_post_ids ) ) {
            $args['post__in'] = $season_post_ids;
        } else {
            // No posts match the season, return early
            return '<p>No sheet music found for this season.</p>';
        }
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<p>No sheet music found.</p>';

    $output = '';

    // INSTRUMENT FILTER FORM 
    $instruments = get_terms([
        'taxonomy'   => 'instrument',
        'hide_empty' => false,
        'orderby'    => 'term_order',
    ]);

    if($instruments && !is_wp_error($instruments)){
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

        // Create a nonce for this filter form
        $filter_nonce = wp_create_nonce('osm_instrument_filter');

        $output .= '<form method="get" class="osm-filter-form">';
        $output .= '<div class="osm-filter-selectinstrument">';
        $output .= '<label for="osm_instrument_filter">Select Instrument: </label>';
        $output .= '<select name="osm_instrument_filter" id="osm_instrument_filter">';
        $output .= '<option value="">All Instruments</option>';
        $output .= $render_options(0, $tree, $selected_instrument);
        $output .= '</select> ';
        $output .= '<input type="hidden" name="osm_instrument_filter_nonce" value="'.esc_attr($filter_nonce).'" />';
        $output .= '<button type="submit" class="button">Filter</button>';
        $output .= '</div></form>';

        // Process the submitted filter safely
        $instrument_raw = isset($_GET['osm_instrument_filter']) 
            ? intval( wp_unslash($_GET['osm_instrument_filter']) ) 
            : 0;

        $nonce_raw = isset($_GET['osm_instrument_filter_nonce']) 
            ? sanitize_text_field( wp_unslash($_GET['osm_instrument_filter_nonce']) ) 
            : '';

        if ( $nonce_raw && wp_verify_nonce( $nonce_raw, 'osm_instrument_filter' ) ) {
            $selected_instrument = intval( $instrument_raw );
        } else {
            $selected_instrument = 0; // invalid or missing nonce, ignore input
        }

    }

    // Build full instrument ID list (selected + children + parents)
    $instrument_ids = [];
    if($selected_instrument){
        $instrument_ids[] = $selected_instrument;

        $children = get_term_children($selected_instrument, 'instrument');
        if($children && !is_wp_error($children))
            $instrument_ids = array_merge($instrument_ids, $children);

        // Correct parent loop
        $term = get_term($selected_instrument, 'instrument');
        $parent_id = $term ? intval($term->parent) : 0;
        while($parent_id){
            $instrument_ids[] = $parent_id;
            $term = get_term($parent_id, 'instrument');
            $parent_id = $term ? intval($term->parent) : 0;
        }
    }

    // SHEET MUSIC RECORDS 
    while($query->have_posts()){
        $query->the_post();
        $files = get_post_meta(get_the_ID(), 'osm_files', true);
        if(!$files) continue;

        $composer = get_post_meta(get_the_ID(), 'osm_composer', true);
        $notes    = get_post_meta(get_the_ID(), 'osm_notes', true);
        $last_updated = get_post_modified_time('F j, Y', true, get_the_ID());

        $output .= '<div class="osm-piece">';
        $output .= '<h3>'.get_the_title().'</h3>';
        if($composer) $output .= '<span class="osm-meta-composer">'.esc_html($composer).'</span><br>';

        // Group & sort files by instrument order
        $files_grouped = [];
        foreach($files as $f) $files_grouped[$f['instrument']][] = $f;
        uasort($files_grouped, function($a, $b){
            $order_a = intval(get_term_meta($a[0]['instrument'], 'instrument_order', true));
            $order_b = intval(get_term_meta($b[0]['instrument'], 'instrument_order', true));
            if($order_a === $order_b){
                $term_a = get_term($a[0]['instrument'], 'instrument');
                $term_b = get_term($b[0]['instrument'], 'instrument');
                return strcasecmp($term_a->name, $term_b->name);
            }
            return $order_a - $order_b;
        });

        $all_files_sorted = [];
        foreach($files_grouped as $fgroup){
            foreach($fgroup as $f){
                $all_files_sorted[] = $f;
            }
        }

        // Display file buttons
        $output .= '<div class="osm-file-buttons">';
        foreach($all_files_sorted as $f){
            if($instrument_ids && !in_array($f['instrument'], $instrument_ids)) continue;
            $term = $f['instrument'] ? get_term($f['instrument'], 'instrument') : null;
            $title = $term ? $term->name : 'Misc';
            $url = wp_get_attachment_url($f['attachment_id']);
            if(!$url) continue;
            $output .= '<a href="'.esc_url($url).'" class="button" target="_blank">'.esc_html($title).'</a> ';
        }
        $output .= '</div>';

        if($notes) $output .= '<span class="osm-meta">Notes: '.esc_html($notes).'</span><br>';
        $output .= '<span class="osm-meta-updated">Updated: '.esc_html($last_updated).'</span><br>';
        $output .= '</div>';
    }

    wp_reset_postdata();

    // Cache the output for 1 hour
    set_transient($cache_key, $output, HOUR_IN_SECONDS);

    return $output;
}
