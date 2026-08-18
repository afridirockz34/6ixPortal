<?php
/**
 * 6ix Developers — "Case Studies" custom post type.
 *
 * A richer companion to the "Client Success" carousel: each Case Study is a
 * full, brochure-style story (background, objectives, achievements, key
 * results) that renders on its own page in the site's theme. No plugins and
 * no ACF required — the editor fills a few native fields and the icons,
 * layout, colours and fonts are applied automatically to match the template.
 *
 * Public URLs:
 *   /case-studies         → listing page (a Page using template-case-studies)
 *   /case-study/{slug}    → single brochure view (single-case-study.php)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register the post type (public, so each story gets its own permalink) ──
add_action( 'init', function () {
    register_post_type( 'six_case_study', array(
        'labels' => array(
            'name'          => 'Case Studies',
            'singular_name' => 'Case Study',
            'add_new_item'  => 'Add Case Study',
            'edit_item'     => 'Edit Case Study',
            'new_item'      => 'New Case Study',
            'view_item'     => 'View Case Study',
            'search_items'  => 'Search Case Studies',
            'menu_name'     => 'Case Studies',
            'all_items'     => 'All Case Studies',
        ),
        'public'        => true,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-portfolio',
        'menu_position' => 25,
        'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
        'has_archive'   => false, // the /case-studies Page is the listing
        'rewrite'       => array( 'slug' => 'case-study', 'with_front' => false ),
    ) );
}, 9 );

/**
 * Icon cycles used ONLY as a fallback for Case Studies saved before the
 * icon-picker existed (plain "one line per item" text, no per-item icon).
 * Never used once an item has been through the picker and saved as JSON.
 */
function six_cs_obj_icons() { return array( 'target', 'trending', 'bulb', 'rocket', 'gauge', 'users' ); }
function six_cs_ach_icons() { return array( 'search', 'layers', 'pen', 'globe', 'rocket', 'link', 'award', 'chart' ); }

/**
 * The icon choices offered to the advisor for each Objective / Achievement
 * row — every icon in the theme's set (mk_icon_list()), labelled for a plain
 * <select>. Keeping this as "all of them" (20) gives the widest relevant
 * range while guaranteeing every pick already matches the site's style.
 */
function six_cs_selectable_icons() {
    return array(
        'target'   => 'Target / Goal',
        'trending' => 'Trending Up',
        'chart'    => 'Analytics / Chart',
        'gauge'    => 'Speed / Performance',
        'rocket'   => 'Growth / Launch',
        'bulb'     => 'Idea / Strategy',
        'spark'    => 'Spark / Highlight',
        'award'    => 'Award / Results',
        'shield'   => 'Trust / Security',
        'clock'    => 'Time / Turnaround',
        'search'   => 'Search / SEO',
        'seo'      => 'SEO',
        'ads'      => 'Ads / PPC',
        'website'  => 'Website',
        'social'   => 'Social Media',
        'globe'    => 'Reach / Global',
        'link'     => 'Links / Backlinks',
        'layers'   => 'Structure / Layers',
        'pen'      => 'Content / Copy',
        'users'    => 'Audience / Team',
    );
}

/**
 * Raw SVG path data for the selectable icons, keyed the same as
 * six_cs_selectable_icons() — passed to the admin repeater's JS so the icon
 * preview can render without any extra asset/API call. Sourced from
 * mk_icon_list() (marketing/helpers.php) so it can never drift from the
 * front-end icon set.
 */
function six_cs_icon_paths_for_js() {
    if ( ! function_exists( 'mk_icon_list' ) ) return array();
    return array_intersect_key( mk_icon_list(), six_cs_selectable_icons() );
}

/** Split a textarea (one item per line) into a clean array of trimmed lines. */
function six_cs_lines( $raw ) {
    $out = array();
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $l ) {
        $l = trim( $l );
        if ( $l !== '' ) $out[] = $l;
    }
    return $out;
}

/**
 * Read one repeater field (Objectives or Achievements) as an array of
 * ['icon'=>..., 'text'=>...]. Prefers the icon-picker JSON; falls back to the
 * legacy newline-text field (auto-cycled icons) for Case Studies created
 * before the picker existed, so nothing already saved goes blank.
 */
function six_cs_parse_items( $post_id, $json_key, $legacy_key, $cycle_icons ) {
    $raw = get_post_meta( $post_id, $json_key, true );
    if ( $raw ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            $out = array();
            foreach ( $decoded as $row ) {
                $text = trim( (string) ( is_array( $row ) ? ( $row['text'] ?? '' ) : '' ) );
                if ( $text === '' ) continue;
                $icon = sanitize_key( $row['icon'] ?? '' );
                if ( ! $icon ) $icon = 'spark';
                $out[] = array( 'icon' => $icon, 'text' => $text );
            }
            if ( $out ) return $out;
        }
    }
    // Legacy fallback.
    $lines = six_cs_lines( get_post_meta( $post_id, $legacy_key, true ) );
    $out = array();
    foreach ( $lines as $i => $l ) {
        $out[] = array( 'icon' => $cycle_icons[ $i % count( $cycle_icons ) ], 'text' => $l );
    }
    return $out;
}

// Load the WP media uploader + the shared admin styling on the Case Study editor screens.
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'six_case_study' ) return;
    wp_enqueue_media();
    $css = SIX_MK_DIR . 'assets/admin-marketing.css';
    wp_enqueue_style( 'six-mk-admin', SIX_MK_URL . 'assets/admin-marketing.css', array(), file_exists( $css ) ? filemtime( $css ) : '1' );
} );

/**
 * Render one icon-picker repeater (Objectives or Achievements): each row is
 * an icon <select> (from six_cs_selectable_icons()) with a live SVG preview,
 * plus a text input. Rows can be added/removed; on form submit, JS serialises
 * them into the hidden JSON field the save handler reads.
 */
function six_cs_render_repeater( $post_id, $json_key, $legacy_key, $cycle_icons, $label, $add_label, $placeholder ) {
    $icons = six_cs_selectable_icons();
    $items = six_cs_parse_items( $post_id, $json_key, $legacy_key, $cycle_icons );
    if ( ! $items ) $items = array( array( 'icon' => $cycle_icons[0], 'text' => '' ) );
    // Pre-fill the hidden JSON field with the CURRENT value server-side, in
    // the same shape the JS produces (rows with blank text dropped) — a
    // safety net in case the sync JS below never runs for a given save. This
    // matters in particular for the WordPress block editor, which saves
    // classic meta boxes via `new FormData(form)` without ever dispatching a
    // 'submit' event, so logic that only ran inside a 'submit' listener
    // never fired there (see home-fields.php's six_home_repeater_initial_json()
    // for the same fix, first found and root-caused there).
    $initial = array();
    foreach ( $items as $it ) {
        $t = trim( (string) ( $it['text'] ?? '' ) );
        if ( $t === '' ) continue;
        $initial[] = array( 'icon' => $it['icon'] ?? $cycle_icons[0], 'text' => $t );
    }
    ?>
    <div style="margin:0 0 18px">
      <label style="display:block;font-weight:600;margin-bottom:8px"><?php echo esc_html( $label ); ?></label>
      <div class="six-cs-repeater" data-default-icon="<?php echo esc_attr( $cycle_icons[0] ); ?>" data-placeholder="<?php echo esc_attr( $placeholder ); ?>">
        <div class="six-cs-rep-rows">
          <?php foreach ( $items as $it ) : ?>
          <div class="six-cs-rep-row">
            <select class="six-cs-rep-icon">
              <?php foreach ( $icons as $key => $lbl ) : ?>
              <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $it['icon'], $key ); ?>><?php echo esc_html( $lbl ); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="six-cs-rep-preview"></span>
            <input type="text" class="six-cs-rep-text" value="<?php echo esc_attr( $it['text'] ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
            <button type="button" class="button six-cs-rep-remove" aria-label="Remove">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- Always-present template for "+ Add" to clone — independent of the
             live rows above, so Add still works after every row is removed. -->
        <div class="six-cs-rep-template" style="display:none" aria-hidden="true">
          <div class="six-cs-rep-row">
            <select class="six-cs-rep-icon">
              <?php foreach ( $icons as $key => $lbl ) : ?>
              <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $cycle_icons[0] ); ?>><?php echo esc_html( $lbl ); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="six-cs-rep-preview"></span>
            <input type="text" class="six-cs-rep-text" value="" placeholder="<?php echo esc_attr( $placeholder ); ?>">
            <button type="button" class="button six-cs-rep-remove" aria-label="Remove">✕</button>
          </div>
        </div>
        <button type="button" class="button six-cs-rep-add"><?php echo esc_html( $add_label ); ?></button>
        <input type="hidden" name="<?php echo esc_attr( $json_key ); ?>" class="six-cs-rep-json" value="<?php echo esc_attr( wp_json_encode( $initial ) ); ?>">
      </div>
    </div>
    <?php
}

// ── Meta box: the sections an editor fills in ───────────────────────────────
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'six_cs_meta', 'Case Study Content', function ( $post ) {
        wp_nonce_field( 'six_cs_meta', 'six_cs_nonce' );
        $g = fn( $k ) => get_post_meta( $post->ID, $k, true );

        $text = function ( $k, $label, $ph = '' ) use ( $g ) {
            echo '<div class="six-adm-field"><label class="six-adm-field-label">' . esc_html( $label ) . '</label>';
            echo '<input type="text" name="' . esc_attr( $k ) . '" value="' . esc_attr( $g( $k ) ) . '" placeholder="' . esc_attr( $ph ) . '"></div>';
        };
        $area = function ( $k, $label, $ph = '', $rows = 4 ) use ( $g ) {
            echo '<div class="six-adm-field"><label class="six-adm-field-label">' . esc_html( $label ) . '</label>';
            echo '<textarea name="' . esc_attr( $k ) . '" rows="' . intval( $rows ) . '" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( $g( $k ) ) . '</textarea></div>';
        };

        echo '<p style="color:#666;margin-top:0">The <strong>title</strong> is the client / organisation name (e.g. “College for Adult Learning”). '
           . 'Fill the sections below — colours and layout are applied automatically to match the site.</p>';

        // ── Featured image picker — shown in the listing/blurb card (home
        // section + /case-studies archive) and behind the title on the
        // single brochure page. A dedicated picker (not the native "Featured
        // Image" box) so it's guaranteed visible regardless of theme setup.
        $img = get_post_meta( $post->ID, 'six_case_featured_image', true );
        echo '<div class="six-adm-field">';
        echo '<label class="six-adm-field-label">Featured Image</label>';
        echo '<p class="six-adm-hint" style="margin-top:0">Shown as the thumbnail on the case study listing and behind the title on the full page.</p>';
        echo '<div id="six-case-img-prev" class="six-home-img-prev' . ( $img ? ' six-adm-has-img' : '' ) . '" style="width:180px;height:120px;' . ( $img ? 'background-image:url(' . esc_url( $img ) . ')' : '' ) . '">' . ( $img ? '' : 'No image' ) . '</div>';
        echo '<input type="hidden" id="six-case-img" name="six_case_featured_image" value="' . esc_attr( $img ) . '">';
        echo '<div class="six-home-img-actions">';
        echo '<button type="button" class="button" id="six-case-img-pick">Select image</button>';
        echo '<button type="button" class="button-link" id="six-case-img-clear" style="' . ( $img ? '' : 'display:none' ) . '">Remove</button>';
        echo '</div></div>';
        ?>
        <script>
        (function(){
            var frame, pick=document.getElementById('six-case-img-pick'),
                clear=document.getElementById('six-case-img-clear'),
                inp=document.getElementById('six-case-img'),
                prev=document.getElementById('six-case-img-prev');
            if(!pick) return;
            function setPreview(url){
                inp.value = url;
                if(url){ prev.style.backgroundImage = 'url('+url+')'; prev.classList.add('six-adm-has-img'); prev.textContent=''; clear.style.display=''; }
                else{ prev.style.backgroundImage = ''; prev.classList.remove('six-adm-has-img'); prev.textContent='No image'; clear.style.display='none'; }
            }
            pick.addEventListener('click', function(e){
                e.preventDefault();
                if(frame){ frame.open(); return; }
                frame = wp.media({ title:'Select featured image', button:{text:'Use this image'}, multiple:false });
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    var url = (a.sizes && a.sizes.large) ? a.sizes.large.url : a.url;
                    setPreview(url);
                });
                frame.open();
            });
            clear.addEventListener('click', function(e){ e.preventDefault(); setPreview(''); });
        })();
        </script>
        <?php

        $text( 'six_cs_subtitle', 'Subtitle / category', 'Training Organization Case Study' );
        $text( 'six_cs_headline', 'Headline result (big banner line)', '300%+ more sales with 60% lower cost per sale' );
        $area( 'six_cs_background', 'Background', "A short paragraph on who the client is and the challenge they came to us with.", 4 );

        // ── Icon-picker repeaters (Objectives / Achievements) ─────────────
        ?>
        <style>
          .six-cs-rep-row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
          .six-cs-rep-icon{width:190px;flex-shrink:0}
          .six-cs-rep-preview{width:34px;height:34px;flex-shrink:0;border-radius:7px;background:#F1F3F8;
            display:flex;align-items:center;justify-content:center;color:#8781BA}
          .six-cs-rep-preview svg{width:18px;height:18px}
          .six-cs-rep-text{flex:1;min-width:0}
          .six-cs-rep-remove{flex-shrink:0}
        </style>
        <?php
        six_cs_render_repeater(
            $post->ID, 'six_cs_objectives_json', 'six_cs_objectives', six_cs_obj_icons(),
            'Objectives — pick an icon for each', '+ Add objective',
            'e.g. Dramatically reduce cost-per-lead to a profitable level.'
        );
        six_cs_render_repeater(
            $post->ID, 'six_cs_achievements_json', 'six_cs_achievements', six_cs_ach_icons(),
            'Achievements — pick an icon for each', '+ Add achievement',
            'e.g. Rebuilt and re-organised the Google Ads campaigns.'
        );
        ?>
        <script>
        (function(){
          var ICONS = <?php echo wp_json_encode( six_cs_icon_paths_for_js() ); ?>;
          function svg(name){
            var p = ICONS[name] || ICONS.spark;
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'+p+'</svg>';
          }
          // Keep a repeater's hidden JSON field in sync on every change, not
          // just on the form's 'submit' event. The WordPress BLOCK EDITOR
          // saves classic meta boxes by building
          // `new FormData(document.getElementById('post'))` directly and
          // never dispatches a 'submit' event, so logic that only ran inside
          // a 'submit' listener never fired there — this repeater's hidden
          // field was submitted blank on every block-editor save (same root
          // cause found and fixed for the Homepage Content editor's
          // repeaters; see home-fields.php).
          function syncRepeaterJSON(rep){
            var out = [];
            rep.querySelectorAll('.six-cs-rep-rows .six-cs-rep-row').forEach(function(row){
              var text = row.querySelector('.six-cs-rep-text').value.trim();
              if(!text) return;
              out.push({ icon: row.querySelector('.six-cs-rep-icon').value, text: text });
            });
            rep.querySelector('.six-cs-rep-json').value = JSON.stringify(out);
          }
          function wireRow(row, rep){
            var sel = row.querySelector('.six-cs-rep-icon');
            var prev = row.querySelector('.six-cs-rep-preview');
            var txt = row.querySelector('.six-cs-rep-text');
            function update(){ prev.innerHTML = svg(sel.value); }
            sel.addEventListener('change', function(){ update(); syncRepeaterJSON(rep); });
            txt.addEventListener('input', function(){ syncRepeaterJSON(rep); });
            update();
            row.querySelector('.six-cs-rep-remove').addEventListener('click', function(){
              row.remove();
              syncRepeaterJSON(rep);
            });
          }
          document.querySelectorAll('.six-cs-repeater').forEach(function(rep){
            var rowsWrap = rep.querySelector('.six-cs-rep-rows');
            var template = rep.querySelector('.six-cs-rep-template .six-cs-rep-row');
            rep.querySelectorAll('.six-cs-rep-rows .six-cs-rep-row').forEach(function(row){ wireRow(row, rep); });
            rep.querySelector('.six-cs-rep-add').addEventListener('click', function(){
              var row = template.cloneNode(true);
              row.querySelector('.six-cs-rep-icon').value = rep.dataset.defaultIcon;
              row.querySelector('.six-cs-rep-text').value = '';
              rowsWrap.appendChild(row);
              wireRow(row, rep);
              row.querySelector('.six-cs-rep-text').focus();
            });
            // Keep it correct from page load too, and once more right before
            // submit as a final safety net for the classic editor's normal
            // form submission.
            syncRepeaterJSON(rep);
          });
          var form = document.getElementById('post');
          if(form){
            form.addEventListener('submit', function(){
              document.querySelectorAll('.six-cs-repeater').forEach(syncRepeaterJSON);
            });
          }
        })();
        </script>
        <?php

        echo '<hr style="margin:20px 0"><p style="font-weight:600;margin:0 0 6px">Key Results</p>';
        echo '<p style="color:#666;margin:0 0 12px">Up to four headline numbers. Leave a row blank to hide it. Direction shows an up or down arrow.</p>';
        echo '<table style="width:100%;border-collapse:collapse"><thead><tr>'
           . '<th style="text-align:left;font-size:12px;color:#666;padding:0 8px 4px 0">Value</th>'
           . '<th style="text-align:left;font-size:12px;color:#666;padding:0 8px 4px 0">Label</th>'
           . '<th style="text-align:left;font-size:12px;color:#666;padding:0 0 4px 0">Direction</th></tr></thead><tbody>';
        for ( $i = 1; $i <= 4; $i++ ) {
            $v = esc_attr( $g( "six_cs_kr{$i}_value" ) );
            $l = esc_attr( $g( "six_cs_kr{$i}_label" ) );
            $d = $g( "six_cs_kr{$i}_dir" ) ?: 'up';
            echo '<tr>';
            echo '<td style="padding:0 8px 8px 0"><input type="text" name="six_cs_kr' . $i . '_value" value="' . $v . '" placeholder="300%+" style="width:100%"></td>';
            echo '<td style="padding:0 8px 8px 0"><input type="text" name="six_cs_kr' . $i . '_label" value="' . $l . '" placeholder="Lead & sales volume increase" style="width:100%"></td>';
            echo '<td style="padding:0 0 8px 0"><select name="six_cs_kr' . $i . '_dir" style="width:100%">'
               . '<option value="up"' . selected( $d, 'up', false ) . '>▲ Up</option>'
               . '<option value="down"' . selected( $d, 'down', false ) . '>▼ Down</option>'
               . '<option value="none"' . selected( $d, 'none', false ) . '>— None</option>'
               . '</select></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }, 'six_case_study', 'normal', 'high' );
} );

// ── Save meta ───────────────────────────────────────────────────────────────
add_action( 'save_post_six_case_study', function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['six_cs_nonce'] ) || ! wp_verify_nonce( $_POST['six_cs_nonce'], 'six_cs_meta' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    foreach ( array( 'six_cs_subtitle', 'six_cs_headline' ) as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
    }
    if ( isset( $_POST['six_case_featured_image'] ) ) {
        update_post_meta( $post_id, 'six_case_featured_image', esc_url_raw( wp_unslash( $_POST['six_case_featured_image'] ) ) );
    }
    if ( isset( $_POST['six_cs_background'] ) ) {
        update_post_meta( $post_id, 'six_cs_background', sanitize_textarea_field( wp_unslash( $_POST['six_cs_background'] ) ) );
    }

    // Objectives / Achievements — icon-picker rows, serialised to JSON by the
    // repeater's JS just before submit. Validate each icon against the
    // allowed set so only real theme icons can ever be stored.
    $allowed_icons = six_cs_selectable_icons();
    foreach ( array( 'six_cs_objectives_json', 'six_cs_achievements_json' ) as $k ) {
        if ( ! isset( $_POST[ $k ] ) ) continue;
        $decoded = json_decode( wp_unslash( $_POST[ $k ] ), true );
        $clean = array();
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $row ) {
                if ( ! is_array( $row ) ) continue;
                $item_text = sanitize_text_field( $row['text'] ?? '' );
                if ( $item_text === '' ) continue;
                $icon = sanitize_key( $row['icon'] ?? '' );
                if ( ! isset( $allowed_icons[ $icon ] ) ) $icon = 'spark';
                $clean[] = array( 'icon' => $icon, 'text' => $item_text );
            }
        }
        update_post_meta( $post_id, $k, wp_json_encode( $clean ) );
    }

    for ( $i = 1; $i <= 4; $i++ ) {
        foreach ( array( 'value', 'label', 'dir' ) as $suf ) {
            $k = "six_cs_kr{$i}_{$suf}";
            if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
        }
    }
} );

/**
 * Assemble one Case Study into a structured array for the templates. Accepts
 * a post object or ID.
 */
function six_cs_get( $post ) {
    $p = get_post( $post );
    if ( ! $p ) return null;
    $id = $p->ID;

    $results = array();
    for ( $i = 1; $i <= 4; $i++ ) {
        $val = get_post_meta( $id, "six_cs_kr{$i}_value", true );
        if ( $val === '' ) continue;
        $results[] = array(
            'value' => $val,
            'label' => get_post_meta( $id, "six_cs_kr{$i}_label", true ),
            'dir'   => get_post_meta( $id, "six_cs_kr{$i}_dir", true ) ?: 'up',
        );
    }

    return array(
        'id'           => $id,
        'title'        => get_the_title( $p ),
        'subtitle'     => get_post_meta( $id, 'six_cs_subtitle', true ),
        'headline'     => get_post_meta( $id, 'six_cs_headline', true ),
        'background'   => get_post_meta( $id, 'six_cs_background', true ),
        // Each item is ['icon'=>..., 'text'=>...] — icon picked by the
        // advisor (or auto-cycled for legacy stories saved before the picker
        // existed; see six_cs_parse_items()).
        'objectives'   => six_cs_parse_items( $id, 'six_cs_objectives_json',   'six_cs_objectives',   six_cs_obj_icons() ),
        'achievements' => six_cs_parse_items( $id, 'six_cs_achievements_json', 'six_cs_achievements', six_cs_ach_icons() ),
        'results'      => $results,
        // Prefer the dedicated Featured Image picker; fall back to the
        // native WP featured image for anyone who set that instead.
        'image'        => get_post_meta( $id, 'six_case_featured_image', true ) ?: ( get_the_post_thumbnail_url( $p, 'large' ) ?: '' ),
        'url'          => get_permalink( $p ),
    );
}

/** Fetch the most recent N case studies as structured arrays (newest first). */
function six_cs_items( $limit = -1 ) {
    $posts = get_posts( array(
        'post_type'   => 'six_case_study',
        'numberposts' => $limit,
        'orderby'     => 'menu_order date',
        'order'       => 'DESC',
        'post_status' => 'publish',
    ) );
    $out = array();
    foreach ( $posts as $p ) {
        $cs = six_cs_get( $p );
        if ( $cs ) $out[] = $cs;
    }
    return $out;
}

/**
 * Render a single Case Study card (used on the home section and the archive so
 * both share one style). $cs is the array from six_cs_get().
 */
function six_cs_card( $cs ) {
    if ( empty( $cs ) ) return;
    // Card highlight: prefer the first key result, else fall back to headline.
    $hl = ! empty( $cs['results'] ) ? $cs['results'][0]['value'] : '';
    ?>
    <a class="mk-card mk-cstudy-card" href="<?php echo esc_url( $cs['url'] ); ?>">
      <div class="mk-cstudy-thumb">
        <?php if ( ! empty( $cs['image'] ) ) : ?>
          <img src="<?php echo esc_url( $cs['image'] ); ?>" alt="<?php echo esc_attr( $cs['title'] ); ?>">
        <?php else : ?>
          <div class="mk-cstudy-thumb-ph" aria-hidden="true"><?php echo mk_icon( 'award' ); ?></div>
        <?php endif; ?>
        <?php if ( $hl ) : ?><span class="mk-cstudy-badge"><?php echo esc_html( $hl ); ?></span><?php endif; ?>
      </div>
      <div class="mk-cstudy-body">
        <?php if ( ! empty( $cs['subtitle'] ) ) : ?><span class="mk-eyebrow"><?php echo esc_html( $cs['subtitle'] ); ?></span><?php endif; ?>
        <h3><?php echo esc_html( $cs['title'] ); ?></h3>
        <?php if ( ! empty( $cs['headline'] ) ) : ?><p><?php echo esc_html( $cs['headline'] ); ?></p><?php endif; ?>
        <span class="mk-card-link">Read case study
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </div>
    </a>
    <?php
}

// ── Route the single view to our brochure template ──────────────────────────
add_filter( 'template_include', function ( $template ) {
    if ( is_singular( 'six_case_study' ) ) {
        $t = SIX_MK_DIR . 'templates/single-case-study.php';
        if ( file_exists( $t ) ) return $t;
    }
    return $template;
}, 99 );
