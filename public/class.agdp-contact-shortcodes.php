<?php

/**
 * AgendaPartage -> Contact -> Shortcodes
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 */
class Agdp_Contact_Shortcodes extends Agdp_Shortcodes {

	const post_type = Agdp_Contact::post_type;
	
	const default_attr = 'post_id';
	
	const info_shortcodes = [ 
		'titre',
		'description',
		'webpage',
		'cree-depuis',
		'modifier-contact',
		'horaires',
		'categories',
		'cities',
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
	* [contact info=titre|description|dates|localisation|details|message-contact|modifier-contact|created_since]
	* [contact-titre]
	* [contact-description]
	* [contact-dates]
	* [contact-localisation]
	* [contact-details]
	* [contact-message-contact]
	* [contact-avec-email]
	* [contact-modifier-contact]
	* [contact-cree-depuis]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){
		
		$post = Agdp_Contact::get_post();
		
		if($post)
			$post_id = $post->ID;
		
		$label = isset($atts['label']) ? $atts['label'] : '' ;
				
		$html = '';
		
		foreach($atts as $key=>$value){
			if(is_numeric($key)){
				$atts[$value] = true;
				if($key != '0')
					unset($atts[$key]);
			}
		}
		
		if(array_key_exists('info', $atts)
		&& in_array($atts['info'], self::info_shortcodes))
			$shortcode .= '-' . $atts['info'];
					
		$no_html = isset($atts['no-html']) && $atts['no-html']
				|| isset($atts['html']) && $atts['html'] == 'no';
		
		switch($shortcode){
			case 'agdpcontact-titre':

				$val = isset( $post->post_title ) ? $post->post_title : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-contact agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpcontact-description':

				$val = isset( $post->post_content ) ? $post->post_content : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-contact agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpcontact-horaires':

				$val = Agdp_Contact::get_contact_horaires_text( $post_id );
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-contact agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpcontact-cree-depuis':

				$val = date_diff_text($post->post_date);
				
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-contact agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'agdpcontact-categories':
				$tax_name = Agdp_Contact::taxonomy_diffusion;
			case 'agdpcontact-cities':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = Agdp_Contact::taxonomy_city;
				
				$terms = Agdp_Contact::get_post_terms( $tax_name, $post_id, 'names');
				if($terms){
					$val = implode(', ', $terms);
					if($no_html)
						$html = $val;
					else{
						$html = '<div class="agdp-contact agdp-'. $shortcode .'">'
							. ($label ? '<span class="label"> '.$label.'<span>' : '')
							. htmlentities($val)
							. '</div>';
					}
				}
				return $html;

			case 'agdpcontact-message-contact':
				
				$meta_name = 'cont-contact' ;
				$organisateur = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
				if( ! $organisateur) {
					return;
				}

				$meta_name = 'cont-email' ;
				$email = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
				if(!$email) {
					return Agdp::icon('warning'
						, 'Vous ne pouvez pas envoyer de message, le contact n\'a pas d\'adresse email associé.', 'agdp-error-light', 'div');
				}

				$form_id = Agdp::get_option('admin_message_contact_form_id');
				if(!$form_id){
					return Agdp::icon('warning'
						, 'Un formulaire de message aux organisteurs du contact n\'est pas défini dans les réglages de AgendaPartage.', 'agdp-error-light', 'div');
				}

				$val = sprintf('[contact-form-7 id="%s" title="*** message au contact ***"]', $form_id);
				return '<div class="agdp-contact agdp-'. $shortcode .'">'
					. do_shortcode( $val)
					. '</div>';


			case 'agdpcontact-webpage':
			
				$meta_name = 'cont-webpage'; 
				$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, true);
				
				if($val){
					return sprintf('%s<a href="%s" target="_blank">%s</a>'
						, ($label ? '<span class="label"> '.$label.'<span>' : '')
						, esc_html($val)
						, $val
					);
				}
				break;

			case 'agdpcontact-modifier-contact':

				return Agdp_Contact_Edit::get_contact_edit_content();

			case 'agdpcontact-details':

				$html = '';
				$val = isset( $post->post_title ) ? $post->post_title : '';
					if($val)
						$html .= esc_html($val) . '<br>';
					
				$meta_name = 'cont-horaires'; 
					$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';
					
				$meta_name = 'cont-webpage'; 
					$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= sprintf('<a href="%s" target="_blank">%s</a><br>', esc_html($val), $val);

				$meta_name = 'cont-contact'; 
					$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'cont-email';
					$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_mailto($val) . '<br>';

				$meta_name = 'cont-phone-show';
					$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
					$show_email = !! $val;

				$meta_name = 'cont-phone';
				if( $show_email ){ //TODO sans contrainte si envoyé à l'auteur
					$val = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
					if($val)
						$html .= antispambot($val) . '<br>';
				}
				
				if(! $html )
					return '';
				
				if($no_html){
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = str_ireplace('<br>', "\r\n", $html);
					//TODO
					$html .= sprintf('Contact créé le %s.\r\n', get_the_date()) ;
					
				}
				else {
					
					// date de création
					$html .= '<div class="entry-details">' ;
					$html .= sprintf('<span>contact créé le %s</span>', get_the_date()) ;
					if(get_the_date() != get_the_modified_date())
						$html .= sprintf('<span>, mise à jour du %s</span>', get_the_modified_date()) ;
					$html .= '</div>' ;
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = '<div class="agdp-contact agdp-'. $shortcode .'">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. $html
						. '</div>';
				}
				return $html;
				
			case Agdp_Contact::shortcode:
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="'.$meta_name.'" du shortcode "contact" est inconnu.</div>';
				$val = Agdp_Contact::get_post_meta($post_id, 'cont-' . $meta_name, true, false);
				
				if($val)
					switch($meta_name){
						case 'phone' :
							$val = Agdp_Contact::get_phone_html($post_id);
							break;
						case 'email' :
							$val = antispambot(esc_html($val));
							break;
					}
				if($val || $content){
					return '<div class="agdp-contact">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. do_shortcode( $val . wp_kses_post($content))
						. '</div>';
				}
				break;

			// shortcode conditionnel
			case 'agdpcontact-condition':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre "info" du shortcode "contact-condition" est inconnu.</div>';
				$val = Agdp_Contact::get_post_meta($post_id, 'cont-' . $meta_name, true, false);
				if($val || $content){
					return do_shortcode( wp_kses_post($val . $content));
				}
				break;


			// shortcode conditionnel sur email
			case 'agdpcontact-avec-email':
				$meta_name = 'cont-email' ;
				$email = Agdp_Contact::get_post_meta($post_id, $meta_name, true, false);
				if(is_email($email)){
					return do_shortcode( wp_kses_post($content));
				}
				return '';

			default:
			
				return '<div class="error">Le shortcode "'.$shortcode.'" inconnu.</div>';
		}
	}
	
	// shortcodes //
	///////////////
}
