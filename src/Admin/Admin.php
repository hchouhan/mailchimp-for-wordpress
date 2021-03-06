<?php

class MC4WP_Admin {

	/**
	 * @var bool True if the BWS Captcha plugin is activated.
	 */
	protected $has_captcha_plugin = false;

	/**
	 * @var string The relative path to the main plugin file from the plugins dir
	 */
	protected $plugin_slug;

	/**
	 * Constructor
	 * @param string $plugin_file
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_slug = plugin_basename( $plugin_file );

		// store whether this plugin has the BWS captcha plugin running (https://wordpress.org/plugins/captcha/)
		$this->has_captcha_plugin = function_exists( 'cptch_display_captcha_custom' );
	}

	/**
	 * Registers all hooks
	 */
	public function add_hooks() {

		global $pagenow;
		$current_page = isset( $pagenow ) ? $pagenow : '';

		// Actions used globally throughout WP Admin
		add_action( 'admin_init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'build_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		// Hooks for Plugins overview page
		if( $current_page === 'plugins.php' ) {
			add_filter( 'plugin_action_links_' . $this->plugin_slug, array( $this, 'add_plugin_settings_link' ), 10, 2 );
			add_filter( 'plugin_row_meta', array( $this, 'add_plugin_meta_links'), 10, 2 );
		}

	}

	/**
	 * Load the plugin translations
	 */
	public function load_translations() {
		// load the plugin text domain
		return load_plugin_textdomain( 'mailchimp-for-wp', false, dirname( MC4WP_PLUGIN_FILE ) . '/languages' );
	}

	/**
	 * Initializes various stuff used in WP Admin
	 *
	 * - Registers settings
	 * - Checks if the Captcha plugin is activated
	 * - Loads the plugin text domain
	 */
	public function init() {
		$this->register_settings();
		$this->load_upgrade_routine();
	}

	protected function register_settings() {
		register_setting( 'mc4wp_settings', 'mc4wp', array( $this, 'validate_settings' ) );
		register_setting( 'mc4wp_integrations_settings', 'mc4wp_integrations', array( $this, 'validate_settings' ) );
		register_setting( 'mc4wp_form_settings', 'mc4wp_form', array( $this, 'validate_settings' ) );
	}

	/**
	 * Upgrade routine
	 */
	protected function load_upgrade_routine() {

		// Only run if db option is at older version than code constant
		$db_version = get_option( 'mc4wp_version', 0 );

		// Option not found.
		// Either plugin is installed for first time or coming from pre-3.0 lite version
		if( ! $db_version ) {

			/**
			 * Upgrade routine for the upgrade routine..... (mc4wp_lite_version => mc4wp_version)
			 *
			 * @since 3.0
			 */
			$db_version = get_option( 'mc4wp_lite_version', 0 );
			if( $db_version ) {
				delete_option( 'mc4wp_lite_version' );
			}
		}

		if( version_compare( MC4WP_VERSION, $db_version, '<=' ) ) {
			return false;
		}

		$upgrader = new MC4WP_Upgrade_Routine( MC4WP_VERSION, $db_version );
		$upgrader->run();
	}

	/**
	 * Add the settings link to the Plugins overview
	 *
	 * @param array $links
	 * @param       $slug
	 *
	 * @return array
	 */
	public function add_plugin_settings_link( $links, $slug ) {
		if( $slug !== $this->plugin_slug ) {
			return $links;
		}

		 $settings_link = '<a href="' . admin_url( 'admin.php?page=mailchimp-for-wp' ) . '">'. __( 'Settings', 'mailchimp-for-wp' ) . '</a>';
		 array_unshift( $links, $settings_link );
		 return $links;
	}

	/**
	 * Adds meta links to the plugin in the WP Admin > Plugins screen
	 *
	 * @param array $links
	 * @param string $slug
	 *
	 * @return array
	 */
	public function add_plugin_meta_links( $links, $slug ) {
		if( $slug !== $this->plugin_slug ) {
			return $links;
		}

		$links[] = '<a href="https://mc4wp.com/kb/#utm_source=wp-plugin&utm_medium=mailchimp-for-wp&utm_campaign=plugins-page">' . __( 'Documentation', 'mailchimp-for-wp' ) . '</a>';
		return $links;
	}

	/**
	* Register the setting pages and their menu items
		*/
	public function build_menu() {

		/**
		 * @filter mc4wp_settings_cap
		 * @expects     string      A valid WP capability like 'manage_options' (default)
		 *
		 * Use to customize the required user capability to access the MC4WP settings pages
		 */
		$required_cap = apply_filters( 'mc4wp_settings_cap', 'manage_options' );

		$menu_items = array(
			'general' => array(
				'title' => __( 'MailChimp API Settings', 'mailchimp-for-wp' ),
				'text' => __( 'MailChimp', 'mailchimp-for-wp' ),
				'slug' => '',
				'callback' => array( $this, 'show_api_settings' ),
			),
			'forms' => array(
				'title' => __( 'Form Settings', 'mailchimp-for-wp' ),
				'text' => __( 'Forms', 'mailchimp-for-wp' ),
				'slug' => 'form-settings',
				'callback' => array( $this, 'show_form_settings' )
			),
			'integrations' => array(
				'title' => __( 'Integration Settings', 'mailchimp-for-wp' ),
				'text' => __( 'Integrations', 'mailchimp-for-wp' ),
				'slug' => 'integration-settings',
				'callback' => array( $this, 'show_integration_settings' ),
			),
		);

		/**
		 * @api
		 * @filter 'mc4wp_menu_items'
		 * @expects array
		 */
		$menu_items = apply_filters( 'mc4wp_menu_items', $menu_items );

		// add top menu item
		add_menu_page( 'MailChimp for WP', 'MailChimp for WP', $required_cap, 'mailchimp-for-wp', array( $this, 'show_api_settings' ), MC4WP_PLUGIN_URL . 'assets/img/menu-icon.png', '99.68491' );

		// add submenu pages
		foreach( $menu_items as $item ) {
			$slug = ( '' !== $item['slug'] ) ? "mailchimp-for-wp-{$item['slug']}" : 'mailchimp-for-wp';
			add_submenu_page( 'mailchimp-for-wp', $item['title'] . ' - MailChimp for WordPress Lite', $item['text'], $required_cap, $slug, $item['callback'] );
		}

	}


	/**
	* Validates the General settings
	*
	* @param array $settings
	* @return array
	*/
	public function validate_settings( array $settings ) {

		// sanitize simple text fields (no HTML, just chars & numbers)
		$simple_text_fields = array( 'api_key', 'redirect', 'css' );
		foreach( $simple_text_fields as $field ) {
			if( isset( $settings[ $field ] ) ) {
				$settings[ $field ] = sanitize_text_field( $settings[ $field ] );
			}
		}

		// validate woocommerce checkbox position
		if( isset( $settings['woocommerce_position'] ) ) {
			// make sure position is either 'order' or 'billing'
			if( ! in_array( $settings['woocommerce_position'], array( 'order', 'billing' ) ) ) {
				$settings['woocommerce_position'] = 'billing';
			}
		}

		// dynamic sanitization
		foreach( $settings as $setting => $value ) {
			// strip special tags from text settings
			if( substr( $setting, 0, 5 ) === 'text_' || $setting === 'label' ) {
				$value = trim( $value );
				$value = strip_tags( $value, '<a><b><strong><em><i><br><u><script><span><abbr><strike>' );
				$settings[ $setting ] = $value;
			}
		}

		// strip <form> from form mark-up
		if( isset( $settings[ 'markup'] ) ) {
			$settings[ 'markup' ] = preg_replace( '/<\/?form(.|\s)*?>/i', '', $settings[ 'markup'] );
		}

		return $settings;
	}

	/**
	 * Load scripts and stylesheet on MailChimp for WP Admin pages
	 * @return bool
	*/
	public function assets() {

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// register scripts which are also used by add-on plugins
		wp_register_style( 'mc4wp-admin', MC4WP_PLUGIN_URL . 'assets/css/admin' . $suffix . '.css' );
		wp_register_script( 'mc4wp-beautifyhtml', MC4WP_PLUGIN_URL . 'assets/js/third-party/beautify-html'. $suffix .'.js', array( 'jquery' ), MC4WP_VERSION, true );
		wp_register_script( 'mc4wp-form-helper', MC4WP_PLUGIN_URL . 'assets/js/form-helper' . $suffix . '.js', array( 'jquery', 'mc4wp-beautifyhtml' ), MC4WP_VERSION, true );
		wp_register_script( 'mc4wp-admin', MC4WP_PLUGIN_URL . 'assets/js/admin' . $suffix . '.js', array( 'jquery', 'quicktags', 'mc4wp-form-helper' ), MC4WP_VERSION, true );

		// only load asset files on the MailChimp for WordPress settings pages
		if( strpos( $this->get_current_page(), 'mailchimp-for-wp' ) === 0 ) {
			$strings = include MC4WP_PLUGIN_DIR . 'config/js-strings.php';

			// css
			wp_enqueue_style( 'mc4wp-admin' );

			// js
			wp_enqueue_script( 'mc4wp-admin' );
			wp_localize_script( 'mc4wp-admin', 'mc4wp',
				array(
					'hasCaptchaPlugin' => $this->has_captcha_plugin,
					'strings' => $strings,
					'mailchimpLists' => MC4WP_MailChimp_Tools::get_lists()
				)
			);

			return true;
		}

		return false;
	}

	/**
	* Show the API settings page
	*/
	public function show_api_settings() {
		$opts = mc4wp()->options;
		$connected = ( mc4wp()->get_api()->is_connected() );

		// cache renewal triggered manually?
		$force_cache_refresh = isset( $_REQUEST['mc4wp-renew-cache'] ) && $_REQUEST['mc4wp-renew-cache'] == 1;
		$lists = MC4WP_MailChimp_Tools::get_lists( $force_cache_refresh );

		// show notice if 100 lists were fetched
		if( $lists && count( $lists ) >= 100 ) {
			add_settings_error( 'mc4wp', 'mc4wp-lists-at-limit', __( 'The plugin can only fetch a maximum of 100 lists from MailChimp, only your first 100 lists are shown.', 'mailchimp-for-wp' ) );
		}

		if ( $force_cache_refresh ) {
			if ( false === empty ( $lists ) ) {
				add_settings_error( 'mc4wp', 'mc4wp-cache-success', __( 'Renewed MailChimp cache.', 'mailchimp-for-wp' ), 'updated' );
			} else {
				add_settings_error( 'mc4wp', 'mc4wp-cache-error', __( 'Failed to renew MailChimp cache - please try again later.', 'mailchimp-for-wp' ) );
			}
		}

		require MC4WP_PLUGIN_DIR . 'src/views/general-settings.php';
	}

	/**
	* Show the Checkbox settings page
	*/
	public function show_integration_settings() {
		$integrations = mc4wp()->integrations;
		$opts = $integrations->options;
		$lists = MC4WP_MailChimp_Tools::get_lists();
		$current_tab = ( isset( $_GET['tab'] ) ) ? $_GET['tab'] : 'general';
		require MC4WP_PLUGIN_DIR . 'src/views/integration-settings.php';
	}

	/**
	 * @param $type
	 * @param $name
	 */
	public function show_integration_specific_settings( $type, $name ) {
		$integrations = mc4wp()->integrations;
		$opts = $integrations->get_integration_options( $type, false );
		$inherited = $integrations->get_integration_options( $type );
		$lists = MC4WP_MailChimp_Tools::get_lists();
		include MC4WP_PLUGIN_DIR . 'src/views/parts/integration-specific-settings.php';
	}

	/**
	* Show the forms settings page
	*/
	public function show_form_settings() {
		$opts = mc4wp()->forms->options;
		$lists = MC4WP_MailChimp_Tools::get_lists();

		require MC4WP_PLUGIN_DIR . 'src/views/form-settings.php';
	}

	/**
	 * @return string
	 */
	protected function get_current_page() {
		return isset( $_GET['page'] ) ? $_GET['page'] : '';
	}

}