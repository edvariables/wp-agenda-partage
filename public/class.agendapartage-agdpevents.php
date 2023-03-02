<?php

/**
 * AgendaPartage -> Evenements
 * Collection d'évènements
 */
class AgendaPartage_Evenements {

	private static $initiated = false;
	public static $default_posts_query = [];
	
	private static $default_posts_per_page = 30;

	public static function init() {
		if ( ! self::$initiated ) {
			
			self::init_default_posts_query();
			
			self::$initiated = true;

			self::init_hooks();
		}
	}
	
	public static function init_default_posts_query() {
		
		self::$default_posts_query = array(
			'post_type' => AgendaPartage_Evenement::post_type,
			'post_status' => 'publish',
			
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
	 * Hook
	 */
	public static function init_hooks() {
		add_action( 'wp_ajax_'.AGDP_TAG.'_show_more', array(__CLASS__, 'on_wp_ajax_agdpevents_show_more_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_show_more', array(__CLASS__, 'on_wp_ajax_agdpevents_show_more_cb') );
	}
	
	public static function get_url(){
		$url = get_permalink(AgendaPartage::get_option('agenda_page_id')) . '#main';
		// $url = home_url();
		return $url;
	}
	
	/**
	*
	*/
	public static function get_posts_query(...$queries){
		$all = self::$default_posts_query;
		// echo "<div style='margin-left: 15em;'>";
		foreach ($queries as $query) {
			if( ! is_array($query)){
				if(is_numeric($query))
					$query = array('posts_per_page' => $query);
				else
					$query = array();
			}
			if(isset($query['meta_query'])){
				if(isset($all['meta_query'])){
					$all['meta_query'] = array(
						(string)uniqid()=> $all['meta_query']
						, (string)uniqid()=> $query['meta_query']
						, 'relation' => 'AND'
					);
				}
				else
					$all['meta_query'] = $query['meta_query'];
				
				unset($query['meta_query']);
			}
			$all = array_merge($all, $query);
		}
		// var_dump($all);
		// echo "</div>";
		return $all;
		
	}
	
	/**
	 * Recherche de évènements d'un mois
	 */
	public static function get_month_posts($month, ...$queries){
		if(!$month) $month = date('Y-m');
		$date_min = substr($month, 0,4) . '-' . substr($month, 5,2) . '-01';
		if(substr($month, 5,2) === '12')
			$date_max = ((int)substr($month, 0,4) + 1) . '-01-01';
		else
			$date_max = substr($month, 0,4) . '-' . ((int)substr($month, 5,2) + 1 ) . '-01';
		$query = array(
			'meta_query' => [
				'relation' => 'AND',
				[ [
					'key' => 'ev-date-debut',
					'value' => $date_min,
					'compare' => '>=',
					'type' => 'DATE',
				],
				'ev-date-debut' => [
					'key' => 'ev-date-debut',
					'value' => $date_max,
					'compare' => '<',
					'type' => 'DATE',
				] ]
			],
			'orderby' => [
				'ev-date-debut' => 'ASC',
				'ev-heure-debut' => 'ASC',
			],
			'nopaging' => true
			
		);
		$query = self::add_filters_query($query);
		$queries[] = $query;
		return self::get_posts(...$queries);
    }
	
	/**
	 * Recherche de évènements
	 */
	public static function get_posts(...$queries){

		$query = self::get_posts_query(...$queries);

		// debug_log('get_posts $queries ', $queries);
		// debug_log('get_posts $query ', $query);

        $the_query = new WP_Query( $query );
		
		// debug_log('get_posts ' . '<pre>'.$the_query->request.'</pre>');
        return $the_query->posts; 
    }

	/**
	 * Recherche de tous les mois contenant des évènements
	 */
	public static function get_posts_months(...$queries){
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		$sql_filters = self::add_filters_query(null, true);
		
		//Find this in other blog 
		$sql = "SELECT DISTINCT DATE_FORMAT(meta.meta_value, '%Y') as year, DATE_FORMAT(meta.meta_value, '%m') as month, COUNT(post_id) as count
				FROM {$blog_prefix}posts posts
				INNER JOIN {$blog_prefix}postmeta meta
					ON posts.ID = meta.post_id
				WHERE posts.post_status = 'publish'
					AND posts.post_type = '". AgendaPartage_Evenement::post_type ."'
					AND meta.meta_key = 'ev-date-debut'
					AND meta.meta_value > CURDATE()
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
				elseif((int)$prev_row->year < (int)$row->year - 1)
					break;
			$months[$row->year . '-' . $row->month] = (int)$row->count;
			$prev_row = $row;
		}
		return $months;
    }

	/**
	 * Complète les filtres de requêtes
	 */
	public static function add_filters_query($query = false, $return_sql = false){
		if(isset($_REQUEST['action'])
		&& $_REQUEST['action'] == 'filters'){
			$query_tax_terms = [];
			$all_sql = [];
		foreach( AgendaPartage_Evenement_Post_Type::get_taxonomies() as $tax_name => $taxonomy){
			$field = $taxonomy['filter'];
			if(isset($_REQUEST[$field])){
				$query_tax_terms[$tax_name] = [];
				$all_sql[$tax_name] = ['IN'=>[]];
				foreach($_REQUEST[$field] as $term_id => $checked){
					if($term_id === '*'){
						unset($query_tax_terms[$tax_name]);
						unset($all_sql[$tax_name]);
						break;
					}
					if($term_id == 0){
						$query_tax_terms[$tax_name][] = array(
							'taxonomy' => $tax_name,
							'operator' => 'NOT EXISTS');
						$all_sql[$tax_name]['NOT EXISTS'] = true;
					} else {
						$query_tax_terms[$tax_name][] = array(
							'taxonomy' => $tax_name,
							'field' => 'term_id',
							'terms' => $term_id);
						$all_sql[$tax_name]['IN'][] = $term_id;
					}
				}
				if(isset($query_tax_terms[$tax_name]) && count($query_tax_terms[$tax_name]) > 1)
					$query_tax_terms[$tax_name]['relation'] = 'OR';
				
				}
			}
			$sql = '';
			if(count($query_tax_terms)){
				if(count($query_tax_terms) > 1)
					$query_tax_terms['relation'] = 'AND';
				if( ! is_array($query) )
					$query = [];
				
				$query['tax_query'] = $query_tax_terms;
				
				if($return_sql){
					global $wpdb;
					$blog_prefix = $wpdb->get_blog_prefix();
					$sql = "SELECT post.ID"
						. "\n FROM {$blog_prefix}posts post"
						. "\n LEFT JOIN {$blog_prefix}term_relationships tax"
						. "\n ON post.ID = tax.object_id";
					$sql_where = '';
					foreach($all_sql as $tax_name => $tax_data){
						$tax_sql = '';
						if(count($tax_data['IN'])){
							$tax_sql = "\n tax.term_taxonomy_id"
									. " IN (" . implode(', ', $tax_data['IN']) . ')'
							;
						}
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
		}
		if($return_sql)
			return isset($sql) ? $sql : '';
		return $query;
	}
	
	/**
	* Rendu Html des évènements sous forme d'arborescence par mois
	*
	* Optimal sous la forme https://.../agenda-local/?eventid=1207#eventid1207
	*/
	public static function get_list_html($query = false, $content = '', $options = false){
		if(!isset($options) || !is_array($options))
			$options = array();
		
		$options = array_merge(
			array(
				'ajax' => true,
				'start_ajax_at_month_index' => 2,
				'max_events' => 30,
				'months' => -1,
				'mode' => 'list' //list|email|text|calendar|TODO...
			), $options);
		if( $options['mode'] == 'email' ){
			$options['ajax'] = false;
		}
		
		$option_ajax = (bool)$options['ajax'];
		
		$months = self::get_posts_months($query);
		
		if($options['months'] > 0 && count($months) > $options['months'])
			$months = array_slice($months, 0, $options['months'], true);
		
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
		
		$requested_id = array_key_exists(AGDP_ARG_EVENTID, $_GET) ? $_GET[AGDP_ARG_EVENTID] : false;
		$requested_month = false;
		if($requested_id){
			$date_debut = get_post_meta($requested_id, 'ev-date-debut', true);
			if($date_debut)
				$requested_month = substr($date_debut, 0, 7);
		}
		
		$html = sprintf('<div class="agdp-agdpevents agdp-agdpevents-%s">', $options['mode']);
		if( $options['mode'] != 'email')
			$html .= self::get_list_header($query, $requested_month);
			
		$not_empty_month_index = 0;
		$events_count = 0;
		
		$html .= '<ul>';
		foreach($months as $month => $month_events_count) {
			if( $option_ajax
			&& ($not_empty_month_index >= $options['start_ajax_at_month_index'])
			&& $month_events_count > 0
			&& $month !== $requested_month) {
				$ajax = sprintf('ajax="once" data="%s"',
					esc_attr( json_encode ( array(
						'action' => AGDP_TAG.'_show_more',
						'data' => [ 'month' => $month ]
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
				$html .= self::get_month_events_list_html( $month, $requested_id, $options );
			}
		
			$html .= '</ul></li>';
			
			if($month_events_count > 0)
				$not_empty_month_index++;
			$events_count += $month_events_count;
		}
		
		$html .= '</ul>';
		$html .= '</div>' . $content;
		return $html;
	}
	
	/**
	* Rendu Html des évènements destinés au corps d'un email (newsletter)
	*
	*/
	public static function get_list_for_email($query = false, $content = '', $options = false){
		if(!isset($options) || !is_array($options))
			$options = array();
		
		$options = array_merge(
			array(
				'ajax' => false,
				'months' => date('d') < 10 ? 1 : 2,
				'mode' => 'email' 
			), $options);
		
		$css = '<style>'
			. '
.entry-content {
	font-family: arial;
}
.agdp-agdpevents-email * {
	background-color: #FFFFFF;
	color: #000000 !important;
}
body.colors-dark .agdp-agdpevents-email * {
	background-color: #FFFFFF;
	color: #000000 !important;
}
.toggle-trigger {
	margin: 0px;
	font-size: larger;
	padding-left: 10px;
	background-color: #F5F5F5;
} 
.toggle-trigger a {
	color: #333;
	text-decoration: none;
	display: block;
}
.toggle-container {
	overflow: hidden;
	padding-left: 10px;
}
.toggle-container pre {
	background-color: #F5F5F5;
	color: #333;
}
.agdp-agdpevents-email .month-title {
	margin-top: 1em;
	font-size: larger;
	font-weight: bold;
	text-decoration: underline;
	text-transform: uppercase;
} 
.agdp-agdpevents-email .agdpevent .dates {
	font-size: larger;
	font-weight: bold;
} 
.agdp-agdpevents-email ul {
	list-style: none;
} 
.agdp-agdpevents-email li {
	padding-top: 1em;
} 
'
			. '</style>';
		$html = $css . self::get_list_html($query, $content, $options );
		foreach([
			'agdp-agdpevents'=> 'aevs'
			, 'agdpevents'=> 'evs'
			, 'agdpevent-'=> 'ev-'
			, 'agdpevent '=> 'ev '
			, 'toggle-trigger' => 'tgt'
			, 'toggle-container' => 'tgc'
			] as $search=>$replace)
			$html = str_replace($search, $replace, $html);
		foreach([
			'/\sagdpevent="\{[^\}]*\}"/' => '',
			'/\sid="\w*"/' => '',
			// '/(\s)\s+/' => '$1'
			] as $search=>$replace)
			$html = preg_replace($search, $replace, $html);
		
		return $html;
	}
	
	/**
	* Rendu Html des évènements sous forme d'arborescence par mois
	*
	* Optimal sous la forme https://.../agenda-local/?eventid=1207#eventid1207
	*/
	public static function get_list_header($query = false, $requested_month = false, $options = false){
		//TODO header('Location:index.php#main');
		
		$filters_summary = '';
		$all_selected_terms = [];
		$except_tax = current_user_can('manage_options') ? '' : 'publication';
		$taxonomies = AgendaPartage_Evenement_Post_Type::get_taxonomies($except_tax);
		foreach( $taxonomies as $tax_name => $taxonomy){
			$taxonomy['terms'] = AgendaPartage_Evenement_Post_type::get_all_terms($tax_name);
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
						if($filters_summary)
							$filters_summary .= ', ';
						$filters_summary .= $name;
					}
				}
			}
			else
				$all_selected_terms[$tax_name] = false;
		}
		$html = '<div class="agdp-agdpevents-list-header">
			<div id="agdp-filters" class="toggle-trigger">'
			. __('Filtres', AGDP_TAG)
			. ($filters_summary ? '<div class="filters-summary">' . $filters_summary . '</div>' : '')
			. '</div>';
		
		$html .= '<form action="" method="get" class="toggle-container" >';
		
		$html .= '<input type="hidden" name="action" value="filters"/>';
		$html .= '<input type="submit" value="Filtrer"/>';
		foreach( $taxonomies as $tax_name => $taxonomy){
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
		$html .= '<!--</select>-->';
		
		$html .= '</form></div>';
		
		return $html;
	}

	/**
	* Rendu Html des évènements d'un mois sous forme de liste
	*/
	public static function get_month_events_list_html($month, $requested_id = false, $options = false){
		
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
					$html .= '<li>' . self::get_item_list_html($event, $requested_id, $options) . '</li>';
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
				$html .= sprintf('<p class="alerte no-events">%s</p>', __('Aucun évènement trouvé', AGDP_TAG));
			}
			
		return $html;
	}
	
	public static function get_item_list_html($event, $requested_id, $options){
		$email_mode = is_array($options) && isset($options['mode']) && $options['mode'] == 'email';
			
		$date_debut = get_post_meta($event->ID, 'ev-date-debut', true);
					
		$url = AgendaPartage_Evenement::get_post_permalink( $event );
		$html = '';
		
		if( ! $email_mode )
			$html .= sprintf(
					'<div class="show-post"><a href="%s">%s</a></div>'
				, $url
				, AgendaPartage::html_icon('media-default')
			);
			
		$html .= sprintf('<div id="%s%d" class="agdpevent toggle-trigger %s" agdpevent="%s">'
			, AGDP_ARG_EVENTID, $event->ID
			, $event->ID == $requested_id ? 'active' : ''
			, esc_attr( json_encode(['id'=> $event->ID, 'date' => $date_debut]) )
		);
		
		$cities = AgendaPartage_Evenement::get_event_cities ($event, 'names');
		
		$value = $event->post_title;
		$localisation = htmlentities(get_post_meta($event->ID, 'ev-localisation', true));
		if($cities){
			$cities = htmlentities(implode(', ', $cities));
			if(self::cities_in_localisation( $cities, $localisation ) === false)
				$localisation .= sprintf('<div class="agdpevent-cities" title="%s"><i>%s</i></div>', 'Communes', $cities);
		}
		$dates = AgendaPartage_Evenement::get_event_dates_text($event->ID);
		$html .= sprintf(
				'<div class="dates">%s</div>'
				.'<div class="titre">%s</div>'
				.'<div class="localisation">%s</div>'
			.''
			, htmlentities($dates), htmlentities($value), $localisation);
			
		
		$categories = AgendaPartage_Evenement::get_event_categories ($event, 'names');
		// var_dump($categories); die();
		if($categories)
			$html .= sprintf('<div class="agdpevent-categories" title="%s"><i>%s</i></div>', 'Catégories', htmlentities(implode(', ', $categories)));
		
		$html .= '</div>';
		
		$html .= '<div class="toggle-container">';
		
		
		$value = $event->post_content;
		if($value)
			$html .= sprintf('<pre>%s</pre>', htmlentities($value) );
		
		$value = get_post_meta($event->ID, 'ev-organisateur', true);
		if($value){
			$html .= sprintf('<div>Organisé par : %s</div>',  htmlentities($value) );
		}
		
		$value = get_post_meta($event->ID, 'ev-siteweb', true);
		if($value){
			$html .= sprintf('<div class="ev-siteweb">%s</div>',  make_clickable( esc_html($value) ) );
		}
		
		$html .= '<div class="footer">';
				
			$html .= '<table><tbody><tr>';
			
			if( ! $email_mode )
				$html .= '<td class="trigger-collapser"><a href="#replier">'
					.AgendaPartage::html_icon('arrow-up-alt2')
					.'</a></td>';

			$url = AgendaPartage_Evenement::get_post_permalink($event);
			$html .= sprintf(
				'<td class="post-edit"><a href="%s">'
					.'Afficher la page l\'évènement'
					. ($email_mode  ? '' : AgendaPartage::html_icon('media-default'))
				.'</a></td>'
				, $url);
				
			$html .= '</tr></tbody></table>';
			
		$html .= '</div>';

		$html .= '</div>';
		
		return $html;
	}
	
	/**
	 * Vérifie si la commune est déjà ennoncé dans la localisation
	 */
	public static function cities_in_localisation( $cities, $localisation ){
		$cities = preg_replace('/\s|-/', '', $cities);
		$localisation = preg_replace('/\s|-/', '', $localisation);
		return stripos( $localisation, $cities );
	}

	/**
	 * Show more
	 */
	public static function on_wp_ajax_agdpevents_show_more_cb() {
		if(! array_key_exists("data", $_POST)){
			$ajax_response = '';
		}
		else {
			$data = $_POST['data'];
			if( array_key_exists("month", $data)){
				$ajax_response = self::get_month_events_list_html($data['month']);
			}
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/**
	 * Show more
	 */
	/* public static function on_wp_ajax_agdpevents_show_more_cb() {
		if(!array_key_exists("last-post", $_POST)){
			$ajax_response = '';
		}
		else {
			$last_post = $_POST["last-post"];
			$date_min = $last_post['date'];
			$last_post_id = $last_post['id'];
			$query = array(
				'meta_query' => [
					'ev-date-debut' => [
						'key' => 'ev-date-debut',
						'value' => $date_min,
						'compare' => '>=',
						'type' => 'DATE',
					],
					// 'ev-heure-debut' => [
						// 'key' => 'ev-heure-debut',
						// 'compare' => 'EXISTS', # we don't actually want any restriction around time
						// 'type' => 'TIME',
					// ],
				],
				'orderby' => [
					'ev-date-debut' => 'ASC',
					'ev-heure-debut' => 'ASC',
				],
				
			);
			
			$posts = self::get_posts(self::$default_posts_per_page + 1, $query);
			//Supprime les posts du début (même date)
			$exclude_counter= 0;
			foreach($posts as $post){
				$exclude_counter++;
				if($post->ID == $last_post_id){
					$posts = array_slice($posts, $exclude_counter);
					break;
				}
			}
			$ajax_response = self::get_agdpevents_list_html($posts);
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	} */
}
