<?php
/**
 * 6ix Developers — Ninja Forms provisioning + admin controls.
 *
 * mk_form() (marketing/forms.php) already supports swapping any built-in
 * form for a Ninja Forms shortcode via a site-wide override option
 * ("ninja_{$key}"), set with mk_update_opt(). This file is what actually
 * turns that on:
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
            'submit' => 'Claim Now',
            'fields' => array(
                array( 'type' => 'textbox',    'key' => 'company1',       'label' => 'Business name', 'required' => true ),
                array( 'type' => 'listselect', 'key' => 'inquiry-typed',  'label' => 'Choose a sign-up offer', 'required' => true,
                    'options' => array( 'Up to $600 credit', 'Up to $1800 credit', 'Up to $3600 credit' ) ),
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
            'submit' => 'Request My Audit',
            'fields' => array(
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

        // The four service pages' Quote/consultation forms — same field set,
        // different goal question + options + submit label per page, and
        // each independently swappable (see mk_form()'s $args['id'] lookup).
        'quote-website-design' => array(
            'title'  => 'Get Quote Now — Website Design',
            'submit' => 'Get My Quote',
            'fields' => $quote_fields( 'What kind of website do you need?', array( 'New website', 'Website redesign', 'E-commerce store', 'Landing page', 'Not sure yet' ) ),
        ),
        'consultation-form' => array(
            'title'  => 'Book Your Google Ads Consultation',
            'submit' => 'Book My Consultation',
            'fields' => $quote_fields( 'Google Ads Marketing Objective', array( 'More qualified leads', 'More phone calls', 'More sales / bookings', 'More website traffic', 'Not sure yet' ) ),
        ),
        'quote-seo' => array(
            'title'  => 'Schedule SEO Call Today',
            'submit' => 'Schedule My Call',
            'fields' => $quote_fields( 'What are your SEO goals?', array( 'Rank higher on Google', 'More organic traffic', 'Local SEO / Google Maps', 'Recover lost rankings', 'Not sure yet' ) ),
        ),
        'quote-social-media' => array(
            'title'  => 'Get Quote Now — Social Media',
            'submit' => 'Get My Quote',
            'fields' => $quote_fields( 'Social Media Inquiry', array( 'Grow my following', 'Social media management', 'Paid social advertising', 'Branding & content', 'Not sure yet' ) ),
        ),

        'contact' => array(
            'title'  => 'Contact Form',
            'submit' => 'Send Message',
            'fields' => array(
                array( 'type' => 'textbox',  'key' => 'username', 'label' => 'Full name', 'required' => true ),
                array( 'type' => 'email',    'key' => 'email',    'label' => 'Email address', 'required' => true ),
                array( 'type' => 'phone',    'key' => 'phone',    'label' => 'Phone number', 'required' => false ),
                array( 'type' => 'textbox',  'key' => 'company',  'label' => 'Business name', 'required' => false ),
                array( 'type' => 'textarea', 'key' => 'textarea', 'label' => 'How can we help?', 'required' => true, 'placeholder' => 'Your message' ),
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
 * Build the full Ninja Forms import_data() payload for one form spec —
 * fields (+ a trailing submit field) and two actions (email notification to
 * the site admin, and a success message), matching the shape Ninja Forms'
 * own import/export uses. Field/action 'id' keys are intentionally omitted
 * — Ninja Forms assigns those on save for a new form.
 */
function six_nf_build_import_data( $key, $spec ) {
    $fields = array();
    foreach ( $spec['fields'] as $f ) {
        $fields[] = array( 'settings' => six_nf_build_field_settings( $f ) );
    }
    $fields[] = array( 'settings' => array(
        'type'  => 'submit',
        'label' => $spec['submit'],
        'classes' => '', 'container_class' => '', 'element_class' => '',
    ) );

    $admin_email = get_option( 'admin_email' );

    return array(
        'fields'   => $fields,
        'actions'  => array(
            array( 'settings' => array(
                'type'          => 'email',
                'label'         => 'Email Notification',
                'active'        => true,
                'email_to'      => $admin_email,
                'from_name'     => get_bloginfo( 'name' ),
                'from_address'  => $admin_email,
                'reply_to'      => '{field:email}',
                'email_subject' => 'New ' . $spec['title'] . ' submission',
                'email_message' => '{all_fields}',
            ) ),
            array( 'settings' => array(
                'type'            => 'successmessage',
                'label'           => 'Success Message',
                'active'          => true,
                'success_message' => 'Thanks — we\'ve received your submission and will be in touch shortly.',
            ) ),
        ),
        'settings' => array(
            'title'            => $spec['title'],
            'key'              => 'six_' . str_replace( '-', '_', $key ),
            'default_label_pos'=> 'above',
            'show_title'       => false,
            'clear_complete'   => 1,
            'hide_complete'    => 0,
        ),
    );
}

/**
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
            $data    = six_nf_build_import_data( $key, $spec );
            $form_id = Ninja_Forms()->form()->import_data( $data )->save()->get_id();
            if ( $form_id && intval( $form_id ) > 0 ) {
                mk_update_opt( 'ninja_' . $key, '[ninja_form id="' . intval( $form_id ) . '"]' );
                $done[ $key ]   = intval( $form_id );
                $report[ $key ] = 'created form #' . intval( $form_id );
            } else {
                $report[ $key ] = 'FAILED — Ninja_Forms()->form()->import_data()->save() did not return a form id';
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
        echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
    }

    $ninja_active = class_exists( 'Ninja_Forms' );
    ?>
    <div class="wrap">
      <h1>Website Forms</h1>
      <p>Every lead-capture form on the marketing site (6ixdevelopers.com), and which Ninja Forms shortcode currently replaces its built-in version. Leave a row blank to use the built-in styled form instead.</p>
      <?php if ( ! $ninja_active ) : ?>
      <div class="notice notice-warning"><p><strong>Ninja Forms isn\'t active</strong> — activate the plugin and reload this page; the forms below will be created automatically.</p></div>
      <?php endif; ?>
      <form method="post">
        <?php wp_nonce_field( 'six_nf_save', 'six_nf_nonce' ); ?>
        <table class="widefat" style="max-width:900px;margin-top:12px">
          <thead><tr><th style="width:220px">Form</th><th>Ninja Forms shortcode</th></tr></thead>
          <tbody>
          <?php foreach ( six_nf_form_specs() as $key => $spec ) :
              $val = mk_opt( 'ninja_' . $key, '' ); ?>
            <tr>
              <td><strong><?php echo esc_html( $spec['title'] ); ?></strong><br><code style="color:#888"><?php echo esc_html( $key ); ?></code></td>
              <td><input type="text" style="width:100%" name="ninja_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" placeholder='e.g. [ninja_form id="3"]'></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="submit"><button type="submit" class="button button-primary">Save Changes</button></p>
      </form>
    </div>
    <?php
}