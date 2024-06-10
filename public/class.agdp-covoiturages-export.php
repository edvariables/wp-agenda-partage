<?php

/**
 * AgendaPartage -> Covoiturages
 * Collection de covoiturages
 */
class Agdp_Covoiturages_Export extends Agdp_Posts_Export {
	
	const post_type = Agdp_Covoiturage::post_type;
	
	/**
	 * Retourne les données TXT pour le téléchargement de l'export des covoiturages
	 */
	public static function export_posts_txt($posts){

		$txt = ['Exportation du ' . wp_date('d/m/Y \à H:i')];
			$txt[] = str_repeat('*', 36);
			$txt[] = str_repeat('*', 36);
		$txt[] = '';
		foreach($posts as $post){
			$txt[] = $post->post_title;
			$txt[] = str_repeat('-', 24);
			$txt[] = Agdp_Covoiturage::get_covoiturage_dates_text( $post->ID );
			$txt[] = get_post_meta($post->ID, 'cov-localisation', true);
			if( $value = Agdp_Covoiturage::get_covoiturage_cities($post->ID))
				$txt[] = implode(', ', $value);
			if( $value = Agdp_Covoiturage::get_covoiturage_categories($post->ID))
				$txt[] = implode(', ', $value);
			$phone_show = get_post_meta($post->ID, 'cov-phone-show', true);
			foreach(['cov-organisateur', 'cov-email', 'cov-phone', 'cov-siteweb'] as $meta_key)
				if( $value = get_post_meta($post->ID, $meta_key, true)
				&& ( ($meta_key != 'cov-phone') || $phone_show ) )
					$txt[] = $value;
			$txt[] = $post->post_content;
			$txt[] = '';
			$txt[] = str_repeat('*', 36);
			$txt[] = '';
			
		}
		return implode("\r\n", $txt);
	}
	
	/**
	 * Retourne les données Bulle-Verte bv.txt pour le téléchargement de l'export des covoiturages
	 */
	public static function export_posts_bv_txt($posts){

		$txt = [];
		foreach($posts as $post){
			$txt[] = $post->post_title;
			
			$txt[] = $post->post_content;
			
			$meta_key = 'cov-organisateur';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				$txt[] = sprintf('Organisé par : %s', $value);
				
			$infos = '';
			$meta_key = 'cov-phone-show';
			if( get_post_meta($post->ID, $meta_key, true) ){
				$meta_key = 'cov-phone';
				if( $value = get_post_meta($post->ID, $meta_key, true) )
					$infos = $value;
			}
			
			$meta_key = 'cov-email';
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
	
	public static function add_post_to_ZCiCal($post, $ical){
		$metas = get_post_meta($post->ID, '', true);
		foreach($metas as $key=>$value)
			if(is_array($value))
				$metas[$key] = implode(', ', $value);
		$metas['date_start'] = self::sanitize_datetime($metas['cov-date-debut'], $metas['cov-heure-debut']);
		$metas['date_end'] = self::sanitize_datetime($metas['cov-date-fin'], $metas['cov-heure-fin'], $metas['cov-date-debut'], $metas['cov-heure-debut']);
				
		$vevent = new ZCiCalNode("VEVENT", $ical->curnode);

		// add start date
		$vevent->addNode(new ZCiCalDataNode("CREATED;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($post->post_date)));

		// DTSTAMP is a required item in VEVENT
		$vevent->addNode(new ZCiCalDataNode("DTSTAMP;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime()));

		// add last modified date
		$vevent->addNode(new ZCiCalDataNode("LAST-MODIFIED;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($post->post_modified)));

		// Add status
		$vevent->addNode(new ZCiCalDataNode("STATUS:" . self::get_vcalendar_status( $post )));

		// add title
		$vevent->addNode(new ZCiCalDataNode("SUMMARY:" . $post->post_title));

		// add start date
		$vevent->addNode(new ZCiCalDataNode("DTSTART;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_start'])));

		// add end date
		if($metas['date_end'])
			$vevent->addNode(new ZCiCalDataNode("DTEND;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_end'])));

		// UID is a required item in VEVENT, create unique string for this event
		// Adding your domain to the end is a good way of creating uniqueness
		$parse = parse_url(content_url());
		$uid = sprintf('%s[%d]@%s', Agdp_Covoiturage::post_type, $post->ID, $parse['host']);
		$vevent->addNode(new ZCiCalDataNode("UID:" . $uid));

		// Add description
		$vevent->addNode(new ZCiCalDataNode("DESCRIPTION:" . ZCiCal::formatContent( $post->post_content)));
				
		foreach([
			'DEPART'=>'cov-depart'
			, 'ARRIVEE'=>'cov-arrivee'
			, 'INTENTION'=>'cov-intention'
			, 'PERIODIQUE'=>'cov-periodique'
			, 'PERIODIQUE-LABEL'=>'cov-periodique-label'
			, 'NB-PLACES'=>'cov-nb-places'
			, 'ORGANISATEUR'=>'cov-organisateur'
			, 'EMAIL'=>'cov-email'
			, 'PHONE'=>'cov-phone'
			, 'PHONE-SHOW'=>'cov-phone-show'
			, 'UID'=>AGDP_IMPORT_UID
			, strtoupper(AGDP_COVOIT_SECRETCODE)=>'cov-'.AGDP_COVOIT_SECRETCODE
			, strtoupper('related_' . Agdp_Evenement::post_type)=>'related_' . Agdp_Evenement::post_type // add source site url
			
		] as $node_name => $meta_key)
			if( ! empty( $metas[$meta_key])
			&& (($meta_key != 'cov-phone') || (! empty($metas['cov-phone-show']) && $metas['cov-phone-show'])))
				$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $metas[$meta_key])));

		// Add terms
		foreach([ 
			'CITIES' => Agdp_Covoiturage::taxonomy_city
		] as $node_name => $tax_name){
			$terms = Agdp_Covoiturage::get_post_terms ($tax_name, $post->ID, 'names');
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
