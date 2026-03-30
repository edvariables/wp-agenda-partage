<?php

/**
 * AgendaPartage -> Covoiturage -> Shortcodes
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 */
class Agdp_Covoiturages_Shortcodes extends Agdp_Shortcodes {

	const post_type = Agdp_Covoiturage::post_type;
	
	const default_attr = 'post_id';
	
	const info_shortcodes = [ 
		'list',
		'liste',
		'email',
	];

	private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::$initiated = true;
			
			parent::init();
		}
	}

	/////////////////
 	// shortcodes //
 	/**
 	 * add_shortcodes
 	 */
	public static function add_shortcodes($shortcodes = false){
		parent::add_shortcodes('covoiturages');

	}
	
	/**
	* [covoiturages]
	* [covoiturages liste|list|calendar|calendrier|week|semaine|ics]
	* [covoiturages mode:liste|list|calendar|calendrier|week|semaine|ics]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){	
		if(array_key_exists('info', $atts)
		&& in_array($atts['info'], self::info_shortcodes))
			$shortcode .= '-' . $atts['info'];
		if(array_key_exists('mode', $atts)
		&& in_array($atts['mode'], self::info_shortcodes))
			$shortcode .= '-' . $atts['mode'];
		
		switch($shortcode){
			case 'covoiturages-liste':
				$shortcode = 'covoiturages-list';
			case 'covoiturages-list':
				
				return Agdp_Covoiturages::get_list_html( $content );
				
			case 'covoiturages-email':
				
				$html = Agdp_Covoiturages::get_list_for_email( $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	// shortcodes //
	///////////////
}
