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
 * Curated icon cycles (keys from mk_icon()). Objectives and Achievements each
 * auto-assign an icon per item, cycling so no two adjacent rows repeat — the
 * editor never has to pick one.
 */
function six_cs_obj_icons() { return array( 'target', 'trending', 'bulb', 'rocket', 'gauge', 'users' ); }
function six_cs_ach_icons() { return array( 'search', 'layers', 'pen', 'globe', 'rocket', 'link', 'award', 'chart' ); }

/** Split a textarea (one item per line) into a clean array of trimmed lines. */
function six_cs_lines( $raw ) {
    $out = array();
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $l ) {
        $l = trim( $l );
        if ( $l !== '' ) $out[] = $l;
    }
    return $out;
}

// Load the WP media uploader on the Case Study editor screens.
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
    $screen = get_current_screen();
    if ( $screen && $screen->post_type === 'six_case_study' ) wp_enqueue_media();
} );

// ── Meta box: the sections an editor fills in ───────────────────────────────
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'six_cs_meta', 'Case Study Content', function ( $post ) {
        wp_nonce_field( 'six_cs_meta', 'six_cs_nonce' );
        $g = fn( $k ) => get_post_meta( $post->ID, $k, true );

        $text = function ( $k, $label, $ph = '' ) use ( $g ) {
            echo '<p style="margin:0 0 16px"><label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
            echo '<input type="text" name="' . esc_attr( $k ) . '" value="' . esc_attr( $g( $k ) ) . '" placeholder="' . esc_attr( $ph ) . '" style="width:100%"></p>';
        };
        $area = function ( $k, $label, $ph = '', $rows = 4 ) use ( $g ) {
            echo '<p style="margin:0 0 16px"><label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
            echo '<textarea name="' . esc_attr( $k ) . '" rows="' . intval( $rows ) . '" placeholder="' . esc_attr( $ph ) . '" style="width:100%">' . esc_textarea( $g( $k ) ) . '</textarea></p>';
        };

        echo '<p style="color:#666;margin-top:0">The <strong>title</strong> is the client / organisation name (e.g. “College for Adult Learning”). '
           . 'Set the hero photo as the <strong>Featured Image</strong>. Fill the sections below — icons, colours and layout are applied automatically to match the site.</p>';

        $text( 'six_cs_subtitle', 'Subtitle / category', 'Training Organization Case Study' );
        $text( 'six_cs_headline', 'Headline result (big banner line)', '300%+ more sales with 60% lower cost per sale' );
        $area( 'six_cs_background', 'Background', "A short paragraph on who the client is and the challenge they came to us with.", 4 );
        $area( 'six_cs_objectives', 'Objectives — one per line', "Dramatically reduce cost-per-lead to a profitable level.\nIncrease the volume of qualified leads.\nAssist with the online launch of new courses.", 5 );
        $area( 'six_cs_achievements', 'Achievements — one per line', "Rebuilt and re-organised the Google Ads campaigns.\nCreated targeted landing pages for specific courses.\nSet up landing-page split tests to improve conversion.", 6 );

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
    foreach ( array( 'six_cs_background', 'six_cs_objectives', 'six_cs_achievements' ) as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) );
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
        'objectives'   => six_cs_lines( get_post_meta( $id, 'six_cs_objectives', true ) ),
        'achievements' => six_cs_lines( get_post_meta( $id, 'six_cs_achievements', true ) ),
        'results'      => $results,
        'image'        => get_the_post_thumbnail_url( $p, 'large' ) ?: '',
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
