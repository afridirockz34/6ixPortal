<?php
/**
 * 6ix Developers — Homepage Content editor.
 *
 * Makes the "6ix — Home" page template actually editable from wp-admin
 * without depending on ACF (mk_field() already checks ACF first if it's
 * installed, but this site has never had ACF field groups configured for
 * this template — so previously "Edit" on the Home page only showed the
 * page's raw, unused post_content, with no real way to change anything).
 *
 * Adds a native "Homepage Content" meta box, covering every text field and
 * image used on the homepage, storing to plain post meta (key
 * `six_field_{name}`) that mk_field() already knows how to read (see
 * marketing/helpers.php). Repeater sections (service cards, deep-dive
 * blocks) use the same add/remove-row + JSON-on-submit pattern as the Case
 * Study icon picker.
 *
 * six_home_defaults() is the single source of truth for the homepage's
 * default copy — template-home.php pulls its mk_field() defaults from here
 * too, so the "current live text" shown when editing and the fallback shown
 * to visitors can never drift apart.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Every default value used on the homepage, keyed by field name. */
function six_home_defaults() {
    $orig = 'https://6ixdevelopers.com/';
    return array(
        'hero_heading'      => 'Discover The Difference',
        'hero_subheading'   => '6ix Developers can make',
        'hero_lead'         => 'Elevate your marketing through industry-leading:',
        'hero_typing_words' => 'PPC Management, Search Engine Marketing, Paid Social Media Advertising, Website Page Speed Optimization, Email Marketing',
        'hero_cta1_label'   => 'Get your free consultation',
        'hero_cta1_url'     => '/contact-us',
        'hero_cta2_label'   => 'Find out how your business is doing',

        'svc_heading' => 'How 6ix Developers Can Help Your Business',
        'svc_intro'   => "6ix Developers is a full stack digital marketing agency with experienced and Google-certified staff. Our team members are specialized in Website Designs that are optimized for lead generation and lead capturing. We have Google Ads (aka. PPC) experts who are Google certified and experienced enough to take your business to another level. Our SEO team can help your business with organic ranking on Google and other search engines. Our Social Media team can show your business the opportunities it deserves. Secret to your success is in our expert team's hands who is fully invested in learning and understanding your business to help it grow exponentially.",
        'svc_cards'   => array(
            array( 'image' => $orig . 'media/icons/ps-web.png', 'title' => 'Website Design', 'text' => 'Professional websites designed to run optimally across all devices', 'link' => '/website-design-agency-toronto' ),
            array( 'image' => $orig . 'media/icons/ps-ads.png', 'title' => 'Google Ads/PPC', 'text' => 'Managed by Google Ads specialists to help your business rank #1', 'link' => '/ppc-google-ads-management-toronto' ),
            array( 'image' => $orig . 'media/icons/ps-seo.png', 'title' => 'SEO', 'text' => 'Rank higher in organic search results and drive more traffic to your website', 'link' => '/seo-agency-toronto' ),
            array( 'image' => $orig . 'media/icons/ps-smm.png', 'title' => 'Social Media', 'text' => 'Customized campaigns and blogs to grow your online presence', 'link' => '/social-media-marketing-agency-toronto' ),
        ),

        'cstudy_eyebrow' => 'Proven Results',
        'cstudy_heading' => 'Case Studies',

        'cs_eyebrow' => 'We are Diverse & Experienced',
        'cs_heading' => 'Client Success',

        'commit_heading' => 'Our Commitment To Helping Other Businesses',
        'commit_p1'      => "We strive to fully understand our client's business and industry before we begin a project. This is critical to build an online presence that meets our clients vision, and is relevant to their goals.",
        'commit_p2'      => 'We are transparent, and involve our clients through the whole process. We continuously seek feedback to ensure we are on the right track.',
        'commit_q'       => 'Could your business benefit from our services?',
        'commit_cta1'    => 'Get free consultation',
        'commit_cta2'    => 'Find out more about us',

        'dd_heading' => 'We Can Help Your Business With',
        'deepdives'  => array(
            array( 'eyebrow' => 'Website Design', 'title' => 'Responsive Website Design',
                'text'      => "Our website designs are optimized for lead generation and lead capturing. Our website designs are flexible and responsive on multiple platforms, devices, and browsers. While designing a website, we consider all important SEO aspects to help your website rank faster and higher than your competitors on Google search. Our striking website designs are developed in-house by experienced web designers. They are specifically designed for your potential clients to take action on the website, whether it's making a phone call or submitting a form.",
                'cta_label' => 'Learn More', 'cta_url' => '/website-design-agency-toronto', 'image' => 'https://placehold.co/640x460/E8547A/ffffff?text=Website+Design' ),
            array( 'eyebrow' => 'Google Ads', 'title' => 'Google Ads/PPC',
                'text'      => 'Google Ads, also known as PPC, is one of the most versatile and scalable platforms to advertise your business. This form of business advertising is primarily used to bring immediate return on investment. Our Google Ads/PPC certified experts specialize in designing a marketing solution for your business that best suits your needs. Regardless of your marketing budget size, we ensure that every dollar spent on marketing returns the best possible results for your business to leverage.',
                'cta_label' => 'Learn More', 'cta_url' => '/ppc-google-ads-management-toronto', 'image' => 'https://placehold.co/640x460/A855F7/ffffff?text=Google+Ads' ),
            array( 'eyebrow' => 'SEO', 'title' => 'SEO (Search Engine Marketing)',
                'text'      => 'When we work with an SEO client, we ensure that the deliverables are set for long-term and consistent returns. Research shows that Google processes more than 3.5 billion searches every day. With the right exposure at the right time, your business can dominate the industry. Certified and experienced SEO experts in your industry at 6ix Developers can take your business to the next level. We achieve this by making your website rank on the first page of Google search. Customer satisfaction is our #1 priority. We have helped many businesses rank #1 on Google. Yours could be next!',
                'cta_label' => 'Learn More', 'cta_url' => '/seo-agency-toronto', 'image' => 'https://placehold.co/640x460/3C8FB5/ffffff?text=SEO' ),
            array( 'eyebrow' => 'Social Media', 'title' => 'Social Media Marketing/Management',
                'text'      => "Social media is one of the most cost-effective yet powerful platforms to promote your services. Our Social Media specialists build a community on social media platforms that shares the same interests as your business. Being in front of your potential prospects all the time keeps your business/brand in their minds. So when they are ready to find someone, your business is the first name that comes to mind. If you don't currently have social media for your business or are just starting, hire us to jump-start your presence and compete with the top competitors in your industry.",
                'cta_label' => 'Learn More', 'cta_url' => '/social-media-marketing-agency-toronto', 'image' => 'https://placehold.co/640x460/83C5ED/0b2233?text=Social+Media' ),
        ),

        'tst_heading' => 'What Our Clients Say',

        'blog_heading' => 'From the Blog',
        'blog_count'   => 3,

        'final_heading'   => 'Ready to find out what sets 6ix Developers apart?',
        'final_cta_label' => 'Get free consultation now',
        'final_cta_url'   => '/contact-us',
    );
}

/** True on the Page edit screen for whichever page uses template-home.php. */
function six_home_is_home_template( $post_id ) {
    return $post_id && get_page_template_slug( $post_id ) === 'marketing/templates/template-home.php';
}

// Load the WP media uploader on the Home page's editor screen.
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
    global $post;
    if ( $post && six_home_is_home_template( $post->ID ) ) wp_enqueue_media();
} );

add_action( 'add_meta_boxes_page', function ( $post ) {
    if ( ! six_home_is_home_template( $post->ID ) ) return;
    add_meta_box( 'six_home_meta', 'Homepage Content', 'six_home_render_meta_box', 'page', 'normal', 'high' );
} );

/**
 * One scalar/textarea field: current value (mk_field's full resolution
 * chain — ACF, then saved post meta, then the coded default) pre-fills the
 * input, so the form always shows what's actually live right now.
 */
function six_home_text_field( $post_id, $key, $label, $type = 'text' ) {
    $defaults = six_home_defaults();
    $val      = mk_field( $key, $defaults[ $key ] ?? '', $post_id );
    $name     = 'six_field_' . $key;
    echo '<div style="margin:0 0 16px"><label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
    if ( $type === 'textarea' ) {
        echo '<textarea name="' . esc_attr( $name ) . '" rows="4" style="width:100%">' . esc_textarea( $val ) . '</textarea>';
    } else {
        echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" style="width:100%">';
    }
    echo '</div>';
}

/**
 * A repeater section (service cards / deep-dive blocks): each row is built
 * from $row_spec — an ordered list of ['key','type'=>text|textarea|image,
 * 'label']. Rows can be added/removed; on submit, JS serialises them to the
 * hidden `six_field_{$field}` JSON input the save handler reads.
 */
function six_home_render_repeater( $post_id, $field, $row_spec, $label, $add_label ) {
    $defaults = six_home_defaults();
    $items    = mk_field( $field, $defaults[ $field ] ?? array(), $post_id );
    if ( ! is_array( $items ) || ! $items ) $items = array( array() );
    ?>
    <div style="margin:0 0 22px">
      <label style="display:block;font-weight:600;margin-bottom:8px"><?php echo esc_html( $label ); ?></label>
      <div class="six-home-repeater">
        <div class="six-home-rep-rows">
          <?php foreach ( $items as $item ) echo six_home_render_row( $row_spec, $item ); ?>
        </div>
        <div class="six-home-rep-template" style="display:none" aria-hidden="true">
          <?php echo six_home_render_row( $row_spec, array() ); ?>
        </div>
        <button type="button" class="button six-home-rep-add"><?php echo esc_html( $add_label ); ?></button>
        <input type="hidden" name="six_field_<?php echo esc_attr( $field ); ?>" class="six-home-rep-json">
      </div>
    </div>
    <?php
}

function six_home_render_row( $row_spec, $item ) {
    ob_start();
    ?>
    <div class="six-home-rep-row" style="border:1px solid #dcdcde;border-radius:8px;padding:14px;margin-bottom:12px;background:#fff">
      <?php foreach ( $row_spec as $f ) :
          $val = $item[ $f['key'] ] ?? '';
          if ( $f['type'] === 'image' ) : ?>
      <div style="margin-bottom:10px">
        <div class="six-home-img-prev" style="width:140px;height:90px;border-radius:6px;border:1px solid #dcdcde;background:#f6f7f7 center/cover no-repeat<?php echo $val ? ';background-image:url(' . esc_url( $val ) . ')' : ''; ?>"></div>
        <input type="hidden" class="six-home-img-input" data-key="<?php echo esc_attr( $f['key'] ); ?>" value="<?php echo esc_attr( $val ); ?>">
        <button type="button" class="button six-home-img-pick" style="margin-top:6px;font-size:11px">Select image</button>
        <button type="button" class="button-link six-home-img-clear" style="margin-left:6px;font-size:11px;<?php echo $val ? '' : 'display:none'; ?>">Remove</button>
      </div>
      <?php else : ?>
      <div style="margin-bottom:10px">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px"><?php echo esc_html( $f['label'] ); ?></label>
        <?php if ( $f['type'] === 'textarea' ) : ?>
        <textarea data-key="<?php echo esc_attr( $f['key'] ); ?>" rows="3" style="width:100%"><?php echo esc_textarea( $val ); ?></textarea>
        <?php else : ?>
        <input type="text" data-key="<?php echo esc_attr( $f['key'] ); ?>" value="<?php echo esc_attr( $val ); ?>" style="width:100%">
        <?php endif; ?>
      </div>
      <?php endif; endforeach; ?>
      <div style="text-align:right"><button type="button" class="button-link six-home-rep-remove" style="color:#b32d2e">Remove row</button></div>
    </div>
    <?php
    return ob_get_clean();
}

function six_home_render_meta_box( $post ) {
    wp_nonce_field( 'six_home_meta', 'six_home_nonce' );
    echo '<p style="color:#666;margin-top:0">These fields are the homepage\'s actual live text and images — editing and saving here updates the site immediately. Sections not listed here (Case Studies, Client Success, Testimonials) each have their own post type in the sidebar menu.</p>';

    echo '<h3 style="margin-top:24px">Hero</h3>';
    six_home_text_field( $post->ID, 'hero_heading', 'Heading' );
    six_home_text_field( $post->ID, 'hero_subheading', 'Sub-heading (smaller line under the heading)' );
    six_home_text_field( $post->ID, 'hero_lead', 'Lead line (before the typing animation)' );
    six_home_text_field( $post->ID, 'hero_typing_words', 'Typing animation words — comma separated' );
    six_home_text_field( $post->ID, 'hero_cta1_label', 'Primary button label' );
    six_home_text_field( $post->ID, 'hero_cta1_url', 'Primary button link (relative, e.g. /contact-us)' );
    six_home_text_field( $post->ID, 'hero_cta2_label', 'Secondary button label' );

    echo '<h3>Services Section</h3>';
    six_home_text_field( $post->ID, 'svc_heading', 'Heading' );
    six_home_text_field( $post->ID, 'svc_intro', 'Intro paragraph', 'textarea' );
    six_home_render_repeater( $post->ID, 'svc_cards', array(
        array( 'key' => 'image', 'type' => 'image' ),
        array( 'key' => 'title', 'type' => 'text', 'label' => 'Title' ),
        array( 'key' => 'text', 'type' => 'textarea', 'label' => 'Description' ),
        array( 'key' => 'link', 'type' => 'text', 'label' => 'Link (relative, e.g. /seo-agency-toronto)' ),
    ), 'Service Cards', '+ Add service card' );

    echo '<h3>Case Studies Section</h3>';
    six_home_text_field( $post->ID, 'cstudy_eyebrow', 'Eyebrow' );
    six_home_text_field( $post->ID, 'cstudy_heading', 'Heading' );

    echo '<h3>Client Success Section</h3>';
    six_home_text_field( $post->ID, 'cs_eyebrow', 'Eyebrow' );
    six_home_text_field( $post->ID, 'cs_heading', 'Heading' );

    echo '<h3>Our Commitment Section</h3>';
    six_home_text_field( $post->ID, 'commit_heading', 'Heading' );
    six_home_text_field( $post->ID, 'commit_p1', 'Paragraph 1', 'textarea' );
    six_home_text_field( $post->ID, 'commit_p2', 'Paragraph 2', 'textarea' );
    six_home_text_field( $post->ID, 'commit_q', 'Question line (bold, before the buttons)' );
    six_home_text_field( $post->ID, 'commit_cta1', 'Primary button label' );
    six_home_text_field( $post->ID, 'commit_cta2', 'Secondary button label' );

    echo '<h3>"We Can Help Your Business With" Section</h3>';
    six_home_text_field( $post->ID, 'dd_heading', 'Heading' );
    six_home_render_repeater( $post->ID, 'deepdives', array(
        array( 'key' => 'image', 'type' => 'image' ),
        array( 'key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow' ),
        array( 'key' => 'title', 'type' => 'text', 'label' => 'Title' ),
        array( 'key' => 'text', 'type' => 'textarea', 'label' => 'Description' ),
        array( 'key' => 'cta_label', 'type' => 'text', 'label' => 'Button label' ),
        array( 'key' => 'cta_url', 'type' => 'text', 'label' => 'Button link (relative)' ),
    ), 'Deep-Dive Blocks', '+ Add block' );

    echo '<h3>Testimonials Section</h3>';
    six_home_text_field( $post->ID, 'tst_heading', 'Heading' );

    echo '<h3>Blog Section</h3>';
    six_home_text_field( $post->ID, 'blog_heading', 'Heading' );
    six_home_text_field( $post->ID, 'blog_count', 'Number of recent posts to show' );

    echo '<h3>Final Call-to-Action</h3>';
    six_home_text_field( $post->ID, 'final_heading', 'Heading' );
    six_home_text_field( $post->ID, 'final_cta_label', 'Button label' );
    six_home_text_field( $post->ID, 'final_cta_url', 'Button link (relative)' );
    ?>
    <style>
      #six_home_meta h3{margin:24px 0 10px;padding-top:16px;border-top:1px solid #dcdcde}
      #six_home_meta h3:first-of-type{margin-top:0;padding-top:0;border-top:0}
    </style>
    <script>
    (function(){
        function wireImagePicker(row){
            var pick = row.querySelector('.six-home-img-pick');
            if(!pick) return;
            var clear = row.querySelector('.six-home-img-clear');
            var inp   = row.querySelector('.six-home-img-input');
            var prev  = row.querySelector('.six-home-img-prev');
            var frame;
            pick.addEventListener('click', function(e){
                e.preventDefault();
                if(frame){ frame.open(); return; }
                frame = wp.media({ title:'Select image', button:{text:'Use this image'}, multiple:false });
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    var url = (a.sizes && a.sizes.large) ? a.sizes.large.url : a.url;
                    inp.value = url; prev.style.backgroundImage = 'url('+url+')'; if(clear) clear.style.display='';
                });
                frame.open();
            });
            if(clear) clear.addEventListener('click', function(e){
                e.preventDefault(); inp.value=''; prev.style.backgroundImage=''; clear.style.display='none';
            });
        }
        function wireRow(row){
            wireImagePicker(row);
            var rm = row.querySelector('.six-home-rep-remove');
            if(rm) rm.addEventListener('click', function(){ row.remove(); });
        }
        document.querySelectorAll('.six-home-repeater').forEach(function(rep){
            var rowsWrap = rep.querySelector('.six-home-rep-rows');
            var template = rep.querySelector('.six-home-rep-template .six-home-rep-row');
            rep.querySelectorAll('.six-home-rep-rows .six-home-rep-row').forEach(wireRow);
            var addBtn = rep.querySelector('.six-home-rep-add');
            if(addBtn) addBtn.addEventListener('click', function(){
                var row = template.cloneNode(true);
                row.querySelectorAll('[data-key]').forEach(function(el){ el.value=''; });
                var prev = row.querySelector('.six-home-img-prev'); if(prev) prev.style.backgroundImage='';
                var clear = row.querySelector('.six-home-img-clear'); if(clear) clear.style.display='none';
                rowsWrap.appendChild(row);
                wireRow(row);
            });
        });
        // Serialise every repeater's rows into its hidden JSON field right
        // before the post form submits. Rows with every field left blank are
        // dropped so an unused "+ Add" click never saves an empty entry.
        var form = document.getElementById('post');
        if(form){
            form.addEventListener('submit', function(){
                document.querySelectorAll('.six-home-repeater').forEach(function(rep){
                    var out = [];
                    rep.querySelectorAll('.six-home-rep-rows .six-home-rep-row').forEach(function(row){
                        var obj = {}, hasValue = false;
                        row.querySelectorAll('[data-key]').forEach(function(el){
                            var v = el.value.trim();
                            obj[el.getAttribute('data-key')] = v;
                            if(v) hasValue = true;
                        });
                        if(hasValue) out.push(obj);
                    });
                    rep.querySelector('.six-home-rep-json').value = JSON.stringify(out);
                });
            });
        }
    })();
    </script>
    <?php
}

add_action( 'save_post_page', function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! six_home_is_home_template( $post_id ) ) return;
    if ( ! isset( $_POST['six_home_nonce'] ) || ! wp_verify_nonce( $_POST['six_home_nonce'], 'six_home_meta' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = array(
        'hero_heading', 'hero_subheading', 'hero_lead', 'hero_typing_words',
        'hero_cta1_label', 'hero_cta1_url', 'hero_cta2_label',
        'svc_heading', 'cstudy_eyebrow', 'cstudy_heading', 'cs_eyebrow', 'cs_heading',
        'commit_heading', 'commit_q', 'commit_cta1', 'commit_cta2',
        'dd_heading', 'tst_heading', 'blog_heading', 'blog_count',
        'final_heading', 'final_cta_label', 'final_cta_url',
    );
    foreach ( $text_fields as $f ) {
        $k = 'six_field_' . $f;
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
    }

    $area_fields = array( 'svc_intro', 'commit_p1', 'commit_p2' );
    foreach ( $area_fields as $f ) {
        $k = 'six_field_' . $f;
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) );
    }

    // Repeaters — validate against a known sub-field schema so only the
    // expected keys, sanitised by type, can ever be stored.
    $repeaters = array(
        'svc_cards' => array( 'image' => 'url', 'title' => 'text', 'text' => 'text', 'link' => 'text' ),
        'deepdives' => array( 'image' => 'url', 'eyebrow' => 'text', 'title' => 'text', 'text' => 'textarea', 'cta_label' => 'text', 'cta_url' => 'text' ),
    );
    foreach ( $repeaters as $field => $schema ) {
        $k = 'six_field_' . $field;
        if ( ! isset( $_POST[ $k ] ) ) continue;
        $decoded = json_decode( wp_unslash( $_POST[ $k ] ), true );
        $clean   = array();
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $row ) {
                if ( ! is_array( $row ) ) continue;
                $clean_row = array();
                foreach ( $schema as $sub_key => $sub_type ) {
                    $raw = $row[ $sub_key ] ?? '';
                    if ( $sub_type === 'url' ) $clean_row[ $sub_key ] = esc_url_raw( $raw );
                    elseif ( $sub_type === 'textarea' ) $clean_row[ $sub_key ] = sanitize_textarea_field( $raw );
                    else $clean_row[ $sub_key ] = sanitize_text_field( $raw );
                }
                if ( array_filter( $clean_row ) ) $clean[] = $clean_row;
            }
        }
        update_post_meta( $post_id, $k, wp_json_encode( $clean ) );
    }
} );
