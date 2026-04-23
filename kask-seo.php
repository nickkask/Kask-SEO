<?php
/**
 * Plugin Name:  Kask SEO
 * Description:  Lightweight per-page meta title, description & noindex for all post types; CPT archive meta; sitewide redirects manager.
 * Version:      1.0.0
 * Author:       Kask Creativity LLC
 * Author URI:   https://kaskcreativity.com
 * License:      GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
//  CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────

define( 'KSEO_VERSION', '1.0.0' );

function kseo_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'kseo_redirects';
}


// ─────────────────────────────────────────────────────────────────────────────
//  ACTIVATION — create redirects table
// ─────────────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, 'kseo_activate' );
function kseo_activate(): void {
	global $wpdb;
	$table   = kseo_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		source        varchar(500)        NOT NULL,
		destination   varchar(500)        NOT NULL,
		redirect_type smallint(3)         NOT NULL DEFAULT 301,
		PRIMARY KEY  (id),
		UNIQUE KEY   source (source(191))
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	add_option( 'kseo_archive_meta', [] );
}


// ─────────────────────────────────────────────────────────────────────────────
//  META BOX — registers on every public post type automatically
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', 'kseo_register_meta_boxes' );
function kseo_register_meta_boxes(): void {
	$post_types = get_post_types( [ 'public' => true ], 'names' );
	foreach ( $post_types as $pt ) {
		add_meta_box(
			'kask_seo',
			'SEO',
			'kseo_meta_box_html',
			$pt,
			'normal',
			'high'
		);
	}
}

function kseo_meta_box_html( WP_Post $post ): void {
	wp_nonce_field( 'kseo_save_meta', 'kseo_nonce' );

	$title   = get_post_meta( $post->ID, '_kseo_title', true );
	$desc    = get_post_meta( $post->ID, '_kseo_description', true );
	$noindex = get_post_meta( $post->ID, '_kseo_noindex', true );

	$placeholder_title = trim( get_the_title( $post ) . ' – ' . get_bloginfo( 'name' ), ' –' );
	?>

	<style>
		#kask_seo .inside           { padding: 12px 12px 4px; }
		.kseo-field                 { margin-bottom: 14px; }
		.kseo-field label           { display: block; font-size: 11px; font-weight: 600;
		                              text-transform: uppercase; letter-spacing: .04em;
		                              color: #50575e; margin-bottom: 5px; }
		.kseo-field input[type=text],
		.kseo-field textarea        { width: 100%; box-sizing: border-box; }
		.kseo-field textarea        { height: 72px; resize: vertical; }
		.kseo-counter               { font-size: 11px; color: #aaa; text-align: right;
		                              margin-top: 3px; transition: color .15s; }
		.kseo-counter.warn          { color: #dba617; }
		.kseo-counter.over          { color: #d63638; font-weight: 600; }
		.kseo-noindex               { display: flex; align-items: center; gap: 7px; }
		.kseo-noindex input         { margin: 0; }
		.kseo-noindex span          { font-size: 13px; }
	</style>

	<div class="kseo-field">
		<label for="kseo_title">Meta Title</label>
		<input type="text" id="kseo_title" name="kseo_title"
		       value="<?php echo esc_attr( $title ); ?>"
		       placeholder="<?php echo esc_attr( $placeholder_title ); ?>"
		       maxlength="200" />
		<div class="kseo-counter" id="kseo_title_counter">0 / 60</div>
	</div>

	<div class="kseo-field">
		<label for="kseo_desc">Meta Description</label>
		<textarea id="kseo_desc" name="kseo_description"
		          placeholder="Leave blank to omit…" maxlength="500"><?php echo esc_textarea( $desc ); ?></textarea>
		<div class="kseo-counter" id="kseo_desc_counter">0 / 160</div>
	</div>

	<div class="kseo-field">
		<div class="kseo-noindex">
			<input type="checkbox" id="kseo_noindex" name="kseo_noindex" value="1"
			       <?php checked( $noindex, '1' ); ?> />
			<span><label for="kseo_noindex" style="font-weight:normal;text-transform:none;letter-spacing:0;">Noindex this page</label></span>
		</div>
	</div>

	<script>
	(function () {
		function counter( inputId, counterId, warn, max ) {
			var inp = document.getElementById( inputId );
			var ctr = document.getElementById( counterId );
			if ( ! inp || ! ctr ) return;
			function update() {
				var n = inp.value.length;
				ctr.textContent = n + ' / ' + max;
				ctr.className   = 'kseo-counter' + ( n > max ? ' over' : ( n > warn ? ' warn' : '' ) );
			}
			inp.addEventListener( 'input', update );
			update();
		}
		counter( 'kseo_title', 'kseo_title_counter', 50, 60 );
		counter( 'kseo_desc',  'kseo_desc_counter',  140, 160 );
	})();
	</script>
	<?php
}

add_action( 'save_post', 'kseo_save_meta' );
function kseo_save_meta( int $post_id ): void {
	if ( ! isset( $_POST['kseo_nonce'] ) || ! wp_verify_nonce( $_POST['kseo_nonce'], 'kseo_save_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	foreach ( [ '_kseo_title' => 'kseo_title', '_kseo_description' => 'kseo_description' ] as $meta => $field ) {
		$val = sanitize_text_field( $_POST[ $field ] ?? '' );
		$val ? update_post_meta( $post_id, $meta, $val ) : delete_post_meta( $post_id, $meta );
	}

	isset( $_POST['kseo_noindex'] ) && $_POST['kseo_noindex'] === '1'
		? update_post_meta( $post_id, '_kseo_noindex', '1' )
		: delete_post_meta( $post_id, '_kseo_noindex' );
}


// ─────────────────────────────────────────────────────────────────────────────
//  FRONT-END OUTPUT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Override the <title> tag.
 */
add_filter( 'pre_get_document_title', 'kseo_document_title' );
function kseo_document_title( string $title ): string {
	if ( is_singular() ) {
		$custom = get_post_meta( get_the_ID(), '_kseo_title', true );
		if ( $custom ) return esc_html( $custom );
	}

	if ( is_post_type_archive() ) {
		$pt   = get_queried_object()->name ?? '';
		$meta = get_option( 'kseo_archive_meta', [] );
		if ( ! empty( $meta[ $pt ]['title'] ) ) return esc_html( $meta[ $pt ]['title'] );
	}

	return $title;
}

/**
 * Output <meta description> and <meta robots> in <head>.
 * Runs at priority 1 so it lands near the top.
 */
add_action( 'wp_head', 'kseo_head_output', 1 );
function kseo_head_output(): void {
	$desc    = '';
	$noindex = false;

	if ( is_singular() ) {
		$id      = get_the_ID();
		$desc    = (string) get_post_meta( $id, '_kseo_description', true );
		$noindex = (bool) get_post_meta( $id, '_kseo_noindex', true );

	} elseif ( is_post_type_archive() ) {
		$pt      = get_queried_object()->name ?? '';
		$meta    = get_option( 'kseo_archive_meta', [] );
		$desc    = $meta[ $pt ]['description'] ?? '';
		$noindex = ! empty( $meta[ $pt ]['noindex'] );
	}

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	if ( $noindex ) {
		echo '<meta name="robots" content="noindex, follow">' . "\n";
	}
}


// ─────────────────────────────────────────────────────────────────────────────
//  ADMIN MENU
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'kseo_admin_menu' );
function kseo_admin_menu(): void {
	add_menu_page(
		'Kask SEO',
		'Kask SEO',
		'manage_options',
		'kask-seo',
		'kseo_redirects_page',
		'dashicons-search',
		81
	);
	add_submenu_page( 'kask-seo', 'Redirects',    'Redirects',    'manage_options', 'kask-seo',          'kseo_redirects_page' );
	add_submenu_page( 'kask-seo', 'Archive Meta',  'Archive Meta', 'manage_options', 'kask-seo-archives', 'kseo_archives_page' );
}


// ─────────────────────────────────────────────────────────────────────────────
//  REDIRECTS ADMIN PAGE
// ─────────────────────────────────────────────────────────────────────────────

function kseo_redirects_page(): void {
	global $wpdb;
	$table   = kseo_table();
	$message = '';

	// ── Delete ──
	if (
		isset( $_GET['action'], $_GET['id'] ) &&
		$_GET['action'] === 'delete' &&
		wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'kseo_delete_redirect' )
	) {
		$wpdb->delete( $table, [ 'id' => absint( $_GET['id'] ) ] );
		kseo_bust_redirects_cache();
		$message = kseo_notice( 'Redirect deleted.' );
	}

	// ── Save (add or edit) ──
	if (
		$_SERVER['REQUEST_METHOD'] === 'POST' &&
		wp_verify_nonce( $_POST['kseo_redirect_nonce'] ?? '', 'kseo_save_redirect' )
	) {
		$source  = kseo_normalize_path( sanitize_text_field( $_POST['kseo_source'] ?? '' ) );
		$dest    = esc_url_raw( trim( $_POST['kseo_destination'] ?? '' ) );
		$type    = in_array( (int) ( $_POST['kseo_type'] ?? 301 ), [ 301, 302 ] ) ? (int) $_POST['kseo_type'] : 301;
		$edit_id = absint( $_POST['kseo_edit_id'] ?? 0 );

		if ( $source && $dest ) {
			if ( $edit_id ) {
				$wpdb->update( $table, [ 'source' => $source, 'destination' => $dest, 'redirect_type' => $type ], [ 'id' => $edit_id ] );
				$message = kseo_notice( 'Redirect updated.' );
			} else {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source = %s", $source ) );
				if ( $exists ) {
					$message = kseo_notice( 'A redirect for that source already exists.', 'error' );
				} else {
					$wpdb->insert( $table, [ 'source' => $source, 'destination' => $dest, 'redirect_type' => $type ] );
					$message = kseo_notice( 'Redirect added.' );
				}
			}
			kseo_bust_redirects_cache();
		} else {
			$message = kseo_notice( 'Source path and destination are both required.', 'error' );
		}
	}

	// ── Load row for editing ──
	$editing = null;
	if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
		$editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $_GET['id'] ) ) );
	}

	$redirects = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY source ASC" );
	?>
	<div class="wrap">
		<h1>Kask SEO — Redirects</h1>
		<?php echo $message; ?>

		<h2 style="margin-top:1.5em;"><?php echo $editing ? 'Edit Redirect' : 'Add Redirect'; ?></h2>
		<form method="post" style="max-width:680px;">
			<?php wp_nonce_field( 'kseo_save_redirect', 'kseo_redirect_nonce' ); ?>
			<?php if ( $editing ) : ?>
				<input type="hidden" name="kseo_edit_id" value="<?php echo esc_attr( $editing->id ); ?>" />
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th><label for="kseo_source">Source Path</label></th>
					<td>
						<input type="text" id="kseo_source" name="kseo_source" class="regular-text"
						       value="<?php echo $editing ? esc_attr( $editing->source ) : ''; ?>"
						       placeholder="/old-page/" required />
						<p class="description">Relative path only — e.g. <code>/old-page/</code>. Trailing slash is added automatically.</p>
					</td>
				</tr>
				<tr>
					<th><label for="kseo_destination">Destination</label></th>
					<td>
						<input type="text" id="kseo_destination" name="kseo_destination" class="regular-text"
						       value="<?php echo $editing ? esc_attr( $editing->destination ) : ''; ?>"
						       placeholder="https://example.com/new-page/" required />
						<p class="description">Full URL or relative path. For external redirects use the full URL.</p>
					</td>
				</tr>
				<tr>
					<th><label for="kseo_type">Type</label></th>
					<td>
						<select id="kseo_type" name="kseo_type">
							<option value="301" <?php selected( $editing ? $editing->redirect_type : 301, 301 ); ?>>301 — Permanent (use for most redirects)</option>
							<option value="302" <?php selected( $editing ? $editing->redirect_type : 301, 302 ); ?>>302 — Temporary</option>
						</select>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary"><?php echo $editing ? 'Update Redirect' : 'Add Redirect'; ?></button>
				<?php if ( $editing ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kask-seo' ) ); ?>" class="button" style="margin-left:6px;">Cancel</a>
				<?php endif; ?>
			</p>
		</form>

		<?php if ( $redirects ) : ?>
		<h2 style="margin-top:2em;">All Redirects <span style="font-weight:400;font-size:14px;color:#888;">(<?php echo count( $redirects ); ?>)</span></h2>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th>Source</th>
					<th>Destination</th>
					<th style="width:70px;">Type</th>
					<th style="width:110px;">Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $redirects as $r ) : ?>
				<tr>
					<td><code><?php echo esc_html( $r->source ); ?></code></td>
					<td style="word-break:break-all;"><?php echo esc_html( $r->destination ); ?></td>
					<td><?php echo esc_html( $r->redirect_type ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=kask-seo&action=edit&id=' . $r->id ) ); ?>">Edit</a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=kask-seo&action=delete&id=' . $r->id ), 'kseo_delete_redirect' ) ); ?>"
						   style="color:#d63638;"
						   onclick="return confirm('Delete this redirect?');">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
		<p>No redirects yet.</p>
		<?php endif; ?>
	</div>
	<?php
}


// ─────────────────────────────────────────────────────────────────────────────
//  ARCHIVE META ADMIN PAGE
// ─────────────────────────────────────────────────────────────────────────────

function kseo_archives_page(): void {
	$message = '';

	if (
		$_SERVER['REQUEST_METHOD'] === 'POST' &&
		wp_verify_nonce( $_POST['kseo_archives_nonce'] ?? '', 'kseo_save_archives' )
	) {
		$data = [];
		foreach ( (array) ( $_POST['kseo_archive'] ?? [] ) as $pt_slug => $vals ) {
			$pt_slug          = sanitize_key( $pt_slug );
			$data[ $pt_slug ] = [
				'title'       => sanitize_text_field( $vals['title']       ?? '' ),
				'description' => sanitize_text_field( $vals['description'] ?? '' ),
				'noindex'     => ! empty( $vals['noindex'] ) ? 1 : 0,
			];
		}
		update_option( 'kseo_archive_meta', $data );
		$message = kseo_notice( 'Archive meta saved.' );
	}

	$saved      = get_option( 'kseo_archive_meta', [] );
	$post_types = get_post_types( [ 'public' => true, 'has_archive' => true ], 'objects' );
	$site_name  = get_bloginfo( 'name' );
	?>
	<div class="wrap">
		<h1>Kask SEO — Archive Meta</h1>
		<?php echo $message; ?>

		<?php if ( empty( $post_types ) ) : ?>
		<p>No public post types with archives were found on this site.</p>
		<?php else : ?>

		<p style="max-width:640px;">Override the title and meta description for CPT archive pages (e.g. <code>/resources/</code>). Leave fields blank to use WordPress defaults.</p>

		<form method="post">
			<?php wp_nonce_field( 'kseo_save_archives', 'kseo_archives_nonce' ); ?>

			<?php foreach ( $post_types as $pt_slug => $pt_obj ) :
				$s            = $saved[ $pt_slug ] ?? [];
				$archive_slug = $pt_obj->rewrite['slug'] ?? $pt_slug;
				$archive_label = $pt_obj->labels->archives ?? $pt_obj->label;
			?>
			<h2 style="margin-top:1.5em;">
				<?php echo esc_html( $pt_obj->label ); ?>
				<code style="font-size:13px;font-weight:400;">/ <?php echo esc_html( $archive_slug ); ?> /</code>
			</h2>
			<table class="form-table" style="max-width:700px;">
				<tr>
					<th style="width:160px;"><label>Meta Title</label></th>
					<td>
						<input type="text" name="kseo_archive[<?php echo esc_attr( $pt_slug ); ?>][title]"
						       class="regular-text"
						       value="<?php echo esc_attr( $s['title'] ?? '' ); ?>"
						       placeholder="<?php echo esc_attr( $archive_label . ' – ' . $site_name ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label>Meta Description</label></th>
					<td>
						<textarea name="kseo_archive[<?php echo esc_attr( $pt_slug ); ?>][description]"
						          class="regular-text" rows="3"><?php echo esc_textarea( $s['description'] ?? '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th>Noindex</th>
					<td>
						<label>
							<input type="checkbox"
							       name="kseo_archive[<?php echo esc_attr( $pt_slug ); ?>][noindex]"
							       value="1"
							       <?php checked( ! empty( $s['noindex'] ) ); ?> />
							Noindex this archive
						</label>
					</td>
				</tr>
			</table>
			<?php endforeach; ?>

			<p style="margin-top:1.5em;">
				<button type="submit" class="button button-primary">Save Archive Meta</button>
			</p>
		</form>
		<?php endif; ?>
	</div>
	<?php
}


// ─────────────────────────────────────────────────────────────────────────────
//  REDIRECTS PROCESSING — cached, fires before template loads
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'template_redirect', 'kseo_process_redirects', 1 );
function kseo_process_redirects(): void {
	// Ignore admin, REST, and cron requests
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;

	$request_path = kseo_normalize_path( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );
	$map          = kseo_get_redirects_cached();

	if ( isset( $map[ $request_path ] ) ) {
		wp_redirect( $map[ $request_path ]['destination'], $map[ $request_path ]['type'] );
		exit;
	}
}

/**
 * Returns all redirects as a keyed array, loaded from a transient (1 day TTL).
 * Cache is busted automatically on any add/edit/delete.
 *
 * @return array<string, array{destination: string, type: int}>
 */
function kseo_get_redirects_cached(): array {
	$cached = get_transient( 'kseo_redirects_map' );
	if ( $cached !== false ) return $cached;

	global $wpdb;
	$rows = $wpdb->get_results( "SELECT source, destination, redirect_type FROM " . kseo_table(), ARRAY_A );

	$map = [];
	foreach ( $rows as $row ) {
		$map[ $row['source'] ] = [
			'destination' => $row['destination'],
			'type'        => (int) $row['redirect_type'],
		];
	}

	set_transient( 'kseo_redirects_map', $map, DAY_IN_SECONDS );
	return $map;
}

function kseo_bust_redirects_cache(): void {
	delete_transient( 'kseo_redirects_map' );
}


// ─────────────────────────────────────────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalise a path: always starts with / and ends with / (lowercase).
 * e.g. "Old-Page" → "/old-page/"
 */
function kseo_normalize_path( string $path ): string {
	$path = strtolower( '/' . trim( $path, '/' ) );
	return rtrim( $path, '/' ) . '/';
}

/**
 * Returns a WP admin notice HTML string.
 */
function kseo_notice( string $message, string $type = 'success' ): string {
	return '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}
