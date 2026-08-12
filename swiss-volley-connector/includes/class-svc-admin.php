<?php
/**
 * Administrationsbereich: Menü "Swiss Volley" mit Einstellungen, Teams und Log.
 *
 * Sicherheit:
 *  - Alle Aktionen erfordern current_user_can( 'manage_options' ).
 *  - Alle Formulare und AJAX-Aufrufe sind mit Nonces geschützt.
 *  - Der API-Key wird nie ausgegeben (weder HTML noch JS noch Log).
 *
 * @package SwissVolleyConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SVC_Admin
 */
class SVC_Admin {

	const CAPABILITY = 'manage_options';
	const MENU_SLUG  = 'swiss-volley-connector';

	/**
	 * Hooks registrieren.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_svc_save_teams', array( __CLASS__, 'handle_save_teams' ) );

		add_action( 'wp_ajax_svc_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_svc_load_teams', array( __CLASS__, 'ajax_load_teams' ) );
		add_action( 'wp_ajax_svc_clear_cache', array( __CLASS__, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_svc_clear_log', array( __CLASS__, 'ajax_clear_log' ) );
	}

	/**
	 * Menüpunkt "Swiss Volley" anlegen.
	 */
	public static function add_menu(): void {
		add_menu_page(
			__( 'Swiss Volley', 'swiss-volley-connector' ),
			__( 'Swiss Volley', 'swiss-volley-connector' ),
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

		wp_enqueue_style( 'svc-admin', SVC_PLUGIN_URL . 'assets/css/admin.css', array(), SVC_VERSION );
		wp_enqueue_script( 'svc-admin', SVC_PLUGIN_URL . 'assets/js/admin.js', array(), SVC_VERSION, true );

		wp_localize_script(
			'svc-admin',
			'svcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'svc_admin' ),
				'i18n'    => array(
					'testing'  => __( 'Verbindung wird geprüft …', 'swiss-volley-connector' ),
					'loading'  => __( 'Teams werden geladen …', 'swiss-volley-connector' ),
					'clearing' => __( 'Cache wird geleert …', 'swiss-volley-connector' ),
					'error'    => __( 'Es ist ein Fehler aufgetreten.', 'swiss-volley-connector' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Einstellungen (Settings API)
	 * ------------------------------------------------------------------- */

	/**
	 * Einstellungen registrieren.
	 */
	public static function register_settings(): void {
		register_setting(
			'svc_settings_group',
			'svc_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => SVC_Plugin::default_settings(),
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
		$defaults = SVC_Plugin::default_settings();
		$current  = get_option( 'svc_settings', $defaults );
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
			$clean['api_base_url'] = $url ? untrailingslashit( $url ) : SVC_API::DEFAULT_BASE_URL;
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
		$clean['debug']         = empty( $input['debug'] ) ? 0 : 1;

		if ( isset( $input['custom_css'] ) ) {
			// Nur CSS-Text zulassen, kein Markup/Script.
			$clean['custom_css'] = wp_strip_all_tags( (string) $input['custom_css'] );
		}

		// Bei geänderten Kern-Einstellungen Cache invalidieren.
		if ( ( $clean['api_key'] ?? '' ) !== ( $current['api_key'] ?? '' )
			|| ( $clean['api_base_url'] ?? '' ) !== ( $current['api_base_url'] ?? '' )
			|| ( $clean['season_year'] ?? '' ) !== ( $current['season_year'] ?? '' ) ) {
			SVC_Cache::clear_all();
		}

		return $clean;
	}

	/* ---------------------------------------------------------------------
	 * Seiten-Rendering
	 * ------------------------------------------------------------------- */

	/**
	 * Hauptseite mit Tabs rendern.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'swiss-volley-connector' ) );
		}

		$tabs = array(
			'settings' => __( 'Einstellungen', 'swiss-volley-connector' ),
			'teams'    => __( 'Teams', 'swiss-volley-connector' ),
			'log'      => __( 'Log', 'swiss-volley-connector' ),
		);

		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige.
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'settings';
		}

		echo '<div class="wrap svc-admin">';
		echo '<h1>' . esc_html__( 'Swiss Volley Connector', 'swiss-volley-connector' ) . ' <span class="svc-beta">' . esc_html__( 'Private Beta 0.1.0', 'swiss-volley-connector' ) . '</span></h1>';

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
		$settings = wp_parse_args( get_option( 'svc_settings', array() ), SVC_Plugin::default_settings() );
		$has_key  = '' !== trim( (string) $settings['api_key'] );

		$minute_choices = array(
			5    => __( '5 Minuten', 'swiss-volley-connector' ),
			15   => __( '15 Minuten', 'swiss-volley-connector' ),
			30   => __( '30 Minuten', 'swiss-volley-connector' ),
			60   => __( '1 Stunde', 'swiss-volley-connector' ),
			180  => __( '3 Stunden', 'swiss-volley-connector' ),
			360  => __( '6 Stunden', 'swiss-volley-connector' ),
			720  => __( '12 Stunden', 'swiss-volley-connector' ),
			1440 => __( '24 Stunden', 'swiss-volley-connector' ),
		);
		?>
		<form method="post" action="options.php" class="svc-settings-form">
			<?php settings_fields( 'svc_settings_group' ); ?>

			<h2><?php esc_html_e( 'Swiss-Volley-API', 'swiss-volley-connector' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="svc_api_key"><?php esc_html_e( 'API-Key / Token', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<input type="password" id="svc_api_key" name="svc_settings[api_key]" value="" autocomplete="new-password" class="regular-text"
							placeholder="<?php echo $has_key ? esc_attr__( '•••••• (gespeichert – leer lassen zum Behalten)', 'swiss-volley-connector' ) : esc_attr__( 'API-Key eingeben', 'swiss-volley-connector' ); ?>" />
						<?php if ( $has_key ) : ?>
							<label class="svc-remove-key">
								<input type="checkbox" name="svc_settings[remove_api_key]" value="1" />
								<?php esc_html_e( 'Gespeicherten Key löschen', 'swiss-volley-connector' ); ?>
							</label>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Den club-gebundenen Key erhältst du im Volley Manager unter Administration → Club → Webservice/API. Der Key wird ausschliesslich serverseitig verwendet und nie im Frontend ausgegeben.', 'swiss-volley-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svc_api_base_url"><?php esc_html_e( 'API-Basis-URL', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<input type="url" id="svc_api_base_url" name="svc_settings[api_base_url]" value="<?php echo esc_attr( $settings['api_base_url'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Standard: https://api.volleyball.ch – nur ändern, falls Swiss Volley die URL anpasst.', 'swiss-volley-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Verbindung testen', 'swiss-volley-connector' ); ?></th>
					<td>
						<button type="button" class="button" id="svc-test-connection"><?php esc_html_e( 'API-Verbindung testen', 'swiss-volley-connector' ); ?></button>
						<span id="svc-test-result" class="svc-inline-result" role="status" aria-live="polite"></span>
						<p class="description"><?php esc_html_e( 'Speichere einen neuen Key zuerst, bevor du die Verbindung testest.', 'swiss-volley-connector' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Verein und Saison', 'swiss-volley-connector' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="svc_club_name"><?php esc_html_e( 'Verein / Club', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<input type="text" id="svc_club_name" name="svc_settings[club_name]" value="<?php echo esc_attr( $settings['club_name'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Wird beim Laden der Teams automatisch erkannt (z. B. Volley Pizol).', 'swiss-volley-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svc_club_id"><?php esc_html_e( 'Club-ID', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<input type="text" id="svc_club_id" name="svc_settings[club_id]" value="<?php echo esc_attr( $settings['club_id'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Swiss-Volley-Club-ID; wird beim Laden der Teams automatisch erkannt.', 'swiss-volley-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svc_season_year"><?php esc_html_e( 'Aktuelle Saison', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<input type="text" id="svc_season_year" name="svc_settings[season_year]" value="<?php echo esc_attr( (string) $settings['season_year'] ); ?>" class="small-text" inputmode="numeric" pattern="[0-9]*" />
						<p class="description">
							<?php esc_html_e( 'Startjahr der Saison, z. B. 2026 für die Saison 2026/27. Leer lassen, um alle von der API gelieferten Daten anzuzeigen (empfohlen; die API liefert jeweils die aktuelle Saison). Beim Laden der Teams werden die verfügbaren Saisons angezeigt.', 'swiss-volley-connector' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Cache', 'swiss-volley-connector' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="svc_cache_minutes"><?php esc_html_e( 'Cache-Dauer', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<select id="svc_cache_minutes" name="svc_settings[cache_minutes]">
							<?php foreach ( $minute_choices as $minutes => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $minutes ); ?>" <?php selected( (int) $settings['cache_minutes'], $minutes ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button" id="svc-clear-cache"><?php esc_html_e( 'Cache jetzt leeren', 'swiss-volley-connector' ); ?></button>
						<span id="svc-cache-result" class="svc-inline-result" role="status" aria-live="polite"></span>
						<p class="description"><?php esc_html_e( 'Die Swiss-Volley-API wird höchstens einmal pro Cache-Dauer abgefragt (WordPress Transients).', 'swiss-volley-connector' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Darstellung', 'swiss-volley-connector' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Eigener Verein hervorheben', 'swiss-volley-connector' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="svc_settings[highlight_own]" value="1" <?php checked( ! empty( $settings['highlight_own'] ) ); ?> />
							<?php esc_html_e( 'Eigene Teams mit der CSS-Klasse svc-own-team kennzeichnen', 'swiss-volley-connector' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svc_league_display"><?php esc_html_e( 'Liga-Darstellung in Spiellisten', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<select id="svc_league_display" name="svc_settings[league_display]">
							<option value="heading" <?php selected( (string) ( $settings['league_display'] ?? 'heading' ), 'heading' ); ?>>
								<?php esc_html_e( 'Als Überschrift über dem Spiel (gross)', 'swiss-volley-connector' ); ?>
							</option>
							<option value="meta" <?php selected( (string) ( $settings['league_display'] ?? 'heading' ), 'meta' ); ?>>
								<?php esc_html_e( 'Klein in der Detailzeile (neben Spielort)', 'swiss-volley-connector' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Gilt für alle Spiellisten. Pro Shortcode übersteuerbar mit league="heading", league="meta" oder league="none".', 'swiss-volley-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Interaktive Gruppierung', 'swiss-volley-connector' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="svc_settings[group_switcher]" value="1" <?php checked( ! empty( $settings['group_switcher'] ) ); ?> />
							<?php esc_html_e( 'Besuchern einen Umschalter «Chronologisch / Nach Liga / Nach Team» über den Spiellisten anzeigen', 'swiss-volley-connector' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Erscheint nur, wenn eine Liste mehrere Ligen oder Teams enthält. Pro Shortcode übersteuerbar mit switcher="1" oder switcher="0". Ein per group_by gesetzter Standard bleibt als Startansicht erhalten.', 'swiss-volley-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svc_custom_css"><?php esc_html_e( 'Eigenes CSS', 'swiss-volley-connector' ); ?></label>
					</th>
					<td>
						<textarea id="svc_custom_css" name="svc_settings[custom_css]" rows="8" class="large-text code" spellcheck="false"><?php echo esc_textarea( (string) $settings['custom_css'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Wird nach dem Standard-Stylesheet des Plugins geladen. Beispiel: .svc-own-team { font-weight: 700; }', 'swiss-volley-connector' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Fehlerdiagnose', 'swiss-volley-connector' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug-Modus', 'swiss-volley-connector' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="svc_settings[debug]" value="1" <?php checked( ! empty( $settings['debug'] ) ); ?> />
							<?php esc_html_e( 'API-Aufrufe protokollieren (Endpunkt, HTTP-Status, Zeitpunkt, Fehlermeldung – nie der API-Key) und Administratoren detaillierte Fehlermeldungen anzeigen', 'swiss-volley-connector' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Einstellungen speichern', 'swiss-volley-connector' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tab: Teams.
	 */
	private static function render_teams_tab(): void {
		$teams    = SVC_Teams::all();
		$settings = wp_parse_args( get_option( 'svc_settings', array() ), SVC_Plugin::default_settings() );
		?>
		<div class="svc-teams-tab">
			<h2><?php esc_html_e( 'Teams aus Swiss Volley laden', 'swiss-volley-connector' ); ?></h2>
			<p>
				<?php esc_html_e( 'Lädt Verein und Mannschaften automatisch aus den Swiss-Volley-Daten. Bestehende Aliase, eigene Teamnamen und Liga-Bezeichnungen sowie die Auswahl für vereinsweite Ansichten bleiben erhalten.', 'swiss-volley-connector' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="svc-load-teams"><?php esc_html_e( 'Verein und Teams jetzt laden', 'swiss-volley-connector' ); ?></button>
				<span id="svc-load-result" class="svc-inline-result" role="status" aria-live="polite"></span>
			</p>

			<?php if ( empty( $teams ) ) : ?>
				<p class="svc-empty-admin"><?php esc_html_e( 'Noch keine Teams geladen. Hinterlege zuerst den API-Key in den Einstellungen und klicke dann auf «Verein und Teams jetzt laden».', 'swiss-volley-connector' ); ?></p>
			<?php else : ?>
				<h2>
					<?php
					if ( '' !== (string) $settings['club_name'] ) {
						/* translators: %s: Vereinsname */
						printf( esc_html__( 'Teams von %s', 'swiss-volley-connector' ), esc_html( (string) $settings['club_name'] ) );
					} else {
						esc_html_e( 'Teams', 'swiss-volley-connector' );
					}
					?>
				</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="svc_save_teams" />
					<?php wp_nonce_field( 'svc_save_teams' ); ?>

					<table class="widefat striped svc-teams-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Team', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Eigener Teamname', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Liga', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Eigene Liga-Bezeichnung', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Saison', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Team-ID', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Alias für Shortcodes', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'Team-Link', 'swiss-volley-connector' ); ?></th>
								<th><?php esc_html_e( 'In vereinsweiten Ansichten', 'swiss-volley-connector' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $teams as $tid => $team ) : ?>
								<tr>
									<td><strong><?php echo esc_html( (string) ( $team['caption'] ?? '' ) ); ?></strong></td>
									<td>
										<input type="text" name="name_label[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['name_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'z. B. Herren 1', 'swiss-volley-connector' ); ?>" />
									</td>
									<td><?php echo esc_html( (string) ( $team['league'] ?? '' ) ); ?></td>
									<td>
										<input type="text" name="league_label[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['league_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'z. B. Herren 2. Liga', 'swiss-volley-connector' ); ?>" />
									</td>
									<td><?php echo esc_html( (string) ( $team['season'] ?? '' ) ); ?></td>
									<td><code><?php echo esc_html( (string) $tid ); ?></code></td>
									<td>
										<input type="text" name="alias[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['alias'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'z. B. herren-1', 'swiss-volley-connector' ); ?>" />
									</td>
									<td>
										<input type="url" class="svc-url-input" name="page_url[<?php echo esc_attr( (string) $tid ); ?>]" value="<?php echo esc_attr( (string) ( $team['page_url'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'z. B. https://www.volleypizol.org/herren-1/', 'swiss-volley-connector' ); ?>" />
									</td>
									<td class="svc-col-check">
										<input type="checkbox" name="in_club[<?php echo esc_attr( (string) $tid ); ?>]" value="1" <?php checked( ! empty( $team['in_club'] ) ); ?> />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="description">
						<?php esc_html_e( 'Eigener Teamname und eigene Liga-Bezeichnung ersetzen in allen Anzeigen (Spiele, Resultate, Rangliste) die Werte von Swiss Volley – z. B. «Volley Pizol Herren 1» → «Herren 1» oder «H2L» → «Herren 2. Liga». Leere Felder verwenden weiterhin den Swiss-Volley-Wert. Gegnernamen bleiben unverändert; Cup-Spiele behalten den offiziellen Wettbewerbsnamen. Team-Link: ist eine URL hinterlegt (z. B. die Teamseite), wird der Teamname in allen Anzeigen verlinkt.', 'swiss-volley-connector' ); ?>
					</p>

					<?php submit_button( __( 'Teams speichern', 'swiss-volley-connector' ) ); ?>
				</form>

				<h2><?php esc_html_e( 'Shortcode-Beispiele', 'swiss-volley-connector' ); ?></h2>
				<p><?php esc_html_e( 'Team-Parameter akzeptiert die Team-ID oder den Alias:', 'swiss-volley-connector' ); ?></p>
				<ul class="svc-shortcode-examples">
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
		$entries = SVC_Logger::entries();
		?>
		<div class="svc-log-tab">
			<h2><?php esc_html_e( 'API-Log', 'swiss-volley-connector' ); ?></h2>
			<?php if ( ! SVC_Logger::enabled() ) : ?>
				<p><?php esc_html_e( 'Der Debug-Modus ist deaktiviert. Aktiviere ihn in den Einstellungen, um API-Aufrufe zu protokollieren.', 'swiss-volley-connector' ); ?></p>
			<?php endif; ?>

			<p>
				<button type="button" class="button" id="svc-clear-log"><?php esc_html_e( 'Log leeren', 'swiss-volley-connector' ); ?></button>
				<span id="svc-log-result" class="svc-inline-result" role="status" aria-live="polite"></span>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<p class="svc-empty-admin"><?php esc_html_e( 'Keine Log-Einträge vorhanden.', 'swiss-volley-connector' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Zeitpunkt', 'swiss-volley-connector' ); ?></th>
							<th><?php esc_html_e( 'Endpunkt', 'swiss-volley-connector' ); ?></th>
							<th><?php esc_html_e( 'HTTP-Status', 'swiss-volley-connector' ); ?></th>
							<th><?php esc_html_e( 'Meldung', 'swiss-volley-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<tr class="<?php echo esc_attr( 'error' === ( $entry['level'] ?? '' ) ? 'svc-log-error' : '' ); ?>">
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

	/* ---------------------------------------------------------------------
	 * Formular-Handler
	 * ------------------------------------------------------------------- */

	/**
	 * Teams speichern (Aliase, eigene Liga-Bezeichnungen, Auswahl).
	 */
	public static function handle_save_teams(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'swiss-volley-connector' ) );
		}
		check_admin_referer( 'svc_save_teams' );

		$teams = SVC_Teams::all();

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

		SVC_Teams::save( $teams );

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

	/* ---------------------------------------------------------------------
	 * AJAX-Handler
	 * ------------------------------------------------------------------- */

	/**
	 * Gemeinsame AJAX-Prüfung (Nonce + Capability).
	 */
	private static function verify_ajax(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'swiss-volley-connector' ) ), 403 );
		}
		check_ajax_referer( 'svc_admin', 'nonce' );
	}

	/**
	 * AJAX: API-Verbindung testen.
	 */
	public static function ajax_test_connection(): void {
		self::verify_ajax();

		$api    = new SVC_API();
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$message = $result['message'];
		if ( ! empty( $result['club_name'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: 1: Vereinsname, 2: Club-ID */
				__( 'Erkannter Verein: %1$s (Club-ID %2$s).', 'swiss-volley-connector' ),
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

		$api = new SVC_API();
		$raw = $api->get_games();
		if ( is_wp_error( $raw ) ) {
			wp_send_json_error( array( 'message' => $raw->get_error_message() ) );
		}

		// Frische Daten auch in den Cache legen.
		SVC_Cache::set( 'games', $raw, 30 * MINUTE_IN_SECONDS );

		$club = SVC_Data::detect_own_club( $raw );
		if ( ! $club ) {
			wp_send_json_error(
				array( 'message' => __( 'In den Swiss-Volley-Daten konnte kein Verein erkannt werden. Enthält die aktuelle Saison bereits Spiele?', 'swiss-volley-connector' ) )
			);
		}

		// Verein in den Einstellungen hinterlegen.
		$settings              = wp_parse_args( get_option( 'svc_settings', array() ), SVC_Plugin::default_settings() );
		$settings['club_id']   = $club['id'];
		$settings['club_name'] = $club['name'];
		update_option( 'svc_settings', $settings );

		$derived = SVC_Data::derive_teams( $raw, $club['id'] );
		$teams   = SVC_Teams::merge_derived( $derived );

		$seasons = SVC_Data::list_seasons( $raw );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: Vereinsname, 2: Anzahl Teams, 3: verfügbare Saisons */
					__( 'Verein «%1$s» erkannt, %2$d Teams geladen. Verfügbare Saisons: %3$s. Die Seite wird neu geladen …', 'swiss-volley-connector' ),
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
		SVC_Cache::clear_all();
		wp_send_json_success( array( 'message' => __( 'Der Cache wurde geleert. Beim nächsten Seitenaufruf werden frische Daten geladen.', 'swiss-volley-connector' ) ) );
	}

	/**
	 * AJAX: Log leeren.
	 */
	public static function ajax_clear_log(): void {
		self::verify_ajax();
		SVC_Logger::clear();
		wp_send_json_success(
			array(
				'message' => __( 'Das Log wurde geleert.', 'swiss-volley-connector' ),
				'reload'  => true,
			)
		);
	}
}
