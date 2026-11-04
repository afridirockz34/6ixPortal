<?php
/**
 * The SEO questionnaire — one declarative schema.
 *
 * This is the single source of truth for the client questionnaire: it drives
 * the form UI, the database columns, what the profile API returns, and — most
 * importantly — what we DON'T ask twice.
 *
 * Deduplication works on three rules, because a question can already be
 * answered in three different ways:
 *
 *   'maps_to'      This question is already asked somewhere in onboarding.
 *                  It reuses that existing column instead of creating a new
 *                  one, and the form shows the existing answer rather than
 *                  asking again.
 *
 *   'skip_if_svc'  Another SERVICE's questions already cover this. A client
 *                  who bought Google Business Profile has already told us
 *                  about their listing, so the SEO questionnaire doesn't
 *                  re-ask it. This is the "duplicated if added with another
 *                  service" case.
 *
 *   'prefill_from' Not the same question, but a related answer makes a
 *                  sensible starting point the client can edit.
 *
 * Any entry with none of those three is genuinely new and gets its own column
 * (see six_seo_questionnaire_columns()).
 *
 * Source: "6ix Developers Client Questionnaire" (SEO), sections 1-10.
 * Section 10 (sign-off) is omitted — onboarding already captures a signature.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @return array[] Section key => ['title','intro','fields'=>[...]]
 */
function six_seo_questionnaire_schema() {
	return array(

		// ── 1. Contact ───────────────────────────────────────────────────
		// Six of the nine fields in this section are already collected at
		// signup, so only three are asked here.
		'contact' => array(
			'title'  => 'Your details',
			'intro'  => 'Most of this we already have — just filling the gaps.',
			'fields' => array(
				array( 'key' => 'first_name',      'label' => 'Full name',      'type' => 'text',  'maps_to' => 'first_name' ),
				array( 'key' => 'contact_title',   'label' => 'Your title',     'type' => 'text',  'placeholder' => 'e.g. Owner, Marketing Manager' ),
				array( 'key' => 'business_name',   'label' => 'Business name',  'type' => 'text',  'maps_to' => 'business_name' ),
				array( 'key' => 'brand_tagline',   'label' => 'Tagline',        'type' => 'text',  'optional' => true, 'placeholder' => 'If you have one' ),
				array( 'key' => 'website',         'label' => 'Website',        'type' => 'url',   'maps_to' => 'website' ),
				array( 'key' => 'phone',           'label' => 'Phone',          'type' => 'tel',   'maps_to' => 'phone' ),
				array( 'key' => 'business_address','label' => 'Physical address','type' => 'text', 'maps_to' => 'business_address' ),
				array( 'key' => 'mailing_address', 'label' => 'Mailing address','type' => 'text',  'optional' => true, 'help' => 'Only if different from the physical address' ),
			),
		),

		// ── 2. Brand ─────────────────────────────────────────────────────
		'brand' => array(
			'title'  => 'Your brand',
			'intro'  => "If you're unsure about a question or want to talk it through first, leave it blank — we'll cover it on the call.",
			'fields' => array(
				array( 'key' => 'brand_business_explain', 'label' => 'Explain your business. What do you do?', 'type' => 'textarea' ),
				array( 'key' => 'brand_story',        'label' => 'Why did you start it? How did you get here?', 'type' => 'textarea' ),
				array( 'key' => 'brand_mission',      'label' => 'What is your purpose and mission?', 'type' => 'textarea' ),
				array( 'key' => 'brand_ideal_client', 'label' => 'Describe your ideal client', 'type' => 'textarea',
				       'help' => 'What are they like, what do they care about, what are they trying to achieve?' ),
				array( 'key' => 'brand_pain_points',  'label' => 'Their top three pain points or frustrations', 'type' => 'textarea' ),
				array( 'key' => 'brand_solution',     'label' => 'What do you offer that solves those?', 'type' => 'textarea',
				       'prefill_from' => array( 'ads_products', 'gbp_services' ) ),
				// Already asked as "Why customers choose you" on both the SEO
				// and Google Ads service steps — one USP, asked once.
				array( 'key' => 'seo_usp',            'label' => 'How are you different from everyone else who does this?', 'type' => 'textarea', 'maps_to' => 'seo_usp' ),
				array( 'key' => 'brand_reputation',   'label' => 'The one thing you want to be known for', 'type' => 'textarea' ),
				array( 'key' => 'brand_values',       'label' => 'Your top five core values', 'type' => 'textarea' ),
				array( 'key' => 'brand_experience',   'label' => 'What should people say about you to a friend?', 'type' => 'textarea' ),
				// Already collected on the final onboarding step.
				array( 'key' => 'competitors',        'label' => 'Main competitors (names and URLs)', 'type' => 'textarea', 'maps_to' => 'competitors',
				       'help' => 'Or anyone doing what you want to do, and doing it well — we benchmark against these.' ),
				array( 'key' => 'brand_sensitivities','label' => 'Anything to avoid?', 'type' => 'textarea', 'optional' => true,
				       'help' => 'Colours, imagery or language that would be wrong for your market' ),
				array( 'key' => 'brand_special_reqs', 'label' => 'Any special requirements?', 'type' => 'textarea', 'optional' => true ),
			),
		),

		// ── 3. On-page SEO ───────────────────────────────────────────────
		'onpage' => array(
			'title'  => 'SEO specifics',
			'fields' => array(
				array( 'key' => 'seo_keywords',  'label' => 'Top phrases people would search to find you', 'type' => 'textarea', 'maps_to' => 'seo_keywords' ),
				array( 'key' => 'seo_scope',     'label' => 'Are you national or local?', 'type' => 'select',
				       'options' => array( 'Local', 'National', 'Both' ) ),
				array( 'key' => 'seo_locations', 'label' => 'Cities or areas that matter most', 'type' => 'textarea', 'maps_to' => 'seo_locations',
				       'show_if' => array( 'seo_scope' => array( 'Local', 'Both' ) ) ),
				array( 'key' => 'seo_prev_work', 'label' => 'Any previous SEO work on the site?', 'type' => 'textarea',
				       'help' => 'Roughly when, and did they leave any reporting we can look at?' ),
				// Replaces the old yes/no "existing blog?" toggle — same
				// ground, but an answer we can actually act on.
				array( 'key' => 'seo_content_workflow', 'label' => 'How does blog/content production work today?', 'type' => 'textarea', 'maps_to' => 'seo_blog' ),
				array( 'key' => 'seo_approver', 'label' => 'Who approves new content, and what is their email?', 'type' => 'text',
				       'help' => 'We SEO-optimise it, then it needs a yes from your side before it goes live.' ),
				array( 'key' => 'seo_priority', 'label' => 'Prioritise maps or organic?', 'type' => 'select',
				       'options' => array( 'No preference — do both', 'Maps (local / Google Business Profile)', 'Organic (content, meta, performance)' ),
				       'help' => 'We normally work both in the same month.' ),
				array( 'key' => 'seo_topics',      'label' => 'Topics to cover — and any to stay away from', 'type' => 'textarea' ),
				array( 'key' => 'seo_paid_links',  'label' => 'Any previous paid backlinking?', 'type' => 'select',
				       'options' => array( 'No', 'Yes', 'Not sure' ),
				       'help' => 'Not a judgement — it changes how we audit your link profile.' ),
				array( 'key' => 'seo_engagement',  'label' => 'How well does the site engage visitors today?', 'type' => 'textarea' ),
				array( 'key' => 'seo_conversion_type', 'label' => 'How do people usually get in touch?', 'type' => 'select',
				       'options' => array( 'Mostly form submissions', 'Mostly phone calls', 'Both about equally', 'Online purchases' ) ),
				array( 'key' => 'seo_crm_tools',   'label' => 'How do you track those enquiries today?', 'type' => 'text', 'maps_to' => 'seo_crm_tools',
				       'help' => 'CRM, spreadsheet, call tracking — whatever you actually use.' ),
				array( 'key' => 'seo_self_check_terms', 'label' => 'Search terms you type in yourself to check your rankings', 'type' => 'textarea', 'optional' => true ),
				array( 'key' => 'seo_profitable_services', 'label' => 'Your most profitable services', 'type' => 'textarea',
				       'prefill_from' => array( 'ads_products', 'gbp_services' ),
				       'help' => 'Including any bigger-ticket work — we weight these higher when choosing keywords.' ),
				array( 'key' => 'seo_sales_process', 'label' => 'What does your sales process look like, first contact to close?', 'type' => 'textarea' ),
			),
		),

		// ── 4. Team & credentials (E-E-A-T) ──────────────────────────────
		'team' => array(
			'title' => 'Team and credentials',
			'intro' => 'This builds the expertise and trust signals Google weighs (E-E-A-T). List anyone whose name or credentials should appear publicly.',
			'fields' => array(
				array( 'key' => 'eeat_team',        'label' => 'Team members to feature (name and role)', 'type' => 'textarea' ),
				array( 'key' => 'eeat_credentials', 'label' => 'Licences, certifications or designations each person holds', 'type' => 'textarea' ),
				array( 'key' => 'eeat_experience',  'label' => 'Years of experience, per person', 'type' => 'textarea',
				       'prefill_from' => array( 'years_in_business' ) ),
				array( 'key' => 'eeat_media',       'label' => 'Media, press, publications or speaking appearances', 'type' => 'textarea', 'optional' => true ),
				// The final onboarding step already asks for awards.
				array( 'key' => 'reviews_awards',   'label' => 'Associations, memberships or awards', 'type' => 'textarea', 'maps_to' => 'reviews_awards', 'optional' => true ),
			),
		),

		// ── 5. Regulated industry ────────────────────────────────────────
		'compliance' => array(
			'title'     => 'Regulated industry and compliance',
			'intro'     => "Skip this if it doesn't apply. If your field is regulated, these answers decide what we can and can't say in your content.",
			'skippable' => true,
			'fields'    => array(
				array( 'key' => 'comp_regulator',         'label' => 'Is your profession regulated? By whom?', 'type' => 'text' ),
				array( 'key' => 'comp_prohibited_claims', 'label' => 'Claims you are not permitted to make', 'type' => 'textarea',
				       'help' => 'Results, guarantees, protected terms like "specialist"' ),
				array( 'key' => 'comp_review_rules',      'label' => 'Rules about testimonials, reviews, or asking for them', 'type' => 'textarea' ),
				array( 'key' => 'comp_disclaimers',       'label' => 'Required disclaimers or disclosures', 'type' => 'textarea' ),
				array( 'key' => 'comp_approver',          'label' => 'Compliance officer or approval process before publishing', 'type' => 'text' ),
			),
		),

		// ── 6. Goals ─────────────────────────────────────────────────────
		'goals' => array(
			'title'  => 'Goals and success',
			'fields' => array(
				// Already picked as goal chips during onboarding.
				array( 'key' => 'goal',                'label' => 'Primary goal for this engagement', 'type' => 'text', 'maps_to' => 'goal' ),
				array( 'key' => 'goal_6_12_months',    'label' => 'What does success look like in 6 months? In 12?', 'type' => 'textarea' ),
				array( 'key' => 'goal_target_number',  'label' => 'A target number you have in mind', 'type' => 'text', 'optional' => true,
				       'help' => 'Leads per month, bookings, whatever you measure' ),
				// Only worth asking when they haven't already bought Ads from
				// us — otherwise their ads budget is already on file.
				array( 'key' => 'goal_ads_budget',     'label' => 'Separate budget for paid ads, aside from the SEO fee?', 'type' => 'text',
				       'optional' => true, 'skip_if_svc' => array( 'google-ads' ) ),
				array( 'key' => 'goal_seasonality',    'label' => 'Is the business seasonal?', 'type' => 'textarea', 'optional' => true,
				       'help' => 'Busiest and slowest periods' ),
			),
		),

		// ── 7. Tools and access ──────────────────────────────────────────
		'tools' => array(
			'title'  => 'Online presence, tools and access',
			'intro'  => "Please don't type passwords here — just tell us what exists, and we'll set up access with you properly.",
			'fields' => array(
				array( 'key' => 'web_platform',     'label' => 'What is the website built on?', 'type' => 'text', 'maps_to' => 'web_platform',
				       'placeholder' => 'e.g. WordPress, Shopify, Squarespace' ),
				array( 'key' => 'tools_seo_plugin', 'label' => 'SEO plugin installed, if any', 'type' => 'text', 'optional' => true,
				       'placeholder' => 'e.g. Rank Math, Yoast' ),
				array( 'key' => 'seo_gsc',          'label' => 'Google Analytics and Search Console — set up, and can you grant access?', 'type' => 'textarea', 'maps_to' => 'seo_gsc' ),
				// The Google Business Profile service asks all of this itself.
				array( 'key' => 'tools_gbp_status', 'label' => 'Is your Google Business Profile claimed and verified? Who manages it?', 'type' => 'textarea',
				       'skip_if_svc' => array( 'google-business' ) ),
				array( 'key' => 'tools_social',     'label' => 'Active social media channels', 'type' => 'textarea', 'optional' => true ),
				array( 'key' => 'tools_other',      'label' => 'Any other SEO, marketing or CRM tools in use', 'type' => 'textarea', 'optional' => true,
				       'prefill_from' => array( 'crm_tools' ) ),
			),
		),

		// ── 8. Content capacity ──────────────────────────────────────────
		'content' => array(
			'title'  => 'Content production',
			'fields' => array(
				// The source document asks this twice (§3 Q6 and §8 Q1).
				// Asked once here.
				array( 'key' => 'content_writer',     'label' => 'Who writes your content today?', 'type' => 'select',
				       'options' => array( 'We need you to write it', 'I write it personally', 'We have an in-house writer', 'We use a freelancer or agency' ) ),
				array( 'key' => 'content_assets',     'label' => 'Do you have photos, video or brand assets we can use?', 'type' => 'textarea',
				       'help' => "Or should we produce them?" ),
				array( 'key' => 'content_turnaround', 'label' => 'How quickly can you review and approve new content?', 'type' => 'select',
				       'options' => array( 'Within 2 days', 'Within a week', '1-2 weeks', "It varies" ) ),
				array( 'key' => 'content_frequency',  'label' => 'How often should new content go live?', 'type' => 'select',
				       'options' => array( 'Weekly', 'Every two weeks', 'Monthly', "You recommend" ) ),
			),
		),

		// ── 9. Languages and accessibility ───────────────────────────────
		'access' => array(
			'title'  => 'Languages and accessibility',
			'fields' => array(
				array( 'key' => 'lang_served',          'label' => 'What languages do you serve clients in?', 'type' => 'text' ),
				array( 'key' => 'lang_multilingual',    'label' => 'Does the website need to support multiple languages?', 'type' => 'select',
				       'options' => array( 'No', 'Yes', 'Maybe later' ) ),
				array( 'key' => 'access_requirements',  'label' => 'Any accessibility requirements to meet?', 'type' => 'textarea', 'optional' => true,
				       'help' => 'AODA, WCAG or similar' ),
			),
		),
	);
}

/**
 * Every field in the schema, flattened to key => definition, with its section
 * attached. Convenience for lookups.
 */
function six_seo_questionnaire_fields() {
	$out = array();
	foreach ( six_seo_questionnaire_schema() as $skey => $section ) {
		foreach ( $section['fields'] as $f ) {
			$f['section'] = $skey;
			$out[ $f['key'] ] = $f;
		}
	}
	return $out;
}

/**
 * The columns this questionnaire needs ADDED to six_checkout_progress — i.e.
 * only the genuinely new questions. Anything with 'maps_to' already has a
 * column and is deliberately absent here.
 */
function six_seo_questionnaire_columns() {
	$cols = array();
	foreach ( six_seo_questionnaire_fields() as $key => $f ) {
		if ( ! empty( $f['maps_to'] ) ) continue;          // reuses an existing column
		$cols[ $key ] = in_array( $f['type'], array( 'textarea' ), true )
			? "TEXT"
			: "VARCHAR(255) DEFAULT ''";
	}
	return $cols;
}

/**
 * Which column actually stores a given question's answer — the mapped one
 * where the question is a duplicate, otherwise its own.
 */
function six_seo_questionnaire_column_for( $key ) {
	$fields = six_seo_questionnaire_fields();
	if ( ! isset( $fields[ $key ] ) ) return '';
	return $fields[ $key ]['maps_to'] ?? $key;
}

/**
 * Drop the questions this particular client shouldn't see, because another
 * service they bought already covers them.
 *
 * @param array $active_slugs Service slugs the client has.
 */
function six_seo_questionnaire_for_client( array $active_slugs ) {
	$schema = six_seo_questionnaire_schema();
	foreach ( $schema as $skey => &$section ) {
		$section['fields'] = array_values( array_filter( $section['fields'], function ( $f ) use ( $active_slugs ) {
			if ( empty( $f['skip_if_svc'] ) ) return true;
			return ! array_intersect( (array) $f['skip_if_svc'], $active_slugs );
		} ) );
	}
	unset( $section );
	return $schema;
}

/**
 * Read every questionnaire answer for a client, resolved through the dedup
 * rules — a question that maps to an onboarding field returns that field's
 * value, so the client sees what they already told us instead of a blank box.
 *
 * @return array question key => array('value','answered','from_onboarding')
 */
function six_seo_questionnaire_answers( $client_id, array $active_slugs = array() ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}six_checkout_progress WHERE user_id=%d", $client_id
	) );
	if ( ! $row ) $row = (object) array();

	$out = array();
	foreach ( six_seo_questionnaire_for_client( $active_slugs ) as $section ) {
		foreach ( $section['fields'] as $f ) {
			$col   = $f['maps_to'] ?? $f['key'];
			$value = isset( $row->$col ) ? (string) $row->$col : '';

			// A blank answer can still be seeded from a related one the client
			// already gave — they can edit it, but they don't start from nothing.
			if ( $value === '' && ! empty( $f['prefill_from'] ) ) {
				foreach ( (array) $f['prefill_from'] as $src ) {
					if ( ! empty( $row->$src ) ) { $value = (string) $row->$src; break; }
				}
			}

			$out[ $f['key'] ] = array(
				'value'           => $value,
				'answered'        => $value !== '',
				'from_onboarding' => ! empty( $f['maps_to'] ),
			);
		}
	}
	return $out;
}

/**
 * How far through the questionnaire a client is. Optional questions don't
 * count against them — chasing someone for a field marked optional is how a
 * progress bar stops meaning anything.
 *
 * @return array{total:int,done:int,percent:int,outstanding:array}
 */
function six_seo_questionnaire_progress( $client_id, array $active_slugs = array() ) {
	$answers = six_seo_questionnaire_answers( $client_id, $active_slugs );
	$total = 0; $done = 0; $outstanding = array();

	foreach ( six_seo_questionnaire_for_client( $active_slugs ) as $skey => $section ) {
		foreach ( $section['fields'] as $f ) {
			if ( ! empty( $f['optional'] ) ) continue;
			$total++;
			if ( ! empty( $answers[ $f['key'] ]['answered'] ) ) {
				$done++;
			} else {
				$outstanding[] = array( 'section' => $section['title'], 'label' => $f['label'] );
			}
		}
	}

	return array(
		'total'       => $total,
		'done'        => $done,
		'percent'     => $total ? (int) round( ( $done / $total ) * 100 ) : 0,
		'outstanding' => $outstanding,
	);
}

/**
 * Save a batch of questionnaire answers for a client.
 *
 * Writes go to the column the dedup rules name, so answering "How are you
 * different?" in the questionnaire updates the same seo_usp the onboarding
 * step wrote — one answer, one home, never two copies drifting apart.
 *
 * @return array{saved:string[],rejected:string[]}
 */
function six_seo_questionnaire_save( $client_id, array $posted ) {
	global $wpdb;
	$fields   = six_seo_questionnaire_fields();
	$updates  = array();
	$saved    = array();
	$rejected = array();

	foreach ( $posted as $key => $value ) {
		if ( ! isset( $fields[ $key ] ) ) { $rejected[] = $key; continue; }
		$col  = $fields[ $key ]['maps_to'] ?? $key;
		$type = $fields[ $key ]['type'] ?? 'text';
		$updates[ $col ] = $type === 'textarea'
			? sanitize_textarea_field( (string) $value )
			: sanitize_text_field( (string) $value );
		$saved[] = $key;
	}

	if ( $updates ) {
		$tbl = $wpdb->prefix . 'six_checkout_progress';
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$tbl} WHERE user_id=%d", $client_id ) );
		if ( $exists ) {
			$wpdb->update( $tbl, $updates, array( 'user_id' => $client_id ) );
		} else {
			$wpdb->insert( $tbl, array_merge( $updates, array( 'user_id' => $client_id ) ) );
		}
	}

	return array( 'saved' => $saved, 'rejected' => $rejected );
}
