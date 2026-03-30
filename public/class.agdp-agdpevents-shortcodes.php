<?php

/**
 * AgendaPartage -> Évènements
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 *
 */
class Agdp_Events_Shortcodes extends Agdp_Shortcodes {

	const post_type = Agdp_Event::post_type;
	
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
		parent::add_shortcodes('agdpevents');

	}
	
	/**
	* [agdpevents]
	* [agdpevents liste|list|calendar|calendrier|week|semaine|ics]
	* [agdpevents mode:liste|list|calendar|calendrier|week|semaine|ics]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){	
		
		if(array_key_exists('info', $atts)
		&& in_array($atts['info'], self::info_shortcodes))
			$shortcode .= '-' . $atts['info'];
		if(array_key_exists('mode', $atts))
			$shortcode .= '-' . $atts['mode'];

		switch($shortcode){
			case 'agdpevents-liste':
				$shortcode = 'agdpevents-list';
			case 'agdpevents-list':
				
				return Agdp_Events::get_list_html( $content );
				
			case 'agdpevents-email':
				
				$html = Agdp_Events::get_list_for_email( $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	// shortcodes //
	///////////////
}
