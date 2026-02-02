<?php

/**
 * AgendaPartage -> Évènement -> Shortcodes
 * Custom post type for WordPress.
 * 
 * Définition des shortcodes 
 *
 * Voir aussi Agdp_Admin_Event
 */
class Agdp_Event_Shortcodes extends Agdp_Shortcodes {

	const post_type = Agdp_Event::post_type;
	
	const default_attr = 'post_id';
	
	const info_shortcodes = [ 
		'titre',
		'categories',
		'cities',
		'diffusions',
		'description',
		'dates',
		'localisation',
		'is-imported',
		'details',
		'attachments',
		'message-contact',
		'avec-email',
		'cree-depuis',
		'modifier-evenement',
		'covoiturage',
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
	* Callback des shortcodes
	*/
	/* public static function shortcodes_callback($atts, $content = '', $shortcode = null){

		if(is_admin() 
		&& ! wp_doing_ajax()
		&& ! Agdp_Newsletter::is_sending_email())
			return;
		
		if( ! is_array($atts)){
			$atts = array();
		}
		
		//champs sans valeur transformer en champ=true
		foreach($atts as $key=>$value){
			if(is_numeric($key) && ! array_key_exists($value, $atts)){
				$atts[$value] = true;
				unset($atts[$key]);
			}
		}
		if(array_key_exists('toggle-ajax', $atts)){
			$atts['toggle'] = $atts['toggle-ajax'];
			$atts['ajax'] = true;
			unset($atts['toggle-ajax']);
		}
		
		$key = 'ajax';
		if(array_key_exists($key, $atts)){
			$ajax = $atts[$key] ? $atts[$key] : true;
			unset($atts[$key]);
		}
		else{
			$ajax = false;
			$key = 'post_id';
			if(array_key_exists($key, $atts)){
				global $post;
				if(!$post){
					$post = get_post($atts[$key]);
					
					//Nécessaire pour WPCF7 pour affecter une valeur à _wpcf7_container_post
					global $wp_query;
					if($post)
						$wp_query->in_the_loop = true;
				}
				$_POST[$key] = $_REQUEST[$key] = $atts[$key];
				unset($atts[$key]);
			}
			$key = AGDP_EVENT_SECRETCODE ;
			if(array_key_exists($key, $atts)){
				$_POST[$key] = $_REQUEST[$key] = $atts[$key];
				unset($atts[$key]);
			}
		}
		// Si attribut toggle [agdpevent-details toggle="Contactez-nous !"]
		// Fait un appel récursif si il y a l'attribut "ajax"
		// TODO Sauf shortcode conditionnel
		if(array_key_exists('toggle', $atts)){
			
			$shortcode_atts = '';
			foreach($atts as $key=>$value){
				if($key == 'toggle'){
					$title = array_key_exists('title', $atts) && $atts['title'] 
						? $atts['title']
						: ( $atts['toggle'] 
							? $atts['toggle'] 
							: __($shortcode, AGDP_TAG)) ;
				}
				elseif( ! is_numeric($key) ){
					if(is_numeric($value))
						$shortcode_atts .= sprintf('%s=%s ', $key, $value);
					else
						$shortcode_atts .= sprintf('%s="%s" ', $key, esc_attr($value));
				}
			}
			
			//Inner
			$html = sprintf('[%s %s]%s[/%s]', $shortcode, $shortcode_atts , $content, $shortcode);
			
			if( ! $ajax){
				$html = do_shortcode($html);
			}
			else{
				$ajax = sprintf('ajax="%s"', esc_attr($ajax));
				$html = esc_attr(str_replace('"', '\\"', $html));
			}
			//toggle
			//Bugg du toggle qui supprime des éléments
			$guid = uniqid(AGDP_TAG);
			$toogler = do_shortcode(sprintf('[toggle title="%s" %s]%s[/toggle]'
				, esc_attr($title)
				, $ajax 
				, $guid
			));
			return str_replace($guid, $html, $toogler);
		}

		//De la forme [agdpevents liste] ou [agdpevents-calendrier]
		if($shortcode == 'agdpevents' || str_starts_with($shortcode, 'agdpevents-')){
			return self::shortcodes_agdpevents_callback($atts, $content, $shortcode);
		}
		
		return self::shortcodes_agdpevent_callback($atts, $content, $shortcode);
	}
	 */
	/**
	* [agdpevent info=titre|description|dates|localisation|details|message-contact|modifier-evenement|attachments]
	* [agdpevent-titre]
	* [agdpevent-description]
	* [agdpevent-dates]
	* [agdpevent-localisation]
	* [agdpevent-attachments]
	* [agdpevent-details]
	* [agdpevent-message-contact]
	* [agdpevent-avec-email]
	* [agdpevent-modifier-evenement]
	*/
	protected static function do_shortcode($atts, $content = '', $shortcode = null){
		
		$post = Agdp_Event::get_post();
		
		if($post)
			$post_id = $post->ID;
		
		$label = isset($atts['label']) ? $atts['label'] : '' ;
				
		$html = '';
		
		if(array_key_exists('info', $atts)
		&& in_array($atts['info'], self::info_shortcodes))
			$shortcode .= '-' . $atts['info'];
		
		$no_html = isset($atts['no-html']) && $atts['no-html']
				|| isset($atts['html']) && $atts['html'] == 'no';
		
		switch($shortcode){
			case 'agdpevent-titre':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = isset( $post->post_title ) ? $post->post_title : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				
			case 'agdpevent-description':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = isset( $post->post_content ) ? $post->post_content : '';
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				
			case 'agdpevent-localisation':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = get_post_meta($post_id, $meta_name, true);
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				
			case 'agdpevent-dates':

				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$val = Agdp_Event::get_event_dates_text( $post_id );
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				
			case 'agdpevent-is-imported':

				return Agdp_Event::get_post_imported( $post_id, $no_html );
				
			case 'agdpevent-cree-depuis':

				$val = date_diff_text($post->post_date);
				
				if($val || $content){
					$val = do_shortcode( wp_kses_post($val . $content));
					if($no_html)
						$html = $val;
					else
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. $val
							. '</div>';
				}
				return $html;
				
			case 'agdpevent-diffusions':
				$tax_name = Agdp_Event::taxonomy_diffusion;
			case 'agdpevent-cities':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = Agdp_Event::taxonomy_city;
			case 'agdpevent-categories':
				if(!isset($tax_name) || !$tax_name)
					$tax_name = Agdp_Event::taxonomy_ev_category;
			// case 'agdpevent-diffusions':
			// case 'agdpevent-cities':
			// case 'agdpevent-categories':
				$meta_name = 'ev-' . substr($shortcode, strlen('agdpevent-')) ;
				$terms = Agdp_Event::get_post_terms( $tax_name, $post_id, 'names');
				if($terms){
					$val = implode(', ', $terms);
					if($no_html)
						$html = $val;
					else{
						$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
							. ($label ? '<span class="label"> '.$label.'<span>' : '')
							. htmlentities($val)
							. '</div>';
					}
				}
				return $html;

			case 'agdpevent-message-contact':
				
				$meta_name = 'ev-organisateur' ;
				$organisateur = Agdp_Event::get_post_meta($post_id, $meta_name, true, false);
				if( ! $organisateur) {
					return;
				}

				$meta_name = 'ev-email' ;
				$email = Agdp_Event::get_post_meta($post_id, $meta_name, true, false);
				if(!$email) {
					return Agdp::icon('warning'
						, 'Vous ne pouvez pas envoyer de message, l\'évènement n\'a pas indiqué d\'adresse email.', 'agdp-error-light', 'div');
				}

				$form_id = Agdp::get_option('agdpevent_message_contact_form_id');
				if(!$form_id){
					return Agdp::icon('warning'
						, 'Un formulaire de message aux organisateurs d\'évènements n\'est pas défini dans les réglages de AgendaPartage.', 'agdp-error-light', 'div');
				}
				
				$val = sprintf('[contact-form-7 id="%s" title="*** message à l\'organisateur d\'évènement ***"]', $form_id);
				return '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
					. do_shortcode( $val)
					. '</div>';

			case 'agdpevent-modifier-evenement':

				return Agdp_Event_Edit::get_agdpevent_edit_content();


			case 'agdpevent-covoiturage':
				return Agdp_Event::get_agdpevent_covoiturage();

			case 'agdpevent-attachments':
				
				return Agdp_Post::get_attachments_links( $post );

			case 'agdpevent-details':

				$html = '';
				$val = isset( $post->post_title ) ? $post->post_title : '';
					if($val)
						$html .= esc_html($val) . '<br>';
					
				$meta_name = 'ev-dates'; 
					$val = Agdp_Event::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'ev-organisateur'; 
					$val = Agdp_Event::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'ev-localisation';
					$val = Agdp_Event::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= esc_html($val) . '<br>';

				$meta_name = 'ev-email';
					$val = Agdp_Event::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_mailto($val) . '<br>';

				$meta_name = 'ev-siteweb';
					$val = Agdp_Event::get_post_meta($post_id, $meta_name, true, true);
					if($val)
						$html .= make_clickable(esc_html($val)) . '<br>';

				$meta_name = 'ev-phone';
					$val = Agdp_Event::get_post_meta($post_id, $meta_name, true, false);
					if($val)
						$html .= antispambot($val) . '<br>';
				
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
					$html .= sprintf('<span>évènement créé le %s</span>', get_the_date()) ;
					if(get_the_date() != get_the_modified_date())
						$html .= sprintf('<span>, mise à jour du %s</span>', get_the_modified_date()) ;
					$html .= '</div>' ;
					$html = do_shortcode( wp_kses_post($html.$content));
					$html = '<div class="agdp-agdpevent agdp-'. $shortcode .'">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. $html
						. '</div>';
				}
				return $html;
				
			case 'agdpevent':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre info="'.$meta_name.'" du shortcode "agdpevent" est inconnu.</div>';
				$val = Agdp_Event::get_post_meta($post_id, 'ev-' . $meta_name, true, false);
				
				if($val)
					switch($meta_name){
						case 'siteweb' :
							$val = make_clickable(esc_html($val));
							break;
						case 'phone' :
						case 'email' :
							$val = antispambot(esc_html($val));
							break;
					}
				if($val || $content){
					return '<div class="agdp-agdpevent">'
						. ($label ? '<span class="label"> '.$label.'<span>' : '')
						. do_shortcode( wp_kses_post($val . $content))
						. '</div>';
				}
				break;

			// shortcode conditionnel
			case 'agdpevent-condition':
				$meta_name = $atts['info'] ;
				if(!$meta_name)
					return '<div class="error">Le paramètre "info" du shortcode "agdpevent-condition" est inconnu.</div>';
				$val = Agdp_Event::get_post_meta($post_id, 'ev-' . $meta_name, true, false);
				if($val || $content){
					return do_shortcode( wp_kses_post($val . $content));
				}
				break;


			// shortcode conditionnel sur email
			case 'agdpevent-avec-email':
				$meta_name = 'ev-email' ;
				$email = Agdp_Event::get_post_meta($post_id, $meta_name, true, false);
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
