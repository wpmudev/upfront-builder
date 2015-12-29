<?php
if ((defined('UF_THX_TMP_SWITCH') && UF_THX_TMP_SWITCH)) {

/**
 * Builder virtual subpage abstraction.
 * All actual endpoints (for creating/editing a theme) inherit from this.
 */
abstract class Thx_VirtualSubpage extends Upfront_VirtualSubpage {

	const INITIAL_SLUG = 'theme';
	const INITIAL_STYLESHEET = 'upfront';

	protected $_stylesheet;
	protected $_slug;

	public function __construct () {
		$this->_slug = self::INITIAL_SLUG;
		$this->_stylesheet = self::INITIAL_STYLESHEET;
	}

	public function parse ($request) {
		upfront_switch_stylesheet($this->_stylesheet);
		add_filter('upfront-storage-key', array($this, 'storage_key_filter'));
		add_filter('upfront-data-storage-key', array($this, 'storage_key_filter'));
		add_filter('upfront-enable-dev-saving', '__return_false');
		query_posts('');
	}

	public function get_slug () {
		return $this->_slug;
 	}

	public function storage_key_filter ($key) {
		return $key . '_new';
	}

	public function start_editor () {
		upfront_exporter_clear_conversion_cache($this->get_slug());
		echo upfront_boot_editor_trigger('theme');
	}
}

/**
 * Initial page.
 */
class Upfront_Thx_InitialPage_VirtualSubpage extends Thx_VirtualSubpage {

	public function render ($request) {
		$this->parse($request);
		$tpl = Thx_Template::plugin();

		wp_enqueue_style('initial_page', $tpl->url('css/initial_page.css'));
		wp_enqueue_style('create_edit', $tpl->url('css/create_edit.css'));
		
		wp_enqueue_script('create_edit', $tpl->url('js/create_edit.js'), array('jquery'));

		load_template($tpl->path('initial_page'));
		die;
	}

}


/**
 * Individual upfront theme editing page.
 */
class Upfront_Thx_ThemePage_VirtualSubpage extends Thx_VirtualSubpage {

	public function __construct ($stylesheet) {
		$this->_slug = $stylesheet;
		$this->_stylesheet = $stylesheet;
	}


 	public function render ($request) {
 		$this->parse($request);
 		add_action('wp_footer', array($this, 'start_editor'), 999);
 		load_template(get_home_template());
 		die;
 	}

	public function storage_key_filter ($key) {
		return $this->_stylesheet;
	}

}

/**
 * Main interface.
 */
class Upfront_Thx_Builder_VirtualPage extends Upfront_VirtualPage {

	const SLUG = 'create_new';
	
	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}
	
	public function get_slug () { 
		return self::SLUG; 
	}

	protected function _add_subpages () {
		$subpages = array(
			new Upfront_Thx_InitialPage_VirtualSubpage(),
		);

		// Add all Upfront child themes
		// TODO add grandchild themes
		foreach(wp_get_themes() as $stylesheet=>$theme) {
			if ($theme->get('Template') !== 'upfront') continue;
			$subpages[] =  new Upfront_Thx_ThemePage_VirtualSubpage($stylesheet);
		}
		$this->_subpages = $subpages;
	}


	public function intercept_page () {
		if (!Upfront_Permissions::current(Upfront_Permissions::BOOT)) {
			$this->redirect('/');
		}
		parent::intercept_page();
	}

	public function parse ($request) {}
	
	/**
	 * Redirect to initial subpage, if we don't have a better request.
	 */
	public function render ($request) {
		$req = join('/', array(
			$this->get_slug(),
			Thx_VirtualSubpage::INITIAL_SLUG
		));
		wp_redirect($this->get_url($req));
		die;
	}

}
}