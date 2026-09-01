<?php
/**
 * 6ix Developers — one-time marketing-site setup (code-driven).
 *
 * Runs once on the /6ix-redesign install after this code deploys, so no
 * dashboard clicks or REST access are needed:
 *   1. Creates a "Home" page using the 6ix — Home template and makes it the
 *      static front page.
 *   2. Seeds the Client Success + Testimonials post types with the current
 *      content so they can be edited/deleted in wp-admin (only if empty).
 *
 * Guarded by an option so it never repeats. Bump the version constant to
 * re-run a specific step in future.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_loaded', function () {
    if ( get_option( 'six_mk_setup_v3' ) ) return;
    update_option( 'six_mk_setup_v3', 1 ); // set first so a fatal can't loop
    // v3 adds the PPC Agency + Digital Marketing landing pages. v2 added the
    // service/about/contact pages. Home + seed steps below are idempotent
    // (existing pages reused, seeds only run when empty).

    // ── 1. Home page + front page ────────────────────────────────────────
    $tpl  = 'marketing/templates/template-home.php';
    $home = get_page_by_path( 'home' );
    if ( ! $home ) {
        // Reuse an existing front page if one is already set, else create.
        $front_id = (int) get_option( 'page_on_front' );
        $home_id  = $front_id ?: wp_insert_post( array(
            'post_title'   => 'Home',
            'post_name'    => 'home',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ) );
    } else {
        $home_id = $home->ID;
    }
    if ( $home_id && ! is_wp_error( $home_id ) ) {
        update_post_meta( $home_id, '_wp_page_template', $tpl );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $home_id );
    }

    // ── 1b. Service + About + Contact pages ──────────────────────────────
    // Create each page with the exact slug the nav/footer links point to and
    // assign the right template. Existing pages are reused (template updated).
    $pages = array(
        array( 'Website Design',      'website-design-agency-toronto',          'marketing/templates/template-service.php' ),
        array( 'Google Ads / PPC',    'ppc-google-ads-management-toronto',      'marketing/templates/template-service.php' ),
        array( 'SEO Services',        'seo-agency-toronto',                     'marketing/templates/template-service.php' ),
        array( 'Social Media',        'social-media-marketing-agency-toronto',  'marketing/templates/template-service.php' ),
        array( 'About Us',            'about-us',                               'marketing/templates/template-about.php' ),
        array( 'Contact Us',          'contact-us',                             'marketing/templates/template-contact.php' ),
        array( 'PPC Agency Toronto',  'ppc-agency-toronto',                     'marketing/templates/template-service.php' ),
        array( 'Digital Marketing',   'digital-marketing-agency-toronto',       'marketing/templates/template-service.php' ),
    );
    foreach ( $pages as $pg ) {
        list( $title, $name, $ptpl ) = $pg;
        $existing = get_page_by_path( $name );
        $pid = $existing ? $existing->ID : wp_insert_post( array(
            'post_title'   => $title,
            'post_name'    => $name,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ) );
        if ( $pid && ! is_wp_error( $pid ) ) {
            update_post_meta( $pid, '_wp_page_template', $ptpl );
        }
    }

    // ── 2. Seed Client Success (only if none exist) ──────────────────────
    if ( ! get_posts( array( 'post_type' => 'six_success', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) {
        $success = array(
            array( 'Criminal Law Firm',               '2024, Q3 - Q4', '16.50%', '6.80%',  '$125.70' ),
            array( 'Family Law Firm',                 '2024, Q3 - Q4', '19.10%', '7.40%',  '$104.84' ),
            array( 'Employment Law Firm',             '2024, Q3 - Q4', '22.10%', '6.30%',  '$61.21' ),
            array( 'Mortgage Agency',                 '2024, Q3 - Q4', '18.80%', '24.10%', '$19.64' ),
            array( 'Custom Apparel Printing Company', '2024, Q3 - Q4', '8.70%',  '8.30%',  '$35.76' ),
            array( 'Auto Mechanic Shop',              '2024, Q3 - Q4', '16.20%', '10.30%', '$25.84' ),
            array( 'Restaurant',                      '2024, Q3 - Q4', '9.04%',  '22.04%', '$9.95' ),
        );
        foreach ( $success as $i => $s ) {
            $id = wp_insert_post( array( 'post_title' => $s[0], 'post_type' => 'six_success', 'post_status' => 'publish', 'menu_order' => $i ) );
            if ( $id && ! is_wp_error( $id ) ) {
                update_post_meta( $id, 'six_cs_period', $s[1] );
                update_post_meta( $id, 'six_cs_conv',   $s[2] );
                update_post_meta( $id, 'six_cs_ctr',    $s[3] );
                update_post_meta( $id, 'six_cs_cpl',    $s[4] );
            }
        }
    }

    // ── 3. Seed Testimonials (only if none exist) ────────────────────────
    if ( ! get_posts( array( 'post_type' => 'six_testimonial', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) {
        $tst = array(
            array( 'Annie C.',      'I am very thankful to 6ix Developers for their services. I am super happy with my website and Google Ads. Coming from a bad experience, they made me feel comfortable and kept me in the loop with the whole progress of the website. Also I would like to thank Musab for suggesting and building a business plan for me and setting my business up with Google Ads. Much appreciated.' ),
            array( 'Elidrissia H.', 'I will definitely recommend this company to everybody who wants a professional and perfect website for their business. I am so impressed with their work, and my website came out perfect.' ),
            array( 'Barnard S.',    '6ix Developers did a great job of meeting our needs and helping us design the site we wanted. They were able to implement all of our requests, and contributed great ideas. Thanks a lot. Most recommended web developers.' ),
            array( 'Momi K.',       "6ix Developers has handled our SEO for over five years now, and have been a key partner in our growth. We were a startup when we first started working together, and they respected our smaller budget and worked to get us the best return on investment. Now that we're established, we know that we are in good hands as we market our company in a very competitive online environment. 5 stars for 6ix Developers." ),
        );
        foreach ( $tst as $i => $t ) {
            wp_insert_post( array( 'post_title' => $t[0], 'post_content' => $t[1], 'post_type' => 'six_testimonial', 'post_status' => 'publish', 'menu_order' => $i ) );
        }
    }
}, 20 );

/**
 * v4 — Case Studies. Creates the /case-studies listing Page (assigned the
 * Case Studies template), seeds one example story so the section/layout is
 * populated on first load, and flushes rewrite rules so the new
 * /case-study/{slug} permalinks resolve. Idempotent and guarded by its own
 * option so it never repeats.
 */
add_action( 'wp_loaded', function () {
    if ( get_option( 'six_mk_setup_v4' ) ) return;
    update_option( 'six_mk_setup_v4', 1 );

    // 1. Listing page → template-case-studies.php at /case-studies
    $existing = get_page_by_path( 'case-studies' );
    $pid = $existing ? $existing->ID : wp_insert_post( array(
        'post_title'  => 'Case Studies',
        'post_name'   => 'case-studies',
        'post_status' => 'publish',
        'post_type'   => 'page',
        'post_content'=> '',
    ) );
    if ( $pid && ! is_wp_error( $pid ) ) {
        update_post_meta( $pid, '_wp_page_template', 'marketing/templates/template-case-studies.php' );
    }

    // 2. Seed one example Case Study (only if none exist yet).
    if ( ! get_posts( array( 'post_type' => 'six_case_study', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) ) ) {
        $id = wp_insert_post( array(
            'post_title'  => 'College for Adult Learning',
            'post_type'   => 'six_case_study',
            'post_status' => 'publish',
            'menu_order'  => 0,
        ) );
        if ( $id && ! is_wp_error( $id ) ) {
            update_post_meta( $id, 'six_cs_subtitle', 'Training Organization Case Study' );
            update_post_meta( $id, 'six_cs_headline', '300%+ more sales with 60% lower cost per sale' );
            update_post_meta( $id, 'six_cs_background',
                'The College for Adult Learning (CAL) is a Melbourne-based Registered Training Organization (RTO) offering courses designed to further their students\' careers in the most effective way. After experiencing a sudden cost spike and unsustainable lead costs, CAL approached 6ix Developers with a number of objectives.' );
            // Icon-tagged JSON (the format the picker UI saves) so the seeded
            // example already demonstrates real icon choices when opened.
            update_post_meta( $id, 'six_cs_objectives_json', wp_json_encode( array(
                array( 'icon' => 'target',   'text' => 'Dramatically reduce cost-per-lead to a profitable level.' ),
                array( 'icon' => 'trending', 'text' => 'Increase the volume of qualified leads to drive business growth.' ),
                array( 'icon' => 'rocket',   'text' => 'Assist with the online launch of new courses and products.' ),
                array( 'icon' => 'bulb',     'text' => 'Implement and test new ideas to keep sales pushing ahead.' ),
            ) ) );
            update_post_meta( $id, 'six_cs_achievements_json', wp_json_encode( array(
                array( 'icon' => 'ads',    'text' => 'Rebuilt and re-organised existing Google Ads campaigns to sharply reduce cost per conversion.' ),
                array( 'icon' => 'layers', 'text' => 'Created targeted landing pages for specific courses.' ),
                array( 'icon' => 'gauge',  'text' => 'Provided input on the optimisation of the sales process.' ),
                array( 'icon' => 'chart',  'text' => 'Set up landing-page split tests to steadily improve conversion rates.' ),
                array( 'icon' => 'users',  'text' => 'Implemented automated email campaigns to qualify and convert enquiries into sales.' ),
            ) ) );
            update_post_meta( $id, 'six_cs_kr1_value', '300%+' );
            update_post_meta( $id, 'six_cs_kr1_label', 'Lead and sales volume increase' );
            update_post_meta( $id, 'six_cs_kr1_dir',   'up' );
            update_post_meta( $id, 'six_cs_kr2_value', '60%' );
            update_post_meta( $id, 'six_cs_kr2_label', 'Cost per sale drops by more than' );
            update_post_meta( $id, 'six_cs_kr2_dir',   'down' );
            update_post_meta( $id, 'six_cs_kr3_value', 'On target' );
            update_post_meta( $id, 'six_cs_kr3_label', 'Profitability and growth improve in line with goals and projections' );
            update_post_meta( $id, 'six_cs_kr3_dir',   'up' );
        }
    }

    // 3. New CPT rewrite slug needs a one-time flush to resolve.
    flush_rewrite_rules( false );
}, 25 );

/**
 * v5 — Repair pages whose _wp_page_template got silently wiped by
 * WordPress's native "Template" dropdown not knowing about our templates
 * (see the theme_page_templates filter in marketing.php for the permanent
 * fix — this repairs anything already broken by it, right now, with no
 * admin action needed). Runs on the very next request (front-end or admin)
 * after this deploys, then never again.
 */
add_action( 'wp_loaded', function () {
    if ( get_option( 'six_mk_setup_v5' ) ) return;
    update_option( 'six_mk_setup_v5', 1 );

    // Home page — whichever page is currently the site's front page.
    $front_id = (int) get_option( 'page_on_front' );
    if ( $front_id ) {
        $tpl = 'marketing/templates/template-home.php';
        if ( get_post_meta( $front_id, '_wp_page_template', true ) !== $tpl ) {
            update_post_meta( $front_id, '_wp_page_template', $tpl );
        }
    }

    // Every other managed page, matched by its known slug.
    foreach ( six_mk_managed_page_templates_by_slug() as $slug => $entry ) {
        $p = get_page_by_path( $slug );
        if ( ! $p ) continue;
        if ( get_post_meta( $p->ID, '_wp_page_template', true ) !== $entry[0] ) {
            update_post_meta( $p->ID, '_wp_page_template', $entry[0] );
        }
    }
}, 30 );

/**
 * v6 — Four more Case Studies covering the client types the site's Case
 * Studies section didn't have an example of yet (two law firms, one
 * construction company, one collectibles retailer — three GTA, one
 * Vancouver), each an SEO + Google Ads engagement. These are illustrative
 * composite stories, not real clients — no featured photo is set for any of
 * them so the card/brochure falls back to the icon placeholder rather than
 * implying a real business's photography. Idempotent (checked by title) and
 * guarded by its own option so it never repeats/duplicates.
 */
add_action( 'wp_loaded', function () {
    if ( get_option( 'six_mk_setup_v6' ) ) return;
    update_option( 'six_mk_setup_v6', 1 );

    $studies = array(
        array(
            'title'      => 'Sterling & Cole LLP',
            'subtitle'   => 'Personal Injury Law Firm Case Study — Toronto, ON',
            'headline'   => '214% more qualified case inquiries with 38% lower cost per lead',
            'background' => "Sterling & Cole LLP is a Toronto-based personal injury law firm representing clients across the GTA in motor vehicle accident, slip-and-fall, and long-term disability claims. Before partnering with 6ix Developers, the firm relied almost entirely on referrals and had no consistent way to generate new case inquiries online, leaving them to compete for scraps against larger, better-funded firms bidding aggressively on the same Google Ads keywords.",
            'objectives' => array(
                array( 'icon' => 'target',   'text' => 'Build a steady, predictable pipeline of qualified personal injury case inquiries.' ),
                array( 'icon' => 'trending', 'text' => "Lower cost per lead in one of Google Ads' most competitive and expensive verticals." ),
                array( 'icon' => 'search',   'text' => "Rank organically for high-intent local searches like 'personal injury lawyer Toronto'." ),
                array( 'icon' => 'users',    'text' => 'Attract the cases the firm actually wants — serious injury claims, not small-dollar disputes.' ),
            ),
            'achievements' => array(
                array( 'icon' => 'ads',    'text' => 'Rebuilt the Google Ads account around individual practice areas (car accidents, slip & fall, LTD) with dedicated ad groups and negative keyword lists.' ),
                array( 'icon' => 'search', 'text' => 'Ran a full technical and on-page SEO overhaul, fixing crawl issues and rebuilding practice-area landing pages around real search intent.' ),
                array( 'icon' => 'link',   'text' => 'Secured local legal directory citations and backlinks to strengthen topical authority and local pack rankings.' ),
                array( 'icon' => 'layers', 'text' => 'Built call tracking and lead scoring into intake so the firm could see which keywords produced retained cases, not just form fills.' ),
                array( 'icon' => 'gauge',  'text' => 'Improved page speed and mobile experience across every practice-area page, cutting bounce rate significantly.' ),
            ),
            'results' => array(
                array( '214%', 'Increase in qualified case inquiries', 'up' ),
                array( '38%',  'Lower cost per lead', 'down' ),
                array( '3x',   "More visibility in Google's Local Pack for core keywords", 'up' ),
                array( '61%',  'Of new inquiries now come from organic search, up from 12%', 'up' ),
            ),
        ),
        array(
            'title'      => 'Harbourview Family Law',
            'subtitle'   => 'Family Law Firm Case Study — Mississauga, ON',
            'headline'   => '176% growth in consultation bookings within 8 months',
            'background' => "Harbourview Family Law is a Mississauga-based family law practice handling divorce, custody, and separation agreements for clients throughout Peel Region and the GTA. The firm had a functioning website but almost no visibility on Google, and its existing Google Ads spend was going mostly to broad, expensive keywords that rarely turned into a booked consultation.",
            'objectives' => array(
                array( 'icon' => 'target',   'text' => 'Increase the volume of booked consultations, not just website form fills.' ),
                array( 'icon' => 'trending', 'text' => 'Reduce cost per consultation to a sustainable level for a small practice.' ),
                array( 'icon' => 'search',   'text' => "Build organic visibility for high-intent searches like 'divorce lawyer Mississauga' and 'child custody lawyer near me'." ),
                array( 'icon' => 'shield',   'text' => 'Position the firm as approachable and trustworthy for clients going through a difficult time.' ),
            ),
            'achievements' => array(
                array( 'icon' => 'ads',   'text' => 'Restructured Google Ads campaigns around specific services and moved to a call-tracking-driven bidding strategy.' ),
                array( 'icon' => 'pen',   'text' => 'Rewrote every service page with clearer, client-first messaging and calls-to-action.' ),
                array( 'icon' => 'seo',   'text' => 'Built out a local SEO foundation — Google Business Profile optimization, localized service pages, and consistent citations.' ),
                array( 'icon' => 'chart', 'text' => 'Set up conversion tracking so booked consultations, not just clicks, became the metric campaigns were optimized around.' ),
            ),
            'results' => array(
                array( '176%', 'Increase in booked consultations', 'up' ),
                array( '41%',  'Lower cost per consultation', 'down' ),
                array( '2.4x', 'Increase in organic search traffic', 'up' ),
                array( '22',   "Keywords ranking on Google's first page, up from 3", 'up' ),
            ),
        ),
        array(
            'title'      => 'BuildRight Construction Group',
            'subtitle'   => 'Commercial & Residential Construction Case Study — Vaughan, ON',
            'headline'   => '312% more project inquiries and a fully booked pipeline',
            'background' => "BuildRight Construction Group is a Vaughan-based general contractor handling residential renovations and mid-size commercial builds across the GTA. Word-of-mouth had carried the business for years, but with larger competitors dominating Google search and ads, BuildRight's project pipeline had become unpredictable — busy months followed by slow ones, with no reliable way to generate new leads on demand.",
            'objectives' => array(
                array( 'icon' => 'target',   'text' => 'Create a predictable flow of qualified project inquiries independent of referrals.' ),
                array( 'icon' => 'trending', 'text' => 'Reduce reliance on slow word-of-mouth growth with a scalable digital channel.' ),
                array( 'icon' => 'rocket',   'text' => "Support the launch of a new commercial-build service line with targeted campaigns." ),
                array( 'icon' => 'search',   'text' => "Rank for high-value local searches like 'general contractor Vaughan' and 'home renovation GTA'." ),
            ),
            'achievements' => array(
                array( 'icon' => 'ads',    'text' => 'Built separate Google Ads campaigns for residential renovation and commercial build inquiries, each with its own landing page and budget.' ),
                array( 'icon' => 'layers', 'text' => 'Restructured the site into clear service and project-type pages, each optimized around real search terms.' ),
                array( 'icon' => 'pen',    'text' => 'Added a project gallery and case-study-style pages to showcase completed work and build trust with commercial prospects.' ),
                array( 'icon' => 'link',   'text' => "Built local citations and supplier/partner backlinks to strengthen the site's authority for construction-related searches." ),
                array( 'icon' => 'gauge',  'text' => 'Fixed site speed and mobile usability issues that were hurting both rankings and lead-form completion rates.' ),
            ),
            'results' => array(
                array( '312%', 'Increase in project inquiries', 'up' ),
                array( '46%',  'Lower cost per lead', 'down' ),
                array( '4x',   'Increase in commercial-build inquiries specifically', 'up' ),
                array( '89%',  "Of the project pipeline now booked 60+ days out", 'up' ),
            ),
        ),
        array(
            'title'      => 'Vancouver Card Vault',
            'subtitle'   => 'Trading Card & Collectibles Retailer Case Study — Vancouver, BC',
            'headline'   => '268% growth in online sales in one year',
            'background' => "Vancouver Card Vault is a Vancouver-based retailer specializing in Pok\xc3\xa9mon and other trading card game singles, sealed product, and collectibles, selling both in-store and online. The trading card market moves fast, and the business needed to compete for search visibility and ad clicks against much larger national and international collectibles retailers with far bigger marketing budgets.",
            'objectives' => array(
                array( 'icon' => 'target',   'text' => 'Increase online sales without out-bidding national competitors dollar-for-dollar on Google Ads.' ),
                array( 'icon' => 'trending', 'text' => 'Grow organic traffic for high-volume searches around specific sets, cards, and product releases.' ),
                array( 'icon' => 'rocket',   'text' => 'Capitalize on new set launches and restocks with fast-turnaround campaigns.' ),
                array( 'icon' => 'users',    'text' => 'Build a loyal local and national customer base beyond one-time impulse buyers.' ),
            ),
            'achievements' => array(
                array( 'icon' => 'ads',    'text' => 'Built Google Shopping and Search campaigns structured around specific sets and product types, prioritizing high-margin sealed product.' ),
                array( 'icon' => 'seo',    'text' => 'Optimized product and category pages for search terms tied to specific sets, singles, and restocks.' ),
                array( 'icon' => 'chart',  'text' => 'Set up inventory-aware campaign automation so ads paused automatically when high-demand product sold out, protecting ad spend.' ),
                array( 'icon' => 'social', 'text' => 'Coordinated organic social content with paid campaigns around major set releases to drive launch-day traffic.' ),
                array( 'icon' => 'gauge',  'text' => 'Improved site speed and checkout flow, reducing cart abandonment during high-traffic restock events.' ),
            ),
            'results' => array(
                array( '268%', 'Growth in online sales', 'up' ),
                array( '34%',  'Lower cost per acquisition', 'down' ),
                array( '5.1x', 'Increase in organic traffic for set-specific searches', 'up' ),
                array( '3.8x', 'Return on ad spend across Google Ads campaigns', 'up' ),
            ),
        ),
    );

    foreach ( $studies as $i => $s ) {
        // Already seeded? (defensive extra check on top of the option guard above)
        if ( get_posts( array( 'post_type' => 'six_case_study', 'title' => $s['title'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) continue;

        $id = wp_insert_post( array(
            'post_title'  => $s['title'],
            'post_type'   => 'six_case_study',
            'post_status' => 'publish',
            'menu_order'  => 10 + $i, // after the original example story
        ) );
        if ( ! $id || is_wp_error( $id ) ) continue;

        update_post_meta( $id, 'six_cs_subtitle',   $s['subtitle'] );
        update_post_meta( $id, 'six_cs_headline',   $s['headline'] );
        update_post_meta( $id, 'six_cs_background', $s['background'] );
        update_post_meta( $id, 'six_cs_objectives_json',   wp_json_encode( $s['objectives'] ) );
        update_post_meta( $id, 'six_cs_achievements_json', wp_json_encode( $s['achievements'] ) );
        foreach ( $s['results'] as $ri => $r ) {
            $n = $ri + 1;
            update_post_meta( $id, "six_cs_kr{$n}_value", $r[0] );
            update_post_meta( $id, "six_cs_kr{$n}_label", $r[1] );
            update_post_meta( $id, "six_cs_kr{$n}_dir",   $r[2] );
        }
    }
}, 35 );

/**
 * v7 — Four SEO-oriented blog posts for 6ix Developers itself (native
 * WordPress `post` type, rendered by index.php's blog archive/single views —
 * no separate CPT needed). Each targets a real search intent tied to one of
 * the agency's own service pages, structured with H2s and a closing CTA so
 * it reads well and links back into the site. Idempotent (checked by title)
 * and guarded by its own option so it never repeats/duplicates.
 */
add_action( 'wp_loaded', function () {
    if ( get_option( 'six_mk_setup_v7' ) ) return;
    update_option( 'six_mk_setup_v7', 1 );

    $posts = array(
        array(
            'title'   => 'How to Choose the Right SEO Agency in Toronto (7 Questions to Ask First)',
            'excerpt' => "Not sure how to vet an SEO agency? Here are the 7 questions that separate a real SEO partner from an agency that will waste your budget.",
            'content' => "<p>If you've searched \"SEO agency Toronto,\" you already know the problem: there are hundreds of them, and most of their websites say almost exactly the same thing. \"Proven results.\" \"Data-driven strategy.\" \"Dedicated account manager.\" None of that tells you whether an agency will actually move the needle for your business.</p>\n<p>After years of working with GTA businesses — law firms, contractors, retailers, and everything in between — here's what we tell every prospective client to ask before signing anything.</p>\n<h2>1. Can you show me results for a business like mine?</h2>\n<p>SEO tactics that work for an e-commerce store selling collectibles look very different from what works for a personal injury law firm. Ask for case studies in your industry or a comparable one, and ask specifically what changed — rankings, traffic, and (most importantly) leads or sales.</p>\n<h2>2. What does your reporting actually measure?</h2>\n<p>Rankings and traffic are easy to show and easy to manipulate with vanity keywords nobody searches for. Ask whether the agency reports on leads, calls, and conversions — the numbers that actually matter to your bottom line.</p>\n<h2>3. Will you tell me what you're actually doing each month?</h2>\n<p>A good agency can explain, in plain language, what work is happening this month and why. If the answer is vague (\"ongoing optimization\"), that's a red flag.</p>\n<h2>4. How do you handle technical SEO?</h2>\n<p>Content and links get the attention, but a slow, broken, or poorly structured website will cap your rankings no matter how much content you publish. Ask how they audit and fix technical issues, not just how they write blog posts.</p>\n<h2>5. What's your approach to local SEO?</h2>\n<p>If most of your customers are in the GTA, your agency needs a real local SEO strategy — Google Business Profile optimization, local citations, and location-specific landing pages — not just generic national SEO applied to a local business.</p>\n<h2>6. What happens if I want to leave?</h2>\n<p>Ask about contract length and what you keep — your website, your content, your Google Business Profile access — if you ever part ways. A confident agency won't lock you into anything that makes you nervous.</p>\n<h2>7. How long until we see results?</h2>\n<p>Anyone promising first-page rankings in 30 days is either exaggerating or planning to use tactics that will get your site penalized later. Real, durable SEO growth typically takes 4–6 months to show clearly in the numbers — an honest agency will tell you that upfront.</p>\n<h2>The bottom line</h2>\n<p>The right SEO agency should be able to answer all seven of these questions clearly, specifically, and without hesitation. If they can't, keep looking.</p>\n<p>6ix Developers has been managing SEO for GTA businesses for over a decade, from law firms to retailers to contractors. If you'd like a straight answer on what SEO could realistically do for your business, <a href=\"" . home_url( '/contact-us' ) . "\">get in touch for a free consultation</a>.</p>",
        ),
        array(
            'title'   => 'Google Ads vs. SEO: Which Should Your Business Invest In First?',
            'excerpt' => "Trying to decide between SEO and Google Ads with a limited budget? Here's how to think about which one to prioritize first — and why the answer isn't the same for every business.",
            'content' => "<p>This is one of the most common questions we get from new clients: should you spend your marketing budget on SEO or Google Ads first? The honest answer is \"it depends,\" but not in a way that dodges the question — there are a few clear factors that should decide it for you.</p>\n<h2>Google Ads is faster — but you pay for every click</h2>\n<p>Google Ads can put you at the top of search results within hours of launching a campaign. If you need leads this month, not in six months, ads are the more reliable short-term lever. The tradeoff is that the moment you stop paying, the traffic stops too.</p>\n<h2>SEO takes longer — but it compounds</h2>\n<p>Organic rankings typically take a few months to build, especially in competitive industries like legal, home services, or e-commerce. But once you rank, you're not paying per click — and a well-optimized page can keep generating leads for years with ongoing maintenance instead of constant new spend.</p>\n<h2>When to prioritize Google Ads first</h2>\n<ul>\n<li>You're a new business with little to no existing search visibility.</li>\n<li>You need leads immediately to fill a slow period or launch a new service.</li>\n<li>You want to test which keywords and messaging actually convert before investing in long-term content around them.</li>\n</ul>\n<h2>When to prioritize SEO first</h2>\n<ul>\n<li>You're in a highly competitive, high-cost-per-click industry (family law and personal injury are classic examples) where ad costs are eating your margins.</li>\n<li>You have the patience and budget to invest for 6+ months before expecting a full payoff.</li>\n<li>You want a channel that keeps producing leads even during months you can't spend on ads.</li>\n</ul>\n<h2>Why most GTA businesses eventually need both</h2>\n<p>In practice, most of our clients end up running both channels together — Google Ads for immediate volume and control, SEO for long-term, lower-cost-per-lead growth that isn't fully dependent on ad spend. The keyword and conversion data from your ad campaigns can even help sharpen your SEO strategy, and vice versa.</p>\n<p>Not sure which channel makes sense for your budget and timeline? <a href=\"" . home_url( '/contact-us' ) . "\">Talk to our team</a> and we'll walk you through a plan built around your actual business, not a one-size-fits-all package.</p>",
        ),
        array(
            'title'   => "5 Website Design Mistakes That Are Quietly Costing You Leads",
            'excerpt' => "Your website might look fine and still be losing you customers. Here are the 5 most common website design mistakes we find when auditing GTA small business sites — and how to fix them.",
            'content' => "<p>When we audit a new client's website, we're rarely looking at whether it \"looks nice.\" We're looking at whether it converts visitors into leads. A site can look polished and still be quietly leaking potential customers every single day. Here are the five mistakes we see most often.</p>\n<h2>1. No clear call-to-action above the fold</h2>\n<p>If a visitor has to scroll to figure out how to contact you or what you actually offer, you've already lost some of them. Every page — especially your homepage and service pages — needs one obvious next step: call now, book a consultation, get a quote.</p>\n<h2>2. Slow load times on mobile</h2>\n<p>Most local searches happen on a phone. If your site takes more than a few seconds to load on mobile, a meaningful percentage of visitors will leave before it even finishes rendering — and Google factors page speed into rankings too, so it's a double cost.</p>\n<h2>3. Generic, copy-pasted service page content</h2>\n<p>Thin, generic service pages don't rank well and don't build trust. Visitors (and Google) can tell the difference between a page written specifically for your business and one that reads like it was lifted from a template.</p>\n<h2>4. No social proof where decisions are actually made</h2>\n<p>Testimonials and case studies buried on a separate \"Reviews\" page do far less work than a few strong ones placed right next to your pricing or contact form, where visitors are actively deciding whether to trust you.</p>\n<h2>5. Contact forms that ask for too much</h2>\n<p>Every extra field on a lead form reduces the number of people who finish filling it out. Ask for what you truly need to follow up — usually just name, phone or email, and a short note — and gather the rest during the actual conversation.</p>\n<h2>The fix isn't always a full redesign</h2>\n<p>Most of these issues can be fixed without rebuilding your entire website from scratch — often it's a matter of restructuring existing pages, tightening load times, and rewriting a handful of key sections.</p>\n<p>Want a free, honest look at what your current site might be costing you in missed leads? <a href=\"" . home_url( '/contact-us' ) . "\">Request a free website review</a> from our team.</p>",
        ),
        array(
            'title'   => 'The GTA Small Business Local SEO Checklist for 2026',
            'excerpt' => "Ranking in the Google local pack matters more than ever for GTA small businesses. Here's a practical, no-fluff local SEO checklist you can start working through today.",
            'content' => "<p>For most local businesses in Toronto, Mississauga, Vaughan, and across the GTA, the Google \"local pack\" — the map and three business listings that show up above the regular search results — is where a huge share of new customers actually find you. Here's a practical checklist for improving your standing in it.</p>\n<h2>1. Fully complete your Google Business Profile</h2>\n<p>Category, hours, service area, phone number, photos, and a full business description — every empty field is a missed signal to Google and a missed trust signal to potential customers. Add new photos regularly; profiles with recent activity tend to perform better.</p>\n<h2>2. Keep your name, address, and phone number consistent everywhere</h2>\n<p>Your business information should match exactly across your website, Google Business Profile, and any directory listings. Inconsistencies (even something as small as \"St.\" vs. \"Street\") can quietly undermine local rankings.</p>\n<h2>3. Build location-specific landing pages if you serve multiple areas</h2>\n<p>If you serve Toronto, Mississauga, and Brampton, a single generic \"service areas\" page won't rank the way three well-written, location-specific pages will — as long as each one has genuinely unique, useful content, not just the city name swapped in.</p>\n<h2>4. Actively collect and respond to reviews</h2>\n<p>Review count and recency are a meaningful local ranking factor, and they're one of the few things a potential customer weighs just as heavily as your ranking position. Make asking for a review part of your normal process, and respond to every review — good or bad.</p>\n<h2>5. Get listed in relevant local and industry directories</h2>\n<p>Local chambers of commerce, industry associations, and reputable local directories all help reinforce that you're a real, established local business — which supports both rankings and trust.</p>\n<h2>6. Make sure your site is genuinely mobile-friendly</h2>\n<p>Most local searches — \"near me\" searches especially — happen on a phone. A site that's slow or awkward to use on mobile will underperform in local search regardless of how good the rest of your SEO is.</p>\n<h2>7. Track calls and direction requests, not just website visits</h2>\n<p>A lot of local SEO's real value shows up as phone calls and \"Get Directions\" taps straight from your Google Business Profile — make sure you're actually tracking those, not just website traffic, when you judge whether local SEO is working.</p>\n<h2>Start with what's broken, not everything at once</h2>\n<p>You don't need to tackle this whole list in a weekend. Start with your Google Business Profile and NAP consistency — they have the highest impact for the least effort — then work down the list.</p>\n<p>If you'd rather have this handled for you, <a href=\"" . home_url( '/contact-us' ) . "\">reach out to 6ix Developers</a> — local SEO for GTA businesses is a big part of what we do every day.</p>",
        ),
    );

    foreach ( $posts as $i => $p ) {
        // Already seeded? (defensive extra check on top of the option guard above)
        if ( get_posts( array( 'post_type' => 'post', 'title' => $p['title'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) continue;

        wp_insert_post( array(
            'post_title'   => $p['title'],
            'post_excerpt' => $p['excerpt'],
            'post_content' => $p['content'],
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( "-" . ( ( count( $posts ) - $i ) * 4 ) . " days" ) ),
        ) );
    }
}, 40 );
