<?php

/**
 * AgendaPartage -> Evenements
 * Collection d'évènements
 */
class Agdp_Evenements_Export extends Agdp_Posts_Export {
	
	const post_type = Agdp_Evenement::post_type;
	
	/**
	 * Retourne les données TXT pour le téléchargement de l'export des évènements
	 */
	public static function export_posts_txt($posts){

		$txt = ['Exportation du ' . wp_date('d/m/Y \à H:i')];
			$txt[] = str_repeat('*', 36);
			$txt[] = str_repeat('*', 36);
		$txt[] = '';
		foreach($posts as $post){
			$txt[] = $post->post_title;
			$txt[] = str_repeat('-', 24);
			$txt[] = Agdp_Evenement::get_event_dates_text( $post->ID );
			$txt[] = get_post_meta($post->ID, 'ev-localisation', true);
			if( $value = Agdp_Evenement::get_event_cities($post->ID))
				$txt[] = implode(', ', $value);
			if( $value = Agdp_Evenement::get_event_categories($post->ID))
				$txt[] = implode(', ', $value);
			foreach(['ev-organisateur', 'ev-email', 'ev-user-email', 'ev-phone', 'ev-siteweb'] as $meta_key)
				if( $value = get_post_meta($post->ID, $meta_key, true) )
					$txt[] = $value;
			$txt[] = $post->post_content;
			$txt[] = '';
			$txt[] = str_repeat('*', 36);
			$txt[] = '';
			
		}
		return implode("\r\n", $txt);
	}
	
	/**
	 * Retourne les données Bulle-Verte bv.txt pour le téléchargement de l'export des évènements
	 */
	public static function export_posts_bv_txt($posts){

		$txt = [];
		foreach($posts as $post){
			if( $cities = Agdp_Evenement::get_event_cities($post->ID))
				$cities = ' - ' . implode(', ', $cities);
			else
				$cities = '';
			$txt[] = $post->post_title . $cities;
			
			$localisation = get_post_meta($post->ID, 'ev-localisation', true);
			if($localisation)
				$localisation = ' - ' . $localisation;
			$dates = Agdp_Evenement::get_event_dates_text( $post->ID );
			$dates = str_replace([ date('Y'), date('Y + 1 year') ], '', $dates);
			$txt[] = $dates . $localisation;
			
			$txt[] = $post->post_content;
			
			$meta_key = 'ev-organisateur';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				$txt[] = sprintf('Organisé par : %s', $value);
				
			$infos = '';
			$meta_key = 'ev-phone';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				$infos = $value;
				
			$meta_key = 'ev-email';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				if($infos)
					$infos .= '/';
				$infos .= $value;
				
			$meta_key = 'ev-siteweb';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				$value = str_replace( [ 'http://', 'https://' ], '', $value);
				if($infos)
					$infos .= ' / ';
				$infos .= $value;
				
			$txt[] = 'Infos : ' . $infos;
			
			$txt[] = '';
			$txt[] = '';
			
		}
		return implode("\r\n", $txt);
	}
	
	/**********************************************************/
	/******************  DOCX  ********************************/
	
	/**
	 * Retourne les données en docx pour le téléchargement de l'export des évènements
	 */
	public static function export_posts_docx($posts, $filters, $return = 'file'){
		$file = self::get_diffusion_docx_model_file( $filters );
		$fileZip = self::get_export_folder() . '/' . basename($file) . '.zip';
		if( file_exists($fileZip) )
			unlink($fileZip);
		//Extraction
		$zip = new ZipArchive();
		if( $zip->open($file) !== true )
			throw new Exception('Impossible de décompresser le fichier modèle.');
		$zipDir = dirname($fileZip) . '/' . basename($file);
		if( is_dir($zipDir) )
			rrmdir($zipDir);
		$zip->extractTo($zipDir);
		$zip->close();
		//Document à modifier
		$xmlFile = $zipDir . '/word/document.xml';
		if( ! file_exists($xmlFile) )
			throw new Exception('Impossible de trouver le document dans le zip : '  . $xmlFile);
		$xml = file_get_contents($xmlFile);
		
		$find = '[**DEBUT**]';
		$pos = strpos($xml, $find);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$find = '<w:p ';
		$pos = strrpos( substr($xml, 0, $pos), $find);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$xml_before = substr( $xml, 0, $pos);
		
		$find = '[**FIN**]';
		$pos = strpos($xml, $find);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$find = '</w:p>';
		$pos = strpos( $xml, $find, $pos);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$xml_after = substr( $xml, $pos + strlen($find));
		
		$xml_block_model = substr( $xml, strlen( $xml_before ), strlen( $xml ) - strlen( $xml_before ) - strlen( $xml_after ));
		$xml_block_model = self::remove_xml_paragraph($xml_block_model, '[**DEBUT**]');
				
		foreach($posts as $post){
			$xml_post = str_replace('[**FIN**]', '', $xml_block_model);
			
			if( $cities = Agdp_Evenement::get_event_cities($post->ID))
				$cities = ' - ' . implode(', ', $cities);
			else
				$cities = '';
			$txt = $post->post_title . $cities;
			$xml_post = str_replace('[Titre-Commune]', htmlspecialchars($txt), $xml_post);
			
			$localisation = get_post_meta($post->ID, 'ev-localisation', true);
			if($localisation)
				$localisation = ' - ' . $localisation;
			$dates = Agdp_Evenement::get_event_dates_text( $post->ID );
			$dates = str_replace([ date('Y'), date('Y + 1 year') ], '', $dates);
			$txt = $dates . $localisation;
			$xml_post = str_replace('[Date-Lieu]', htmlspecialchars($txt), $xml_post);
			
			$txt = $post->post_content;
			if( ! trim($txt) ){
				$xml_post = self::remove_xml_paragraph($xml_post, '[Description]');
			} elseif( strpos($txt, "\n") === false){
				$xml_post = str_replace('[Description]', htmlspecialchars($txt), $xml_post);
			} else {
				$parts = self::explode_xml_paragraph($xml_post, '[Description]');
				$xml_post = $parts[0];
				foreach( explode("\n", $txt) as $txt_row){
					if( trim($txt_row) )
						$xml_post .= str_replace('[Description]', htmlspecialchars($txt_row), $parts[1]);
				}
				$xml_post .= $parts[2];
			}
			
			$meta_key = 'ev-organisateur';
			if( $value = get_post_meta($post->ID, $meta_key, true) ){
				$txt = sprintf('Organisé par : %s', $value);
				$xml_post = str_replace('[Organisateur]', $txt, $xml_post);
			}
			else
				$xml_post = self::remove_xml_paragraph($xml_post, '[Organisateur]');
			
			$infos = '';
			$meta_key = 'ev-phone';
			if( $value = get_post_meta($post->ID, $meta_key, true) )
				$infos = $value;
				
			$meta_key = 'ev-email';
			if( $value = get_post_meta($post->ID, $meta_key, true) ){
				if($infos)
					$infos .= ' / ';
				$infos .= $value;
			}
			if( $infos ){
				$infos = 'Infos : ' . $infos;
				$xml_post = str_replace('[Infos]', $infos, $xml_post);
			}
			else
				$xml_post = self::remove_xml_paragraph($xml_post, '[Infos]');
			
			$meta_key = 'ev-siteweb';
			if( $value = get_post_meta($post->ID, $meta_key, true) ){
				$txt = str_replace( [ 'http://', 'https://' ], '', $value);
				$xml_post = str_replace('[Site-web]', $txt, $xml_post);
			}
			else
				$xml_post = self::remove_xml_paragraph($xml_post, '[Site-web]');
			
			$xml_before .= $xml_post;
		}
		$xml = $xml_before . $xml_after;
		
		$date = new DateTime();
		$date->modify('+1 month');
		$xml = str_replace( '[MOIS]', __($date->format('F')), $xml);
		$xml = str_replace( '[ANNEE]', $date->format('Y'), $xml);
		
		file_put_contents($xmlFile, $xml);
		
		//Regénération du fichier docx
		zip_create_folder_zip($zipDir, $fileZip);
		//Nettoyage	
		rrmdir($zipDir);
		if( $return === 'data' ){
			$data = file_get_contents($fileZip);
			unlink($fileZip);
			return $data;
		}
		$file = sprintf( '%s/%s-%s', dirname($fileZip), $date->format('Y-m'), basename($file));
		rename($fileZip, $file);
		
		if( $return === 'url' ){
			$url = self::get_export_url(basename($file));
			return $url;
		}
		return $file;
	}
	
	/**********************************************************/
	
	/**
	 * OpenAgenda
	 */
	public static function add_post_to_OpenAgenda($post, &$openagenda, $filters = false, $metas = false){
		// metas
		if( ! $metas ){
			$metas = get_post_meta($post->ID, '', true);
			foreach($metas as $key=>$value)
				if(is_array($value))
					$metas[$key] = implode(', ', $value);
		}
		$metas['date_start'] = self::sanitize_datetime($metas['ev-date-debut'], $metas['ev-heure-debut']);
		$metas['date_end'] = self::sanitize_datetime($metas['ev-date-fin'], $metas['ev-heure-fin'], $metas['ev-date-debut'], $metas['ev-heure-debut']);
				
		$vevent = parent::add_post_to_OpenAgenda($post, $openagenda, $filters, $metas);

		// Add terms
		$keywords = [];
		$cities = [];
		foreach([ 
			'CATEGORIES' => Agdp_Evenement::taxonomy_ev_category
			, 'CITIES' => Agdp_Evenement::taxonomy_city
		] as $node_name => $tax_name){
			$terms = Agdp_Evenement::get_post_terms ($tax_name, $post->ID, 'all');
			if($terms){
				foreach($terms as $term)
					$keywords[] = $term->name;
				if( $node_name === 'CITIES' )
					$cities = $terms;
			}
		}
		if( $keywords )
			$vevent->keywords = [ 'fr' => $keywords ];
		
		//Localisation avec openagenda_uid
		if( $cities ){
			foreach( $cities as $location ){
				$external_ids = get_term_meta( $location->term_id, 'external_ids', true );
				if( $external_ids ){
					$matches = [];
					if( preg_match_all( '/(\d+)\@(\d+)\.openagenda/', $external_ids, $matches ) ){
						for($match = 0; $match < count($matches[1]); $match++){
							if( empty($openagenda->openagenda_uid) ){
								//Mémorise tous les id en attendant le sanitize_event
								if( empty($vevent->locationUid) )
									$vevent->locationUid = [];
								$vevent->locationUid[ $matches[2][$match].'' ] = $matches[1][$match];
							}
							elseif( $openagenda->openagenda_uid === $matches[2][$match] )
								$vevent->locationUid = $matches[1][$match];
						}
					}
				}
			}
		}
		
		// registration
		$registration = [];
		if( ! empty($metas['ev-siteweb']) )
			$registration[] = $metas['ev-siteweb'];
		if( ! empty($metas['ev-email']) )
			$registration[] = $metas['ev-email'];
		if( ! empty($metas['ev-phone']) )
			$registration[] = $metas['ev-phone'];
		if( $registration )
			$vevent->registration = $registration;
		return $vevent;
	}/** OpenAgenda	 */
	
	/**
	 * ics/ZCiCal
	 */
	public static function add_post_to_ZCiCal($post, $ical, $filters = false, $metas = false){
		
		// metas
		if( ! $metas ){
			$metas = get_post_meta($post->ID, '', true);
			foreach($metas as $key=>$value)
				if(is_array($value))
					$metas[$key] = implode(', ', $value);
		}
		$metas['date_start'] = self::sanitize_datetime($metas['ev-date-debut'], $metas['ev-heure-debut']);
		$metas['date_end'] = self::sanitize_datetime($metas['ev-date-fin'], $metas['ev-heure-fin'], $metas['ev-date-debut'], $metas['ev-heure-debut']);
				
		$vevent = parent::add_post_to_ZCiCal($post, $ical, $filters, $metas);
		
		// add start date
		$vevent->addNode(new ZCiCalDataNode("DTSTART;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_start'])));

		// add end date
		if($metas['date_end'])
			$vevent->addNode(new ZCiCalDataNode("DTEND;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_end'])));

		// Add fields
		$fields = [
			'LOCATION'=>'ev-localisation'
			, 'ORGANISATEUR'=>'ev-organisateur'
			, 'EMAIL'=>'ev-email'
			, 'USER-EMAIL'=>'ev-user-email'
			, 'PHONE'=>'ev-phone'
		];
		if( ! empty($filters[Agdp_Evenement::secretcode_argument]) )
			$fields[ strtoupper(Agdp_Evenement::secretcode_argument) ] = Agdp_Evenement::field_prefix . Agdp_Evenement::secretcode_argument;
		foreach($fields as $node_name => $meta_key)
			if( ! empty( $metas[$meta_key]))
				$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $metas[$meta_key])));

		// Add terms
		foreach([ 
			'CATEGORIES' => Agdp_Evenement::taxonomy_ev_category
			, 'CITIES' => Agdp_Evenement::taxonomy_city
			, 'DIFFUSIONS' => Agdp_Evenement::taxonomy_diffusion
		] as $node_name => $tax_name){
			$terms = Agdp_Evenement::get_post_terms ($tax_name, $post->ID, 'names');
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
