<?php

/**
 * AgendaPartage -> Contacts
 * Collection de contacts
 */
class Agdp_Contacts_Export extends Agdp_Posts_Export {
	
	const post_type = Agdp_Contact::post_type;
	
	/**
	 * Retourne les données TXT pour le téléchargement de l'export des contacts
	 */
	public static function export_posts_txt($posts){

		$txt = ['Exportation du ' . wp_date('d/m/Y \à H:i')];
			$txt[] = str_repeat('*', 36);
			$txt[] = str_repeat('*', 36);
		$txt[] = '';
		foreach($posts as $post){
			$txt[] = $post->post_title;
			$txt[] = str_repeat('-', 24);
			$txt[] = Agdp_Contact::get_contact_dates_text( $post->ID );
			$txt[] = get_post_meta($post->ID, 'cont-localisation', true);
			if( $value = Agdp_Contact::get_contact_cities($post->ID))
				$txt[] = implode(', ', $value);
			if( $value = Agdp_Contact::get_contact_categories($post->ID))
				$txt[] = implode(', ', $value);
			$phone_show = get_post_meta($post->ID, 'cont-phone-show', true);
			foreach(['cont-organisateur', 'cont-email', 'cont-phone', 'cont-siteweb'] as $meta_key)
				if( $value = get_post_meta($post->ID, $meta_key, true)
				&& ( ($meta_key != 'cont-phone') || $phone_show ) )
					$txt[] = $value;
			$txt[] = $post->post_content;
			$txt[] = '';
			$txt[] = str_repeat('*', 36);
			$txt[] = '';
			
		}
		return implode("\r\n", $txt);
	}
	
	/**
	 * Retourne les données Bulle-Verte bv.txt pour le téléchargement de l'export des contacts
	 */
	public static function export_posts_bv_txt($posts){

		$txt = [];
		foreach($posts as $post){
			$txt[] = $post->post_title;
			
			$txt[] = $post->post_content;
			
			$meta_key = 'cont-organisateur';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				$txt[] = sprintf('Organisé par : %s', $value);
				
			$infos = '';
			$meta_key = 'cont-phone-show';
			if( get_post_meta($post->ID, $meta_key, true) ){
				$meta_key = 'cont-phone';
				if( $value = get_post_meta($post->ID, $meta_key, true) )
					$infos = $value;
			}
			
			$meta_key = 'cont-email';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				if($infos)
					$infos .= '/';
				$infos .= $value;
				
			$txt[] = 'Infos : ' . $infos;
			
			$txt[] = '';
			$txt[] = '';
			
		}
		return implode("\r\n", $txt);
	}
	
	public static function add_post_to_OpenAgenda($post, &$openagenda, $filters = false, $metas = false){
		//TODO
		return parent::add_post_to_OpenAgenda($post, $openagenda, $filters, $metas);
	}
	
	public static function add_post_to_ZCiCal($post, $ical, $filters = false, $metas = false){
		
		// metas
		if( ! $metas ){
			$metas = get_post_meta($post->ID, '', true);
			foreach($metas as $key=>$value)
				if(is_array($value))
					$metas[$key] = implode(', ', $value);
		}
		$metas['date_start'] = self::sanitize_datetime($metas['cont-date-debut'], $metas['cont-heure-debut']);
		$metas['date_end'] = self::sanitize_datetime($metas['cont-date-fin'], $metas['cont-heure-fin'], $metas['cont-date-debut'], $metas['cont-heure-debut']);
		
		$vevent = parent::add_post_to_ZCiCal($post, $ical, $filters, $metas);
		
		foreach([
			'DEPART'=>'cont-depart'
			, 'ARRIVEE'=>'cont-arrivee'
			, 'INTENTION'=>'cont-intention'
			, 'PERIODIQUE'=>'cont-periodique'
			, 'PERIODIQUE-LABEL'=>'cont-periodique-label'
			, 'NB-PLACES'=>'cont-nb-places'
			, 'ORGANISATEUR'=>'cont-organisateur'
			, 'EMAIL'=>'cont-email'
			, 'PHONE'=>'cont-phone'
			, 'PHONE-SHOW'=>'cont-phone-show'
			, strtoupper('related_' . Agdp_Event::post_type)=>'related_' . Agdp_Event::post_type // add source site url
			
		] as $node_name => $meta_key)
			if( ! empty( $metas[$meta_key])
			&& (($meta_key != 'cont-phone') || (! empty($metas['cont-phone-show']) && $metas['cont-phone-show'])))
				$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $metas[$meta_key])));

		// Add terms
		foreach([ 
			'CITIES' => Agdp_Contact::taxonomy_city
		] as $node_name => $tax_name){
			$terms = Agdp_Contact::get_post_terms ($tax_name, $post->ID, 'names');
			if($terms){
				//$terms = array_map(function($tax_name){ return str_replace(',','-', $tax_name);}, $terms);//escape ','
				foreach($terms as $term_name)
					$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $term_name)));
					
				// $vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( implode(',', $terms) )));
			}
		}
		return $vevent;
	}
}
