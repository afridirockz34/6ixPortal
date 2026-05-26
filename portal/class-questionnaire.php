<?php
/**
 * Six_Questionnaire — Central question registry for all onboarding services.
 *
 * Each service defines its question blocks. Shared fields are declared once
 * and merged/deduped when a user selects multiple services.
 *
 * UPLOAD TO: /wp-content/themes/6ixClaude/portal/class-questionnaire.php
 */

if ( ! defined('ABSPATH') ) exit;

class Six_Questionnaire {

    // ── Shared field IDs that should never appear twice ───────────────────
    // Key = field ID used in JS/HTML, value = human label
    public static $shared_fields = array(
        'target_location' => 'Target Location',
        'usp'             => 'Unique Selling Points',
        'monthly_budget'  => 'Monthly Budget',
    );

    // ── Service definitions ────────────────────────────────────────────────
    // Each service returns an array of sections, each section has fields.
    // Field types: text, textarea, tags, slider, toggle, chips, hours
    // shared:true means the field is deduplicated across services.

    public static function get_service_questions( string $slug ): array {
        $map = array(
            'google-ads'      => array( __CLASS__, 'q_google_ads' ),
            'seo'             => array( __CLASS__, 'q_seo' ),
            'google-business' => array( __CLASS__, 'q_gbp' ),
            'website'         => array( __CLASS__, 'q_website' ),
        );
        if ( ! isset($map[$slug]) ) return array();
        return call_user_func( $map[$slug] );
    }

    // ── Google Ads ────────────────────────────────────────────────────────
    public static function q_google_ads(): array {
        return array(
            array(
                'section' => 'Campaign Setup',
                'fields'  => array(
                    array('id'=>'ads_prod',  'label'=>'Product / Service to Advertise',   'type'=>'textarea', 'placeholder'=>'e.g. Dental implants, emergency plumbing…'),
                    array('id'=>'ads_loc',   'label'=>'Target Locations',                 'type'=>'text',     'placeholder'=>'e.g. Toronto, North York, Mississauga', 'shared'=>true),
                    array('id'=>'ads_kw',    'label'=>'Target Keywords',                  'type'=>'tags',     'placeholder'=>'Type keyword + Enter'),
                    array('id'=>'ads_bud',   'label'=>'Monthly Google Ads Budget',        'type'=>'slider',   'min'=>300,'max'=>20000,'default'=>2000,'prefix'=>'$'),
                ),
            ),
            array(
                'section' => 'Offer & Messaging',
                'fields'  => array(
                    array('id'=>'ads_usp',   'label'=>'Unique Selling Points',            'type'=>'textarea', 'placeholder'=>'What makes you better than competitors?', 'shared'=>true),
                    array('id'=>'ads_promo', 'label'=>'Current Promotion or Offer',       'type'=>'text',     'placeholder'=>'e.g. Free consultation, 10% off first service'),
                    array('id'=>'ads_fin',   'label'=>'Financing Available?',             'type'=>'toggle'),
                ),
            ),
        );
    }

    // ── SEO ───────────────────────────────────────────────────────────────
    public static function q_seo(): array {
        return array(
            array(
                'section' => 'SEO Setup',
                'fields'  => array(
                    array('id'=>'seo_pages', 'label'=>'Primary Pages to Rank',           'type'=>'textarea', 'placeholder'=>'e.g. Homepage, Services, Dental Implants Toronto…'),
                    array('id'=>'seo_loc',   'label'=>'Target Locations',                'type'=>'text',     'placeholder'=>'e.g. Toronto, North York, Scarborough', 'shared'=>true),
                    array('id'=>'seo_kw',    'label'=>'Target Keywords',                 'type'=>'tags',     'placeholder'=>'Type keyword + Enter'),
                    array('id'=>'seo_bud',   'label'=>'Monthly SEO Budget',              'type'=>'slider',   'min'=>300,'max'=>10000,'default'=>1200,'prefix'=>'$'),
                    array('id'=>'seo_gsc',   'label'=>'Google Search Console Access?',   'type'=>'toggle'),
                ),
            ),
            array(
                'section' => 'Content & Strategy',
                'fields'  => array(
                    array('id'=>'seo_usp',   'label'=>'Unique Selling Points',           'type'=>'textarea', 'placeholder'=>'Why should customers choose you?', 'shared'=>true),
                    array('id'=>'seo_blog',  'label'=>'Existing Blog / Content?',        'type'=>'toggle'),
                    array('id'=>'seo_comp',  'label'=>'Top Competitors (URLs or names)', 'type'=>'text',     'placeholder'=>'e.g. competitor1.com, Local Plumber Co'),
                    array('id'=>'seo_domain','label'=>'Current Domain Age / Authority',  'type'=>'text',     'placeholder'=>'e.g. 3 years, or "new domain"'),
                ),
            ),
        );
    }

    // ── Google Business Profile ───────────────────────────────────────────
    public static function q_gbp(): array {
        return array(
            array(
                'section' => 'Business Profile',
                'fields'  => array(
                    array('id'=>'gbp_name',   'label'=>'Business Name on Google',        'type'=>'text',     'placeholder'=>'Acme Dental Care'),
                    array('id'=>'gbp_cat',    'label'=>'Primary Category',               'type'=>'text',     'placeholder'=>'e.g. Dentist, Plumber, Restaurant…'),
                    array('id'=>'gbp_svcs',   'label'=>'Services to Highlight',          'type'=>'textarea', 'placeholder'=>'List your main services…'),
                    array('id'=>'gbp_hrs',    'label'=>'Business Hours',                 'type'=>'hours'),
                    array('id'=>'gbp_bud',    'label'=>'Monthly GBP Budget',             'type'=>'slider',   'min'=>200,'max'=>3000,'default'=>400,'prefix'=>'$'),
                ),
            ),
            array(
                'section' => 'Reputation & Presence',
                'fields'  => array(
                    array('id'=>'gbp_rating', 'label'=>'Current Rating',                 'type'=>'text',     'placeholder'=>'e.g. 4.7 stars, 120 reviews'),
                    array('id'=>'gbp_photos', 'label'=>'Professional Photos Available?', 'type'=>'toggle'),
                    array('id'=>'gbp_posts',  'label'=>'Post Frequency Goal',            'type'=>'chips',    'options'=>array('Weekly','Bi-weekly','Monthly')),
                    array('id'=>'gbp_area',   'label'=>'Service Area (if differs from address)', 'type'=>'text', 'placeholder'=>'e.g. Greater Toronto Area'),
                ),
            ),
        );
    }

    // ── Website Development ───────────────────────────────────────────────
    public static function q_website(): array {
        return array(
            array(
                'section' => 'Website Project',
                'fields'  => array(
                    array('id'=>'web_goal',     'label'=>'Website Goal',                 'type'=>'chips',    'options'=>array('Lead Generation','E-commerce','Bookings','Portfolio','Brand Awareness')),
                    array('id'=>'web_pages',    'label'=>'Pages Needed',                 'type'=>'text',     'placeholder'=>'e.g. Home, About, Services, Contact, Blog'),
                    array('id'=>'web_platform', 'label'=>'Preferred Platform',           'type'=>'chips',    'options'=>array('WordPress','Shopify','Custom','No preference')),
                    array('id'=>'web_timeline', 'label'=>'Launch Timeline',              'type'=>'chips',    'options'=>array('ASAP','1 month','2–3 months','Flexible')),
                    array('id'=>'web_bud',      'label'=>'Website Budget',               'type'=>'slider',   'min'=>1500,'max'=>25000,'default'=>5000,'prefix'=>'$'),
                ),
            ),
            array(
                'section' => 'Design & Features',
                'fields'  => array(
                    array('id'=>'web_style',    'label'=>'Design Style',                 'type'=>'chips',    'options'=>array('Modern & Clean','Bold & Vibrant','Minimal','Corporate')),
                    array('id'=>'web_features', 'label'=>'Key Features Needed',          'type'=>'chips',    'options'=>array('Contact Form','Live Chat','Blog','Booking/Scheduling','E-commerce','Client Portal'), 'multi'=>true),
                    array('id'=>'web_refs',     'label'=>'Reference Sites You Like',     'type'=>'text',     'placeholder'=>'Paste URLs of sites you admire'),
                    array('id'=>'web_exist',    'label'=>'Existing Website URL',         'type'=>'text',     'placeholder'=>'https://yoursite.com (or "none")'),
                    array('id'=>'web_usp',      'label'=>'Unique Selling Points',        'type'=>'textarea', 'placeholder'=>'What makes you stand out?', 'shared'=>true),
                ),
            ),
        );
    }

    // ── Deduplication ─────────────────────────────────────────────────────
    // Merges questions from multiple services, removing shared field duplicates.
    // Returns sections keyed by service, with shared fields extracted.

    public static function merge_for_services( array $slugs ): array {
        $seen_shared = array(); // track shared field IDs already added
        $merged      = array(); // array of ['service'=>slug, 'section'=>..., 'fields'=>...]

        foreach ( $slugs as $slug ) {
            $sections = self::get_service_questions( $slug );
            foreach ( $sections as $section ) {
                $clean_fields = array();
                foreach ( $section['fields'] as $field ) {
                    if ( ! empty($field['shared']) ) {
                        // Shared field: only include first occurrence
                        $base_id = preg_replace('/^(ads|seo|gbp|web)_/', '', $field['id']);
                        // Normalise to common key
                        $share_key = isset(self::$shared_fields[$base_id]) ? $base_id : $field['label'];
                        if ( in_array($share_key, $seen_shared) ) continue;
                        $seen_shared[] = $share_key;
                    }
                    $clean_fields[] = $field;
                }
                if ( ! empty($clean_fields) ) {
                    $merged[] = array(
                        'service' => $slug,
                        'section' => $section['section'],
                        'fields'  => $clean_fields,
                    );
                }
            }
        }
        return $merged;
    }

    // ── DB migration ─────────────────────────────────────────────────────
    // Call via ?six_questionnaire_setup=1
    public static function run_migration(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'six_checkout_progress';

        $new_cols = array(
            'seo_comp'            => "VARCHAR(500) DEFAULT ''",
            'seo_domain'          => "VARCHAR(200) DEFAULT ''",
            'gbp_photos'          => "TINYINT(1) DEFAULT 0",
            'gbp_posts'           => "VARCHAR(50) DEFAULT ''",
            'gbp_area'            => "VARCHAR(200) DEFAULT ''",
            'web_platform'        => "VARCHAR(50) DEFAULT ''",
            'web_timeline'        => "VARCHAR(50) DEFAULT ''",
            'web_features'        => "VARCHAR(500) DEFAULT ''",
            'schedule_call_date'  => "DATE NULL",
            'schedule_call_time'  => "VARCHAR(20) DEFAULT ''",
            'schedule_call_notes' => "VARCHAR(500) DEFAULT ''",
            'call_scheduled_at'   => "DATETIME NULL",
        );

        $existing = $wpdb->get_col("DESCRIBE `{$table}`");
        $added    = array();

        foreach ( $new_cols as $col => $def ) {
            if ( ! in_array($col, $existing) ) {
                $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
                $added[] = $col;
            }
        }

        error_log('6ix Questionnaire migration: added=' . implode(',', $added ?: array('none')));
    }
}

// ── Admin URL trigger ──────────────────────────────────────────────────────
add_action('admin_init', function() {
    if ( current_user_can('manage_options') && isset($_GET['six_questionnaire_setup']) ) {
        Six_Questionnaire::run_migration();
        wp_die('Questionnaire migration complete. <a href="' . admin_url() . '">Back to admin</a>');
    }
});
