<?php
/**
 * Six_Stripe — Stripe Payment Integration
 * Store card on file, charge when service starts
 */
if (!defined('ABSPATH')) exit;

class Six_Stripe {

    private static function get_secret_key() {
        return get_option('six_stripe_secret_key');
    }

    private static function api($endpoint, $data = array(), $method = 'POST') {
        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . self::get_secret_key(),
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Stripe-Version' => '2024-04-10',
            ),
        );
        if (!empty($data)) $args['body'] = http_build_query($data, '', '&');

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
        $response = ($method === 'GET')
            ? wp_remote_get($url . '?' . http_build_query($data), $args)
            : wp_remote_post($url, $args);

        if (is_wp_error($response)) return array('error' => $response->get_error_message());
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    // ── CREATE SETUP INTENT (save card without charging) ──────────────────
    public static function create_setup_intent($user_id) {
        $customer_id = self::get_or_create_customer($user_id);
        if (!$customer_id) return false;

        $result = self::api('setup_intents', array(
            'customer'             => $customer_id,
            'payment_method_types' => array('card'),
            'usage'                => 'off_session',
        ));
        return $result['client_secret'] ?? false;
    }

    // ── CREATE OR RETRIEVE STRIPE CUSTOMER ────────────────────────────────
    public static function get_or_create_customer($user_id) {
        $existing = get_user_meta($user_id, 'six_stripe_customer_id', true);
        if ($existing) return $existing;

        $user = get_userdata($user_id);
        $result = self::api('customers', array(
            'email'    => $user->user_email,
            'name'     => $user->display_name,
            'metadata' => array('wp_user_id' => $user_id),
        ));

        if (!empty($result['id'])) {
            update_user_meta($user_id, 'six_stripe_customer_id', $result['id']);
            return $result['id'];
        }
        return false;
    }

    // ── CHARGE CLIENT (when service starts) ───────────────────────────────
    public static function charge_client($user_id, $amount_cents, $description) {
        $customer_id = get_user_meta($user_id, 'six_stripe_customer_id', true);
        $pm_id       = get_user_meta($user_id, 'six_stripe_payment_method', true);

        if (!$customer_id || !$pm_id) return array('error' => 'No payment method on file.');

        return self::api('payment_intents', array(
            'amount'               => intval($amount_cents),
            'currency'             => 'cad',
            'customer'             => $customer_id,
            'payment_method'       => $pm_id,
            'description'          => $description,
            'confirm'              => 'true',
            'off_session'          => 'true',
        ));
    }

    // ── SAVE PAYMENT METHOD AFTER SETUP ───────────────────────────────────
    public static function save_payment_method($user_id, $payment_method_id) {
        // Attach PM to customer
        $customer_id = self::get_or_create_customer($user_id);
        self::api("payment_methods/{$payment_method_id}/attach", array(
            'customer' => $customer_id,
        ));
        update_user_meta($user_id, 'six_stripe_payment_method', $payment_method_id);
        update_user_meta($user_id, 'six_card_saved', 1);
        six_calculate_checkout_score($user_id); // update score
        return true;
    }

    // ── GET PAYMENT METHOD DETAILS (card brand, last4, expiry) ──────────
    public static function get_payment_method_details( $payment_method_id ) {
        if ( ! $payment_method_id ) return null;
        return self::api( "payment_methods/{$payment_method_id}", array(), 'GET' );
    }

    // ── GET INVOICES ───────────────────────────────────────────────────────
    public static function get_invoices($user_id) {
        $customer_id = get_user_meta($user_id, 'six_stripe_customer_id', true);
        if (!$customer_id) return array();

        $result = self::api("invoices", array('customer' => $customer_id, 'limit' => 12), 'GET');
        return $result['data'] ?? array();
    }

    // ── ATTACH PAYMENT METHOD TO CUSTOMER ─────────────────────────────────
    // Alias kept for backwards compatibility — internally calls save_payment_method
    public static function attach_payment_method( $user_id, $payment_method_id ) {
        return self::save_payment_method( $user_id, $payment_method_id );
    }

    // ── WEBHOOK HANDLER ───────────────────────────────────────────────────
    public static function handle_webhook( $payload, $sig_header ) {
        $secret = get_option( 'six_stripe_webhook_secret' );
        $event  = null;

        // Verify signature if secret is configured
        if ( $secret ) {
            // Manual HMAC verification (no Stripe PHP SDK required)
            $parts = explode( ',', $sig_header );
            $ts = $v1 = '';
            foreach ( $parts as $part ) {
                $part = trim( $part );
                if ( strpos($part, 't=')  === 0 ) $ts = substr($part, 2);
                if ( strpos($part, 'v1=') === 0 ) $v1 = substr($part, 3);
            }
            $signed_payload = $ts . '.' . $payload;
            $expected       = hash_hmac( 'sha256', $signed_payload, $secret );
            if ( ! hash_equals($expected, $v1) ) {
                return array( 'error' => 'Invalid signature' );
            }
        }

        $event = json_decode( $payload, true );
        if ( ! $event || ! isset($event['type']) ) {
            return array( 'error' => 'Invalid event payload' );
        }

        $type = $event['type'];
        $obj  = $event['data']['object'] ?? array();

        switch ( $type ) {
            // ── Setup intents (card saved during onboarding) ──────────────
            case 'setup_intent.succeeded':
                $customer_id = $obj['customer'] ?? '';
                $pm_id       = $obj['payment_method'] ?? '';
                if ( $customer_id && $pm_id ) {
                    $user_id = self::get_user_by_customer( $customer_id );
                    if ( $user_id ) {
                        self::save_payment_method( $user_id, $pm_id );
                    }
                }
                break;

            // ── Payment intents (charges) ─────────────────────────────────
            case 'payment_intent.created':
                // No action needed on creation
                break;

            case 'payment_intent.succeeded':
                $customer_id = $obj['customer'] ?? '';
                $amount      = $obj['amount']   ?? 0;
                $description = $obj['description'] ?? '';
                do_action( 'six_payment_succeeded', $customer_id, $amount, $description );
                break;

            // ── Invoices ──────────────────────────────────────────────────
            case 'invoice.created':
                // Log or store if needed
                do_action( 'six_invoice_created', $obj );
                break;

            case 'invoice.paid':
                $customer_id = $obj['customer'] ?? '';
                $amount      = $obj['amount_paid'] ?? 0;
                $user_id     = self::get_user_by_customer( $customer_id );
                if ( $user_id ) {
                    do_action( 'six_invoice_paid', $user_id, $amount, $obj );
                }
                break;

            // ── Checkout sessions (if you ever use Stripe Checkout) ───────
            case 'checkout.session.completed':
                $customer_id = $obj['customer'] ?? '';
                $user_id     = $customer_id ? self::get_user_by_customer($customer_id) : 0;
                do_action( 'six_checkout_completed', $user_id, $obj );
                break;

            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                // Handle failed/expired sessions
                do_action( 'six_checkout_failed', $obj );
                break;

            case 'checkout.session.async_payment_succeeded':
                $customer_id = $obj['customer'] ?? '';
                $user_id     = $customer_id ? self::get_user_by_customer($customer_id) : 0;
                do_action( 'six_checkout_payment_succeeded', $user_id, $obj );
                break;
        }

        return array( 'success' => true );
    }

    private static function get_user_by_customer( $customer_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key='six_stripe_customer_id' AND meta_value=%s",
            $customer_id
        ) );
    }
}

// ── REGISTER THE STRIPE WEBHOOK REST ENDPOINT ─────────────────────────────
// This is why you were getting 404 on your webhook URL.
// In your Stripe dashboard the webhook URL should be:
// https://6ixdevelopers.com/6ix-redesign/wp-json/six/v1/stripe-webhook
add_action( 'rest_api_init', 'six_register_stripe_webhook_route' );
function six_register_stripe_webhook_route() {
    register_rest_route( 'six/v1', '/stripe-webhook', array(
        'methods'             => 'POST',
        'callback'            => 'six_handle_stripe_webhook_request',
        'permission_callback' => '__return_true', // Stripe doesn't send auth headers
    ) );
}

function six_handle_stripe_webhook_request( WP_REST_Request $request ) {
    $payload    = $request->get_body();
    $sig_header = $request->get_header('Stripe-Signature');

    if ( ! class_exists('Six_Stripe') ) {
        return new WP_REST_Response( array('error' => 'Stripe class not loaded'), 500 );
    }

    $result = Six_Stripe::handle_webhook( $payload, $sig_header ?: '' );

    if ( isset($result['error']) ) {
        error_log( '6ix Stripe webhook error: ' . $result['error'] );
        return new WP_REST_Response( array('error' => $result['error']), 400 );
    }

    return new WP_REST_Response( array('success' => true), 200 );
}
