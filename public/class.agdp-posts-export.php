<?php

/**
 * AgendaPartage -> Posts abstract -> Export
 * Export de posts suivant différents formats
 */
abstract class Agdp_Posts_Export {
	
	const post_type = false; //Must override
	
	/**
	 * Constantes des classes
	 */
	public static function get_posts_page_option( $post_type = false ){
		if( ! $post_type )
			if( ! ( $post_type = static::post_type ) )
				return false;
		return (Agdp_Post::abstracted_class( $post_type ))::posts_page_option;
	}
	public static function get_secretcode_argument( $post_type = false ){
		if( ! $post_type )
			if( ! ( $post_type = static::post_type ) )
				return false;
		return (Agdp_Post::abstracted_class( $post_type ))::secretcode_argument;
	}
	public static function get_field_prefix( $post_type = false ){
		if( ! $post_type )
			if( ! ( $post_type = static::post_type ) )
				return false;
		return (Agdp_Post::abstracted_class( $post_type ))::field_prefix;
	}
	public static function get_taxonomy_diffusion( $post_type = false ){
		if( ! $post_type )
			if( ! ( $post_type = static::post_type ) )
				return false;
		return (Agdp_Post::abstracted_class( $post_type ))::taxonomy_diffusion;
	}
	
	/**
	  * Export
	  *
	  * $return = url|file|data
	  */
	 public static function do_export($posts, $file_format = 'ics', $return = 'url', $filters = false ){
		$post_type = static::post_type;
		if( ! $post_type ){
			foreach($posts as $post){
				$post_type = get_post_type($post);
				break;
			}
			if( empty($post_type) )
				return false;
			
			require_once( sprintf('%s/class.agdp-%ss-export.php', dirname(__FILE__), $post_type) );
			$posts_class = Agdp_Page::get_posts_class( $post_type );
			return ($posts_class .'_Export')::do_export( $posts, $file_format, $return, $filters);
		}
		
		$encode_to = "UTF-8";
		switch( strtolower( $file_format )){
			case 'vcalendar':
				$file_format = 'ics';
			case 'ics':
				$export_data = static::export_posts_ics($posts, $filters);
				break;
			case 'csv':
				$encode_to = "Windows-1252"; //SIC : reste en UTF8
				$export_data = static::export_posts_csv($posts, $filters);
				break;
			case 'txt':
				$encode_to = "Windows-1252";
				$export_data = static::export_posts_txt($posts);
				break;
			case 'bv.txt':
				$encode_to = "Windows-1252";
				$export_data = static::export_posts_bv_txt($posts);
				break;
			case 'docx':
				$encode_to = false;
				//TODO if empty ?
				return static::export_posts_docx($posts, $filters, $return);
			case 'openagenda':
				$export_data = static::export_posts_openagenda($posts, $filters);
				$return = 'data';
				break;
			case 'json':
				$export_data = json_encode( self::export_posts_object($posts, $filters), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				$return = 'data';
				break;
				
			default:
				return sprintf('format inconnu : "%s"', $file_format);
		}
		
		if( ! $export_data){
			if($return === 'data')
				return '';
			else
				return sprintf('alert:Aucune donnée à exporter');
		}
		if($return === 'data')
			return $export_data;
		
		if( is_string($export_data) && $encode_to ) {
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
			// $txt[] = Agdp_Event::get_event_dates_text( $post->ID );
			// $txt[] = get_post_meta($post->ID, 'ev-localisation', true);
			// if( $value = Agdp_Event::get_event_cities($post->ID))
				// $txt[] = implode(', ', $value);
			// if( $value = Agdp_Event::get_event_categories($post->ID))
				// $txt[] = implode(', ', $value);
			// foreach(['ev-organisateur', 'ev-email', 'ev-user-email', 'ev-phone', 'ev-siteweb'] as $meta_key)
				// if( $value = get_post_meta($post->ID, $meta_key, true) )
					// $txt[] = $value;
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
			// if( $cities = Agdp_Event::get_event_cities($post->ID))
				// $cities = ' - ' . implode(', ', $cities);
			// else
				// $cities = '';
			// $txt[] = $post->post_title . $cities;
			
			// $localisation = get_post_meta($post->ID, 'ev-localisation', true);
			// if($localisation)
				// $localisation = ' - ' . $localisation;
			// $dates = Agdp_Event::get_event_dates_text( $post->ID );
			// $dates = str_replace([ date('Y'), date('Y + 1 year') ], '', $dates);
			// $txt[] = $dates . $localisation;
			
			$txt[] = $post->post_content;
			
			// $meta_key = 'ev-organisateur';
			// if( $value = get_post_meta($post->ID, $meta_key, true) )
				// $txt[] = sprintf('Organisé par : %s', $value);
				
			$infos = '';
			// $meta_key = 'ev-phone';
			// if( $value = get_post_meta($post->ID, $meta_key, true) )
				// $infos = $value;
				
			// $meta_key = 'ev-email';
			// if( $value = get_post_meta($post->ID, $meta_key, true) )
				// if($infos)
					// $infos .= '/';
				// $infos .= $value;
				
			// $meta_key = 'ev-siteweb';
			// if( $value = get_post_meta($post->ID, $meta_key, true) )
				// $value = str_replace( [ 'http://', 'https://' ], '', $value);
				// if($infos)
					// $infos .= ' / ';
				// $infos .= $value;
				
			$txt[] = 'Infos : ' . $infos;
			
			$txt[] = '';
			$txt[] = '';
			
		}
		return implode("\r\n", $txt);
	}
	
	/**********************************************************/
	/******************  CSV  ********************************/
	
	/**
	 * Retourne les données CSV pour le téléchargement de l'export des posts
	 */
	public static function export_posts_csv($posts){
		
		$data = array([
			'id',
			'title', 
			'description',
		]);
		
		foreach($posts as $post){
			$fields = [ $post->ID ];
			$fields[] = self::export_escape_csv( $post->post_title );
			
			$fields[] = self::export_escape_csv( $post->post_content );
			
			$data[] = $fields;
		}
		
		return self::export_data_csv($data);
	}
	
	/**
	 * Retourne les données CSV pour un tableau
	 */
	public static function export_data_csv($data){
		// output up to 5MB is kept in memory, if it becomes bigger it will automatically be written to a temporary file
		$csv = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');

		foreach($data as $fields){
			fputcsv($csv, $fields, "\t", '"', '\\');
		}
		rewind($csv);
		// put it all in a variable
		$output = stream_get_contents($csv);
		fclose($csv);
		return $output;
	}
	
	/**
	 * Escape for CSV
	 */
	public static function export_escape_csv($str){
		return str_replace("\n", '\\n', 
			str_replace("\r", '\\r', 
			str_replace("\t", '  ', 
				$str
		)));
	}
	
	/**********************************************************/
	/******************  DOCX  ********************************/
	
	/**
	 * Retourne les données en docx pour le téléchargement de l'export des posts
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
			
			// if( $cities = Agdp_Event::get_event_cities($post->ID))
				// $cities = ' - ' . implode(', ', $cities);
			// else
				// $cities = '';
			// $txt = $post->post_title . $cities;
			// $xml_post = str_replace('[Titre-Commune]', htmlspecialchars($txt), $xml_post);
			
			// $localisation = get_post_meta($post->ID, 'ev-localisation', true);
			// if($localisation)
				// $localisation = ' - ' . $localisation;
			// $dates = Agdp_Event::get_event_dates_text( $post->ID );
			// $dates = str_replace([ date('Y'), date('Y + 1 year') ], '', $dates);
			// $txt = $dates . $localisation;
			// $xml_post = str_replace('[Date-Lieu]', htmlspecialchars($txt), $xml_post);
			
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
			
			// $meta_key = 'ev-organisateur';
			// if( $value = get_post_meta($post->ID, $meta_key, true) ){
				// $txt = sprintf('Organisé par : %s', $value);
				// $xml_post = str_replace('[Organisateur]', $txt, $xml_post);
			// }
			// else
				// $xml_post = self::remove_xml_paragraph($xml_post, '[Organisateur]');
			
			$infos = '';
			// $meta_key = 'ev-phone';
			// if( $value = get_post_meta($post->ID, $meta_key, true) )
				// $infos = $value;
				
			// $meta_key = 'ev-email';
			// if( $value = get_post_meta($post->ID, $meta_key, true) ){
				// if($infos)
					// $infos .= ' / ';
				// $infos .= $value;
			// }
			if( $infos ){
				$infos = 'Infos : ' . $infos;
				$xml_post = str_replace('[Infos]', $infos, $xml_post);
			}
			else
				$xml_post = self::remove_xml_paragraph($xml_post, '[Infos]');
			
			// $meta_key = 'ev-siteweb';
			// if( $value = get_post_meta($post->ID, $meta_key, true) ){
				// $txt = str_replace( [ 'http://', 'https://' ], '', $value);
				// $xml_post = str_replace('[Site-web]', $txt, $xml_post);
			// }
			// else
				// $xml_post = self::remove_xml_paragraph($xml_post, '[Site-web]');
			
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
	public static function get_diffusion_docx_model_file($filters){
		$term_id = false;
		foreach($filters as $filter_name => $filter_value)
			if( $filter_name === self::get_taxonomy_diffusion() ){
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
	public static function explode_xml_paragraph($xml, $field){
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
	public static function remove_xml_paragraph($xml, $field){
		$parts = self::explode_xml_paragraph($xml, $field);
		
		return $parts[0] . $parts[2];
		
	}
	/**********************************************************/
	
	/**
	 * Retourne les données json pour une synchronisation OpenAgenda
	 *
	 *  $filters['set_post_status']
	 */
	public static function export_posts_openagenda($posts, $filters = false){

		require_once(AGDP_PLUGIN_DIR . "/includes/openagenda/oa-publisher.php");
		
		$openagenda = static::get_new_OpenAgenda();
		foreach($posts as $post){
			if( is_numeric($post) )
				$post = get_post($post);
			$event = static::add_post_to_OpenAgenda($post, $openagenda, $filters);
		}
		return $openagenda;
	}
	
	public static function get_new_OpenAgenda(){
		return new OpenAgenda\Publisher();
	}
	
	/**
	 * add_post_to_OpenAgenda
	 */
	public static function add_post_to_OpenAgenda($post, &$openagenda, $filters = false, $metas = false){
		
		$vevent = $openagenda->add_event();

		// metas
		if( ! is_array($metas) ){
			$metas = get_post_meta($post->ID, '', true);
			foreach($metas as $key=>$value)
				if(is_array($value))
					$metas[$key] = implode(', ', $value);
		}
		$vevent->country = [
			'code' => 'FR',
			'fr' => 'France (Métropole)',
		];
	  
		$vevent->slug = $post->post_name;
		$vevent->createdAt = $openagenda->toUTCDateTime($post->post_date);
		$vevent->updatedAt = $openagenda->toUTCDateTime($post->post_modified);
		$vevent->private = 0;
		$vevent->timezone = 'Europe/Paris';
		$vevent->attendanceMode = 1; //(offline): Participation physique au lieu où se déroule l'événement, cf sanitize_event
		$post_url = get_permalink($post->ID);
		if( strpos( $post_url, 'http:' ) !== false ) //DEBUG
			$post_url = 'https://agenda-partage.fr';
		$vevent->onlineAccessLink = $post_url;
		/* $vevent->links = [ [
			'link' => $post_url,
			'data' => [
				'url' => $post_url,
				"type"=> "rich",
				"version"=> "1.0",
				"title"=> substr( $post->post_title, 0, 140 ),
				"author"=> "Agenda partagé",
				// "author_url"=> "https://www.calameo.com/accounts/53137",
				"provider_name"=> "Agenda partagé",
				"description"=> $post->post_content,
				// "thumbnail_url"=> "https://p.calameoassets.com/210205112752-fc92911ad9.../p1.jpg",
				// "thumbnail_width"=> 1125,
				// "thumbnail_height"=> 1596,
				"html"=> "<div style=\"left: 0; width: 100%;></div>",
				"cache_age"=> 86400
			]
		] ]; */
		
		$vevent->wp_post_id = $post->ID; 
		$vevent->extIds = [ [ 'key' => AGDP_TAG
							, 'value' => sprintf('%d@%s', $post->ID, get_site_url() ) ] ]; 
		
		// Add status & state
		// Details inutiles car tout non publish est supprimé
		switch($post->post_status){
			case 'publish':
				$vevent->state = 2;
				$vevent->status = 1;
				break;
			case 'trash':
				$vevent->state = -1;
				$vevent->status = 6;
				break;
			case 'draft':
				$vevent->state = 0;
				$vevent->status = 6;
				break;
			default:
				$vevent->state = 0;
				$vevent->status = 6;
				break;
		}

		// Add description
		$vevent->title = [ 'fr' => substr( $post->post_title, 0, 140 ) ];
		$vevent->description = [ 'fr' => substr( $post->post_title, 0, 200 ) ];
		$vevent->longDescription = [ 'fr' => substr( $post->post_content, 0, 10000 ) ];
		
		// debug_log( __FUNCTION__, wp_date('Ymd H:i:s'), $openagenda->toUTCDateTime(wp_date('Ymd H:i:s')));
		// debug_log( __FUNCTION__, $metas['date_start'], $openagenda->toUTCDateTime($metas['date_start']));
		// die();
		
		$vevent->timings = [[]];
		// add start date
		if( ! empty($metas['date_start']) )
			$vevent->timings[0]['begin'] = $openagenda->toUTCDateTime($metas['date_start'], true);

		// add end date
		if( ! empty($metas['date_end']) )
			$vevent->timings[0]['end'] = $openagenda->toUTCDateTime($metas['date_end']);
		else{
			$vevent->timings[0]['end'] = $openagenda->toUTCMidnight($metas['date_start']);
		}
		// openagenda_event_uid
		if( ! empty($metas['openagenda_event_uid']) )
			$vevent->uid = $metas['openagenda_event_uid'];

		//DEBUG
		// $vevent->title['fr'] = 'test '.$vevent->title['fr'];
		// $vevent->test = 1;
		
		// debug_log( __FUNCTION__, $vevent);
		
		return $vevent;
	}
	
	
	/**********************************************************/
	
	/**
	 * Retourne les données ICS pour le téléchargement de l'export des évènements
	 *
	 *  $filters['set_post_status']
	 */
	public static function export_posts_ics($posts, $filters = false){

		require_once(AGDP_PLUGIN_DIR . "/includes/icalendar/zapcallib.php");
		
		$iCal = static::get_new_ZCiCal();
		foreach($posts as $post){
			if( is_numeric($post) )
				$post = get_post($post);
			static::add_post_to_ZCiCal($post, $iCal, $filters);
		}
		return $iCal->export();
	}
	
	public static function get_new_ZCiCal(){
		$ical= new ZCiCal();
		//TITLE
		$datanode = new ZCiCalDataNode("TITLE:" . ZCiCal::formatContent( get_bloginfo( 'name', 'display' )));
		$ical->curnode->data[$datanode->getName()] = $datanode;
		//DESCRIPTION
		$page_option = self::get_posts_page_option();
		$page_id = Agdp::get_option($page_option);
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
	
	/**
	 * add_post_to_ZCiCal
	 */
	public static function add_post_to_ZCiCal($post, $ical_or_vevent, $filters = false, $metas = false){
		if( is_a($ical_or_vevent, 'ZCiCal') ){
			$ical = $ical_or_vevent;
			$vevent = new ZCiCalNode("VEVENT", $ical->curnode);
		}
		else 
			$vevent = $ical_or_vevent;

		// metas
		if( ! is_array($metas) ){
			$metas = get_post_meta($post->ID, '', true);
			foreach($metas as $key=>$value)
				if(is_array($value))
					$metas[$key] = implode(', ', $value);
		}

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

		// UID is a required item in VEVENT, create unique string for this event
		// Adding your domain to the end is a good way of creating uniqueness
		$uid = Agdp_post::get_uid($post);
		$vevent->addNode(new ZCiCalDataNode("UID:" . $uid));

		// Add description
		$vevent->addNode(new ZCiCalDataNode("DESCRIPTION:" . ZCiCal::formatContent( $post->post_content)));
		
		// add start date
		if( ! empty($metas['date_start']) )
			$vevent->addNode(new ZCiCalDataNode("DTSTART;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_start'])));

		// add end date
		if( ! empty($metas['date_end']) )
			$vevent->addNode(new ZCiCalDataNode("DTEND;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_end'])));

		// Add fields
		$secretcode_argument = static::get_secretcode_argument(); 
		if( ! empty($filters[$secretcode_argument]) )
			$fields[ strtoupper($secretcode_argument) ] = static::get_field_prefix() . $secretcode_argument;
		
		$fields = [
			AGDP_IMPORT_UID=>AGDP_IMPORT_UID,
			AGDP_IMPORT_REFUSED=>AGDP_IMPORT_REFUSED,
		];
		foreach($fields as $node_name => $meta_key)
			if( ! empty( $metas[$meta_key]))
				$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $metas[$meta_key])));
			
			
		return $vevent;
	}
	
	/**
	 * sanitize_datetime
	 */
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

	
	/************************
	 * Export posts and terms
	 *
	 * used for packages
	 */
	/**
	 * Export posts as an objects array
	 *
	 * $options['include_terms'] as ids Array
	 */
	public static function export_posts_object( $posts, $options = false ) {
		$action = 'export';
		
		if( ! is_array($options) )
			$options = [];
		$include_terms = isset($options['include_terms']) ? $options['include_terms'] : false;
		
		$data = [];
		$post_type_taxonomies = [];
		$taxonomies = [];
		$used_terms = [];
		foreach( $posts as $post_id ){
			if( is_a($post_id, 'WP_Post') ){
				$post = $post_id;
				$post_id = $post->ID;
			}
			else
				$post = get_post( $post_id );
			
			$meta_input = get_post_meta($post->ID, '', true);//TODO ! true
			$metas = [];
			if( ! isset($meta_input['error'])){
				foreach($meta_input as $meta_name => $meta_value){
					if( $meta_name[0] === '_' )
						continue;
					
					// $meta_value = implode("\r\n", $meta_value);
					// if(is_serialized($meta_value))
						// $meta_value = var_export(unserialize($meta_value), true);
					if( is_array($meta_value) && count($meta_value) === 1 )
						$meta_value = $meta_value[0];
					$metas[ $meta_name ] = $meta_value;
				}
			}
			$post->post_password = null;
			$post_data = [
				'post' => $post,
				'metas' => $metas,
			];
			
			$post_type = $post->post_type;
			if( isset( $post_type_taxonomies[$post_type] ) )
				$post_taxonomies = $post_type_taxonomies[$post_type];
			else {
				$post_taxonomies = get_taxonomies([ 'object_type' => [$post_type] ], 'objects');
				$post_type_taxonomies[$post_type] = $post_taxonomies;
				$taxonomies = array_merge( $taxonomies, $post_taxonomies );
			}
			$post_terms = [];
			foreach( $post_taxonomies as $tax_name => $taxonomy ){
				$tax_terms = wp_get_post_terms($post_id, $tax_name);;
				foreach( $tax_terms as $term ){
					if( ! isset( $used_terms[ $term->term_id.'' ] ) )
						$used_terms[ $term->term_id.'' ] = $term;
					
					if( ! isset( $post_terms[$tax_name] ) )
						$post_terms[$tax_name] = [];
					if( ! isset( $post_terms[$tax_name][$term->term_id.''] ) )
						$post_terms[$tax_name][ $term->term_id.'' ] = $term->slug;
				}
			}
			if( $post_terms )
				$post_data['terms'] = $post_terms;
			
			$post_data = apply_filters( AGDP_TAG . '_export_object_' . $post_type, $post_data, $post_id );
			
			if( $post_data )
				$data[] = $post_data;
		}
		
		if( $used_terms || $include_terms ){
			if( $include_terms ) 
				$include_terms = array_merge( array_keys($used_terms), $include_terms);
			else
				$include_terms = array_keys($used_terms);
			//get_terms
			$in_terms = get_terms([ 
				'taxonomy' => array_keys($taxonomies),
				'include' => $include_terms,
				'hide_empty' => false, 
				'fields' => 'all',
			]);
			
			if( count($in_terms) >= 0 ){
				//get_terms_export
				$data['terms'] = static::export_terms_object( $in_terms );
				if( $data['terms'] ){
					//taxonomies
					$data['taxonomies'] = [];
					foreach($in_terms as $term){
						if( ! isset($data['taxonomies'][$term->taxonomy]) ){
							$data['taxonomies'][$term->taxonomy] = $term->taxonomy; //$taxonomies[$term->taxonomy];
						}
					}
				}
			}
		}
		
		return $data;
		
	}
	
	/**
	 * Export taxonomies
	 */
	public static function export_terms_object( $terms ) {
		$action = 'export';
		$data = [];
		foreach( $terms as $term_id ){
			if( is_a($term_id, 'WP_Term') ){
				$term = $term_id;
				$term_id = $term->term_id;
			}
			else
				$term = get_term( $term_id );
			
			$meta_input = get_term_meta($term->term_id, '', true);//TODO ! true
			$metas = [];
			if( ! isset($meta_input['error'])){
				foreach($meta_input as $meta_name => $meta_value){
					if( $meta_name[0] === '_' )
						continue;
					if( is_array($meta_value) && count($meta_value) === 1 )
						$meta_value = $meta_value[0];
					$metas[ $meta_name ] = $meta_value;
				}
			}
			$data[] = [
				'term' => $term,
				'metas' => $metas,
			];
		}
		
		return $data;
	}
}
