<?php
/**
 * The task board: states, phases, publishing, and writes that tell the truth.
 *
 * The board is the only place that says what has shipped and what is sitting
 * with the client. Three things therefore have to hold:
 *
 *  - **A status that did not save must not look like one that did.** Every
 *    write path reports the database's answer. The advisor's select snaps
 *    back on a refusal; this suite checks the handler gives it something to
 *    snap back for.
 *  - **Tasks arrive unpublished.** A generated plan is working material. If a
 *    draft reached the customer, the dates would move under them while the
 *    advisor edited, and a schedule that moves means nothing.
 *  - **The month decides the phase.** The content is produced in three blocks
 *    of four months while the board always shows the full year, so month 5 is
 *    phase 2 whether or not anybody passed a phase.
 *
 *     php tools/tests/test_tasks.php
 */
define( 'ABSPATH', '/x/' );

$pass = 0; $fail = 0;
function check( $name, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n" . ( $detail ? "        $detail\n" : '' ); }
}

$GLOBALS['TABLES_PRESENT'] = true;
$GLOBALS['WRITE_FAILS']    = false;   // make every write return false
$GLOBALS['ROWS_TOUCHED']   = 1;       // what update()/delete() report

function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_kses_post( $s ) { return (string) $s; }
function wp_unslash( $v ) { return $v; }
function current_time( $f ) { return $f === 'mysql' ? '2026-11-17 10:00:00' : gmdate( $f ); }
function current_user_can( $c ) { return true; }
function get_current_user_id() { return 3; }
function check_ajax_referer( ...$a ) { return true; }
function add_action( $hook, $cb ) { $GLOBALS['HOOKS'][ $hook ] = $cb; }
function six_table_exists( $t ) { return $GLOBALS['TABLES_PRESENT']; }
function six_create_tables() {}

class SixAjaxSuccess extends Exception { public $data; function __construct( $d ) { $this->data = $d; } }
class SixAjaxError   extends Exception { public $data; function __construct( $d ) { $this->data = $d; } }
function wp_send_json_success( $d = null ) { throw new SixAjaxSuccess( $d ); }
function wp_send_json_error( $d = null )   { throw new SixAjaxError( $d ); }

class Six_Roles { public static function is_advisor() { return true; } }

class Six_Test_WPDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $writes = array();
	public $insert_id = 0;
	public $rows = array();              // what get_results returns
	public $row  = null;                 // what get_row returns

	public function suppress_errors( $x = true ) {}
	public function prepare( $q, ...$a ) {
		return vsprintf( str_replace( array( '%d', '%s' ), array( '%d', "'%s'" ), $q ), $a );
	}
	public function get_var( $q ) { return 0; }
	public function get_row( $q ) { return $this->row; }
	public function get_results( $q ) { return $this->rows; }

	public function insert( $table, $data ) {
		if ( $GLOBALS['WRITE_FAILS'] ) { $this->last_error = 'insert blew up'; return false; }
		$this->writes[] = array( 'op' => 'insert', 'table' => $table, 'data' => $data );
		$this->insert_id = 100 + count( $this->writes );
		return 1;
	}
	public function update( $table, $data, $where ) {
		if ( $GLOBALS['WRITE_FAILS'] ) { $this->last_error = 'update blew up'; return false; }
		$this->writes[] = array( 'op' => 'update', 'table' => $table, 'data' => $data, 'where' => $where );
		return $GLOBALS['ROWS_TOUCHED'];
	}
	public function delete( $table, $where ) {
		if ( $GLOBALS['WRITE_FAILS'] ) { $this->last_error = 'delete blew up'; return false; }
		$this->writes[] = array( 'op' => 'delete', 'table' => $table, 'where' => $where );
		return $GLOBALS['ROWS_TOUCHED'];
	}
}

$GLOBALS['wpdb'] = new Six_Test_WPDB();
$GLOBALS['HOOKS'] = array();

require dirname( __DIR__, 2 ) . '/portal/class-tasks.php';

$wpdb = $GLOBALS['wpdb'];
function reset_db() { $GLOBALS['wpdb']->writes = array(); $GLOBALS['wpdb']->last_error = ''; }

// ── The four states are the whole vocabulary ─────────────────────────────
// They are quoted in the advisor UI, in the customer's plan block and in the
// MCP tool description. Renaming one without the others is how a board stops
// agreeing with what Claude reads out of it.
check( 'there are exactly four states', count( Six_Tasks::STATUSES ) === 4 );
foreach ( array( 'todo', 'client_review', 'implementation', 'complete' ) as $k ) {
	check( "state `$k` exists", isset( Six_Tasks::STATUSES[ $k ] ) );
	check( "and `$k` says what it means", ! empty( Six_Tasks::STATUS_MEANS[ $k ] ) );
}
check( 'client review names the client, so it is not read as "not started"',
	stripos( Six_Tasks::STATUSES['client_review'], 'client' ) !== false );
check( 'an unknown status falls back to itself rather than to blank',
	Six_Tasks::status_label( 'nonsense' ) === 'nonsense' );

// ── Creating a plan ──────────────────────────────────────────────────────
reset_db();
$res = Six_Tasks::create( 195, array(
	array( 'title' => 'Rewrite the bail hearing page title', 'month_no' => 1,  'category' => 'On-Page' ),
	array( 'title' => 'Earn three legal-directory links',    'month_no' => 5,  'category' => 'Backlinks' ),
	array( 'title' => 'Publish the sentencing resource hub', 'month_no' => 9,  'category' => 'Content' ),
	array( 'title' => '',                                    'month_no' => 2 ),
) );
check( 'three of four tasks are created', $res['saved'] === 3, json_encode( $res ) );
check( 'and the titleless one is reported rather than dropped silently',
	isset( $res['failed'][3] ), json_encode( $res['failed'] ) );
check( 'each created task comes back with an id', count( $res['ids'] ) === 3 );

check( 'month 1 is phase 1',  (int) $wpdb->writes[0]['data']['phase'] === 1 );
check( 'month 5 is phase 2',  (int) $wpdb->writes[1]['data']['phase'] === 2,
	'got phase ' . ( $wpdb->writes[1]['data']['phase'] ?? '?' ) );
check( 'month 9 is phase 3',  (int) $wpdb->writes[2]['data']['phase'] === 3 );
check( 'a new task starts at todo',
	$wpdb->writes[0]['data']['status'] === 'todo' );
check( 'and unpublished, so a draft plan cannot reach the client',
	(int) $wpdb->writes[0]['data']['published'] === 0 );
check( 'the origin records that Claude wrote it',
	$wpdb->writes[0]['data']['origin'] === 'claude' );

// A month outside the plan is clamped rather than writing a phase nobody
// renders.
reset_db();
Six_Tasks::create( 195, array( array( 'title' => 'Late task', 'month_no' => 99 ) ) );
check( 'an out-of-range month is clamped',
	(int) $wpdb->writes[0]['data']['month_no'] <= 24 );

// ── A refused write is reported as a refusal ─────────────────────────────
reset_db();
$GLOBALS['WRITE_FAILS'] = true;
$res = Six_Tasks::create( 195, array( array( 'title' => 'Anything' ) ) );
check( 'nothing is claimed saved when the insert fails', $res['saved'] === 0 );
check( 'and the reason comes back', ! empty( $res['failed'] ) );
$GLOBALS['WRITE_FAILS'] = false;

reset_db();
$GLOBALS['TABLES_PRESENT'] = false;
$res = Six_Tasks::create( 195, array( array( 'title' => 'Anything' ) ) );
check( 'a missing table is named, not swallowed',
	$res['saved'] === 0 && strpos( implode( ' ', $res['failed'] ), 'six_tasks' ) !== false,
	json_encode( $res['failed'] ) );
check( 'and reading with no table returns nothing rather than erroring',
	Six_Tasks::all( 195 ) === array() );
$GLOBALS['TABLES_PRESENT'] = true;

// ── Updating ─────────────────────────────────────────────────────────────
$wpdb->row = (object) array( 'id' => 7, 'client_id' => 195, 'status' => 'todo', 'month_no' => 1 );

reset_db();
$u = Six_Tasks::update( 195, 7, array( 'status' => 'complete' ) );
check( 'completing a task succeeds', $u['ok'] === true, $u['error'] );
check( 'and stamps the date, because "done" with no date cannot be reported on',
	! empty( $wpdb->writes[0]['data']['completed_at'] ) );

reset_db();
Six_Tasks::update( 195, 7, array( 'status' => 'implementation' ) );
check( 'moving back off complete clears the completion date',
	array_key_exists( 'completed_at', $wpdb->writes[0]['data'] )
	&& $wpdb->writes[0]['data']['completed_at'] === null );

reset_db();
$u = Six_Tasks::update( 195, 7, array( 'status' => 'in_review' ) );
check( 'an invented status is refused', $u['ok'] === false );
check( 'and the refusal lists the states that do exist',
	strpos( $u['error'], 'client_review' ) !== false, $u['error'] );
check( 'nothing was written on the refusal', ! $wpdb->writes );

reset_db();
Six_Tasks::update( 195, 7, array( 'month_no' => 6 ) );
check( 'moving a task in the timeline moves its phase with it',
	(int) $wpdb->writes[0]['data']['phase'] === 2 );

reset_db();
$u = Six_Tasks::update( 195, 7, array() );
check( 'an update with no fields says so instead of reporting success',
	$u['ok'] === false && $u['error'] === 'Nothing to change.' );

$wpdb->row = null;
$u = Six_Tasks::update( 195, 999, array( 'status' => 'complete' ) );
check( 'a task that is not this client\'s cannot be updated', $u['ok'] === false );
$wpdb->row = (object) array( 'id' => 7, 'client_id' => 195, 'status' => 'todo', 'month_no' => 1 );

reset_db();
$GLOBALS['WRITE_FAILS'] = true;
$u = Six_Tasks::update( 195, 7, array( 'status' => 'complete' ) );
check( 'a refused update is reported as a failure, not a save', $u['ok'] === false );
$GLOBALS['WRITE_FAILS'] = false;

// ── Deleting ─────────────────────────────────────────────────────────────
reset_db();
$d = Six_Tasks::delete( 195, 7 );
check( 'deleting a task succeeds', is_array( $d ) && $d['ok'] === true );

reset_db();
$GLOBALS['ROWS_TOUCHED'] = 0;
$d = Six_Tasks::delete( 195, 7 );
check( 'a delete that removed nothing is a failure, not a success',
	$d['ok'] === false, 'a row still on the board after a confirmed delete' );
$GLOBALS['ROWS_TOUCHED'] = 1;

reset_db();
$GLOBALS['WRITE_FAILS'] = true;
$d = Six_Tasks::delete( 195, 7 );
check( 'a refused delete is reported', $d['ok'] === false );
$GLOBALS['WRITE_FAILS'] = false;

// ── Publishing ───────────────────────────────────────────────────────────
reset_db();
$GLOBALS['ROWS_TOUCHED'] = 12;
$pub = Six_Tasks::publish( 195, true );
check( 'publishing succeeds', $pub['ok'] === true );
check( 'and says how many rows actually changed', $pub['count'] === 12 );
check( 'publishing sets the flag', (int) $wpdb->writes[0]['data']['published'] === 1 );
check( 'and does not touch any other field',
	array_keys( $wpdb->writes[0]['data'] ) === array( 'published' ) );

reset_db();
Six_Tasks::publish( 195, true, 2 );
check( 'publishing one phase scopes the write to that phase',
	(int) ( $wpdb->writes[0]['where']['phase'] ?? 0 ) === 2 );

reset_db();
Six_Tasks::publish( 195, false );
check( 'unpublishing clears the flag', (int) $wpdb->writes[0]['data']['published'] === 0 );

reset_db();
$GLOBALS['ROWS_TOUCHED'] = 0;
$pub = Six_Tasks::publish( 195, true );
check( 'publishing nothing is reported as nothing, not as a publish',
	$pub['ok'] === true && $pub['count'] === 0 );
$GLOBALS['ROWS_TOUCHED'] = 1;

// ── Reading ──────────────────────────────────────────────────────────────
$wpdb->rows = array(
	(object) array( 'id' => 1, 'client_id' => 195, 'service_slug' => 'seo', 'phase' => 1,
	                'month_no' => 1, 'category' => 'On-Page', 'title' => 'Rewrite titles',
	                'detail' => '', 'deliverable' => '', 'entity' => '', 'keyword' => '',
	                'status' => 'complete', 'priority' => 1, 'due_date' => null,
	                'assigned_to' => '', 'artifact_id' => 0, 'work_log_id' => 0,
	                'sort_order' => 1, 'published' => 1, 'origin' => 'claude',
	                'notes' => '', 'completed_at' => '2026-02-01 10:00:00',
	                'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-02-01 10:00:00' ),
);
$all = Six_Tasks::all( 195 );
check( 'reading returns shaped arrays, not raw rows', isset( $all[0]['title'] ) );
check( 'the id comes back as an integer', $all[0]['id'] === 1 );
check( 'and published as something the template can test', ! empty( $all[0]['published'] ) );

// The customer's plan block asks for published rows only. `published => true`
// has to survive the filter — a falsy-check would drop it and show drafts.
reset_db();
$GLOBALS['LAST_QUERY'] = '';
class Six_Capture_WPDB extends Six_Test_WPDB {
	public function get_results( $q ) { $GLOBALS['LAST_QUERY'] = $q; return array(); }
}
$GLOBALS['wpdb'] = new Six_Capture_WPDB();
Six_Tasks::all( 195, array( 'published' => true ) );
check( 'asking for published tasks filters on published',
	strpos( $GLOBALS['LAST_QUERY'], 'published=1' ) !== false, $GLOBALS['LAST_QUERY'] );
Six_Tasks::all( 195, array( 'published' => false ) );
check( 'and asking for drafts filters the other way',
	strpos( $GLOBALS['LAST_QUERY'], 'published=0' ) !== false, $GLOBALS['LAST_QUERY'] );
Six_Tasks::all( 195 );
check( 'while asking for neither returns both',
	strpos( $GLOBALS['LAST_QUERY'], 'published=' ) === false, $GLOBALS['LAST_QUERY'] );
$GLOBALS['wpdb'] = $wpdb;

// ── The UI and the handlers agree ────────────────────────────────────────
// A class rename on either side silently disables a button and the screen
// gives no sign — the exact failure the metric delete shipped with.
/* The advisor template plus the task-row partial. The row's markup lives in
   its own file because the board is rendered twice — by month and by
   category — and two copies would drift. These checks are about the row and
   its listener agreeing, so they have to see both halves. */
$adv = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/advisor-dashboard.php' )
     . file_get_contents( dirname( __DIR__, 2 ) . '/portal/partials/task-row.php' );

check( 'the board lives under the service it belongs to',
	strpos( $adv, 'To do &mdash; ' ) !== false
	&& strpos( $adv, 'data-panel="todo"' ) !== false,
	'one undifferentiated list for a twelve-month plan across three services' );
check( 'and it is grouped by category, not printed as ninety numbered lines',
	strpos( $adv, '$svc_bycat' ) !== false );
check( 'the three things an advisor does to a service are three equal buttons',
	strpos( $adv, 'grid-template-columns:repeat(3,1fr)' ) !== false
	&& strpos( $adv, 'data-act="metrics"' ) !== false
	&& strpos( $adv, 'data-act="work"' ) !== false
	&& strpos( $adv, 'data-act="todo"' ) !== false );
check( 'with only one open at a time, so the panel is not several screens long',
	strpos( $adv, "panes.forEach(function(p){ p.style.display = 'none'; });" ) !== false );
check( 'and the To do button carries the count of what is open',
	strpos( $adv, '$svc_open' ) !== false );
check( 'the status select carries the class its listener looks for',
	strpos( $adv, 'six-task-status' ) !== false
	&& preg_match( "/querySelectorAll\(\s*'\.six-task-status'/", $adv ) === 1 );
check( 'the select reads dataset.id, and the markup supplies data-id',
	preg_match( '/six-task-status"[^>]*data-id=/', $adv ) === 1 );
check( 'a refused status change snaps the select back',
	strpos( $adv, 'self.value = was' ) !== false,
	'the select would keep a value the database rejected' );
check( 'the delete button and its listener agree',
	strpos( $adv, 'six-delete-task' ) !== false
	&& preg_match( "/querySelectorAll\(\s*'\.six-delete-task'/", $adv ) === 1 );
check( 'each task row has an id so it can be removed from the page',
	strpos( $adv, 'id="task-' ) !== false );
check( 'the publish button and its listener agree',
	preg_match( "/querySelectorAll\(\s*'\.six-publish-tasks'/", $adv ) === 1 );
check( 'every state is offered on each task row',
	strpos( $adv, 'foreach($svc_status as $sk => $sl)' ) !== false );
check( 'a draft is marked as a draft on the row',
	strpos( $adv, '>draft</span>' ) !== false );

/* The work log went to Reports for one release, which was the wrong call:
   the advisor who just finished a service's metrics is the person who knows
   what was done to that service, and sending them to another tab meant it
   was not recorded. It is back beside the metrics, per service. */
check( 'the work log sits with the service it is about',
	strpos( $adv, 'data-panel="work"' ) !== false );
check( 'and files against that service rather than asking which one',
	strpos( $adv, 'var slug  = this.dataset.slug;' ) !== false );
check( 'each service has its own result box, so two panels cannot write to one',
	strpos( $adv, "document.querySelector('.wl-result-'+slug)" ) !== false );
// ── The same rows, two ways through them ─────────────────────────────────
// An advisor asks two different questions of a board: what is due this
// month, and where are we on backlinks. A year of work under one heading is
// a list nobody reads.
check( 'the board can be grouped by month',
	strpos( $adv, 'data-by="month"' ) !== false );
check( 'or by category',
	strpos( $adv, 'data-by="category"' ) !== false );
check( 'the row markup is a partial, so the two views cannot drift',
	file_exists( dirname( __DIR__, 2 ) . '/portal/partials/task-row.php' ) );
check( 'and both views include it rather than repeating it',
	substr_count( $adv, 'include six_task_row_path()' ) === 2,
	'two copies would drift, and the half nobody looks at would drift furthest' );
check( 'the month the client is actually in is marked',
	strpos( $adv, "echo \$is_now?' · now':''" ) !== false );
check( 'and the choice of view is remembered per service',
	strpos( $adv, 'six_tgroup_' ) !== false );

// ── The month-end reminder ───────────────────────────────────────────────
// A to-do list nobody is reminded of is a list. Three days out, not because
// three is special, but because a month-end with four open rows and no
// warning is how a plan slips a month at a time.
check( 'open tasks in the current month are counted',
	strpos( $adv, '$svc_due_here' ) !== false );
check( 'the reminder fires three days before the month ends',
	strpos( $adv, "\$svc_due['days_left'] <= 3" ) !== false );
check( 'it names the categories rather than only a number',
	strpos( $adv, '$due_cats' ) !== false,
	'"4 tasks open" does not tell an advisor what to pick up' );
check( 'and the last day of the month reads as today, not as zero days',
	strpos( $adv, 'the month ends today' ) !== false );

$tasks_src = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-tasks.php' );
check( 'the current month is derived from the day work began',
	strpos( $tasks_src, 'function current_month(' ) !== false );
check( 'and is clamped to the plan length',
	strpos( $tasks_src, 'min( (int) $months, $n + 1 )' ) !== false,
	'month 14 of a twelve-month plan is not a month the plan ever had' );
check( 'with no start date there is no "current month" to remind about',
	strpos( $tasks_src, "if ( ! \$start ) return 0;" ) !== false );
check( 'completed rows are not counted as open',
	strpos( $tasks_src, "if ( \$row['status'] === 'complete' ) continue;" ) !== false );

check( 'Reports says where tasks and the log went',
	strpos( $adv, 'Tasks and the work log live under' ) !== false );

$cust = file_get_contents( dirname( __DIR__, 2 ) . '/portal/templates/customer-dashboard.php' );
check( 'the customer sees only published tasks',
	strpos( $cust, "'published'=>true" ) !== false,
	'a draft plan would reach the client' );
check( 'and the ones waiting on them are called out',
	strpos( $cust, 'waiting on your approval' ) !== false );

// A year of work is ninety-odd rows. Ninety rows under one heading is a
// document nobody reads: a client wants to know what is happening to their
// content, or to their links, not to scroll through both to find either.
check( 'the plan is grouped into category tabs',
	strpos( $cust, 'six-plan-tabs' ) !== false );
check( 'with Everything first, so the whole plan is one click away',
	strpos( $cust, "data-cat=\"__all\"" ) !== false );
check( 'an uncategorised task lands under Other rather than vanishing',
	strpos( $cust, "\$plan_cats[trim(\$pl['category']) ?: 'Other']" ) !== false );
check( 'and Other sorts last',
	strpos( $cust, "if(\$x==='Other') return 1;" ) !== false );
check( 'each category is its own pane, so the month headings stay true',
	strpos( $cust, 'six-plan-pane' ) !== false,
	'filtering one list would leave "Month 3 of 12" sitting above nothing' );
check( 'the months say they are months of twelve',
	strpos( $cust, 'Month <?php echo (int)$pm; ?> of 12' ) !== false );
check( 'the tabs are styled in the always-emitted stylesheet',
	strpos( $cust, '.six-plan-tabs{' ) !== false
	&& strpos( $cust, '.six-plan-tabs{' ) > strpos( $cust, "elseif(\$active_tab==='profile')" ),
	'a service page would render them as a row of plain buttons' );
check( 'and the chosen category is remembered',
	strpos( $cust, 'six_plan_cat_' ) !== false );

// ── The handlers ─────────────────────────────────────────────────────────
$ajax = file_get_contents( dirname( __DIR__, 2 ) . '/portal/ajax-handlers.php' );
foreach ( array( 'six_list_tasks', 'six_update_task', 'six_add_task', 'six_delete_task', 'six_publish_tasks' ) as $h ) {
	check( "handler $h is registered", strpos( $ajax, "wp_ajax_$h" ) !== false );
}

$upd = substr( $ajax, strpos( $ajax, "wp_ajax_six_update_task" ), 1800 );
check( 'updating refuses without a task id', strpos( $upd, 'Missing task.' ) !== false );
check( 'and passes the database\'s refusal back rather than its own optimism',
	strpos( $upd, "wp_send_json_error( \$res['error'] )" ) !== false );

$pubh = substr( $ajax, strpos( $ajax, "wp_ajax_six_publish_tasks" ), 1400 );
check( 'publishing reports how many rows changed',
	strpos( $pubh, "\$res['count']" ) !== false );
check( 'and says so plainly when nothing changed',
	strpos( $pubh, 'Nothing changed' ) !== false );

// ── The MCP tools ────────────────────────────────────────────────────────
// Claude reads the board to know what has shipped. Reading is open; writing
// needs a write-scoped token, like every other mutation on this server.
$mcp = file_get_contents( dirname( __DIR__, 2 ) . '/portal/class-mcp-server.php' );
foreach ( array( 'create_tasks', 'list_tasks', 'update_task' ) as $tool ) {
	check( "MCP tool $tool is declared", strpos( $mcp, "'name'        => '$tool'" ) !== false );
	check( "and $tool is dispatched", strpos( $mcp, "case '$tool':" ) !== false );
}
preg_match( '/in_array\(\s*\$name,\s*array\((.*?)\)\s*\)/s', $mcp, $gate );
$gate_list = $gate[1] ?? '';
check( 'create_tasks needs a write-scoped token', strpos( $gate_list, "'create_tasks'" ) !== false );
check( 'update_task needs one too', strpos( $gate_list, "'update_task'" ) !== false );
check( 'but reading the board does not', strpos( $gate_list, "'list_tasks'" ) === false,
	'Claude could not check what has shipped without write access' );
check( 'list_tasks returns what each state means, so Claude reads them the same way the UI does',
	strpos( $mcp, 'Six_Tasks::STATUS_MEANS' ) !== false );
check( 'create_tasks names the deliverables that get forgotten',
	stripos( $mcp, 'backlinks and digital PR' ) !== false );
check( 'and says the plan arrives as a draft',
	strpos( $mcp, 'Tasks are created as a draft' ) !== false );

echo "\nPassed: $pass  Failed: $fail\n";
exit( $fail ? 1 : 0 );
