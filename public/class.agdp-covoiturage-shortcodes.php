<?php

/**
 * AgendaPartage -> Covoiturage -> Shortcodes
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 */
class Agdp_Covoiturage_Shortcodes extends Agdp_Shortcodes {

	const post_type = Agdp_Covoiturage::post_type;
	
	const default_attr = 'post_id';
	
	const info_shortcodes = [ 
		'titre',
		'categories',
		'cities',
		'diffusions',
		'description',
		'dates',
		'localisation',
		'details',
		'message-contact',
		'avec-email',
		'cree-depuis',
		'modifier-covoiturage',
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
	* [covoiturage info=titre|description|dates|localisation|details|message-contact|modifier-covoiturage|created_since]
	* [covoiturage-titre]
	* [covoiturage-description]
	* [covoiturage-dates]
	* [covoiturage-localisation]
	* [covoiturage-details]
	* [covoiturage-message-contact]
	* [covoiturage-avec-email]
	* [covoiturage-modifier-covoiturage]
	* [covoiturage-cree-depuis]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){
		
		$post = Agdp_Covoiturage::get_post();
		
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
			case 'covoiturage-titre':

				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$val = isset( $post->post_title ) ? $post->post_title : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-description':

				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$val = isset( $post->post_content ) ? $post->post_content : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-dates':

				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$val = Agdp_Covoiturage::get_covoiturage_dates_text( $post_id );
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-cree-depuis':

				$val = date_diff_text($post->post_date);
				
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				break;
				
			case 'covoiturage-diffusions':
				$tax_name = Agdp_Covoiturage::taxonomy_diffusion;
			case 'covoiturage-cities':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = Agdp_Covoiturage::taxonomy_city;
				
				$meta_name = 'cov-' . substr($shortcode, strlen('covoiturage-')) ;
				$terms = Agdp_Covoiturage::get_post_terms( $tax_name, $post_id, 'names');
				if($terms){
					$val = implode(', ', $terms);
					if($no_html)
						$html = $val;
					else{
						$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
							. ($label ? '<span class="label"> '.$label.'<span>' : '')
							. htmlentities($val)
							. '</div>';
					}
				}
				return $html;

			case 'covoiturage-message-contact':
				
				$meta_name = 'cov-organisateur' ;
				$organisateur = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
				if( ! $organisateur) {
					return;
				}

				$meta_name = 'cov-email' ;
				$email = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
				if(!$email) {
					return Agdp::icon('warning'
						, 'Vous ne pouvez pas envoyer de message, le covoiturage n\'a pas d\'adresse email associé.', 'agdp-error-light', 'div');
				}

				$form_id = Agdp::get_option('agdpevent_message_contact_form_id');
				if(!$form_id){
					return Agdp::icon('warning'
						, 'Un formulaire de message aux organisteurs du covoiturage n\'est pas défini dans les réglages de AgendaPartage.', 'agdp-error-light', 'div');
				}

				$val = sprintf('[contact-form-7 id="%s" title="*** message à l\'organisateur du covoiturage ***"]', $form_id);
				return '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
					. do_shortcode( $val)
					. '</div>';


			case 'covoiturage-modifier-covoiturage':

				return Agdp_Covoiturage_Edit::get_covoiturage_edit_content();

			case 'covoiturage-details':

				$html = '';
				$val = isset( $post->post_title ) ? $post->post_title : '';
					if($val)
						$html .= esc_html($val) . '<br>';
					
				$meta_name = 'cov-dates'; 
					$val = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';
					
				$meta_name = 'cov-nb-places'; 
					$val = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'cov-organisateur'; 
					$val = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'cov-email';
					$val = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_mailto($val) . '<br>';

				$meta_name = 'cov-phone-show';
					$val = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
					$show_email = !! $val;

				$meta_name = 'cov-phone';
				if( $show_email ){ //TODO sans contrainte si envoyé à l'auteur
					$val = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
					if($val)
						$html .= antispambot($val) . '<br>';
				}
				
				if(! $html )
					return '';
				
				if($no_html){
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = str_ireplace('<br>', "\r\n", $html);
					//TODO
					$html .= sprintf('Evènement créé le %s.\r\n', get_the_date()) ;
					
				}
				else {
					
					// date de création
					$html .= '<div class="entry-details">' ;
					$html .= sprintf('<span>covoiturage créé le %s</span>', get_the_date()) ;
					if(get_the_date() != get_the_modified_date())
						$html .= sprintf('<span>, mise à jour du %s</span>', get_the_modified_date()) ;
					$html .= '</div>' ;
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = '<div class="agdp-covoiturage agdp-'. $shortcode .'">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. $html
						. '</div>';
				}
				return $html;
				
			case 'covoiturage':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="'.$meta_name.'" du shortcode "covoiturage" est inconnu.</div>';
				$val = Agdp_Covoiturage::get_post_meta($post_id, 'cov-' . $meta_name, true, false);
				
				if($val)
					switch($meta_name){
						case 'phone' :
							$val = Agdp_Covoiturage::get_phone_html($post_id);
							break;
						case 'email' :
							$val = antispambot(esc_html($val));
							break;
					}
				if($val || $content){
					return '<div class="agdp-covoiturage">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. do_shortcode( $val . wp_kses_post($content))
						. '</div>';
				}
				break;

			// shortcode conditionnel
			case 'covoiturage-condition':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre "info" du shortcode "covoiturage-condition" est inconnu.</div>';
				$val = Agdp_Covoiturage::get_post_meta($post_id, 'cov-' . $meta_name, true, false);
				if($val || $content){
					return do_shortcode( wp_kses_post($val . $content));
				}
				break;


			// shortcode conditionnel sur email
			case 'covoiturage-avec-email':
				$meta_name = 'cov-email' ;
				$email = Agdp_Covoiturage::get_post_meta($post_id, $meta_name, true, false);
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
