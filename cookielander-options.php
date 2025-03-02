<?php
class CookielanderOptions {
	// initially generated via http://wpsettingsapi.jeroensormani.com/

	const X = 'wordpress';

	#region ------------- customization --------------

	var $title = 'Cookielander - Landing-page to Cookie';
	var $menu_title = 'Cookielander';

	/**
	 * Who can use it
	 */
	var $capability = 'manage_options'; // general, manage_options

	/**
	 * Namespace
	 */
	const N = 'cookielander';

	#endregion ------------- customization --------------


	#region ------- fields etc ----------

	const F_RAW = 'json';
	const F_EXPIRES = 'expires';
	const F_301 = 'r301';
	
	function add_settings($page) {
		add_action( 'admin_enqueue_scripts', array(&$this, 'scripts') );
		
		$section = implode('_', array(static::N, static::N/**/, 'section'));
			
		add_settings_section(
			$section,
			__( 'Configuration', static::X ), 
			array(&$this, 'section'), 
			$page
		);

		add_settings_field( 
			self::F_EXPIRES, 
			__( 'Default Cookie Expiration', static::X ), 
			array(&$this, 'render_expires'), 
			$page, 
			$section 
		);

		add_settings_field( 
			self::F_RAW, 
			__( 'Raw JSON', static::X ), 
			array(&$this, 'render_raw'), 
			$page, 
			$section 
		);

		add_settings_field(
			self::F_301,
			__( 'Redirect?', static::X ),
			array(&$this, 'render_redirect'),
			$page,
			$section
		);

	}
	
	function scripts() {
		// don't use latest, may break compatibility with decorator (//cdn.ractivejs.org/latest/ractive.js)
		wp_enqueue_script( 'ractive', '//cdnjs.cloudflare.com/ajax/libs/ractive/0.7.3/ractive.min.js', null, '0.8', false );
		wp_enqueue_script( 'ractive-addable', plugins_url('/Ractive-decorators-addable.js', __FILE__), array('ractive'), '1.0', false );
		wp_enqueue_script( static::N, plugins_url('/cookielander.js', __FILE__), array('ractive', 'ractive-addable'), '1.0', false );
		wp_localize_script( static::N, 'cookielander', array(
			'n' => static::N,
			'keyRaw' => self::F_RAW,
			'dataSrc' => sprintf('[name="%s[%s]"]', static::N, self::F_RAW),
		));
	}


	function section(  ) {
		include('cookielander-ui.php');
		?>
		<script>
			(function($) {
				$(function() {
					cookielander.editor = cookielander.init(cookielander);
				});
			})(jQuery);
		</script>
		<?php
	}

	function render_raw(  ) {
		// dump all the setings out as JSON

		$field = self::F_RAW;
		$options = self::settings();

		// TODO: codemirror...
		?>
		<textarea class='large-text code' rows='10' name='<?php echo static::N, '[', $field ?>]'><?php echo esc_html(json_encode($this->exist($options, $field, array()), JSON_PRETTY_PRINT)); ?></textarea>
		<?php
	}

	function render_expires(  ) {
		$this->renderInput(self::F_EXPIRES, 7 * DAY_IN_SECONDS);
		echo '<em>seconds</em>';
	}

	function render_redirect(  ) {
		// dump all the setings out as JSON

		$field = self::F_301;
		$options = self::settings();

		?>
		<input type="checkbox" name='<?php echo static::N, '[', $field ?>]' value="1" <?php checked($options[$field], 1) ?> />
		<?php
	}


	function sanitize($val) {
		### _log('sanitizing ' . static::N . '.' . self::F_RAW, $val);

		// pull out json and turn into array
		$val[self::F_RAW] = json_decode($val[self::F_RAW], true);

		### _log('sanitized ' . static::N . '.' . self::F_RAW, $val);

		// okay?
		$error = json_last_error();
		if($error === JSON_ERROR_NONE) return $val;

		add_settings_error(
			// setting name
			static::N,
			// html id
			static::N,
			// what went wrong
			sprintf(__('Invalid JSON (code %s)', static::X), $error),
			// css class: error, updated
			'error'
		);
		$this->failed();

		return $val;
	}

	#endregion ------- fields etc ----------






	#region ------------- settings, singleton --------------

	public static function settings($key = null) {
		$settings = get_option( static::N );
		if(null == $key) return $settings;
		return isset($settings[$key]) ? $settings[$key] : null;
	}

	private static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @see http://www.phptherightway.com/pages/Design-Patterns.html#singleton
	 * @return Singleton The *Singleton* instance.
	 */
	public static function instance($root = null) {
		if (null === static::$instance) {
			static::$instance = new static(empty($root) ? __FILE__ : $root);
			//static::$instance->root = empty($root) ? __FILE__ : $root;
		}

		return static::$instance;
	}
	#endregion ------------- settings, singleton --------------

	/**
	 * Plugin options 'root' path (<c>__FILE__</c>), used to create plugin listing settings link
	 */
	var $root;

	/**
	 * Create a new instance of the plugin; consider also the singleton <c>::instance($root)</c> instead
	 * @remarks technically should be `private`
	 */
	public function __construct($root) {
		if(!is_admin()) return;
		
		$this->root = $root;

		// TODO: multisite? https://codex.wordpress.org/Creating_Options_Pages#Pitfalls
		add_action( 'admin_menu', array(&$this, 'add_admin_menu') );
		add_action( 'admin_init', array(&$this, 'settings_init') );
	}

	function add_admin_menu(  ) { 
		add_options_page(
			// page title
			$this->title,
			// menu title
			$this->menu_title,
			// access
			$this->capability,
			// namespace/option
			static::N,
			// callback
			array(&$this, 'options_page')
		);

		//add plugin entry settings link
		add_filter( 'plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );
	}//--	add_admin_menu

	/**
	 * HOOK - Add the "Settings" link to the plugin list entry
	 * @param $links
	 * @param $file
	 */
	function plugin_action_links( $links, $file ) {
		if ( $file != plugin_basename($this->root ) )
			return $links;
	
		$url = esc_url_raw(admin_url('options-general.php?page=' . static::N));
	
		$settings_link = '<a title="Capability ' . $this->capability . ' required" href="' . esc_attr( $url ) . '">'
			. esc_html( __( 'Settings', static::X ) ) . '</a>';
	
		array_unshift( $links, $settings_link );
	
		return $links;
	}


	function settings_init(  ) { 
		register_setting( static::N/**/, static::N, array(&$this, 'sanitize') );

		$this->add_settings(static::N/**/);
	}//--	settings_init


	function options_page(  ) { 

		?>
		<form action='options.php' method='post'>
			
			<h1><?php _e($this->title, static::X) ?></h1>
			
			<?php
			settings_fields( static::N/**/ );
			do_settings_sections( static::N/**/ );
			submit_button();
			?>
			
		</form>
		<?php

	}

	function failed() {
		add_action( 'admin_notices', array(&$this, 'option_failed') );
	}
	function option_failed() {
		settings_errors(static::N);
	}




	protected function renderInput($field, $default = '') {
		$options = self::settings();
		?>
		<input type='text' class='regular-text' name='<?php echo static::N, '[', $field ?>]' value='<?php echo esc_attr( $this->exist($options, $field, $default) ); ?>'>
		<?php
	}
	protected function renderText($field, $default = '') {
		$options = self::settings();

		// TODO: codemirror...
		?>
		<textarea class='large-text code' rows='10' name='<?php echo static::N, '[', $field ?>]'><?php echo esc_html( $this->exist($options, $field, $default) ); ?></textarea>
		<?php
	}
	
	protected function exist($source, $key, $default = null) {
		return isset($source[$key]) ? $source[$key] : $default;
	}


}//---	CookielanderOptions


