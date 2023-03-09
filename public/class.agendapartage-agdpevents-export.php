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
	 public static function do_export($posts, $file_format = 'ics', $return = 'url' ){
		
		switch( strtolower( $file_format )){
			case 'vcalendar':
				$file_format = 'ics';
			case 'ics':
				$export_data = self::export_posts_ics($posts, true);
				break;
			default:
				return sprintf('format inconnu : "%s"', $file_format);
		}

		if($return === 'data')
			return $export_data;
		
		if( ! $export_data)
			return sprintf('Aucune donnée à exporter');
		
		self::clear_export_history();
		
		$file = self::get_export_filename( $file_format );

		$handle = fopen($file, "w");
		fwrite($handle, $export_data);
		fclose($handle);
		
		if($return === 'file')
			return $file;
		
		$url = self::get_export_url(basename($file));
		
		return $url;
		
	}
	
	/**
	 * Retourne le nom d'un fichier temporaire pour le téléchargement de l'export des évènements
	 */
	public static function export_posts_ics($posts){

		require_once(AGDP_PLUGIN_DIR . "/includes/icalendar/zapcallib.php");
		
		$iCal = self::get_new_ZCiCal();
		foreach($posts as $post){
			self::add_agdpevent_to_ZCiCal($post, $iCal);
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
	
	public static function add_agdpevent_to_ZCiCal($post, $ical){
		$metas = get_post_meta($post->ID, '', true);
		foreach($metas as $key=>$value)
			if(is_array($value))
				$metas[$key] = implode(', ', $value);
		$metas['date_start'] = self::sanitize_datetime($metas['ev-date-debut'], $metas['ev-heure-debut']);
		$metas['date_end'] = self::sanitize_datetime($metas['ev-date-fin'], $metas['ev-heure-fin'], $metas['ev-date-debut']);
				
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
		$vevent->addNode(new ZCiCalDataNode("DTEND;TZID=Europe/Paris:" . ZCiCal::fromSqlDateTime($metas['date_end'])));

		// UID is a required item in VEVENT, create unique string for this event
		// Adding your domain to the end is a good way of creating uniqueness
		$parse = parse_url(content_url());
		$uid = sprintf('%s[%d]@%s', AgendaPartage_Evenement::post_type, $post->ID, $parse['host']);
		$vevent->addNode(new ZCiCalDataNode("UID:" . $uid));

		// Add description
		$vevent->addNode(new ZCiCalDataNode("DESCRIPTION:" . ZCiCal::formatContent( $post->post_content)));

		// Add fields
		foreach([
			'LOCATION'=>'ev-localisation'
			, 'ORGANISATEUR'=>'ev-organisateur'
			, 'EMAIL'=>'ev-email'
			, 'PHONE'=>'ev-phone'
		] as $node_name => $meta_key)
			if( ! empty( $metas[$meta_key]))
				$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $metas[$meta_key])));

		// Add terms
		foreach([ 
			'CATEGORIES' => AgendaPartage_Evenement::taxonomy_type_agdpevent
			, 'CITIES' => AgendaPartage_Evenement::taxonomy_city
			, 'PUBLICATIONS' => AgendaPartage_Evenement::taxonomy_publication
		] as $node_name => $tax_name){
			$terms = AgendaPartage_Evenement::get_event_terms ($tax_name, $post->ID, 'names');
			if($terms){
				//$terms = array_map(function($tax_name){ return str_replace(',','-', $tax_name);}, $terms);//escape ','
				foreach($terms as $term_name)
					$vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( $term_name)));
					
				// $vevent->addNode(new ZCiCalDataNode($node_name . ':' . ZCiCal::formatContent( implode(',', $terms) )));
			}
		}
		return $vevent;
	}
	
	public static function sanitize_datetime($date, $time, $date_alt = null){
		if( ! $date )
			$date = $date_alt;
		if( ! $date )
			return;
		$dateTime = rtrim($date . ' ' . str_replace('h', ':', $time));
		if($dateTime[strlen($dateTime)-1] === ':')
			$dateTime .= '00';
		return $dateTime;
	}
		
	public static function get_vcalendar_status($post){
		switch($post->post_status){
			case 'publish' :
				return 'CONFIRMED';
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
