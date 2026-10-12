<?php
/**
 * Main plugin class.
 *
 * @package UTM_Phone_Switcher
 */

namespace NeedToMe\UTMPhoneSwitcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and front-end number replacement.
 */
final class Plugin {

	const OPTION_NAME = 'utm_phone_switcher_settings';
	const LEGACY_OPTION_NAME = 'pdxphone_for_utm_options';
	const COOKIE_NAME = 'utm_phone_switcher';
	const LEGACY_COOKIE_NAME = 'pdxphone_for_utm';
	const PAGE_SLUG = 'utm-phone-switcher';
	const MAX_RULES = 100;

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		$plugin = new self();
		add_action( 'init', array( $plugin, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $plugin, 'maybe_migrate_legacy_settings' ), 20 );
		add_action( 'admin_menu', array( $plugin, 'register_settings_page' ) );
		add_action( 'admin_init', array( $plugin, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin, 'enqueue_frontend_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UTM_PHONE_SWITCHER_FILE ), array( $plugin, 'add_settings_link' ) );
	}

	/**
	 * Load translations bundled with the plugin.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		$locale               = determine_locale();
		$polylang_locale      = $this->get_polylang_admin_locale();

		if ( '' !== $polylang_locale ) {
			$locale = $polylang_locale;
		}

		$wordpress_translation = WP_LANG_DIR . '/plugins/utm-phone-switcher-' . $locale . '.mo';
		$bundled_translation   = plugin_dir_path( UTM_PHONE_SWITCHER_FILE ) . 'languages/utm-phone-switcher-' . $locale . '.mo';

		if ( is_textdomain_loaded( 'utm-phone-switcher' ) || is_readable( $wordpress_translation ) || ! is_readable( $bundled_translation ) ) {
			return;
		}

		load_textdomain( 'utm-phone-switcher', $bundled_translation );
	}

	/**
	 * Get the selected Polylang content language for this plugin's settings screen.
	 *
	 * Polylang's admin language filter does not change the WordPress user locale.
	 * Use its selected language here so this screen follows the language chosen in
	 * the admin toolbar while leaving other admin pages unchanged.
	 *
	 * @return string
	 */
	private function get_polylang_admin_locale() {
		if ( ! is_admin() || ! function_exists( 'pll_languages_list' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing; this value does not trigger a state change.
		$page_slug = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page_slug ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Polylang language selection; no action is performed.
		$requested_language_slug = isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : null;
		if ( null !== $requested_language_slug ) {
			$language_slug = $requested_language_slug;
			if ( 'all' === $language_slug ) {
				return '';
			}
		} else {
			$language_slug = sanitize_key( (string) get_user_meta( get_current_user_id(), 'pll_filter_content', true ) );
		}

		if ( '' === $language_slug ) {
			return '';
		}

		$language_slugs   = pll_languages_list( array( 'fields' => 'slug' ) );
		$language_locales = pll_languages_list( array( 'fields' => 'locale' ) );
		$language_index   = is_array( $language_slugs ) ? array_search( $language_slug, $language_slugs, true ) : false;

		if ( false === $language_index || ! isset( $language_locales[ $language_index ] ) ) {
			return '';
		}

		return sanitize_text_field( $language_locales[ $language_index ] );
	}

	/**
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                 => 1,
			'admin_only'              => 0,
			'duration_days'           => 14,
			'delete_data_on_uninstall' => 0,
			'rules'                   => array(
				array(
					'id'              => 'test_plugin_example',
					'enabled'         => 1,
					'query_parameter' => 'utm_source',
					'query_value'     => 'test_plugin_value',
					'selector'        => '.your_phone_selector',
					'phone'           => '+1 234 555 6789',
				),
			),
		);
	}

	/**
	 * Migrate the former PDX option once without deleting its original data.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_settings() {
		if ( null !== get_option( self::OPTION_NAME, null ) ) {
			return;
		}

		$legacy = get_option( self::LEGACY_OPTION_NAME, array() );
		if ( ! is_array( $legacy ) ) {
			return;
		}

		$settings = array(
			'enabled'       => empty( $legacy['cancel'] ) ? 1 : 0,
			'duration_days' => isset( $legacy['save_period'] ) ? absint( $legacy['save_period'] ) : 14,
			'rules'         => array(),
		);

		if ( ! empty( $legacy['phones'] ) && is_array( $legacy['phones'] ) ) {
			foreach ( array_slice( $legacy['phones'], 0, self::MAX_RULES ) as $index => $phone ) {
				if ( ! is_array( $phone ) ) {
					continue;
				}

				$settings['rules'][] = array(
					'id'             => wp_generate_password( 12, false, false ),
					'enabled'        => 1,
					'query_parameter' => isset( $phone['utm_tag'] ) ? $phone['utm_tag'] : '',
					'query_value'    => isset( $phone['utm_value'] ) ? $phone['utm_value'] : '',
					'selector'       => isset( $phone['sector'] ) ? $phone['sector'] : '',
					'phone'          => isset( $phone['phone_number'] ) ? $phone['phone_number'] : '',
				);
			}
		}

		update_option( self::OPTION_NAME, self::sanitize_settings( $settings ) );
	}

	/**
	 * Register the WordPress Settings API option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'utm_phone_switcher',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize all saved settings and rule rows.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		$duration = isset( $input['duration_days'] ) && is_numeric( $input['duration_days'] )
			? absint( $input['duration_days'] )
			: 14;
		$duration = min( 365, $duration );

		$settings = array(
			'enabled'                  => empty( $input['enabled'] ) ? 0 : 1,
			'admin_only'               => empty( $input['admin_only'] ) ? 0 : 1,
			'duration_days'            => $duration,
			'delete_data_on_uninstall' => empty( $input['delete_data_on_uninstall'] ) ? 0 : 1,
			'rules'                    => array(),
		);

		if ( empty( $input['rules'] ) || ! is_array( $input['rules'] ) ) {
			return $settings;
		}

		$seen_ids = array();
		foreach ( array_slice( $input['rules'], 0, self::MAX_RULES ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$id = isset( $rule['id'] ) ? sanitize_key( $rule['id'] ) : '';
			if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
				$id = wp_generate_password( 12, false, false );
			}
			$seen_ids[ $id ] = true;

			$query_parameter = isset( $rule['query_parameter'] )
				? trim( sanitize_text_field( $rule['query_parameter'] ) )
				: '';
			if ( '' !== $query_parameter && ! preg_match( '/^[A-Za-z0-9_.\[\]*-]{1,100}$/D', $query_parameter ) ) {
				$query_parameter = '';
			}

			$phone = isset( $rule['phone'] ) ? trim( sanitize_text_field( $rule['phone'] ) ) : '';
			if ( '' !== $phone && ! preg_match( '/^[0-9+(). #xX\-]{3,80}$/D', $phone ) ) {
				$phone = '';
			}

			$settings['rules'][] = array(
				'id'              => $id,
				'enabled'         => empty( $rule['enabled'] ) ? 0 : 1,
				'query_parameter' => $query_parameter,
				'query_value'     => isset( $rule['query_value'] ) ? wp_html_excerpt( sanitize_text_field( $rule['query_value'] ), 200, '' ) : '',
				'selector'        => isset( $rule['selector'] ) ? wp_html_excerpt( trim( sanitize_text_field( $rule['selector'] ) ), 300, '' ) : '',
				'phone'           => $phone,
			);
		}

		return $settings;
	}

	/**
	 * Add the settings submenu under Settings.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page(
			esc_html__( 'UTM Phone Switcher', 'utm-phone-switcher' ),
			esc_html__( 'UTM Phone Switcher', 'utm-phone-switcher' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add a Settings shortcut on the Plugins screen.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function add_settings_link( $links ) {
		$url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'options-general.php' ) );
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'utm-phone-switcher' )
			)
		);

		return $links;
	}

	/**
	 * Render settings and rule rows.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation; no action is performed.
		$active_tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		if ( ! in_array( $active_tab, array( 'settings', 'plugins', 'development' ), true ) ) {
			$active_tab = 'settings';
		}
		?>
		<div class="wrap utm-phone-switcher-settings">
			<h1><?php esc_html_e( 'UTM Phone Switcher', 'utm-phone-switcher' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'settings'    => __( 'Settings', 'utm-phone-switcher' ),
					'plugins'     => __( 'Other plugins', 'utm-phone-switcher' ),
					'development' => __( 'Website Development', 'utm-phone-switcher' ),
				);
				foreach ( $tabs as $tab => $label ) :
					$tab_url = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $tab,
						),
						admin_url( 'options-general.php' )
					);
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php if ( 'settings' === $active_tab ) : ?>
				<?php
				$settings = self::get_settings();
				$rules    = array_values( $settings['rules'] );
				?>
				<p><?php esc_html_e( 'Show a campaign-specific phone number when a visitor arrives with a matching URL parameter. Add a CSS selector for the element that contains the phone number.', 'utm-phone-switcher' ); ?></p>
				<p class="description"><?php esc_html_e( 'Example: parameter utm_source, value newsletter, selector .header-phone, phone +1 555 010 1234.', 'utm-phone-switcher' ); ?></p>

				<form action="options.php" method="post">
					<?php settings_fields( 'utm_phone_switcher' ); ?>
					<?php $this->render_general_settings( $settings ); ?>

					<h2><?php esc_html_e( 'Number replacement rules', 'utm-phone-switcher' ); ?></h2>
					<p><?php esc_html_e( 'Rules are checked from top to bottom. When more than one matching rule targets the same element, the first rule wins.', 'utm-phone-switcher' ); ?></p>
					<p class="description"><?php esc_html_e( 'Parameter value is optional. Leave it blank to match any value when the URL parameter is present. Use * as a wildcard in the parameter name, for example utm_* matches names beginning with utm_.', 'utm-phone-switcher' ); ?></p>

					<div class="utm-phone-switcher-table-wrap">
						<table class="widefat striped utm-phone-switcher-rules">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'URL parameter', 'utm-phone-switcher' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Parameter value', 'utm-phone-switcher' ); ?></th>
									<th scope="col"><?php esc_html_e( 'CSS selector', 'utm-phone-switcher' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Replacement phone', 'utm-phone-switcher' ); ?></th>
									<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'utm-phone-switcher' ); ?></span></th>
								</tr>
							</thead>
							<tbody id="utm-phone-switcher-rules" data-next-index="<?php echo esc_attr( (string) count( $rules ) ); ?>">
								<?php foreach ( $rules as $index => $rule ) : ?>
									<?php $this->render_rule_row( $index, $rule ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<p><button type="button" class="button" id="utm-phone-switcher-add-rule"><?php esc_html_e( 'Add rule', 'utm-phone-switcher' ); ?></button></p>
					<p class="description"><?php esc_html_e( 'Use a selector that targets only the phone text, such as .header-phone or .contact-phone a. If it targets a tel: link, the link destination is updated too. Selectors are validated in the browser when a page is viewed.', 'utm-phone-switcher' ); ?></p>
					<?php $this->render_selector_help(); ?>

					<template id="utm-phone-switcher-row-template">
						<?php $this->render_rule_row( '__INDEX__', array( 'id' => '__RULE_ID__' ) ); ?>
					</template>

					<?php submit_button( __( 'Save Changes', 'utm-phone-switcher' ) ); ?>
				</form>
				<?php $this->render_support_box(); ?>
			<?php elseif ( 'plugins' === $active_tab ) : ?>
				<?php $this->render_profile_page( 'plugins' ); ?>
			<?php else : ?>
				<?php $this->render_profile_page( 'development' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the developer profile tabs with this plugin's translations.
	 *
	 * @param string $tab Profile tab to render.
	 * @return void
	 */
	private function render_profile_page( $tab ) {
		?>
		<div class="pdxglobal_container">
			<div class="pdxglobal_sidebar">
				<?php $this->render_profile_contacts(); ?>
			</div>
			<div class="pdxglobal_content">
				<?php if ( 'plugins' === $tab ) : ?>
					<h2><?php esc_html_e( 'WordPress plugins', 'utm-phone-switcher' ); ?></h2>
					<ul>
						<li><a href="https://wordpress.org/plugins/nohackme-defender/" target="_blank" rel="noreferrer noopener">NoHackMe Defender</a> — <?php esc_html_e( 'WordPress security plugin.', 'utm-phone-switcher' ); ?></li>
						<li><a href="https://needtome.com/utm-phone-switcher/" target="_blank" rel="noreferrer noopener">UTM Phone Switcher</a> — <?php esc_html_e( 'Campaign-based phone number replacement.', 'utm-phone-switcher' ); ?></li>
					</ul>
				<?php else : ?>
					<h2><?php esc_html_e( 'Website Development', 'utm-phone-switcher' ); ?></h2>
					<p><?php esc_html_e( 'Selected projects and related products:', 'utm-phone-switcher' ); ?></p>
					<h3><a href="https://kra.name/ru/" target="_blank" rel="noreferrer noopener">kra.name — <?php esc_html_e( 'AI Worker', 'utm-phone-switcher' ); ?></a></h3>
					<h3><a href="https://o-keto.com/en/" target="_blank" rel="noreferrer noopener">o-keto.com — <?php esc_html_e( 'Nutrition Platform', 'utm-phone-switcher' ); ?></a></h3>
					<p><?php esc_html_e( 'Related sections', 'utm-phone-switcher' ); ?>:</p>
					<ul>
						<li><a href="https://o-keto.com/en/recipes/" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Recipes', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://o-keto.com/en/products/" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Products', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://o-keto.com/en/nutrients/" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Nutrients', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://o-keto.com/en/quiz/" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Quizzes', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://o-keto.com/en/shop/" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Shop', 'utm-phone-switcher' ); ?></a></li>
					</ul>
					<h3><a href="https://protradejournal.net/" target="_blank" rel="noreferrer noopener">ProTradeJournal — <?php esc_html_e( 'Trading Platform', 'utm-phone-switcher' ); ?></a></h3>
					<p><?php esc_html_e( 'Related products', 'utm-phone-switcher' ); ?>:</p>
					<ul>
						<li><a href="https://protradejournal.net/" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Trading simulator and journal', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://protradejournal.net/trading" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Trading terminal', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://protradejournal.net/robots" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Trading robots', 'utm-phone-switcher' ); ?></a></li>
					</ul>
					<p><?php esc_html_e( 'More works can be seen in the WordPress and AI & automation resumes.', 'utm-phone-switcher' ); ?></p>
					<ul>
						<li><a href="https://docs.google.com/document/d/11w_tmtQbJ7ebcQHQi7jE9Y5WClfVHyMtxbR3-GW3b-s/edit?usp=sharing" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'WordPress resume', 'utm-phone-switcher' ); ?></a></li>
						<li><a href="https://docs.google.com/document/d/1tbHaxGPeN9je1qozgkQcX3djzaTrpR_MLvghjqMAuFM/edit?usp=sharing" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'AI & automation resume', 'utm-phone-switcher' ); ?></a></li>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render contact links used on the developer profile tabs.
	 *
	 * @return void
	 */
	private function render_profile_contacts() {
		$contacts = array(
			array(
				'label' => __( 'Email', 'utm-phone-switcher' ),
				'value' => 'paraz0n3@gmail.com',
				'url'   => 'mailto:paraz0n3@gmail.com',
			),
			array(
				'label' => __( 'LinkedIn', 'utm-phone-switcher' ),
				'value' => 'Roman Klymenko',
				'url'   => 'https://www.linkedin.com/in/roman-klymenko-parad0x/',
			),
			array(
				'label' => __( 'Viber', 'utm-phone-switcher' ),
				'value' => '+380935523587',
				'url'   => 'viber://chat?number=%2B380935523587',
			),
			array(
				'label' => __( 'Telegram', 'utm-phone-switcher' ),
				'value' => 'klymenko_roman',
				'url'   => 'https://t.me/klymenko_roman',
			),
		);

		foreach ( $contacts as $contact ) {
			printf(
				'<a href="%1$s" class="pdxglobal_contact-link"%2$s><strong>%3$s:</strong><br>%4$s</a>',
				esc_url( $contact['url'], array( 'http', 'https', 'mailto', 'viber' ) ),
				false !== strpos( $contact['url'], '://' ) ? ' target="_blank" rel="noopener noreferrer"' : '',
				esc_html( $contact['label'] ),
				esc_html( $contact['value'] )
			);
		}
	}

	/**
	 * Render the plugin support links.
	 *
	 * @return void
	 */
	private function render_support_box() {
		$links = array(
			array(
				'label'       => 'DonationAlerts',
				'url'         => 'https://www.donationalerts.com/r/needtome',
				'description' => __( 'My current donation page.', 'utm-phone-switcher' ),
			),
			array(
				'label'       => 'Patreon',
				'url'         => 'https://patreon.com/needtome',
				'description' => __( 'Best known for recurring support and membership tiers.', 'utm-phone-switcher' ),
			),
		);
		?>
		<div class="utm-phone-switcher-support-box">
			<div class="utm-phone-switcher-support-hero">
				<div class="utm-phone-switcher-support-visual">
					<svg class="utm-phone-switcher-support-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" aria-hidden="true" focusable="false"><path fill="#74c0fc" d="M320 48c-13 0-24 11-24 24v12h-2a66 66 0 0 0-8 132l61 8q8 1 9 10-1 9-10 10h-74a28 28 0 1 0 0 56h24v12a24 24 0 1 0 48 0v-12h2a66 66 0 0 0 8-132l-61-7q-8-2-9-11 1-9 10-10h66a28 28 0 1 0 0-56h-16V72c0-13-11-24-24-24M141 406l-42 42H64c-18 0-32 14-32 32v64c0 18 14 32 32 32h321q45 0 80-26l127-94a40 40 0 1 0-48-64l-119 88H312a24 24 0 1 1 0-48h72a32 32 0 1 0 0-64H232c-34 0-67 14-91 38"/></svg>
				</div>
				<div class="utm-phone-switcher-support-copy">
					<p class="utm-phone-switcher-support-title"><?php esc_html_e( 'Support the development of the plugin', 'utm-phone-switcher' ); ?></p>
					<p class="utm-phone-switcher-support-text"><?php esc_html_e( 'If UTM Phone Switcher helps you show the right phone number for each campaign, you can support its further development and maintenance via the services below.', 'utm-phone-switcher' ); ?></p>
				</div>
			</div>
			<ul>
				<?php foreach ( $links as $link ) : ?>
					<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a> — <?php echo esc_html( $link['description'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render shared attribution options.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return void
	 */
	private function render_general_settings( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable number replacement', 'utm-phone-switcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( 1, (int) $settings['enabled'] ); ?>>
						<?php esc_html_e( 'Apply enabled rules on the public site', 'utm-phone-switcher' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Administrator-only testing mode', 'utm-phone-switcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[admin_only]" value="1" <?php checked( 1, (int) $settings['admin_only'] ); ?>>
						<?php esc_html_e( 'Apply enabled rules only for logged-in administrators on the public site.', 'utm-phone-switcher' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Other visitors will see the original phone numbers.', 'utm-phone-switcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="utm-phone-switcher-duration"><?php esc_html_e( 'Remember the matching campaign', 'utm-phone-switcher' ); ?></label></th>
				<td>
					<input type="number" min="0" max="365" class="small-text" id="utm-phone-switcher-duration" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[duration_days]" value="<?php echo esc_attr( (string) $settings['duration_days'] ); ?>">
					<p class="description"><?php esc_html_e( 'Days. Use 0 to remember it only for the current browser session. The first-party cookie stores matching rule IDs, not phone numbers or visitor profiles.', 'utm-phone-switcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'utm-phone-switcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delete_data_on_uninstall]" value="1" <?php checked( 1, (int) $settings['delete_data_on_uninstall'] ); ?>>
						<?php esc_html_e( 'Delete plugin settings when the plugin is deleted', 'utm-phone-switcher' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render browser-specific selector instructions as accessible tooltips.
	 *
	 * @return void
	 */
	private function render_selector_help() {
		$browsers = array(
			'firefox' => array(
				'label'       => 'Firefox',
				'instructions' => __( 'Right-click the phone number and choose Inspect. In the Inspector, right-click the highlighted element and choose Copy → CSS Selector.', 'utm-phone-switcher' ),
			),
			'chrome'  => array(
				'label'       => 'Chrome',
				'instructions' => __( 'Right-click the phone number and choose Inspect. In the Elements panel, right-click the highlighted element and choose Copy → Copy selector.', 'utm-phone-switcher' ),
			),
			'edge'    => array(
				'label'       => 'Microsoft Edge',
				'instructions' => __( 'Right-click the phone number and choose Inspect. In the Elements panel, right-click the highlighted element and choose Copy → Copy selector.', 'utm-phone-switcher' ),
			),
			'safari'  => array(
				'label'       => 'Safari',
				'instructions' => __( 'Open Safari Web Inspector, select the phone element in the Elements tab, then use the selected node’s context menu to copy its selector.', 'utm-phone-switcher' ),
			),
		);
		?>
		<div class="utm-phone-switcher-selector-help">
			<p class="description"><?php esc_html_e( 'Find a CSS selector in your browser:', 'utm-phone-switcher' ); ?></p>
			<div class="utm-phone-switcher-browser-list">
				<?php foreach ( $browsers as $browser => $details ) : ?>
					<div class="utm-phone-switcher-browser-item">
						<button type="button" class="utm-phone-switcher-browser-toggle" aria-expanded="false" aria-controls="utm-phone-switcher-tooltip-<?php echo esc_attr( $browser ); ?>" aria-describedby="utm-phone-switcher-tooltip-<?php echo esc_attr( $browser ); ?>"><?php echo esc_html( $details['label'] ); ?></button>
						<div id="utm-phone-switcher-tooltip-<?php echo esc_attr( $browser ); ?>" class="utm-phone-switcher-browser-tooltip" role="tooltip" hidden>
							<?php echo esc_html( $details['instructions'] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one editable rule row.
	 *
	 * @param int|string          $index Row index.
	 * @param array<string,mixed> $rule Rule values.
	 * @return void
	 */
	private function render_rule_row( $index, $rule ) {
		$values = wp_parse_args(
			$rule,
			array(
				'id'              => '',
				'enabled'         => 1,
				'query_parameter' => '',
				'query_value'     => '',
				'selector'        => '',
				'phone'           => '',
			)
		);
		$base = self::OPTION_NAME . '[rules][' . $index . ']';
		?>
		<tr class="utm-phone-switcher-rule">
			<td>
				<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $values['id'] ); ?>">
				<input type="text" name="<?php echo esc_attr( $base . '[query_parameter]' ); ?>" value="<?php echo esc_attr( $values['query_parameter'] ); ?>" maxlength="100" placeholder="utm_source" aria-label="<?php esc_attr_e( 'URL parameter', 'utm-phone-switcher' ); ?>">
				<label class="utm-phone-switcher-rule-enabled"><input type="checkbox" name="<?php echo esc_attr( $base . '[enabled]' ); ?>" value="1" <?php checked( 1, (int) $values['enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'utm-phone-switcher' ); ?></label>
			</td>
			<td><input type="text" name="<?php echo esc_attr( $base . '[query_value]' ); ?>" value="<?php echo esc_attr( $values['query_value'] ); ?>" maxlength="200" placeholder="<?php esc_attr_e( 'Optional', 'utm-phone-switcher' ); ?>" aria-label="<?php esc_attr_e( 'Parameter value', 'utm-phone-switcher' ); ?>"></td>
			<td><input type="text" name="<?php echo esc_attr( $base . '[selector]' ); ?>" value="<?php echo esc_attr( $values['selector'] ); ?>" maxlength="300" placeholder=".header-phone" aria-label="<?php esc_attr_e( 'CSS selector', 'utm-phone-switcher' ); ?>"></td>
			<td><input type="tel" name="<?php echo esc_attr( $base . '[phone]' ); ?>" value="<?php echo esc_attr( $values['phone'] ); ?>" maxlength="80" placeholder="+1 555 010 1234" aria-label="<?php esc_attr_e( 'Replacement phone', 'utm-phone-switcher' ); ?>"></td>
			<td><button type="button" class="button-link-delete utm-phone-switcher-remove-rule"><?php esc_html_e( 'Remove', 'utm-phone-switcher' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * Load settings-page assets only on this plugin's screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		$admin_css_file = plugin_dir_path( UTM_PHONE_SWITCHER_FILE ) . 'assets/css/admin.css';
		$admin_js_file  = plugin_dir_path( UTM_PHONE_SWITCHER_FILE ) . 'assets/js/admin.js';
		$admin_css_ver  = is_file( $admin_css_file ) ? (string) filemtime( $admin_css_file ) : UTM_PHONE_SWITCHER_VERSION;
		$admin_js_ver   = is_file( $admin_js_file ) ? (string) filemtime( $admin_js_file ) : UTM_PHONE_SWITCHER_VERSION;

		wp_enqueue_style(
			'utm-phone-switcher-admin',
			plugins_url( 'assets/css/admin.css', UTM_PHONE_SWITCHER_FILE ),
			array(),
			$admin_css_ver
		);
		wp_enqueue_script(
			'utm-phone-switcher-admin',
			plugins_url( 'assets/js/admin.js', UTM_PHONE_SWITCHER_FILE ),
			array(),
			$admin_js_ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueue the small front-end script when there is active rule data.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( ! empty( $settings['admin_only'] ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rules = array();
		foreach ( $settings['rules'] as $rule ) {
			if ( empty( $rule['enabled'] ) || '' === $rule['query_parameter'] || '' === $rule['selector'] || '' === $rule['phone'] ) {
				continue;
			}
			$rules[] = array(
				'id'              => $rule['id'],
				'query_parameter' => $rule['query_parameter'],
				'query_value'     => $rule['query_value'],
				'selector'        => $rule['selector'],
				'phone'           => $rule['phone'],
			);
		}

		if ( empty( $rules ) ) {
			return;
		}

		wp_enqueue_script(
			'utm-phone-switcher',
			plugins_url( 'assets/js/frontend.js', UTM_PHONE_SWITCHER_FILE ),
			array(),
			UTM_PHONE_SWITCHER_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'cookie_name'   => self::COOKIE_NAME,
			'legacy_cookie_name' => self::LEGACY_COOKIE_NAME,
			'duration_days' => (int) $settings['duration_days'],
			'rules'         => $rules,
		);
		$inline = 'window.UTMPhoneSwitcher = ' . wp_json_encode(
			$config,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		) . ';';

		wp_add_inline_script( 'utm-phone-switcher', $inline, 'before' );
	}

	/**
	 * Load and normalize saved settings, migrating older settings if needed.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_settings() {
		$settings = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $settings ) ) {
			$settings = self::defaults();
		}

		return self::sanitize_settings( array_merge( self::defaults(), $settings ) );
	}
}
