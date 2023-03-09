<?php

/**
 * AgendaPartage -> Evenements
 * Collection d'évènements
 */
class AgendaPartage_Evenements_Import {
	
	
	/**
	* import_ics
	*/
	public static function import_ics($file_name, $default_post_status = 'publish', $original_file_name = null){
		// require_once( AGDP_PLUGIN_DIR . '/admin/class.ical.php' );				
		// $iCal = new iCal($file_name);
		$iCal = self::get_vcalendar($file_name);
		
		$import_source = 'import_ics_' . $iCal['title'];
		
		$post_statuses = get_post_statuses();
		$today = strtotime(wp_date("Y-m-d"));
		$successCounter = 0;
		$failCounter = 0;
		$ignoreCounter = 0;
		$log = array();
		$log[] = sprintf('<ul><b>Importation ICS "%s", %s</b>'
			, isset($original_file_name) && $original_file_name ? $original_file_name : basename( $file_name )
			, date_i18n('Y-m-d H:i'));
		$log[] = sprintf('<ul><b>Source : "%s", le %s - %s</b>'
			, empty($iCal['title']) ? '' : $iCal['title']
			, date_i18n('d/m/Y H:i:s', strtotime($iCal['events'][0]['dtstamp']))
			, empty($iCal['description']) ? '' : $iCal['description']);
		
		if(!$default_post_status)
			$default_post_status = 'publish';
		
		if(($user = wp_get_current_user())
		&& $user->ID){
		    $post_author = $user->ID;
		}
		else {
			$post_author = AgendaPartage_User::get_blog_admin_id();
		}
	
		foreach($iCal['events'] as $event){
			
			switch(strtoupper($event['status'])){
				case 'CONFIRMED':
				case 'TENTATIVE':
					$post_status = $default_post_status;
					break;
				case 'DRAFT':
					$post_status = 'draft';
					break;
				case 'CANCELLED':
					$post_status = 'trash';//TODO signaler
					break;
				default: 
					debug_log('[UNKNOWN]$event->status = ' . $event['status']);
					$ignoreCounter++;
					continue 2;
			}
			// if(($successCounter + $ignoreCounter) > 5) break;//debug
			
			$dateStart = $event['dtstart'];
			$dateEnd = $event['dtend'];
			$timeStart = substr($dateStart, 11, 5);//TODO
			$timeEnd = substr($dateEnd, 11, 5);//TODO 
			if($timeStart == '00:00')
				$timeStart = '';
			if($timeEnd == '00:00')
				$timeEnd = '';
			$dateStart = substr($dateStart, 0, 10);
			$dateEnd = substr($dateEnd, 0, 10);
			if(strtotime($dateStart) < $today) {
					debug_log('[IGNORE]$dateStart = ' . $dateStart);
				$ignoreCounter++;
				continue;
			}
			
			$inputs = array(
				'ev-date-debut' => $dateStart,
				'ev-date-fin' => $dateEnd,
				'ev-heure-debut' =>$timeStart,
				'ev-heure-fin' => $timeEnd,
				'ev-localisation' => empty($event['location']) ? '' : $event['location'],
				'ev-organisateur' => empty($event['organisateur']) ? '' : $event['organisateur'],
				'ev-email' => empty($event['email']) ? '' : $event['email'],
				'ev-phone' => empty($event['phone']) ? '' : $event['phone'],
				'ev-import-uid' => empty($event['uid']) ? '' : $event['uid'],
				'ev-date-journee-entiere' => $timeStart ? '' : '1',
				'ev-codesecret' => AgendaPartage::get_secret_code(6),
				'post-source' => $import_source
			);
						
			$post_title = $event['summary'];
			$post_content = $event['description'];
			if ($post_content === null) $post_content = '';
			
			//Check doublon
			$doublon = AgendaPartage_Evenement_Edit::get_post_idem($post_title, $inputs);
			if($doublon){
				//var_dump($doublon);var_dump($post_title);var_dump($inputs);
				debug_log('[IGNORE]$doublon = ' . var_export($doublon, true));
				$ignoreCounter++;
				$url = AgendaPartage_Evenement::get_post_permalink($doublon);
				$log[] = sprintf('<li><a href="%s">%s</a> existe déjà, avec le statut "%s".</li>', $url, htmlentities($doublon->post_title), $post_statuses[$doublon->post_status]);
				continue;				
			}
			
			// terms
			$taxonomies = [];
			foreach([ 
				'CATEGORIES' => AgendaPartage_Evenement::taxonomy_type_agdpevent
				, 'CITIES' => AgendaPartage_Evenement::taxonomy_city
				, 'PUBLICATIONS' => AgendaPartage_Evenement::taxonomy_publication
			] as $node_name => $tax_name){
				$node_name = strtolower($node_name);
				if( empty($event[$node_name]))
					continue;
				if( is_string($event[$node_name]))
					$event[$node_name] = explode(',', $event[$node_name]);
				$taxonomies[$tax_name] = [];
				$all_terms = AgendaPartage_Evenement_Post_type::get_all_terms($tax_name, 'name'); //indexé par $term->name
				foreach($event[$node_name] as $term_name){
					if( ! array_key_exists($term_name, $all_terms)){
						$log[] = sprintf('<li>Dans la taxonomie %s, le terme "%s" n\'existe pas.</li>', $tax_name, htmlentities($term_name));
						//TODO : create ?
						continue;
					}
					$taxonomies[$tax_name][] =  $all_terms[$term_name]->term_id;
				}
			}
			
			$postarr = array(
				'post_title' => $post_title,
				'post_name' => sanitize_title( $post_title ),
				'post_type' => AgendaPartage_Evenement::post_type,
				'post_author' => $post_author,
				'meta_input' => $inputs,
				'post_content' =>  $post_content,
				'post_status' => $post_status,
				'tax_input' => $taxonomies
			);
			
			// terms
			$taxonomies = [];
			foreach([ 
				'CATEGORIES' => AgendaPartage_Evenement::taxonomy_type_agdpevent
				, 'CITIES' => AgendaPartage_Evenement::taxonomy_city
				, 'PUBLICATIONS' => AgendaPartage_Evenement::taxonomy_publication
			] as $node_name => $term_name){
				if( ! empty($event[strtolower($node_name)]))
					$taxonomies[$term_name] = $event[strtolower($node_name)];
			}
			
			$post_id = wp_insert_post( $postarr, true );
			
			if(!$post_id || is_wp_error( $post_id )){
				$failCounter++;
				$log[] = '<li class="error">Erreur de création de l\'évènement</li>';
				if(is_wp_error( $post_id)){
					$log[] = sprintf('<pre>%s</pre>', var_export($post_id, true));
				}
				$log[] = sprintf('<pre>%s</pre>', var_export($event, true));
				$log[] = sprintf('<pre>%s</pre>', var_export($postarr, true));
			}
			else{
				$successCounter++;
				$post = get_post($post_id);
				$url = AgendaPartage_Evenement::get_post_permalink($post);
				$log[] = sprintf('<li><a href="%s">%s</a> a été importé avec le statut "%s"%s</li>'
						, $url, htmlentities($post->post_title)
						, $post_statuses[$post->post_status]
						, $post->post_status != $default_post_status ? ' !' : '.'
				);
			}
		}
		
		$log[] = sprintf('<li><b>%d importation(s), %d échec(s), %d ignorée(s)</b></li>', $successCounter, $failCounter, $ignoreCounter);
		$log[] = '</ul>';
		if(class_exists('AgendaPartage_Admin'))
			AgendaPartage_Admin::set_import_report ( $log );
		
		return $successCounter;
	}
	/**
	 * get_vcalendar($file_name)
	 */
	public static function get_vcalendar($file_name){
		require_once(AGDP_PLUGIN_DIR . "/includes/icalendar/zapcallib.php");	
		$ical= new ZCiCal(file_get_contents($file_name));
		$vcalendar = [];
		
		// debug_log($ical->tree->data);
		
		foreach($ical->tree->data as $key => $value){
			$key = strtolower($key);
			if(is_array($value)){
				$vcalendar[$key] = '';
				for($i = 0; $i < count($value); $i++){
					$p = $value[$i]->getParameters();
					if($vcalendar[$key])
						$vcalendar[$key] .= ',';
					$vcalendar[$key] .= $value[$i]->getValues();
				}
			} else {
				$vcalendar[$key] = $value->getValues();
			}
		}
		
		if(empty($iCal['description']))
			$iCal['description'] = 'vcalendar_' . wp_date('Y-m-d H:i:s');
		if(empty($iCal['title']))
			$iCal['title'] = $iCal['description'];
		
		$vevents = [];
		if(isset($ical->tree->child)) {
			foreach($ical->tree->child as $node) {
				// debug_log($node->data);
				if($node->getName() == "VEVENT") {
					$vevent = [];
					foreach($node->data as $key => $value) {
						$key = strtolower($key);
						if(is_array($value)){
							$vevent[$key] = [];
							$vevent[$key .'[parameters]'] = [];
							for($i = 0; $i < count($value); $i++) {
								$vevent[$key][] = $value[$i]->getValues();
								$p = $value[$i]->getParameters();
								if($p){
									$vevent[$key .'[parameters]'][] = $p;
								}
							}
						} else {
							if( isset($vevent[$key]) ){
								if( ! is_array($vevent[$key])){
									$vevent[$key] = [$vevent[$key]];
									if(isset($vevent[$key .'[parameters]']))
										$vevent[$key .'[parameters]'] = [$vevent[$key .'[parameters]']];
								}
								$vevent[$key][] = $value->getValues();
							}
							else
								$vevent[$key] = $value->getValues();
							$p = $value->getParameters();
							if($p){
								if(!empty($vevent[$key .'[parameters]']) && is_array($vevent[$key .'[parameters]']))
									$vevent[$key .'[parameters]'][] = $p;
								else
									$vevent[$key .'[parameters]'] = $p;
							}
						}
					}
					$vevent['dtstart'] = date('Y-m-d H:i:s', strtotime($vevent['dtstart'])); 
					$vevent['dtend'] = date('Y-m-d H:i:s', strtotime($vevent['dtend'])); 
					$vevents[] = $vevent;
				}
			}
		}
		
		$vcalendar['events'] = $vevents;
		debug_log($vcalendar);
		return $vcalendar;
	}
	/*
	**/
}
