<?php

/**
 * AgendaPartage -> Post
 * 
 * Définition des shortcodes 
 *
 *	
 */
class Agdp_Post_Shortcodes extends Agdp_Shortcodes {
	
	private static $initiated = false;
	
	public static $report_stack = [];

	public static function init() {
		if ( ! self::$initiated ) {
			
			self::$initiated = true;
			
			parent::init();

			static::add_shortcodes();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		
		add_action( 'wp_ajax_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_shortcode_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_shortcode', array(__CLASS__, 'on_wp_ajax_shortcode_cb') );
		
		add_filter('wpcf7_mail_components', array(__CLASS__, 'on_wpcf7_mail_components'), 10, 3);
		
		parent::init_hooks();
	}
	
	/**
 	 * add_shortcodes
 	 */
	public static function add_shortcodes( $shortcodes = false ){
		
		add_shortcode( 'agdpstats', array(__CLASS__, 'shortcodes_agdpstats_callback') );
		
		parent::add_shortcodes('post');
	}
	
	/**
	* [post]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){
		
		$post = get_post();
		if(!$post){
			echo $content;
			return;
		}

		if( ! is_array($atts)){
			$atts = array();
		}

		if(! array_key_exists('info', $atts)
		|| ! ($info = $atts['info']))
			$info = 'post_title';

		switch($info){
			case 'uri':
			case 'url':
				return $_SERVER['HTTP_REFERER'];
			case 'link':
			case 'a':
				return sprintf('<a href="%s">%s</a>', Agdp_Event::get_post_permalink($post), $post->post_title);

			case 'mailto':
				$email = get_post_meta( $post->ID, 'ev-email', true);
				return sprintf('<a href="mailto:%s">%s</a>', antispambot(sanitize_email($email)), $post->post_title);//TODO anti-spam

			case 'dump':
				return sprintf('<pre>%s</pre>', 'shortcodes dump : ' . var_export($post, true));

			case 'title':
				$info = 'post_title';

			default :
				if(isset($post->$info))
					return $post->$info;
				return get_post_meta( $post->ID, $info, true);

		}
	}
	
	public static function shortcodes_agdpstats_callback($atts, $content = '', $shortcode = null){
		require_once(AGDP_PLUGIN_DIR . '/admin/class.agdp-admin-stats.php');
		if( count($atts)) {
			if( in_array('postscounters', $atts) )
				return Agdp_Admin_Stats::posts_stats_counters() . $content;
			if( in_array('eventscounters', $atts) )
				return Agdp_Admin_Stats::agdpevents_stats_counters() . $content;
			if( in_array('covoituragescounters', $atts) )
				return Agdp_Admin_Stats::covoiturages_stats_counters() . $content;
		}
		return Agdp_Admin_Stats::get_stats_result() . $content;
	}
	
	/**
	 * Hook wp_ajax_shortcode
	 * Get code secret from Ajax query, redirect to post url
	 */
	public static function on_wp_ajax_shortcode_cb() {
		$ajax_response = '';
		$data = $_POST['data'];
		if($data){ 
			if( is_string( $data ) )
				$data  = str_replace('\\"', '"', wp_specialchars_decode( $data ));
			
			$ajax_response = do_shortcode( $data );
			
		}
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/**
	 * Define the wpcf7_mail_components callback 
	 */
	public static function on_wpcf7_mail_components( $components, $wpcf7_get_current_contact_form, $instance ){ 
		$components['body'] = do_shortcode($components['body']);
		return $components;
	} 
	
}
