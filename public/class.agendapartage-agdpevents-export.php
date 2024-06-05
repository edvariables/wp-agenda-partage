<?php

/**
 * AgendaPartage -> Evenements
 * Collection d'évènements
 */
class AgendaPartage_Evenements_Export {
	 
	 /**
	  * Export
	  *
	  * $return = url|file|data
	  */
	 public static function do_export($posts, $file_format = 'ics', $return = 'url', $filters = false ){
		$encode_to = "UTF-8";
		switch( strtolower( $file_format )){
			case 'vcalendar':
				$file_format = 'ics';
			case 'ics':
				$export_data = self::export_posts_ics($posts, $filters);
				break;
			case 'txt':
				$encode_to = "Windows-1252";
				$export_data = self::export_posts_txt($posts);
				break;
			case 'bv.txt':
				$encode_to = "Windows-1252";
				$export_data = self::export_posts_bv_txt($posts);
				break;
			case 'docx':
				$encode_to = false;
				//TODO if empty ?
				return self::export_posts_docx($posts, $filters, $return);
				
			default:
				return sprintf('format inconnu : "%s"', $file_format);
		}
		
		if( ! $export_data){
			if($return === 'data')
				return '';
			else
				return sprintf('alert:Aucune donnée à exporter');
		}
		if( $encode_to ) {
			$enc = mb_detect_encoding($export_data);
			$export_data = mb_convert_encoding($export_data, $encode_to, $enc);
		}
		self::clear_export_history();
		//TODO see 'export_posts_docx' qui retourne un fichier
		if( strrpos($export_data, '.' . $file_format) === strlen($export_data) - 1 - strlen($file_format) ){
			$file = $export_data;
			if($return === 'data')
				return file_get_contents( $file );
		} else {
			if($return === 'data')
				return $export_data;
			
			$file = self::get_export_filename( $file_format );

			$handle = fopen($file, "w");
			fwrite($handle, $export_data);
			fclose($handle);
		}
		if($return === 'file')
			return $file;
		
		$url = self::get_export_url(basename($file));
		
		return $url;
		
	}
	
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
			$txt[] = AgendaPartage_Evenement::get_event_dates_text( $post->ID );
			$txt[] = get_post_meta($post->ID, 'ev-localisation', true);
			if( $value = AgendaPartage_Evenement::get_event_cities($post->ID))
				$txt[] = implode(', ', $value);
			if( $value = AgendaPartage_Evenement::get_event_categories($post->ID))
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
			if( $cities = AgendaPartage_Evenement::get_event_cities($post->ID))
				$cities = ' - ' . implode(', ', $cities);
			else
				$cities = '';
			$txt[] = $post->post_title . $cities;
			
			$localisation = get_post_meta($post->ID, 'ev-localisation', true);
			if($localisation)
				$localisation = ' - ' . $localisation;
			$dates = AgendaPartage_Evenement::get_event_dates_text( $post->ID );
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
			
			if( $cities = AgendaPartage_Evenement::get_event_cities($post->ID))
				$cities = ' - ' . implode(', ', $cities);
			else
				$cities = '';
			$txt = $post->post_title . $cities;
			$xml_post = str_replace('[Titre-Commune]', htmlspecialchars($txt), $xml_post);
			
			$localisation = get_post_meta($post->ID, 'ev-localisation', true);
			if($localisation)
				$localisation = ' - ' . $localisation;
			$dates = AgendaPartage_Evenement::get_event_dates_text( $post->ID );
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
	
	/**
	 * Retourne le fichier modèle pour la diffusion
	 */
	private static function get_diffusion_docx_model_file($filters){
		$term_id = false;
		foreach($filters as $filter_name => $filter_value)
			if( $filter_name === AgendaPartage_Evenement::taxonomy_diffusion ){
				$term_id = $filter_value;
				break;
			}
		if( ! $term_id )
			throw new Exception('Impossible de trouver le fichier modèle : terme de diffusion des évènements introuvable.');
		$meta_name = 'download_file_model';
		$file = get_term_meta($term_id, $meta_name, true);
		if( ! $file )
			throw new Exception('Impossible de trouver le fichier modèle : valeur non définie dans la configuration du terme de diffusion des évènements.');
		
		$upload_dir = wp_upload_dir();
		$upload_dir = str_replace('\\', '/', $upload_dir['basedir']);
		$file = $upload_dir . $file;
		if( ! file_exists( $file ) )
			throw new Exception('Impossible de trouver le fichier modèle : fichier introuvable ('. $file . ').');
		
		return $file;
	}
	
	/**
	 * Découpe en 3 (avant, paragraphe, après) un XML autours d'un paragraphe contenant $field
	 */
	private static function explode_xml_paragraph($xml, $field){
		$find = $field;
		$pos = strpos($xml, $find);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$find = '<w:p ';
		$pos = strrpos( substr($xml, 0, $pos), $find);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$xml_before = substr( $xml, 0, $pos);
		
		$find = '</w:p>';
		$pos = strpos( $xml, $find, $pos);
		if( $pos === false ) throw new Exception('Impossible d\'analyser le document : '  . $find);
		$xml_after = substr( $xml, $pos + strlen($find));
		
		$xml_inner = substr( $xml, strlen( $xml_before ), strlen( $xml ) - strlen( $xml_before ) - strlen( $xml_after ));
		
		return [$xml_before, $xml_inner, $xml_after];
		
	}
	
	/**
	 * Supprime, dans un XML, un paragraphe contenant $field
	 */
	private static function remove_xml_paragraph($xml, $field){
		$parts = self::explode_xml_paragraph($xml, $field);
		
		return $parts[0] . $parts[2];
		
	}
	/**********************************************************/
	
	/**
	 * Retourne les données ICS pour le téléchargement de l'export des évènements
	 *
	 *  $filters['set_post_status']
	 */
	public static function export_posts_ics($posts, $filters = false){

		require_once(AGDP_PLUGIN_DIR . "/includes/icalendar/zapcallib.php");
		
		$iCal = self::get_new_ZCiCal();
		foreach($posts as $post){
			self::add_agdpevent_to_ZCiCal($post, $iCal, $filters);
		}
		return $iCal->export();
		
		/* require_once(AGDP_PLUGIN_DIR . '/admin/class.ical.php');
		$iCal = new iCal();
		$iCal->title = get_bloginfo( 'name', 'display' );
		$iCal->description = content_url();
		foreach($posts as $post){
			$iCal->events[] = new iCal_Event($post);
		}
		return $iCal->generate(); */
	}
	
	public static function get_new_ZCiCal(){
		$ical= new ZCiCal();
		//TITLE
		$datanode = new ZCiCalDataNode("TITLE:" . ZCiCal::formatContent( get_bloginfo( 'name', 'display' )));
		$ical->curnode->data[$datanode->getName()] = $datanode;
		//DESCRIPTION
		$page_id = AgendaPartage::get_option('agenda_page_id');
		if($page_id)
			$url = get_permalink($page_id);
		else
			$url = get_site_url();
		$datanode = new ZCiCalDataNode("DESCRIPTION:" . ZCiCal::formatContent( $url ));
		$ical->curnode->data[$datanode->getName()] = $datanode;
		//VTIMEZONE
		$vtimezone = new ZCiCalNode("VTIMEZONE", $ical->curnode);
		$vtimezone->addNode(new ZCiCalDataNode("TZID:Europe/Paris"));
		
		return $ical;
	}
	
	public static function add_agdpevent_to_ZCiCal($post, $ical, $filters = false){
		$metas = get_post_meta($post->ID, '', true);
		foreach($metas as $key=>$value)
			if(is_array($value))
				$metas[$key] = implode(', ', $value);
		$metas['date_start'] = self::sanitize_datetime($metas['ev-date-debut'], $metas['ev-heure-debut']);
		$metas['date_end'] = self::sanitize_datetime($metas['ev-date-fin'], $metas['ev-heure-fin'], $metas['ev-date-debut'], $metas['ev-heure-debut']);
				
		$vevent = new ZCiCalNode("VEVENT", $ical->curnode);

		// add start date
		$vevent->addNode(new ZCiCalDataNode("CREATED;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($post->post_date)));

		// DTSTAMP is a required item in VEVENT
		$vevent->addNode(new ZCiCalDataNode("DTSTAMP;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime()));

		// add last modified date
		$vevent->addNode(new ZCiCalDataNode("LAST-MODIFIED;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($post->post_modified)));

		// Add status
		$vevent->addNode(new ZCiCalDataNode("STATUS:" . self::get_vcalendar_status( $post, $filters )));

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
		$uid = sprintf('%s[%d]@%s', AgendaPartage_Evenement::post_type, $post->ID, $parse['host']);
		$vevent->addNode(new ZCiCalDataNode("UID:" . $uid));

		// Add description
		$vevent->addNode(new ZCiCalDataNode("DESCRIPTION:" . ZCiCal::formatContent( $post->post_content)));

		// Add fields
		$fields = [
			'LOCATION'=>'ev-localisation'
			, 'ORGANISATEUR'=>'ev-organisateur'
			, 'EMAIL'=>'ev-email'
			, 'USER-EMAIL'=>'ev-user-email'
			, 'PHONE'=>'ev-phone'
		];
		if( ! empty($filters[AgendaPartage_Evenement::secretcode_argument]) )
			$fields[ strtoupper(AgendaPartage_Evenement::secretcode_argument) ] = AgendaPartage_Evenement::field_prefix . AgendaPartage_Evenement::secretcode_argument;
		foreach($fields as $node_name => $meta_key)
			if( ! empty( $metas[$meta_key]))
				$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $metas[$meta_key])));

		// Add terms
		foreach([ 
			'CATEGORIES' => AgendaPartage_Evenement::taxonomy_ev_category
			, 'CITIES' => AgendaPartage_Evenement::taxonomy_city
			, 'DIFFUSIONS' => AgendaPartage_Evenement::taxonomy_diffusion
		] as $node_name => $tax_name){
			$terms = AgendaPartage_Evenement::get_post_terms ($tax_name, $post->ID, 'names');
			if($terms){
				//$terms = array_map(function($tax_name){ return str_replace(',','-', $tax_name);}, $terms);//escape ','
				foreach($terms as $term_name)
					$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $term_name)));
					
				// $vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( implode(',', $terms) )));
			}
		}
		return $vevent;
	}
	
	public static function sanitize_datetime($date, $time, $date_start = false, $time_start = false){
		if( ! $date ){
			// debug_log('sanitize_datetime(', $date, $time, $date_start);
			//if not end date, not time and start date contains time, skip dtend
			if($date_start
			&& (! $time || $time == '00:00' || $time == '00:00:00')
			&& $time_start)
				return '';
				
			$date = $date_start;
		}
		if( ! $date )
			return;
		if( $date_start
		&& (! $time || $time == '00:00' || $time == '00:00:00')
		&& ! $time_start){
			//date_start without hour, date_end is the next day, meaning 'full day'
			return date('Y-m-d', strtotime($date . ' + 1 day'));
		}
		$dateTime = rtrim($date . ' ' . str_replace('h', ':', $time));
		if($dateTime[strlen($dateTime)-1] === ':')
			$dateTime .= '00';
		$dateTime = preg_replace('/\s+00\:00(\:00)?$/', '', $dateTime);
		return $dateTime;
	}
		
	public static function get_vcalendar_status($post, $filters = false){
		$post_status = $post->post_status;
		if( is_array($filters) )
			if( ! empty($filters['set_post_status']) )
				$post_status = $filters['set_post_status'];
		switch($post_status){
			case 'publish' :
				return 'CONFIRMED';
			case 'trash' :
				return 'CANCELLED';
			default :
				return strtoupper($post->post_status);//only CANCELLED or DRAFT or TENTATIVE
		}
	}
	
	
	/****************
	*/
	
	/**
	 * Retourne le nom d'un fichier temporaire pour le téléchargement de l'export des évènements
	 */
	public static function get_export_filename($extension, $sub_path = 'export'){
		$folder = self::get_export_folder($sub_path);
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		$file = wp_tempnam(AGDP_TAG, $folder . '/');
		return str_replace('.tmp', '.' . $extension, $file);
	}
	/**
	 * Retourne le répertoire d'exportation pour téléchargement
	 */
	public static function get_export_folder($sub_path = 'export'){
		$folder = WP_CONTENT_DIR;
		if($sub_path){
			$folder .= '/' . $sub_path;
			if( ! file_exists($folder) )
				mkdir ( $folder );
		}
		$period = wp_date('Y-m');
		$folder .= '/' . $period;
		if( ! file_exists($folder) )
			mkdir ( $folder );
		
		return $folder;
	}
	public static function get_export_url($file = false, $sub_path = 'export'){
		$url = content_url($sub_path);
		$period = wp_date('Y-m');
		$url .= '/' . $period;
		if($file)
			$url .= '/' . $file;
		return $url;
	}
	/**
	 * Nettoie le répertoire d'exportation pour téléchargement
	 */
	public static function clear_export_history($sub_path = 'export'){
		$folder = dirname(self::get_export_folder($sub_path));
		if( ! file_exists($folder) )
			return;
		$period = wp_date('Y-m');
		$periods_to_keep = [ $period, wp_date('Y-m', strtotime($period . '-01 - 1 month'))];
		
		$cdir = scandir($folder);
		foreach ($cdir as $key => $value){
			if (!in_array($value, array(".",".."))){
				if (is_dir($folder . DIRECTORY_SEPARATOR . $value)
				&& ! in_array($value, $periods_to_keep)){
					rrmdir($folder . DIRECTORY_SEPARATOR . $value);
				}
			}
		}
	}
}
