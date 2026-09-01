<?php
/**
 * 6ix Developers — Ninja Forms provisioning + admin controls.
 *
 * OFF by default. The built-in forms (marketing/forms.php) are real,
 * fully-verified, working forms on their own now — genuine multi-step
 * behaviour matching the original site exactly (Eligibility, Audit), a
 * native AJAX submit handler (marketing/form-handler.php) emailing every
 * submission with no plugin needed at all. This file exists purely as an
 * optional manual escape hatch (WP Admin → 6ix Portal → Website Forms) for
 * anyone who deliberately wants a specific form replaced with a real Ninja
 * Forms (or any other shortcode-based) form instead — auto-provisioning
 * only ever runs when six_nf_auto_provision_enabled is explicitly turned on
 * there.
 *
 * mk_form() (marketing/forms.php) supports swapping any built-in form for a
 * shortcode via a site-wide override option ("ninja_{$key}"), set with
 * mk_update_opt(). This file is what actually turns that on:
 *
 *   1. six_nf_form_specs() — the single source of truth for every lead-form
 *      on the site: fields, labels, options, required-ness, exactly
 *      mirroring what marketing/forms.php's built-in clones already render,
 *      so nothing about the copy or field set changes for a visitor when
 *      the swap happens — only the plugin generating the markup does.
 *   2. An idempotent, self-healing provisioning routine (same pattern as
 *      marketing/setup.php) that runs once Ninja Forms is active: creates
 *      any of the 7 forms that don't exist yet, using Ninja Forms' own
 *      import API, then wires the resulting shortcode into the matching
 *      override option automatically — no manual copy/paste needed.
 *   3. A small settings screen (wp-admin → 6ix Portal → Website Forms) that
 *      shows each form's current override shortcode (auto-filled once step
 *      2 has run) and lets it be edited by hand — e.g. to point at a form
 *      you rebuilt yourself, or to blank it out and fall back to the
 *      built-in clone again.
 *
 * IMPORTANT — this was written and tested without a live WordPress +
 * Ninja Forms install to verify against (this environment doesn't have
 * one). The provisioning routine is defensive (checks the plugin is
 * active, wraps creation in try/catch, never fatals, reports exactly what
 * it did/skipped/failed via an admin notice) — but the very first run on
 * the real site is the real test. If a form comes through empty or wrong,
 * six_nf_form_specs() below is the exact field-by-field spec to rebuild it
 * by hand in the Ninja Forms builder in a couple of minutes; nothing else
 * needs to change since mk_form() only cares about the shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Every lead-form on the marketing site, keyed exactly as mk_form()'s
 * override lookup expects (see marketing/forms.php — plain $key for
 * eligibility/audit/contact, $args['id'] for each quote variant).
 *
 * Field 'type' values map directly to Ninja Forms field types: textbox,
 * email, phone, textarea, listselect (dropdown), submit.
 */
function six_nf_form_specs() {
    $quote_fields = function ( $goal_label, $goal_options ) {
        return array(
            array( 'type' => 'listselect', 'key' => 'inquiry-type', 'label' => $goal_label, 'required' => true, 'options' => $goal_options ),
            array( 'type' => 'textbox',    'key' => 'website',      'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current website' ),
            array( 'type' => 'textbox',    'key' => 'company',      'label' => 'Business name', 'required' => true ),
            array( 'type' => 'textbox',    'key' => 'username',     'label' => 'Full name', 'required' => true ),
            array( 'type' => 'email',      'key' => 'email',        'label' => 'Email address', 'required' => true ),
            array( 'type' => 'phone',      'key' => 'phone',        'label' => 'Phone number', 'required' => false ),
            array( 'type' => 'textarea',   'key' => 'textarea',     'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message' ),
        );
    };

    return array(
        'eligibility' => array(
            'title'  => 'Google Ads $1800 Credit Eligibility',
            'submit' => 'Check Eligibility',
            'fields' => array(
                array( 'type' => 'textbox',    'key' => 'company1',       'label' => 'Business name', 'required' => true ),
                array( 'type' => 'listselect', 'key' => 'inquiry-typed',  'label' => 'Choose a sign-up offer', 'required' => true, 'options' => array(
                    '$600 in ad credit (Spend $600 with Google Ads in the first 60 days to unlock the credit)',
                    '$1200 in ad credit (Spend $1800 with Google Ads in the first 60 days to unlock the credit)',
                    '$1800 in ad credit (Spend $3600 with Google Ads in the first 60 days to unlock the credit)',
                ) ),
                array( 'type' => 'listselect', 'key' => 'account-type',   'label' => 'Do you already have a Google Ads account?', 'required' => true,
                    'options' => array( 'Yes', 'No' ) ),
                array( 'type' => 'textbox',    'key' => 'website1',       'label' => 'Provide website URL', 'required' => true, 'placeholder' => 'https://' ),
                array( 'type' => 'email',      'key' => 'email1',         'label' => 'Email address', 'required' => true ),
                array( 'type' => 'textbox',    'key' => 'username1',      'label' => 'Full name', 'required' => true, 'placeholder' => 'Name' ),
                array( 'type' => 'phone',      'key' => 'phone1',         'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone' ),
            ),
        ),
        'audit' => array(
            'title'  => 'Google Ads Audit Request',
            'submit' => 'SEND MESSAGE',
            'fields' => array(
                array( 'type' => 'listselect', 'key' => 'audit-inquiry-type', 'label' => 'Are you requesting an account audit for your business or someone else?', 'required' => true,
                    'options' => array( 'My Business', "Someone Else's Business" ) ),
                array( 'type' => 'textbox', 'key' => 'aboutbusiness',       'label' => 'Tell us about your Business / Industry', 'required' => true ),
                array( 'type' => 'textbox', 'key' => 'audit-company-name',  'label' => 'Business name', 'required' => true ),
                array( 'type' => 'textbox', 'key' => 'audit-website',       'label' => 'Provide website URL', 'required' => true, 'placeholder' => 'https://' ),
                array( 'type' => 'textbox', 'key' => 'audit-goals',         'label' => 'Google Ads Marketing Objective', 'required' => true, 'placeholder' => 'E.g. 30 leads/month' ),
                array( 'type' => 'textbox', 'key' => 'audit-services',      'label' => 'Description of services / products', 'required' => false ),
                array( 'type' => 'textbox', 'key' => 'audit-comp',          'label' => 'Your top online competitors', 'required' => false ),
                array( 'type' => 'textbox', 'key' => 'audit-selling',       'label' => 'Your Unique Selling Proposition', 'required' => false ),
                array( 'type' => 'textbox', 'key' => 'audit-current-leads', 'label' => 'Current number of leads / month', 'required' => false ),
                array( 'type' => 'textbox', 'key' => 'audit-desired-leads', 'label' => 'Desired number of leads / month', 'required' => false ),
                array( 'type' => 'textbox', 'key' => 'audit-monthly-ads',   'label' => 'Your current monthly ad spend', 'required' => true ),
                array( 'type' => 'textbox', 'key' => 'audit-account',       'label' => 'Google Ads account ID', 'required' => false, 'placeholder' => 'We will not send an access request without your permission' ),
                array( 'type' => 'textbox', 'key' => 'audit-username',      'label' => 'Full name', 'required' => true, 'placeholder' => 'Name' ),
                array( 'type' => 'email',   'key' => 'audit-email',         'label' => 'Email address', 'required' => true ),
                array( 'type' => 'phone',   'key' => 'audit-phone',         'label' => 'Phone number', 'required' => false ),
            ),
        ),

        // The four service pages' Quote/consultation forms — each genuinely
        // a different form on the original site (not one shared template):
        // Website Design has a package picker + a Google Ads upsell
        // checkbox, SEO has a keywords field, Social Media has a checkbox
        // group instead of a dropdown, and none of their fields are
        // required (unlike Eligibility/Audit above). The Google Ads page's
        // "consultation" form has no live original-site counterpart to
        // copy, so it keeps the earlier generic goal-select shape.
        'quote-website-design' => array(
            'title'  => 'Get Quote Now',
            'submit' => 'SEND MESSAGE',
            'fields' => array(
                array( 'type' => 'textbox',    'key' => 'username', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name' ),
                array( 'type' => 'email',      'key' => 'email',    'label' => 'Email address', 'required' => false, 'placeholder' => 'Email' ),
                array( 'type' => 'phone',      'key' => 'phone',    'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone' ),
                array( 'type' => 'textbox',    'key' => 'website',  'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website' ),
                array( 'type' => 'listselect', 'key' => 'package',  'label' => 'Website Type', 'required' => false,
                    'options' => array( 'Starter (1 to 5 Pages)', 'Standard (6 to 12 Pages)', 'Advanced / E-Commerce (13+ Pages)' ) ),
                array( 'type' => 'textarea',   'key' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message' ),
                array( 'type' => 'checkbox',   'key' => 'claim-google-ads', 'label' => 'Claim Free Google Ads Setup Valued $1500', 'required' => false ),
            ),
        ),
        'consultation-form' => array(
            'title'  => 'Book Your Google Ads Consultation',
            'submit' => 'Book My Consultation',
            'fields' => $quote_fields( 'Google Ads Marketing Objective', array( 'More qualified leads', 'More phone calls', 'More sales / bookings', 'More website traffic', 'Not sure yet' ) ),
        ),
        'quote-seo' => array(
            'title'  => 'Schedule SEO Call Today',
            'submit' => 'SEND MESSAGE',
            'fields' => array(
                array( 'type' => 'textbox', 'key' => 'username', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name' ),
                array( 'type' => 'email',   'key' => 'email',    'label' => 'Email address', 'required' => false, 'placeholder' => 'Email' ),
                array( 'type' => 'phone',   'key' => 'phone',    'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone' ),
                array( 'type' => 'textbox', 'key' => 'company',  'label' => 'Business name', 'required' => false, 'placeholder' => 'Company' ),
                array( 'type' => 'textbox', 'key' => 'website',  'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website' ),
                array( 'type' => 'textbox', 'key' => 'keywords', 'label' => 'Keywords', 'required' => false, 'placeholder' => 'Enter keywords separated by comma' ),
                array( 'type' => 'textarea','key' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message' ),
            ),
        ),
        'quote-social-media' => array(
            'title'  => 'Get Quote Now',
            'submit' => 'SEND MESSAGE',
            'fields' => array(
                array( 'type' => 'textbox',  'key' => 'username', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name' ),
                array( 'type' => 'email',    'key' => 'email',    'label' => 'Email address', 'required' => false, 'placeholder' => 'Email' ),
                array( 'type' => 'phone',    'key' => 'phone',    'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone' ),
                array( 'type' => 'textbox',  'key' => 'company',  'label' => 'Business name', 'required' => false, 'placeholder' => 'Company' ),
                array( 'type' => 'textbox',  'key' => 'website',  'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website' ),
                // The original site groups these as one "Social Media Inquiry"
                // checkbox set sharing an array field name — Ninja Forms has
                // no native equivalent, so each is its own checkbox field here.
                array( 'type' => 'checkbox', 'key' => 'chk-management', 'label' => 'Social Media Management', 'required' => false ),
                array( 'type' => 'checkbox', 'key' => 'chk-paid',       'label' => 'Social Media Paid Advertising', 'required' => false ),
                array( 'type' => 'checkbox', 'key' => 'chk-organic',    'label' => 'Social Media Organic Engagement', 'required' => false ),
                array( 'type' => 'checkbox', 'key' => 'chk-brand',      'label' => 'Social Media Brand Awareness', 'required' => false ),
                array( 'type' => 'textarea', 'key' => 'textarea', 'label' => 'Additional information', 'required' => false, 'placeholder' => 'Message' ),
            ),
        ),

        'contact' => array(
            'title'  => 'Book a Call',
            'submit' => 'SEND MESSAGE',
            'fields' => array(
                array( 'type' => 'textbox',  'key' => 'username', 'label' => 'Full name', 'required' => false, 'placeholder' => 'Name' ),
                array( 'type' => 'email',    'key' => 'email',    'label' => 'Email address', 'required' => false, 'placeholder' => 'Email' ),
                array( 'type' => 'phone',    'key' => 'phone',    'label' => 'Phone number', 'required' => false, 'placeholder' => 'Phone' ),
                array( 'type' => 'textbox',  'key' => 'company',  'label' => 'Business name', 'required' => false, 'placeholder' => 'Company' ),
                array( 'type' => 'textbox',  'key' => 'website',  'label' => 'Provide website URL', 'required' => false, 'placeholder' => 'Current Website' ),
                array( 'type' => 'textarea', 'key' => 'textarea', 'label' => 'How can we help?', 'required' => false, 'placeholder' => 'Message' ),
            ),
        ),
    );
}

/** Build one Ninja Forms field's settings array from a six_nf_form_specs() field entry. */
function six_nf_build_field_settings( $f ) {
    $settings = array(
        'type'        => $f['type'],
        'key'         => $f['key'],
        'label'       => $f['label'],
        'required'    => ! empty( $f['required'] ) ? 1 : 0,
        'label_pos'   => 'above',
        'placeholder' => $f['placeholder'] ?? '',
        'default'     => '',
        'admin_label' => '',
        'classes'     => '',
        'container_class' => '',
        'element_class'   => '',
    );
    if ( $f['type'] === 'listselect' ) {
        $opts = array();
        foreach ( (array) $f['options'] as $o ) {
            $opts[] = array( 'label' => $o, 'value' => sanitize_title( $o ), 'calc' => '', 'selected' => 0 );
        }
        $settings['options']         = $opts;
        $settings['use_key_as_value']= false;
    }
    return $settings;
}

/**
 * Build the payload for Ninja Forms' real import mechanism — verified
 * directly against the plugin's own source (NF_Database_Models_Form::import(),
 * called via Ninja_Forms()->form()->import_form()), not guessed:
 *   - $import['fields'] / $import['actions'] are FLAT arrays of settings
 *     arrays — no {'settings' => …} wrapper. (import() does
 *     `foreach ($import['fields'] as $settings)` and uses $settings
 *     directly.)
 *   - Field/action 'id' keys are omitted — import() unsets any 'id' anyway
 *     when $is_conversion is false (the case here, a fresh form).
 *   - The email action's real setting keys are `to` (not `email_to`),
 *     `email_format`, `email_message`, `from_name`, `from_address`,
 *     `reply_to`, `email_subject` (includes/Actions/Email.php +
 *     includes/Config/ActionEmailSettings.php).
 *   - The "all submitted fields" merge tag is {all_fields_table}, not
 *     {all_fields}.
 *   - The success-message action's real setting key is `success_msg`, not
 *     `success_message` (includes/Actions/SuccessMessage.php).
 */
function six_nf_build_import_data( $key, $spec ) {
    $fields = array();
    foreach ( $spec['fields'] as $f ) {
        $fields[] = six_nf_build_field_settings( $f );
    }
    $fields[] = array(
        'type'  => 'submit',
        'label' => $spec['submit'],
        'classes' => '', 'container_class' => '', 'element_class' => '',
    );

    $admin_email = get_option( 'admin_email' );

    return array(
        'fields'   => $fields,
        'actions'  => array(
            array(
                'type'          => 'email',
                'label'         => 'Email Notification',
                'active'        => true,
                'to'            => $admin_email,
                'from_name'     => get_bloginfo( 'name' ),
                'from_address'  => $admin_email,
                'reply_to'      => '{field:email}',
                'email_subject' => 'New ' . $spec['title'] . ' submission',
                'email_format'  => 'html',
                'email_message' => '{all_fields_table}',
            ),
            array(
                'type'       => 'successmessage',
                'label'      => 'Success Message',
                'active'     => true,
                'success_msg'=> 'Thanks — we\'ve received your submission and will be in touch shortly.',
            ),
        ),
        'settings' => array(
            'title'            => $spec['title'],
            'key'              => 'six_' . str_replace( '-', '_', $key ),
            'default_label_pos'=> 'above',
            // Was false — every form section on the site is meant to open
            // with a real heading (matching the original site's <h2>/<h3>
            // above each form); leaving Ninja Forms' own title suppressed
            // made every swapped-in form open straight into fields with no
            // heading at all.
            'show_title'       => true,
            'clear_complete'   => 1,
            'hide_complete'    => 0,
        ),
    );
}

/**
 * Auto-provisioning is OFF by default (see six_nf_disable_stale_overrides()
 * below for why) — the built-in forms (marketing/forms.php) are the real,
 * fully-verified lead forms now, including genuine multi-step behaviour and
 * a working native submit handler (marketing/form-handler.php) that
 * doesn't depend on any plugin. This stays available as an explicit opt-in:
 * flip six_nf_auto_provision_enabled to true (WP Admin → 6ix Portal →
 * Website Forms sets this, or `update_option('six_nf_auto_provision_enabled', true)`)
 * if a real Ninja Forms install is ever wanted for one of these forms —
 * whichever ones get a shortcode here still don't get multi-step behaviour
 * from Ninja Forms itself without a separate paid add-on.
 *
 * One-time, idempotent provisioning: create any of the 7 forms that don't
 * exist yet (matched by their settings.key, so re-running never duplicates),
 * and auto-wire the resulting shortcode into mk_opt('ninja_{$key}') so
 * mk_form() picks it up immediately with no other step needed.
 *
 * Guarded so it only ever attempts each key once successfully; a form that
 * fails to create is retried on the next page load rather than being
 * marked "done" — transient issues (e.g. Ninja Forms still finishing its
 * own activation routine) shouldn't need a manual re-trigger.
 */
add_action( 'wp_loaded', function () {
    if ( ! get_option( 'six_nf_auto_provision_enabled' ) ) return;
    if ( ! class_exists( 'Ninja_Forms' ) ) return;
    if ( ! function_exists( 'Ninja_Forms' ) ) return;

    $done = get_option( 'six_nf_provisioned', array() );
    $report = array();

    foreach ( six_nf_form_specs() as $key => $spec ) {
        if ( ! empty( $done[ $key ] ) ) continue; // already created (or already had an override set by hand)

        // Don't clobber a shortcode someone already set manually (e.g. via
        // the settings screen, pointed at a form they built themselves).
        if ( mk_opt( 'ninja_' . $key, '' ) ) {
            $done[ $key ] = 'manual';
            continue;
        }

        try {
            $data = six_nf_build_import_data( $key, $spec );
            // Ninja Forms' real, verified import entry point — see
            // six_nf_build_import_data()'s docblock. import_form() returns
            // the new form's numeric ID directly (it saves the form, its
            // fields, and its actions internally); there is no further
            // ->save()/->get_id() to chain.
            $form_id = Ninja_Forms()->form()->import_form( $data, false, false );
            if ( $form_id && intval( $form_id ) > 0 ) {
                mk_update_opt( 'ninja_' . $key, '[ninja_form id="' . intval( $form_id ) . '"]' );
                $done[ $key ]   = intval( $form_id );
                $report[ $key ] = 'created form #' . intval( $form_id );
            } else {
                $report[ $key ] = 'FAILED — Ninja_Forms()->form()->import_form() did not return a form id (got: ' . var_export( $form_id, true ) . ')';
            }
        } catch ( \Throwable $e ) {
            $report[ $key ] = 'FAILED — ' . $e->getMessage();
        }
    }

    if ( $report ) {
        update_option( 'six_nf_provisioned', $done );
        set_transient( 'six_nf_provision_report', $report, 300 );
    }
}, 40 );

/**
 * One-time cleanup: clear any 'ninja_*' overrides a PREVIOUS run of the
 * auto-provisioning above already set (e.g. on the eligibility form),
 * before this file's own bug is what disables it going forward.
 *
 * Those forms were provisioned with show_title => false (fixed in this
 * same change, but Ninja Forms never re-syncs an already-created form's
 * settings from a changed spec — provisioning only ever creates once),
 * so an already-provisioned form kept opening with no visible heading no
 * matter what the spec said afterwards. Clearing the override here makes
 * mk_form() fall back to the built-in form immediately — now the real,
 * fully-verified, multi-step, working one — instead of leaving a stale
 * Ninja Forms embed in charge. Guarded so it only ever runs once; an
 * admin who deliberately wants Ninja Forms back can still set the
 * option(s) again by hand on the Website Forms screen.
 */
add_action( 'wp_loaded', function () {
    if ( get_option( 'six_nf_stale_overrides_cleared_v1' ) ) return;
    update_option( 'six_nf_stale_overrides_cleared_v1', 1 );

    global $wpdb;
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'six_field_option_ninja_' ) . '%'
    ) );
}, 10 );

/** Admin notice summarising the most recent provisioning run (self-clearing). */
add_action( 'admin_notices', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || strpos( (string) $screen->id, 'six-portal' ) === false ) return;
    $report = get_transient( 'six_nf_provision_report' );
    if ( ! $report ) return;
    delete_transient( 'six_nf_provision_report' );
    $has_fail = false;
    echo '<div class="notice notice-info is-dismissible"><p><strong>Ninja Forms auto-setup ran:</strong></p><ul style="margin-left:18px;list-style:disc">';
    foreach ( $report as $key => $line ) {
        if ( strpos( $line, 'FAILED' ) === 0 ) $has_fail = true;
        echo '<li><code>' . esc_html( $key ) . '</code> — ' . esc_html( $line ) . '</li>';
    }
    echo '</ul>';
    if ( $has_fail ) {
        echo '<p>Some forms didn\'t create automatically. See 6ix Portal → Website Forms to set those shortcodes by hand once you\'ve built them in Ninja Forms — the exact fields each one needs are documented in marketing/ninja-forms.php.</p>';
    }
    echo '</div>';
} );

/* ── Admin screen: view / override each form's shortcode ────────────────── */
// Priority 20 — the '6ix Portal' parent menu itself is registered by
// six_admin_menu() (portal/admin-settings.php) on the default priority 10.
// functions.php requires marketing/marketing.php (which loads this file)
// BEFORE portal/admin-settings.php, so at the default priority this
// callback would run first and call add_submenu_page('six-portal', …)
// before add_menu_page(…, 'six-portal', …) has created that parent slug at
// all. WordPress doesn't error on that — it still lists the item — but
// without a resolved parent it can't build a correct admin.php?page=…
// link (the sidebar link comes out as the bare slug, e.g. /wp-admin/
// six-portal-forms instead of /wp-admin/admin.php?page=six-portal-forms,
// a real 404 on click) and direct navigation to the page fails as
// "Sorry, you are not allowed to access this page." Running this after
// the parent exists fixes both.
add_action( 'admin_menu', function () {
    add_submenu_page( 'six-portal', 'Website Forms', 'Website Forms', 'manage_options', 'six-portal-forms', 'six_nf_admin_page' );
}, 20 );

function six_nf_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['six_nf_nonce'] ) && wp_verify_nonce( $_POST['six_nf_nonce'], 'six_nf_save' ) ) {
        foreach ( array_keys( six_nf_form_specs() ) as $key ) {
            $field = 'ninja_' . $key;
            if ( isset( $_POST[ $field ] ) ) {
                mk_update_opt( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }
        update_option( 'six_nf_auto_provision_enabled', ! empty( $_POST['six_nf_auto_provision_enabled'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
    }

    $ninja_active   = class_exists( 'Ninja_Forms' );
    $auto_enabled   = (bool) get_option( 'six_nf_auto_provision_enabled' );
    ?>
    <div class="wrap">
      <h1>Website Forms</h1>
      <p>Every lead-capture form on the marketing site is a real, working, built-in form by default — multi-step where the original site's is (Eligibility, Audit), submits via AJAX, and emails the submission through this site's mail setup. No plugin required.</p>
      <p>Leave every row below blank to keep using those built-in forms (recommended — Ninja Forms and most other WP form plugins don't support multi-step without a separate paid add-on, so swapping a stepped form for one loses that behaviour). Set a row's shortcode only to deliberately replace one specific form with something you built yourself.</p>
      <?php if ( ! $ninja_active ) : ?>
      <div class="notice notice-warning"><p><strong>Ninja Forms isn't active.</strong> Auto-provisioning (below) needs it active to create anything.</p></div>
      <?php endif; ?>
      <form method="post">
        <?php wp_nonce_field( 'six_nf_save', 'six_nf_nonce' ); ?>
        <p>
          <label><input type="checkbox" name="six_nf_auto_provision_enabled" value="1"<?php checked( $auto_enabled ); ?>>
          Auto-create these as real Ninja Forms forms and use them in place of the built-in ones (off by default)</label>
        </p>
        <table class="widefat" style="max-width:900px;margin-top:12px">
          <thead><tr><th style="width:220px">Form</th><th>Shortcode override</th></tr></thead>
          <tbody>
          <?php foreach ( six_nf_form_specs() as $key => $spec ) :
              $val = mk_opt( 'ninja_' . $key, '' ); ?>
            <tr>
              <td><strong><?php echo esc_html( $spec['title'] ); ?></strong><br><code style="color:#888"><?php echo esc_html( $key ); ?></code></td>
              <td><input type="text" style="width:100%" name="ninja_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" placeholder='e.g. [ninja_form id="3"] or [contact-form-7 id="3"]'></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="submit"><button type="submit" class="button button-primary">Save Changes</button></p>
      </form>
    </div>
    <?php
}