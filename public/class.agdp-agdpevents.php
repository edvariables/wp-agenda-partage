<?php

/**
 * AgendaPartage -> Evenements
 * Collection d'évènements
 */
class Agdp_Evenements extends Agdp_Posts {

	const post_type = Agdp_Evenement::post_type;
	const postid_argument = Agdp_Evenement::postid_argument;
	const page_id_option = Agdp_Evenement::posts_page_option;
	const newsletter_diffusion_term_id = 'agdpevents_nl_diffusion_term_id';
	
	const icon = 'calendar-alt';
	
	private static $initiated = false;
	
	public static $default_posts_query = [];

	public static function init() {
		if ( ! self::$initiated ) {
			parent::init();
			
			self::init_default_posts_query();
			
			self::$initiated = true;

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		parent::init_hooks();
	}
	/*
	 * Hook
	 ******/
	
	public static function init_default_posts_query() {
		
		if( current_user_can('moderate_comments') )
			$post_status = [ 'publish', 'pending' ];
		else
			$post_status = 'publish';
		
		self::$default_posts_query = array(
			'post_type' => Agdp_Evenement::post_type,
			'post_status' => $post_status,
			
			// BUGG du OR qui fait qu'il manque un meta_key = 'ev-date-debut'
			'meta_query' => [
				'relation' => 'OR',
				'ev-date-debut' => [ 
					'key' => 'ev-date-debut',
					'value' => wp_date('Y-m-d'),
					'compare' => '>=',
					'type' => 'DATE'
				],
				'ev-date-fin' => [
					'relation' => 'AND',
					[
						'key' => 'ev-date-fin',
						'value' => '',
						'compare' => '!='
					],
					[
						'key' => 'ev-date-fin',
						'value' => wp_date('Y-m-d'),
						'compare' => '>=',
						'type' => 'DATE'
					]
				]
			],
			'orderby' => [
				'ev-date-debut' => 'ASC',
				'ev-heure-debut' => 'ASC',
			],
			
			'posts_per_page' => self::$default_posts_per_page
		);
	}
	
	/**
	 * Recherche des évènements d'un mois
	 */
	public static function get_month_posts($month){
		$today_month = date('Y-m');
		if( ! $month) $month = $today_month;
		$date_min = substr($month, 0,4) . '-' . substr($month, 5,2) . '-' . ($month === $today_month ? date('d') : '01');
		if(substr($month, 5,2) === '12')
			$date_max = ((int)substr($month, 0,4) + 1) . '-01-01';
		else
			$date_max = substr($month, 0,4) . '-' . sprintf('%02d', ((int)substr($month, 5,2) + 1 )) . '-01';
		$date_01 = substr($month, 0,4) . '-' . substr($month, 5,2) . '-' . '01';
		//TODO events à cheval sur deux mois : n'apparait que dans en fin du 1er mois ! ( $date_01 devrait être plus tôt )
		$query = array(
			'meta_query' => [
				// 'relation' => 'AND',
				// [ [
					// 'key' => 'ev-date-debut',
					// 'value' => $date_min,
					// 'compare' => '>=',
					// 'type' => 'DATE',
				// ], [
					// 'key' => 'ev-date-debut',
					// 'value' => $date_max,
					// 'compare' => '<',
					// 'type' => 'DATE',
				// ] ]
				
				'relation' => 'OR',
				[ 
					'relation' => 'AND',
					[ [
						'key' => 'ev-date-debut',
						'compare' => '>=',
						'value' => $date_min,
						'type' => 'DATE',
					], [
						'key' => 'ev-date-debut',
						'compare' => '<',
						'value' => $date_max,
						'type' => 'DATE',
					] ],
				],
				[ 
					'relation' => 'AND',
					[ [
						'key' => 'ev-date-debut',
						'compare' => '>=',
						'value' => $date_01,
						'type' => 'DATE',
					], [
						'key' => 'ev-date-fin',
						'compare' => '>=',
						'value' => $date_min,
						'type' => 'DATE',
					], [
						'key' => 'ev-date-fin',
						'compare' => '<',
						'value' => $date_max,
						'type' => 'DATE',
					] ],
				]
			],
			'orderby' => [
				'ev-date-debut' => 'ASC',
				'ev-heure-debut' => 'ASC',
			],
			'nopaging' => true
			
		);
		
		// debug_log(__FUNCTION__, $query, self::get_filters_query(false));
		$posts = self::get_posts($query, self::get_filters_query(false));
		return $posts;
    }
	

	/**
	 * Recherche de tous les mois contenant des évènements mais aussi les mois sans.
	 * Return array($month => $count)
	 */
	public static function get_posts_months(){
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		$sql_filters = self::get_filters_query(true);
		
		$sql = "SELECT DISTINCT DATE_FORMAT(date_debut.meta_value, '%Y') as year
				, DATE_FORMAT(date_debut.meta_value, '%m') as month
				, COUNT(post_id) as count
				FROM {$blog_prefix}posts posts
				INNER JOIN {$blog_prefix}postmeta date_debut
					ON posts.ID = date_debut.post_id
					AND date_debut.meta_key = 'ev-date-debut'
					AND date_debut.meta_value >= CURDATE()
				WHERE posts.post_status = 'publish'
					AND posts.post_type = '". Agdp_Evenement::post_type ."'
		";
		if($sql_filters)
			$sql .= " AND posts.ID IN ({$sql_filters})";
		
		$sql .= "GROUP BY year, month
				ORDER BY year, month
				";
		$result = $wpdb->get_results($sql);
		
		$months = [];
		$prev_row = false;
		foreach($result as $row){
			if($prev_row)
				//Complète les mois manquants
				if($prev_row->year === $row->year){
					for($m = (int)$prev_row->month + 1; $m < (int)$row->month; $m++)
						$months[$prev_row->year . '-' . sprintf("%02d",$m)] = 0;
				}
				elseif((int)$prev_row->year === (int)$row->year - 1){
					for($m = (int)$prev_row->month + 1; $m <= 12; $m++)
						$months[$prev_row->year . '-' . $m] = 0;
					for($m = 1; $m < (int)$row->month; $m++)
						$months[$row->year . '-' . sprintf("%02d",$m)] = 0;
				}
				//More than one year
				elseif((int)$prev_row->year < (int)$row->year - 1)
					break;
			$months[$row->year . '-' . $row->month] = (int)$row->count;
			$prev_row = $row;
		}
		return $months;
    }

	/**
	 * Retourne les filtres de requête soit en sql soit array('posts_where_filters'=>sql) que get_posts() traite.
	 */
	public static function get_filters_query($return_sql = false, $filters = null){
		$filters = self::get_filters($filters);
		// debug_log('get_filters_query IN ', $filters);
		if(count($filters)){
			$query_tax_terms = [];
			// Taxonomies
			foreach( Agdp_Evenement_Post_type::get_taxonomies() as $tax_name => $taxonomy){
				if(isset($filters[$tax_name]))
					$field = $tax_name;
				else
					$field = $taxonomy['filter'];
				if(isset($filters[$field])){
					$query_tax_terms[$tax_name] = ['IN'=>[]];
					if( ! is_array($filters[$field]) )
						$filters[$field] = [ $filters[$field] => 1];
					foreach($filters[$field] as $term_id => $checked){
						if($term_id === '*'){
							unset($query_tax_terms[$tax_name]);
							break;
						}
						if($term_id == 0){
							$query_tax_terms[$tax_name]['NOT EXISTS'] = true;
						} else {
							$query_tax_terms[$tax_name]['IN'][] = $term_id;
						}
					}
				}
			}
			
			$sql = '';
			if(count($query_tax_terms)){
				global $wpdb;
				$blog_prefix = $wpdb->get_blog_prefix();
				$sql = "SELECT post.ID"
					. "\n FROM {$blog_prefix}posts post";
				//JOIN
				foreach($query_tax_terms as $tax_name => $tax_data){
					$sql .= "\n LEFT JOIN {$blog_prefix}term_relationships tax_rl_{$tax_name}"
								. "\n ON post.ID = tax_rl_{$tax_name}.object_id"
					;
					if($tax_name === Agdp_Evenement::taxonomy_city
					&& count($tax_data['IN'])){
						$sql .=   "\n INNER JOIN {$blog_prefix}postmeta as localisation"
									. "\n ON post.ID = localisation.post_id"
									. "\n AND localisation.meta_key = 'ev-localisation'"
						;
					}
				}
				//WHERE
				$sql_where = '';
				foreach($query_tax_terms as $tax_name => $tax_data){
					if(count($tax_data['IN'])){
						$tax_sql = "\n tax_rl_{$tax_name}.term_taxonomy_id"
								. " IN (" . implode(', ', $tax_data['IN']) . ')'
						;
						if($tax_name === Agdp_Evenement::taxonomy_city){
							$terms_like = Agdp_Evenement_Post_type::get_terms_like($tax_name, $tax_data['IN']);
							foreach($terms_like as $like)
								$tax_sql .= "\n OR localisation.meta_value LIKE '%{$like}%'";
						}
					}
					else
						$tax_sql = '';
					if(isset($tax_data['NOT EXISTS'])){
						if($tax_sql)
							$tax_sql .= ' OR ';
						$tax_sql .= " NOT EXISTS ("
							. "\n SELECT 1"
							. "\n FROM {$blog_prefix}term_relationships taxx"
							. "\n INNER JOIN {$blog_prefix}term_taxonomy taxname"
								. "\n ON taxx.term_taxonomy_id = taxname.term_id"
							. "\n WHERE taxname.taxonomy = '{$tax_name}'"
							. "\n AND taxx.object_id = post.ID"
							. ')';
					}
					$sql_where .= ($sql_where ? "\n AND " : "\n WHERE ") . '(' . $tax_sql . ')';
				}
				$sql .= $sql_where;
				// echo "<pre>";echo($sql);echo "</pre>";
			}
		}
		// debug_log('get_filters_query', isset($sql) ? $sql : '', $query_tax_terms);
		if($return_sql)
			return isset($sql) ? $sql : '';
		if( ! empty($sql))
			return [ 'posts_where_filters' => $sql ];
		return [];
	}
	
	/**
	* Rendu Html des évènements sous forme d'arborescence par mois
	*
	* Optimal sous la forme https://.../agenda-local/?eventid=1207#eventid1207
	*/
	public static function get_list_html($content = '', $options = false){
		if(!isset($options) || !is_array($options))
			$options = array();
		
		$options = array_merge(
			array(
				'ajax' => true,
				'start_ajax_at_month_index' => 2,
				'max_events' => 30,
				'mode' => 'list' //list|email|text|calendar|TODO...
			), $options);
		$options = self::filter_anteriority_option($options, ANTERIORITY_ALL);
		if( $options['mode'] == 'email' ){
			$options['ajax'] = false;
		}
		
		$option_ajax = (bool)$options['ajax'];
		
		$months = self::get_posts_months();
		
		if(isset($options['months']) && $options['months'] !== ANTERIORITY_ALL && $options['months'] > 0 && count($months) > $options['months'])
			$months = array_slice($months, 0, $options['months'], true);
		elseif(isset($options['weeks']) && $options['weeks'] > 0 && count($months) > $options['weeks']/4)
			$months = array_slice($months, 0, 1, true);
		elseif(isset($options['days']) && $options['days'] > 0 && count($months) > 1)
			$months = array_slice($months, 0, 1, true);
		elseif(isset($options['hours']) && $options['hours'] > 0 && count($months) > 1)
			$months = array_slice($months, 0, 1, true);
		
		//Si le premier mois est déjà gros, on diffère le chargement du suivant par ajax
		$events_count = 0;
		$months_count = 0;
		foreach($months as $month => $month_events_count) {
			$events_count += $month_events_count;
			$months_count++;
			if($events_count >= $options['max_events']){
				if($options['start_ajax_at_month_index'] > $months_count)
					$options['start_ajax_at_month_index'] = $months_count;
				break;
			}
		}
		if( $events_count === 0)
			return false;
		
		$requested_id = array_key_exists(AGDP_ARG_EVENTID, $_GET) ? $_GET[AGDP_ARG_EVENTID] : false;
		$requested_month = false;
		if($requested_id){
			$date_debut = get_post_meta($requested_id, 'ev-date-debut', true);
			if($date_debut)
				$requested_month = substr($date_debut, 0, 7);
		}
		
		$html = sprintf('<div class="agdp-agdpevents agdp-agdpevents-%s">', $options['mode']);
		if( $options['mode'] != 'email')
			$html .= self::get_list_header($requested_month);
			
		$not_empty_month_index = 0;
		$events_count = 0;
		
		if( $option_ajax )
			$filters = self::get_filters();
		
		$html .= '<ul>';
		foreach($months as $month => $month_events_count) {
			if( $option_ajax
			&& ($not_empty_month_index >= $options['start_ajax_at_month_index'])
			&& $month_events_count > 0
			&& $month !== $requested_month) {
				$data = [ 'month' => $month ];
				if($filters && count($filters))
					$data['filters'] = $filters;
				$ajax = sprintf('ajax="once" data="%s"',
					esc_attr( json_encode ( array(
						'action' => Agdp_Evenement::post_type . '_show_more',
						'data' => $data
					)))
				);
			} else
				$ajax = false;
			$html .= sprintf(
				'<li><div class="month-title toggle-trigger %s %s" %s>%s <span class="nb-items">(%d)</span></div>
				<ul id="month-%s" class="agdpevents-month toggle-container">'
				, $month_events_count === 0 ? 'no-items' : ''
				, !$ajax && $month_events_count ? 'active' : ''
				, $ajax ? $ajax : ''
				, wp_date('F Y', mktime(0,0,0, substr($month, 5,2), 1, substr($month, 0,4)))
				, $month_events_count
				, $month
			);
			if(!$ajax && $month_events_count){
				$html .= self::get_month_posts_list_html( $month, $requested_id, $options );
			}
		
			$html .= '</ul></li>';
			
			if($month_events_count > 0)
				$not_empty_month_index++;
			$events_count += $month_events_count;
		}
		
		$html .= '</ul>';
		
		if( $options['mode'] !== 'email' )
			$html .= self::download_links();
		
		$html .= '</div>' . $content;
		return $html;
	}
	
	/**
	* Affecte l'option d'anteriorité
	*
	*/
	public static function filter_anteriority_option($options, $anteriority = false, $newsletter = false){
		return Agdp_Newsletter::filter_anteriority_option($options, $anteriority
			, date('d') < 10 ? ANTERIORITY_ONEMONTH : ANTERIORITY_TWOMONTHS
			, $newsletter
		);
	}
	
	/**
	* Rendu Html des évènements destinés au corps d'un email (newsletter)
	*
	*/
	public static function get_list_for_email($content = '', $options = false){
		if(!isset($options) || !is_array($options))
			$options = array();
		$options = array_merge(
			array(
				'ajax' => false,
				'mode' => 'email'
			), self::filter_anteriority_option($options));
				
		if(Agdp_Evenement_Post_type::is_diffusion_managed()){
			$term_id = Agdp_Evenements::get_newsletter_diffusion_term_id();
			self::add_tax_filter(Agdp_Evenement::taxonomy_diffusion, $term_id);
		}
		
		$css = '<style>'
			. '
.entry-content {
	font-family: arial;
}
.toggle-trigger {
	margin: 8px 0px 0px 0px;
	font-size: larger;
	padding-left: 10px;
	background-color: #F5F5F5;
	white-space: collapse;
} 
.toggle-trigger a {
	color: #333;
	text-decoration: none;
	display: block; 
}
.toggle-container {
	overflow: hidden;
	padding-left: 10px;
	white-space: collapse;
}
.toggle-container pre {
	background-color: #F5F5F5;
	color: #333;
	white-space: pre-line;
}
.ev-covoiturages {
	white-space: collapse;
	font-size: smaller;
} 
.agdp-agdpevents-email .month-title {
	margin-top: 1em;
	margin-bottom: 1em;
	font-size: larger;
	font-weight: bold;
	text-decoration: underline;
	text-transform: uppercase;
} 
.agdp-agdpevents-email .agdpevent .dates {
	font-size: larger;
	font-weight: bold;
} 
.agdp-agdpevents-email a-li a-li {
	margin-left: 1em;
	padding-top: 2em;
}
.agdp-agdpevents-email div.titre, .agdp-agdpevents-email div.localisation, .agdp-agdpevents-email div.ev-cities {
	font-weight: bold;
}
.agdp-agdpevents-email i {
	font-style: normal;
}
.created-since {
	font-size: smaller;
	font-style: italic;
}
.footer {
	border-bottom: solid gray 2px;
	margin-bottom: 2em;
	white-space: collapse;
}
'
			. '</style>';
		$html = self::get_list_html($content, $options );
		
		if( ! $html ){
			if ( Agdp_Newsletter::is_sending_email() )
				Agdp_Newsletter::content_is_empty( true );
			return false;
		}
		
		$html = $css . $html;

		foreach([
			'agdp-agdpevents'=> 'aevs'
			, 'agdpevents'=> 'evs'
			, 'agdpevent-'=> 'ev-'
			, 'agdpevent '=> 'ev '
			, 'toggle-trigger' => 'tgt'
			, 'toggle-container' => 'tgc'
			
			, '<ul' => '<div class="a-ul"'
			, '</ul' => '</div'
			, '<li' => '<div class="a-li"'
			, '</li' => '</div'
			
			] as $search=>$replace)
			$html = str_replace($search, $replace, $html);
		
		if(false) '{{';//bugg notepad++ functions list
		foreach([
			'/\sagdpevent="\{[^\}]*\}"/' => '',
			'/\sid="\w*"/' => '',
			'/([\}\>\;]\s)\s+/m' => '$1'
			] as $search=>$replace)
			$html = preg_replace($search, $replace, $html);
		return $html;
	}
	
	/**
	* Rendu Html des filtres en tête de liste
	*
	* Optimal sous la forme https://.../agenda-local/?eventid=1207#eventid1207
	*/
	public static function get_list_header($requested_month = false, $options = false){
		
		$filters_summary = [];
		$all_selected_terms = [];
		if( ! current_user_can('manage_options') || Agdp_Evenements::get_newsletter_diffusion_term_id() == -1)
			$except_tax = Agdp_Evenement::taxonomy_diffusion;
		else
			$except_tax = '';
		$taxonomies = Agdp_Evenement_Post_type::get_taxonomies($except_tax);
		foreach( $taxonomies as $tax_name => $taxonomy){
			$taxonomy['terms'] = Agdp_Evenement::get_all_terms($tax_name);
			if( count($taxonomy['terms']) === 0 ){
				unset($taxonomy['terms']);
				continue;
			}
			if( count($taxonomy['terms']) > 1 )
				$taxonomy['terms'] = array_merge([ [ 'term_id' => '*', 'name' => $taxonomy['all_label']] ]
					, $taxonomy['terms']
					, [ [ 'term_id' => '0', 'name' => $taxonomy['none_label'] ] ]);
			$taxonomies[$tax_name]['terms'] = $taxonomy['terms'];
			$selected_terms = isset($_GET[$taxonomy['filter']]) ? $_GET[$taxonomy['filter']] : false;
			if(is_array($selected_terms)){
				$all_selected_terms[$tax_name] = $selected_terms;
				foreach($taxonomy['terms'] as $term){
					if(is_array($term)){
						$name = $term['name'];
						$id = $term['term_id'];
					}
					else{
						$name = $term->name;
						$id = $term->term_id;
					}
					if($id != '*' && $selected_terms && array_key_exists( $id, $selected_terms) ){
						$filters_summary[] = $name;
					}
				}
			}
			else
				$all_selected_terms[$tax_name] = false;
		}
		$html = '<div class="agdp-agdpevents-list-header">'
			. sprintf('<div id="agdp-filters" class="toggle-trigger %s">', count($filters_summary) ? 'active' : '')
			. '<table><tr><th>'. __('Filtres', AGDP_TAG).'</th>'
			. '<td>'
			. '<p class="agdp-title-link">'
				. '<a href="'. get_page_link( Agdp::get_option('new_agdpevent_page_id')) .'" title="Cliquez ici pour ajouter un nouvel évènement"><span class="dashicons dashicons-welcome-add-page"></span></a>'
				. '<a href="reload:" title="Cliquez ici pour recharger la liste"><span class="dashicons dashicons-update"></span></a>'
			. '</p>'
			. (count($filters_summary) ? '<div class="filters-summary">' 
				. implode(', ', $filters_summary)
				. Agdp::icon('no', '', 'clear-filters', 'span', __('Efface les filtres', AGDP_TAG))
				. '</div>' : '')
			. '</td></tr></table></div>';
		
		$html .= '<form action="#main" method="get" class="toggle-container" >';
		
		$html .= '<input type="hidden" name="action" value="filters"/>';
		$html .= '<input type="submit" value="Filtrer"/>';
		
		foreach( $taxonomies as $tax_name => $taxonomy){
			if( empty($taxonomy['terms']))
				continue;
			$field = $taxonomy['filter'];
			if( count($taxonomy['terms']) === 1 )
				$label = $taxonomy['label'];
			else
				$label = $taxonomy['plural'];
			$html .= sprintf('<div class="taxonomy %s"><label for="%s[]">%s</label>', $field, $field, $label);
			foreach($taxonomy['terms'] as $term){
				if(is_array($term)){
					$name = $term['name'];
					$id = $term['term_id'];
				}
				else{
					$name = $term->name;
					$id = $term->term_id;
				}
				// $html .= sprintf('<option value="%s" %s>%s</option>'
				$html .= sprintf('<label><input type="checkbox" name="%s[%s]" %s/>%s</label>'
					, $field
					, $id
					// , selected( in_array( $id, $selected_categories), true, false)
					, checked( 
						$all_selected_terms[$tax_name] && array_key_exists( $id, $all_selected_terms[$tax_name]) 
						|| ! $all_selected_terms[$tax_name] && $id == '*'
						, true, false)
					, $name);
			}
			$html .= '</div>';
		}
		
		$html .= '</form></div>';
		
		self::$filters_summary = implode(', ', $filters_summary);
		
		return $html;
	}
	
	/**
	* Rendu Html des évènements d'un mois sous forme de liste
	*/
	public static function get_month_posts_list_html($month, $requested_id = false, $options = false){
		
		$events = self::get_month_posts($month);
		
		if(is_wp_error( $events)){
			$html = sprintf('<p class="alerte no-events">%s</p>%s', __('Erreur lors de la recherche d\'évènements.', AGDP_TAG), var_export($events, true));
		}
		elseif($events){
			$html = '';
			if(count($events) === 0){
				$html .= sprintf('<p class="alerte no-events">%s</p>', __('Aucun évènement trouvé', AGDP_TAG));
			}
			else {
				foreach($events as $event){
					$html .= '<li>' . self::get_list_item_html($event, $requested_id, $options) . '</li>';
				}
				
				//Ce n'est plus nécessaire, les mois sont chargés complètement
				//TODO post_per_page
				if(count($events) == self::$default_posts_per_page){
					$html .= sprintf('<li class="show-more"><h3 class="agdpevent toggle-trigger" ajax="show-more">%s</h3></li>'
						, __('Afficher plus de résultats', AGDP_TAG));
				}
			}
		}
		else{
				$html = sprintf('<p class="alerte no-events">%s</p>', __('Aucun évènement trouvé', AGDP_TAG));
			}
			
		return $html;
	}
	
	public static function get_list_item_html($event, $requested_id, $options){
		$email_mode = is_array($options) && isset($options['mode']) && $options['mode'] == 'email';
		
		if( $event->post_status === 'pending'
		 && ( $email_mode
			|| ! current_user_can('moderate_comments') ) )
			return false;
		
		$date_debut = get_post_meta($event->ID, 'ev-date-debut', true);
					
		$url = Agdp_Evenement::get_post_permalink( $event );
		$html = '';
		
		if( ! $email_mode ){
			$html .= sprintf(
					'<div class="show-post post-status-%s"><a href="%s">%s</a></div>'
				, $event->post_status
				, $url
				, Agdp::icon('media-default')
			);
			if( $event->post_status === 'pending' ){
				$html .= sprintf('<div class="approve-post">%s</div>'
					, Agdp_Evenement::get_agdpevent_action_link(
						$event->ID
						, 'publish'
						, true
						, ' Approuver'
						, false, null
						, /* $data */ [
							'reload' => Agdp_Evenements::get_url($event)
				]));
			}
		}
		$html .= sprintf('<div id="%s%d" class="agdpevent post-status-%s %s %s" agdpevent="%s">'
			, AGDP_ARG_EVENTID, $event->ID
			, $event->post_status
			, $email_mode ? '' : 'toggle-trigger'
			, $email_mode && ($event->ID == $requested_id) ? 'active' : ''
			, esc_attr( json_encode(['id'=> $event->ID, 'date' => $date_debut]) )
		);
		
		$title = $event->post_title;
		$localisation = Agdp_Evenement::get_event_localisation_and_cities($event->ID);
		$dates = Agdp_Evenement::get_event_dates_text($event->ID);
		$covoiturages = self::get_agdpevent_covoiturages( $event, false );
		
		$html .= sprintf(
				'<div class="dates">%s</div>'
				.'<div class="titre">%s</div>'
				.'<div class="localisation">%s</div>'
				.'<div class="covoiturage">%s</div>'
			.''
			, htmlentities($dates), htmlentities($title), $localisation, $covoiturages);
		
		$categories = Agdp_Evenement::get_event_categories ($event, 'names');
		// var_dump($categories); die();
		if($categories)
			$html .= sprintf('<div class="agdpevent-categories" title="%s"><i>%s</i></div>', 'Catégories', htmlentities(implode(', ', $categories)));
		
		$html .= '</div>';
		
		if( ! $email_mode )
			$html .= '<div class="toggle-container">';
		else
			$html .= '<div>';
		
		
		$value = $event->post_content;
		if($value)
			$html .= sprintf('<pre>%s</pre>', htmlentities($value) );
		
		$value = get_post_meta($event->ID, 'ev-organisateur', true);
		if($value){
			$html .= sprintf('<div>Organisé par : %s</div>',  htmlentities($value) );
		}
		
		$value = get_post_meta($event->ID, 'ev-phone', true);
		if($value){
			$html .= sprintf('<div class="ev-phone">Téléphone : %s</div>',  antispambot($value) );
		}
		
		$value = get_post_meta($event->ID, 'ev-siteweb', true);
		if($value){
			$html .= sprintf('<div class="ev-siteweb">%s</div>',  make_clickable( esc_html($value) ) );
		}
		
		$covoiturages = self::get_agdpevent_covoiturages( $event, true );
		if( ! $email_mode && $covoiturages)
			$html .= sprintf('<div class="ev-covoiturages">%s</div>',  $covoiturages );
		
			
		$created_by = '';
		if(is_user_logged_in()){
			global $current_user;
			//Rôle autorisé
			if(	$current_user->has_cap( 'edit_posts' ) ){
				$creator = new WP_User($event->post_author);
				if(($user_name = $creator->get('display_name'))
				|| ($user_name = $creator->get('user_login'))){
					$created_by = ', créé par <a>' . $user_name . '</a>';
				}
			}
		}
				
		$html .= date_diff_text($event->post_date, true, '<div class="created-since">', $created_by . '</div>');
		
		$html .= '<div class="footer">';
				
		$html .= '<table><tbody><tr>';
			
		if( $email_mode && $covoiturages )
			$html .= sprintf(
				'<td class="ev-covoiturages">%s</td></tr><tr>'
				, $covoiturages);
		
		if( ! $email_mode )
			$html .= '<td class="trigger-collapser"><a href="#replier">'
				.Agdp::icon('arrow-up-alt2')
				.'</a></td>';
	
		$html .= sprintf(
			'<td class="post-edit"><a href="%s">'
				.'Afficher la page de l\'évènement%s'
			.'</a></td>'
			, $url
			, ($email_mode  ? '' : Agdp::icon('media-default'))
		);
			
		$html .= '</tr></tbody></table>';
			
		$html .= '</div>';

		$html .= '</div>';
		
		return $html;
	}	
	
 	/**
 	 * Retourne le Content de la page de l'évènement
 	 */
	public static function get_agdpevent_covoiturages( $agdpevent = null, $details = false ) {
		if( ! Agdp_Covoiturage::is_managed() )
			return '';
		
		global $post;
 		if( ! isset($agdpevent) || ! is_a($agdpevent, 'WP_Post')){
			$agdpevent = $post;
		}
		
		static $covoiturages_agdevent_id;
		static $covoiturages;
		if( ! $covoiturages_agdevent_id || $covoiturages_agdevent_id !== $agdpevent->ID ){
			$covoiturages_agdevent_id = $agdpevent->ID;
			$covoiturages = get_posts([
				'post_type' => Agdp_Covoiturage::post_type,
				'post_status' => 'publish',
				'meta_key' => 'related_'.Agdp_Evenement::post_type,
				'meta_value' => $agdpevent->ID,
				'meta_compare' => '=',
			]);
		}
		if( count($covoiturages) === 0 && !$details ) return false;
		
		if( $details ){
			$html = sprintf('<ul class="agdp-covoiturages-list">');
			if( count($covoiturages) )
				$html .= sprintf('<label>%s covoiturage%s associé%s</label>'
					, count($covoiturages), count($covoiturages) > 1 ? 's' : '', count($covoiturages) > 1 ? 's' : '');
			foreach($covoiturages as $covoiturage){
				$title = Agdp_Covoiturage::get_post_title( $covoiturage, true );
				$html .= sprintf('<li><a href="%s">%s %s</a></li>'
					, get_post_permalink($covoiturage->ID)
					, Agdp::icon('car')
					, $title
				);
			}
			//Ajouter
			$new_link = sprintf('<a href="%s&%s=%d">Ajouter un nouveau covoiturage</a>'
				, get_post_permalink(Agdp::get_option('new_covoiturage_page_id'))
				, AGDP_ARG_EVENTID, $agdpevent->ID
			);
			$html .= sprintf('<li>%s%s</li>'
				, Agdp::icon(count($covoiturages) ? 'welcome-add-page' : 'car')
				, $new_link);
		}
		else {
			$html = '';
			foreach($covoiturages as $covoiturage){
				$title = Agdp_Covoiturage::get_post_title( $covoiturage, true );
				$html .= sprintf('<span title="Covoiturage : %s">%s</span>'
					, $title
					, Agdp::icon('car')
				);
			}
			// $html .= sprintf('<small>%s covoiturage%s associé%s</small>'
				// , count($covoiturages), count($covoiturages) > 1 ? 's' : '', count($covoiturages) > 1 ? 's' : '');
			
		}
			
		
		if($details)
			$html .= '</ul>';
		
		return $html;
	}
	
	public static function download_links(){
		$html = '';
		$data = [
			'file_format' => 'ics'
		];
		$title = 'Télécharger les évènements au format ICS';
		if( count($_GET) && self::$filters_summary ){
			$title .= ' filtrés (' . self::$filters_summary . ')';
			$data['filters'] = $_GET;
		}
		$href = sprintf('/wp-admin/admin-ajax.php/?action=%s_%s_action&%s'
			, AGDP_TAG
			, 'agdpevents_download'
			, http_build_query(['data' => $data]) );
		$html .= sprintf('<a href="%s"><span class="dashicons-before dashicons-download "></span></a>', $href);
		//$html .= Agdp::get_ajax_action_link(false, ['agdpevents','download_file'], 'download', '', $title, false, $data, $href);
		
		$meta_name = 'download_link';
		foreach(Agdp_Evenement::get_all_terms(Agdp_Evenement::taxonomy_diffusion) as $term_id => $term){
			$file_format = get_term_meta($term->term_id, $meta_name, true);
			if( $file_format ){
				$data = [
					'file_format' => $file_format,
					'filters' => [ Agdp_Evenement::taxonomy_diffusion => $term_id]
				];
				$title = sprintf('Télécharger les évènements pour %s (%s)', $term->name, $file_format);
				
				$href = sprintf('/wp-admin/admin-ajax.php/?action=%s_%s_action&%s'
					, AGDP_TAG
					, 'agdpevents_download'
					, http_build_query(['data' => $data]) );
				$html .= sprintf('<a href="%s"><span class="dashicons-before dashicons-download "></span></a>', $href);
				//$html .= Agdp::get_ajax_action_link(false, ['agdpevents','download_file'], 'download', '', $title, false, $data, $href);
			}
		}
		return $html;
	}

	/**
	 * Requête Ajax de téléchargement de fichier
	 */
	public static function on_ajax_action_download_file($data, $return = 'download:url') {
		
		$filters = empty($data['filters']) ? false : $data['filters'];
		$query = array(
			'meta_query' => [[
				'key' => 'ev-date-debut',
				'value' => wp_date('Y-m-d', strtotime(wp_date('Y-m-d') . ' + 1 year')),
				'compare' => '<=',
				'type' => 'DATE',
			], 
			'relation' => 'AND',
			[
				'key' => 'ev-date-debut',
				'value' => wp_date('Y-m-01'),
				'compare' => '>=',
				'type' => 'DATE',
			]],
			'orderby' => [
				'ev-date-debut' => 'ASC',
				'ev-heure-debut' => 'ASC',
			],
			'nopaging' => true
		);
			
		$query = self::get_filters_query(false, $filters);
		$posts = self::get_posts($query);
		
		if( ! $posts){
			if( $return === 'data' )
				return '';
			else
				return sprintf('Aucun évènement à exporter');
		}
		$file_format = $data['file_format'];
		
		require_once( dirname(__FILE__) . '/class.agdp-posts-export.php');
		if( $return === 'download:url' )
			$return_value = 'url';
		else
			$return_value = $return;
		$value = Agdp_Posts_Export::do_export($posts, $file_format, $return_value, $filters);
		switch( $return ){
			case 'data' :
			case 'url' :
			case 'file' :
				return $value;
			default:
				return 'download:' . $value;
		}
	}
}
