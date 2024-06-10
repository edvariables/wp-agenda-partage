<?php

/**
 * AgendaPartage -> Evenements
 * Collection d'évènements
 */
class Agdp_Evenements_Import extends Agdp_Posts_Import {
	
	const post_type = Agdp_Evenement::post_type;
	
	
	/**
	* import_ics
	*/
	public static function import_ics($file_name, $data = 'publish', $original_file_name = null){
		$iCal = self::get_vcalendar($file_name);
		
		$import_source = 'import_ics_' . $iCal['title'];
		
		$post_statuses = self::get_post_statuses();
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
			, date_i18n('d/m/Y H:i:s', strtotime($iCal['posts'][0]['dtstamp']))
			, empty($iCal['description']) ? '' : $iCal['description']);
		
		$default_post_status = 'publish';
		if( is_string($data) ){
			$default_post_status = $data;
			$data = [ 'post_status' => $data ];
		}
		elseif( is_array( $data ) ){
			if( ! empty( $data['post_status'] ) )
				$default_post_status = $data['post_status'];
		}
		
		if(($user = wp_get_current_user())
		&& $user->ID){
		    $default_post_author = $user->ID;
		}
		else {
			$default_post_author = Agdp_User::get_blog_admin_id();
		}
		// debug_log("import_ics events", $iCal['posts'], "\r\n\r\n\r\n\r\n");
		foreach($iCal['posts'] as $event){
			$post_title = $event['summary'];
			
			switch(strtoupper($event['status'])){
				case 'CONFIRMED':
				case 'TENTATIVE':
					$post_status = $default_post_status;
					break;
				case 'DRAFT':
					$post_status = 'draft';
					break;
				case 'CANCELLED':
				case 'TRASH':
					$post_status = 'trash';//TODO signaler
					break;
				default: 
					debug_log('[UNKNOWN]$event->status = ' . $event['status']);
					$ignoreCounter++;
					continue 2;
			}
			
			if( $existing_post = self::get_existing_post($event) ){
					
				if( $post_status !== 'trash' ){
					$meta_name = AGDP_IMPORT_REFUSED;
					if( get_post_meta( $existing_post->ID, $meta_name, true ) ){
						$ignoreCounter++;
						debug_log('[REFUSED]post_status = ' . $meta_name . ' / ' . var_export($post_title, true));
						continue;
					}
				}
				
				if( $post_status !== 'publish' ){
					
					Agdp_Evenement::change_post_status($existing_post->ID, $post_status);
					$successCounter++;
					debug_log('[UPDATE]post_status = ' . $post_status . ' / ' . var_export($post_title, true));
					continue;
				}
				//Update
			}
			
			if( $post_status === 'trash' ){
				$ignoreCounter++;
				debug_log('[IGNORE]trash = ' . var_export($post_title, true));
				continue;
			}
			
			$dateStart = $event['dtstart'];
			$dateEnd = empty($event['dtend']) ? '' : $event['dtend'];
			$timeStart = substr($dateStart, 11, 5);//TODO
			$timeEnd = substr($dateEnd, 11, 5);//TODO 
			if($timeStart == '00:00')
				$timeStart = '';
			else
				$timeStart =  preg_replace( '/^0([1-9])/', '$1', $timeStart);
			if($timeEnd == '00:00')
				$timeEnd = '';
			else
				$timeEnd =  preg_replace( '/^0([1-9])/', '$1', $timeEnd);
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
				'ev-localisation' => empty($event['location']) ? '' : trim($event['location']),
				'ev-organisateur' => empty($event['organisateur']) ? '' : trim($event['organisateur']),
				'ev-email' => empty($event['email']) ? '' : trim($event['email']),
				'ev-user-email' => empty($event['user-email']) ? '' : trim($event['user-email']),
				'ev-phone' => empty($event['phone']) ? '' : trim($event['phone']),
				AGDP_IMPORT_UID => empty($event['uid']) ? '' : $event['uid'],
				'ev-date-journee-entiere' => $timeStart ? '' : '1',
				// 'ev-codesecret' => Agdp::get_secret_code(6),
				'_post-source' => $import_source
			);
			
			//ev-codesecret
			if( ! empty($event[ strtoupper(Agdp_Evenement::secretcode_argument) ]) )
				$inputs['ev-codesecret'] = $event[ strtoupper(Agdp_Evenement::secretcode_argument) ];
			else
				$inputs['ev-codesecret'] = Agdp::get_secret_code(6);
		
			//meta_input in arguments
			if( ! empty( $data['meta_input'] ) ){
				$inputs = array_merge( $data['meta_input'], $inputs );
			}
			
			//email
			if( ! empty($inputs['ev-user-email']) )
				$user_email = $inputs['ev-user-email'];
			elseif( ! empty($inputs['ev-email']) )
				$user_email = $inputs['ev-email'];
			else
				$user_email = false;
			
			//description
			$post_content = empty($event['description']) ? '' : trim($event['description']);
			if ($post_content === null) $post_content = '';
			
			if( ! $existing_post ){
				//Check doublon
				$doublon = Agdp_Evenement_Edit::get_post_idem($post_title, $inputs);
				if($doublon){
					//var_dump($doublon);var_dump($post_title);var_dump($inputs);
					debug_log('[IGNORE]$doublon = ' . var_export($post_title, true));
					$ignoreCounter++;
					$url = Agdp_Evenement::get_post_permalink($doublon);
					$log[] = sprintf('<li><a href="%s">%s</a> existe déjà, avec le statut "%s".</li>', $url, htmlentities($doublon->post_title), $post_statuses[$doublon->post_status]);
					continue;				
				}
			}
			
			// terms
			$all_taxonomies = Agdp_Evenement_Post_type::get_taxonomies();
			
			$post_type_taxonomies = array_change_key_case( Agdp_Evenement::get_taxonomies(), CASE_LOWER );
			$taxonomies = [];
			foreach($post_type_taxonomies as $taxonomy){
				$tax_name = $taxonomy['name'];
				$node_name = $taxonomy['filter'];
				if( empty($event[$node_name]))
					continue;
				if( is_string($event[$node_name]))
					$event[$node_name] = explode(',', $event[$node_name]);
				$taxonomies[$tax_name] = [];
				$all_terms = Agdp_Evenement::get_all_terms($tax_name, 'name'); //indexé par $term->name
				$all_terms_lowercase = array_change_key_case( $all_terms );
				foreach($event[$node_name] as $term_name){
					if( ! array_key_exists( strtolower($term_name), $all_terms_lowercase)){
						$data = [
							'post_type'=>Agdp_Evenement::post_type,
							'taxonomy'=>$tax_name,
							'term'=>$term_name
						];
						$log[] = sprintf('<li>Dans la taxonomie "%s", le terme "<b>%s</b>" n\'existe pas. %s</li>'
							, $all_taxonomies[$tax_name]['label']
							, htmlentities($term_name)
							, Agdp::get_ajax_action_link(false, 'insert_term', 'add', 'Cliquez ici pour l\'ajouter', 'Crée un nouveau terme', true, $data)
						);
						continue;
					}
					$taxonomies[$tax_name][] =  $all_terms_lowercase[strtolower($term_name)]->term_id;
				}
			}
			
			$postarr = array(
				'post_title' => $post_title,
				'post_name' => sanitize_title( $post_title ),
				'post_type' => Agdp_Evenement::post_type,
				'meta_input' => $inputs,
				'post_content' =>  $post_content,
				'post_status' => $post_status,
				'tax_input' => $taxonomies
			);
			
			// terms
			$taxonomies = [];
			foreach($post_type_taxonomies as $node_name => $taxonomy){
				if( ! empty($event[$node_name]))
					$taxonomies[$taxonomy['filter']] = $event[$node_name];
			}
			
			#DEBUG
			// if( strlen($postarr['post_title']) >= 10 ){
				// $postarr['post_title'] = substr($postarr['post_title'], 0, 5) . "[...]";
				// $postarr['post_name'] = sanitize_title( $postarr['post_title'] );
			// }
			// if( strlen($postarr['post_content']) >= 10 )
				// $postarr['post_content'] = substr($postarr['post_content'], 0, 5) . "[...]";
			
			if( $existing_post ){
				$postarr['ID'] = $existing_post->ID;
				//update
				$post_id = wp_update_post( $postarr, true );
			}
			else {
				if( $user_email && ($post_author = email_exists($user_email)) ){
					if( is_multisite() ){
						$blogs = get_blogs_of_user($post_author, false);
						if( ! isset( $blogs[ get_current_blog_id() ] ) )
							$post_author = $default_post_author;
					}
				}
				else
					$post_author = $default_post_author;
				$postarr['post_author'] = $post_author;
				//insert
				$post_id = wp_insert_post( $postarr, true );
			}
			
			if( ! $post_id || is_wp_error( $post_id )){
				$failCounter++;
				if( $existing_post )
					debug_log('[UPDATE]' . $existing_post->ID);
				debug_log('[INSERT ERROR]$post_title = ' . var_export($post_title, true));
				debug_log('[INSERT ERROR+]$post_content = ' . var_export($post_content, true));
				$log[] = '<li class="error">Erreur de création de l\'évènement</li>';
				if(is_wp_error( $post_id)){
					debug_log('[INSERT ERROR+]$post_id = ' . var_export($post_id, true));
					$log[] = sprintf('<pre>%s</pre>', var_export($post_id, true));
				}
				$log[] = sprintf('<pre>%s</pre>', var_export($event, true));
				$log[] = sprintf('<pre>%s</pre>', var_export($postarr, true));
			}
			else{
				$successCounter++;
				$post = get_post($post_id);
				$url = Agdp_Evenement::get_post_permalink($post);
				$log[] = sprintf('<li><a href="%s">%s</a> a été importé avec le statut "%s"%s</li>'
						, $url, htmlentities($post->post_title)
						, $post_statuses[$post->post_status]
						, $post->post_status != $default_post_status ? ' !' : '.'
				);
			}
		}
		
		$log[] = sprintf('<li><b>%d importation(s), %d échec(s), %d ignorée(s)</b></li>', $successCounter, $failCounter, $ignoreCounter);
		debug_log('[FINAL REPORT] ' . sprintf('%d importation(s), %d echec(s), %d ignoree(s)', $successCounter, $failCounter, $ignoreCounter));
		$log[] = '</ul>';
		
		if(class_exists('Agdp_Admin'))
			Agdp_Admin::set_import_report ( $log );
		
		return $successCounter;
	}
}
