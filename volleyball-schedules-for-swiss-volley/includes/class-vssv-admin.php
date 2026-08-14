<?php
/**
 * Administrationsbereich: Menü "Swiss Volley" mit Einstellungen, Teams und Log.
 *
 * Sicherheit:
 *  - Alle Aktionen erfordern current_user_can( 'manage_options' ).
 *  - Alle Formulare und AJAX-Aufrufe sind mit Nonces geschützt.
 *  - Der API-Key wird nie ausgegeben (weder HTML noch JS noch Log).
 *
 * @package VolleyballSchedulesForSwissVolley
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VSSV_Admin
 */
class VSSV_Admin {

	const CAPABILITY = 'manage_options';
	const MENU_SLUG  = 'volleyball-schedules-for-swiss-volley';

	/**
	 * Hooks registrieren.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_vssv_save_teams', array( __CLASS__, 'handle_save_teams' ) );

		add_action( 'wp_ajax_vssv_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_vssv_load_teams', array( __CLASS__, 'ajax_load_teams' ) );
		add_action( 'wp_ajax_vssv_clear_cache', array( __CLASS__, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_vssv_clear_log', array( __CLASS__, 'ajax_clear_log' ) );
	}

	/**
	 * Menüpunkt "Swiss Volley" anlegen.
	 */
	public static function add_menu(): void {
		add_menu_page(
			__( 'Swiss Volley', 'volleyball-schedules-for-swiss-volley' ),
			__( 'Swiss Volley', 'volleyball-schedules-for-swiss-volley' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-awards',
			58
		);
	}

	/**
	 * Admin-Assets nur auf der Plugin-Seite laden.
	 *
	 * @param string $hook Aktuelle Admin-Seite.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'vssv-admin', VSSV_PLUGIN_URL . 'assets/css/admin.css', array(), VSSV_VERSION );
		wp_enqueue_script( 'vssv-admin', VSSV_PLUGIN_URL . 'assets/js/admin.js', array(), VSSV_VERSION, true );

		wp_localize_script(
			'vssv-admin',
			'vssvAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vssv_admin' ),
				'i18n'    => array(
					'testing'  => __( 'Testing connection...', 'volleyball-schedules-for-swiss-volley' ),
					'loading'  => __( 'Loading teams...', 'volleyball-schedules-for-swiss-volley' ),
					'clearing' => __( 'Clearing cache...', 'volleyball-schedules-for-swiss-volley' ),
					'error'    => __( 'An error occurred.', 'volleyball-schedules-for-swiss-volley' ),
				),
			)
		);
	}

	/*
	 * ---------------------------------------------------------------------
	 * Einstellungen (Settings API)
	 * -------------------------------------------------------------------
	 */

	/**
	 * Einstellungen registrieren.
	 */
	public static function register_settings(): void {
		register_setting(
			'vssv_settings_group',
			'vssv_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => VSSV_Plugin::default_settings(),
			)
		);
	}

	/**
	 * Eingaben validieren und sanitizen.
	 *
	 * @param mixed $input Rohdaten aus dem Formular.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $input ): array {
		$defaults = VSSV_Plugin::default_settings();
		$current  = get_option( 'vssv_settings', $defaults );
		if ( ! is_array( $current ) ) {
			$current = $defaults;
		}
		if ( ! is_array( $input ) ) {
			return $current;
		}

		$clean = $current;

		// API-Key: leeres Feld = bestehenden Key behalten (er wird nie angezeigt).
		if ( isset( $input['api_key'] ) ) {
			$key = trim( (string) $input['api_key'] );
			if ( '' !== $key ) {
				$clean['api_key'] = sanitize_text_field( $key );
			}
		}
		if ( ! empty( $input['remove_api_key'] ) ) {
			$clean['api_key'] = '';
		}

		if ( isset( $input['api_base_url'] ) ) {
			$url                   = esc_url_raw( trim( (string) $input['api_base_url'] ), array( 'https', 'http' ) );
			$clean['api_base_url'] = $url ? untrailingslashit( $url ) : VSSV_API::DEFAULT_BASE_URL;
		}

		if ( isset( $input['club_id'] ) ) {
			$clean['club_id'] = sanitize_text_field( (string) $input['club_id'] );
		}
		if ( isset( $input['club_name'] ) ) {
			$clean['club_name'] = sanitize_text_field( (string) $input['club_name'] );
		}

		if ( isset( $input['season_year'] ) ) {
			$season               = trim( (string) $input['season_year'] );
			$clean['season_year'] = ( '' === $season ) ? '' : (string) absint( $season );
		}

		$allowed_minutes        = array( 5, 15, 30, 60, 180, 360, 720, 1440 );
		$minutes                = isset( $input['cache_minutes'] ) ? (int) $input['cache_minutes'] : 30;
		$clean['cache_minutes'] = in_array( $minutes, $allowed_minutes, true ) ? $minutes : 30;

		$clean['highlight_own'] = empty( $input['highlight_own'] ) ? 0 : 1;

		$league_display          = isset( $input['league_display'] ) ? (string) $input['league_display'] : 'heading';
		$clean['league_display'] = in_array( $league_display, array( 'heading', 'meta' ), true ) ? $league_display : 'heading';

		$clean['group_switcher'] = empty( $input['group_switcher'] ) ? 0 : 1;
		$clean['debug']          = empty( $input['debug'] ) ? 0 : 1;

		if ( isset( $input['custom_css'] ) ) {
			// Nur CSS-Text zulassen, kein Markup/Script.
			$clean['custom_css'] = wp_strip_all_tags( (string) $input['custom_css'] );
		}

		// Bei geänderten Kern-Einstellungen Cache invalidieren.
		if ( ( $clean['api_key'] ?? '' ) !== ( $current['api_key'] ?? '' )
			|| ( $clean['api_base_url'] ?? '' ) !== ( $current['api_base_url'] ?? '' )
			|| ( $clean['season_year'] ?? '' ) !== ( $current['season_year'] ?? '' ) ) {
			VSSV_Cache::clear_all();
		}

		return $clean;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Seiten-Rendering
	 * -------------------------------------------------------------------
	 */

	/**
	 * Hauptseite mit Tabs rendern.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No permission.', 'volleyball-schedules-for-swiss-volley' ) );
		}

		$tabs = array(
			'settings' => __( 'Settings', 'volleyball-schedules-for-swiss-volley' ),
			'teams'    => __( 'Teams', 'volleyball-schedules-for-swiss-volley' ),
			'log'      => __( 'Log', 'volleyball-schedules-for-swiss-volley' ),
		);

		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige.
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'settings';
		}

		echo '<div class="wrap vssv-admin">';
		echo '<h1>' . esc_html__( 'Volleyball Schedules for Swiss Volley', 'volleyball-schedules-for-swiss-volley' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$class = 'nav-tab' . ( $active === $slug ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		switch ( $active ) {
			case 'teams':
				self::render_teams_tab();
				break;
			case 'log':
				self::render_log_tab();
				break;
			default:
				self::render_settings_tab();
		}

		echo '</div>';
	}

	/**
	 * Tab: Einstellungen.
	 */
	private static function render_settings_tab(): void {
		$settings = wp_parse_args( get_option( 'vssv_settings', array() ), VSSV_Plugin::default_settings() );
		$has_key  = '' !== trim( (string) $settings['api_key'] );

		$minute_choices = array(
			5    => __( '5 minutes', 'volleyball-schedules-for-swiss-volley' ),
			15   => __( '15 minutes', 'volleyball-schedules-for-swiss-volley' ),
			30   => __( '30 minutes', 'volleyball-schedules-for-swiss-volley' ),
			60   => __( '1 hour', 'volleyball-schedules-for-swiss-volley' ),
			180  => __( '3 hours', 'volleyball-schedules-for-swiss-volley' ),
			360  => __( '6 hours', 'volleyball-schedules-for-swiss-volley' ),
			720  => __( '12 hours', 'volleyball-schedules-for-swiss-volley' ),
			1440 => __( '24 hours', 'volleyball-schedules-for-swiss-volley' ),
		);
		?>
		<form method="post" action="options.php" class="vssv-settings-form">
			<?php settings_fields( 'vssv_settings_group' ); ?>

			<h2><?php esc_html_e( 'Swiss Volley API', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="vssv_api_key"><?php esc_html_e( 'API key / token', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<input type="password" id="vssv_api_key" name="vssv_settings[api_key]" value="" autocomplete="new-password" class="regular-text"
							placeholder="<?php echo $has_key ? esc_attr__( '****** (saved - leave empty to keep)', 'volleyball-schedules-for-swiss-volley' ) : esc_attr__( 'Enter API key', 'volleyball-schedules-for-swiss-volley' ); ?>" />
						<?php if ( $has_key ) : ?>
							<label class="vssv-remove-key">
								<input type="checkbox" name="vssv_settings[remove_api_key]" value="1" />
								<?php esc_html_e( 'Delete saved key', 'volleyball-schedules-for-swiss-volley' ); ?>
							</label>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'You get the club-bound key in the Volley Manager under Administration -> Club -> Webservice/API. The key is used server-side only and is never output in the frontend.', 'volleyball-schedules-for-swiss-volley' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vssv_api_base_url"><?php esc_html_e( 'API base URL', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<input type="url" id="vssv_api_base_url" name="vssv_settings[api_base_url]" value="<?php echo esc_attr( $settings['api_base_url'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Default: https://api.volleyball.ch - change only if Swiss Volley updates the URL.', 'volleyball-schedules-for-swiss-volley' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Test connection', 'volleyball-schedules-for-swiss-volley' ); ?></th>
					<td>
						<button type="button" class="button" id="vssv-test-connection"><?php esc_html_e( 'Test API connection', 'volleyball-schedules-for-swiss-volley' ); ?></button>
						<span id="vssv-test-result" class="vssv-inline-result" role="status" aria-live="polite"></span>
						<p class="description"><?php esc_html_e( 'Save a new key first before testing the connection.', 'volleyball-schedules-for-swiss-volley' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Club and season', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="vssv_club_name"><?php esc_html_e( 'Club', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<input type="text" id="vssv_club_name" name="vssv_settings[club_name]" value="<?php echo esc_attr( $settings['club_name'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Automatically detected when loading the teams (e.g. Volley Pizol).', 'volleyball-schedules-for-swiss-volley' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vssv_club_id"><?php esc_html_e( 'Club ID', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<input type="text" id="vssv_club_id" name="vssv_settings[club_id]" value="<?php echo esc_attr( $settings['club_id'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Swiss Volley club ID; automatically detected when loading the teams.', 'volleyball-schedules-for-swiss-volley' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vssv_season_year"><?php esc_html_e( 'Current season', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<input type="text" id="vssv_season_year" name="vssv_settings[season_year]" value="<?php echo esc_attr( (string) $settings['season_year'] ); ?>" class="small-text" inputmode="numeric" pattern="[0-9]*" />
						<p class="description">
							<?php esc_html_e( 'Start year of the season, e.g. 2026 for the 2026/27 season. Leave empty to show all data provided by the API (recommended; the API always returns the current season). Available seasons are shown when loading the teams.', 'volleyball-schedules-for-swiss-volley' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Cache', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="vssv_cache_minutes"><?php esc_html_e( 'Cache duration', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<select id="vssv_cache_minutes" name="vssv_settings[cache_minutes]">
							<?php foreach ( $minute_choices as $minutes => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $minutes ); ?>" <?php selected( (int) $settings['cache_minutes'], $minutes ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button" id="vssv-clear-cache"><?php esc_html_e( 'Clear cache now', 'volleyball-schedules-for-swiss-volley' ); ?></button>
						<span id="vssv-cache-result" class="vssv-inline-result" role="status" aria-live="polite"></span>
						<p class="description"><?php esc_html_e( 'The Swiss Volley API is queried at most once per cache duration (WordPress transients).', 'volleyball-schedules-for-swiss-volley' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Display', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Highlight own club', 'volleyball-schedules-for-swiss-volley' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="vssv_settings[highlight_own]" value="1" <?php checked( ! empty( $settings['highlight_own'] ) ); ?> />
							<?php esc_html_e( 'Mark own teams with the CSS class vssv-own-team', 'volleyball-schedules-for-swiss-volley' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vssv_league_display"><?php esc_html_e( 'League display in game lists', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<select id="vssv_league_display" name="vssv_settings[league_display]">
							<option value="heading" <?php selected( (string) ( $settings['league_display'] ?? 'heading' ), 'heading' ); ?>>
								<?php esc_html_e( 'As a heading above the game (large)', 'volleyball-schedules-for-swiss-volley' ); ?>
							</option>
							<option value="meta" <?php selected( (string) ( $settings['league_display'] ?? 'heading' ), 'meta' ); ?>>
								<?php esc_html_e( 'Small, in the detail row (next to venue)', 'volleyball-schedules-for-swiss-volley' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Applies to all game lists. Can be overridden per shortcode with league="heading", league="meta", or league="none".', 'volleyball-schedules-for-swiss-volley' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Interactive grouping', 'volleyball-schedules-for-swiss-volley' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="vssv_settings[group_switcher]" value="1" <?php checked( ! empty( $settings['group_switcher'] ) ); ?> />
							<?php esc_html_e( 'Show visitors a "Chronological / By league / By team" toggle above the game lists', 'volleyball-schedules-for-swiss-volley' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Only appears if a list contains multiple leagues or teams. Can be overridden per shortcode with switcher="1" or switcher="0". A default set via group_by remains the starting view.', 'volleyball-schedules-for-swiss-volley' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vssv_custom_css"><?php esc_html_e( 'Custom CSS', 'volleyball-schedules-for-swiss-volley' ); ?></label>
					</th>
					<td>
						<textarea id="vssv_custom_css" name="vssv_settings[custom_css]" rows="8" class="large-text code" spellcheck="false"><?php echo esc_textarea( (string) $settings['custom_css'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Loaded after the default stylesheet of this plugin. Example: .vssv-own-team { font-weight: 700; }', 'volleyball-schedules-for-swiss-volley' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Error diagnostics', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug mode', 'volleyball-schedules-for-swiss-volley' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="vssv_settings[debug]" value="1" <?php checked( ! empty( $settings['debug'] ) ); ?> />
							<?php esc_html_e( 'Log API calls (endpoint, HTTP status, time, error message - never the API key) and show administrators detailed error messages', 'volleyball-schedules-for-swiss-volley' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save settings', 'volleyball-schedules-for-swiss-volley' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Teams.
	 */
	private static function render_teams_tab(): void {
		$teams    = VSSV_Teams::all();
		$settings = wp_parse_args( get_option( 'vssv_settings', array() ), VSSV_Plugin::default_settings() );
		?>
		<div class="vssv-teams-tab">
			<h2><?php esc_html_e( 'Load teams from Swiss Volley', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<p>
				<?php esc_html_e( 'Automatically loads the club and teams from the Swiss Volley data. Existing aliases, custom team names, league names, and the selection for club-wide views are preserved.', 'volleyball-schedules-for-swiss-volley' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="vssv-load-teams"><?php esc_html_e( 'Load club and teams now', 'volleyball-schedules-for-swiss-volley' ); ?></button>
				<span id="vssv-load-result" class="vssv-inline-result" role="status" aria-live="polite"></span>
			</p>

			<?php if ( empty( $teams ) ) : ?>
				<p class="vssv-empty-admin"><?php esc_html_e( 'No teams loaded yet. First enter the API key in the settings, then click "Load club and teams now".', 'volleyball-schedules-for-swiss-volley' ); ?></p>
			<?php else : ?>
				<h2>
					<?php
					if ( '' !== (string) $settings['club_name'] ) {
						/* translators: %s: club name */
						printf( esc_html__( 'Teams of %s', 'volleyball-schedules-for-swiss-volley' ), esc_html( (string) $settings['club_name'] ) );
					} else {
						esc_html_e( 'Teams', 'volleyball-schedules-for-swiss-volley' );
					}
					?>
				</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="vssv_save_teams" />
					<?php wp_nonce_field( 'vssv_save_teams' ); ?>

					<table class="widefat striped vssv-teams-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Team', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'Custom team name', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'League', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'Custom league name', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'Season', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'Team ID', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'Alias for shortcodes', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'Team link', 'volleyball-schedules-for-swiss-volley' ); ?></th>
								<th><?php esc_html_e( 'In club-wide views', 'volleyball-schedules-for-swiss-volley' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $teams as $tid => $team ) : ?>
								<tr>
									<td><strong><?php echo esc_html( (string) ( $team['caption'] ?? '' ) ); ?></strong></td>
									<td>
										<input type="text" name="name_label[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['name_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Herren 1', 'volleyball-schedules-for-swiss-volley' ); ?>" />
									</td>
									<td><?php echo esc_html( (string) ( $team['league'] ?? '' ) ); ?></td>
									<td>
										<input type="text" name="league_label[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['league_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Herren 2. Liga', 'volleyball-schedules-for-swiss-volley' ); ?>" />
									</td>
									<td><?php echo esc_html( (string) ( $team['season'] ?? '' ) ); ?></td>
									<td><code><?php echo esc_html( (string) $tid ); ?></code></td>
									<td>
										<input type="text" name="alias[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['alias'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. herren-1', 'volleyball-schedules-for-swiss-volley' ); ?>" />
									</td>
									<td>
										<input type="url" class="vssv-url-input" name="page_url[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['page_url'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. https://www.volleypizol.org/herren-1/', 'volleyball-schedules-for-swiss-volley' ); ?>" />
									</td>
									<td class="vssv-col-check">
										<input type="checkbox" name="in_club[<?php echo esc_attr( (string) $tid ); ?>]" value="1" <?php checked( ! empty( $team['in_club'] ) ); ?> />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="description">
						<?php esc_html_e( 'Custom team name and custom league name replace the Swiss Volley values in all displays (games, results, standings) - e.g. "Volley Pizol Herren 1" -> "Herren 1" or "H2L" -> "Herren 2. Liga". Empty fields continue to use the Swiss Volley value. Opponent names remain unchanged; cup games keep the official competition name. Team link: if a URL is set (e.g. the team page), the team name is linked in all displays.', 'volleyball-schedules-for-swiss-volley' ); ?>
					</p>

					<?php submit_button( __( 'Save teams', 'volleyball-schedules-for-swiss-volley' ) ); ?>
				</form>

				<h2><?php esc_html_e( 'Shortcode examples', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
				<p><?php esc_html_e( 'The team parameter accepts the team ID or the alias:', 'volleyball-schedules-for-swiss-volley' ); ?></p>
				<ul class="vssv-shortcode-examples">
					<li><code>[swissvolley_team team="herren-1"]</code></li>
					<li><code>[swissvolley_games team="12345" limit="10"]</code></li>
					<li><code>[swissvolley_results team="12345" limit="10"]</code></li>
					<li><code>[swissvolley_ranking team="herren-1"]</code></li>
					<li><code>[swissvolley_club_games limit="10"]</code></li>
					<li><code>[swissvolley_club_results limit="10"]</code></li>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tab: Log.
	 */
	private static function render_log_tab(): void {
		$entries = VSSV_Logger::entries();
		?>
		<div class="vssv-log-tab">
			<h2><?php esc_html_e( 'API log', 'volleyball-schedules-for-swiss-volley' ); ?></h2>
			<?php if ( ! VSSV_Logger::enabled() ) : ?>
				<p><?php esc_html_e( 'Debug mode is disabled. Enable it in the settings to log API calls.', 'volleyball-schedules-for-swiss-volley' ); ?></p>
			<?php endif; ?>

			<p>
				<button type="button" class="button" id="vssv-clear-log"><?php esc_html_e( 'Clear log', 'volleyball-schedules-for-swiss-volley' ); ?></button>
				<span id="vssv-log-result" class="vssv-inline-result" role="status" aria-live="polite"></span>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<p class="vssv-empty-admin"><?php esc_html_e( 'No log entries available.', 'volleyball-schedules-for-swiss-volley' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'volleyball-schedules-for-swiss-volley' ); ?></th>
							<th><?php esc_html_e( 'Endpoint', 'volleyball-schedules-for-swiss-volley' ); ?></th>
							<th><?php esc_html_e( 'HTTP status', 'volleyball-schedules-for-swiss-volley' ); ?></th>
							<th><?php esc_html_e( 'Message', 'volleyball-schedules-for-swiss-volley' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<tr class="<?php echo esc_attr( 'error' === ( $entry['level'] ?? '' ) ? 'vssv-log-error' : '' ); ?>">
								<td><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
								<td><code><?php echo esc_html( (string) ( $entry['endpoint'] ?? '' ) ); ?></code></td>
								<td><?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/*
	 * ---------------------------------------------------------------------
	 * Formular-Handler
	 * -------------------------------------------------------------------
	 */

	/**
	 * Teams speichern (Aliase, eigene Liga-Bezeichnungen, Auswahl).
	 */
	public static function handle_save_teams(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No permission.', 'volleyball-schedules-for-swiss-volley' ) );
		}
		check_admin_referer( 'vssv_save_teams' );

		$teams = VSSV_Teams::all();

		$aliases = isset( $_POST['alias'] ) && is_array( $_POST['alias'] ) ? wp_unslash( $_POST['alias'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unten pro Wert sanitisiert.
		$in_club = isset( $_POST['in_club'] ) && is_array( $_POST['in_club'] ) ? wp_unslash( $_POST['in_club'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur Schlüssel verwendet.
		$labels  = isset( $_POST['league_label'] ) && is_array( $_POST['league_label'] ) ? wp_unslash( $_POST['league_label'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unten pro Wert sanitisiert.
		$names   = isset( $_POST['name_label'] ) && is_array( $_POST['name_label'] ) ? wp_unslash( $_POST['name_label'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unten pro Wert sanitisiert.
		$urls    = isset( $_POST['page_url'] ) && is_array( $_POST['page_url'] ) ? wp_unslash( $_POST['page_url'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unten pro Wert sanitisiert.

		$used_aliases = array();
		foreach ( $teams as $tid => $team ) {
			$raw_alias = isset( $aliases[ $tid ] ) ? (string) $aliases[ $tid ] : '';
			$alias     = sanitize_title( $raw_alias );

			// Doppelte Aliase vermeiden (erster gewinnt).
			if ( '' !== $alias && isset( $used_aliases[ $alias ] ) ) {
				$alias = '';
			}
			if ( '' !== $alias ) {
				$used_aliases[ $alias ] = true;
			}

			$teams[ $tid ]['alias']        = $alias;
			$teams[ $tid ]['in_club']      = isset( $in_club[ $tid ] );
			$teams[ $tid ]['league_label'] = sanitize_text_field( isset( $labels[ $tid ] ) ? (string) $labels[ $tid ] : '' );
			$teams[ $tid ]['name_label']   = sanitize_text_field( isset( $names[ $tid ] ) ? (string) $names[ $tid ] : '' );
			$teams[ $tid ]['page_url']     = esc_url_raw( trim( isset( $urls[ $tid ] ) ? (string) $urls[ $tid ] : '' ) );
		}

		VSSV_Teams::save( $teams );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::MENU_SLUG,
					'tab'     => 'teams',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/*
	 * ---------------------------------------------------------------------
	 * AJAX-Handler
	 * -------------------------------------------------------------------
	 */

	/**
	 * Gemeinsame AJAX-Prüfung (Nonce + Capability).
	 */
	private static function verify_ajax(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'volleyball-schedules-for-swiss-volley' ) ), 403 );
		}
		check_ajax_referer( 'vssv_admin', 'nonce' );
	}

	/**
	 * AJAX: API-Verbindung testen.
	 */
	public static function ajax_test_connection(): void {
		self::verify_ajax();

		$api    = new VSSV_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$message = $result['message'];
		if ( ! empty( $result['club_name'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: 1: club name, 2: club ID */
				__( 'Detected club: %1$s (club ID %2$s).', 'volleyball-schedules-for-swiss-volley' ),
				$result['club_name'],
				$result['club_id']
			);
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX: Verein erkennen und Teams laden.
	 */
	public static function ajax_load_teams(): void {
		self::verify_ajax();

		$api = new VSSV_API();
		$raw = $api->get_games();
		if ( is_wp_error( $raw ) ) {
			wp_send_json_error( array( 'message' => $raw->get_error_message() ) );
		}

		// Frische Daten auch in den Cache legen.
		VSSV_Cache::set( 'games', $raw, 30 * MINUTE_IN_SECONDS );

		$club = VSSV_Data::detect_own_club( $raw );
		if ( ! $club ) {
			wp_send_json_error(
				array( 'message' => __( 'No club could be detected in the Swiss Volley data. Does the current season already contain games?', 'volleyball-schedules-for-swiss-volley' ) )
			);
		}

		// Verein in den Einstellungen hinterlegen.
		$settings              = wp_parse_args( get_option( 'vssv_settings', array() ), VSSV_Plugin::default_settings() );
		$settings['club_id']   = $club['id'];
		$settings['club_name'] = $club['name'];
		update_option( 'vssv_settings', $settings );

		$derived = VSSV_Data::derive_teams( $raw, $club['id'] );
		$teams   = VSSV_Teams::merge_derived( $derived );

		$seasons = VSSV_Data::list_seasons( $raw );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: club name, 2: number of teams, 3: available seasons */
					__( 'Club "%1$s" detected, %2$d teams loaded. Available seasons: %3$s. Reloading the page...', 'volleyball-schedules-for-swiss-volley' ),
					$club['name'],
					count( $teams ),
					$seasons ? implode( ', ', $seasons ) : '–'
				),
				'reload'  => true,
			)
		);
	}

	/**
	 * AJAX: Cache leeren.
	 */
	public static function ajax_clear_cache(): void {
		self::verify_ajax();
		VSSV_Cache::clear_all();
		wp_send_json_success( array( 'message' => __( 'The cache has been cleared. Fresh data will be loaded on the next page view.', 'volleyball-schedules-for-swiss-volley' ) ) );
	}

	/**
	 * AJAX: Log leeren.
	 */
	public static function ajax_clear_log(): void {
		self::verify_ajax();
		VSSV_Logger::clear();
		wp_send_json_success(
			array(
				'message' => __( 'The log has been cleared.', 'volleyball-schedules-for-swiss-volley' ),
				'reload'  => true,
			)
		);
	}
}
