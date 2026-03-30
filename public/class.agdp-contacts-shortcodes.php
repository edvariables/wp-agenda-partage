<?php

/**
 * AgendaPartage -> Contact -> Shortcodes
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 */
class Agdp_Contacts_Shortcodes extends Agdp_Shortcodes {

	const post_type = Agdp_Contact::post_type;
	
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
		parent::add_shortcodes('agdpcontacts');

	}
	
	/**
	* [contacts]
	* [contacts liste|list|calendar|calendrier|week|semaine|ics]
	* [contacts mode:liste|list|calendar|calendrier|week|semaine|ics]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){	
		if(array_key_exists('info', $atts)
		&& in_array($atts['info'], self::info_shortcodes))
			$shortcode .= '-' . $atts['info'];
		if(array_key_exists('mode', $atts)
		&& in_array($atts['mode'], self::info_shortcodes))
			$shortcode .= '-' . $atts['mode'];
		
		switch($shortcode){
			case 'agdpcontacts-liste':
				$shortcode = 'contacts-list';
			case 'agdpcontacts-list':
				
				return Agdp_Contacts::get_list_html( $content );
				
			case 'agdpcontacts-email':
				
				$html = Agdp_Contacts::get_list_for_email( $content );
				return $html;

			default:
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	// shortcodes //
	///////////////
}
