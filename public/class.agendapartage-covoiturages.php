<?php

/**
 * AgendaPartage -> Covoiturages
 * Collection de covoiturages
 */
class AgendaPartage_Covoiturages {

	private static $initiated = false;
	public static $default_posts_query = [];
	
	private static $default_posts_per_page = 30;
	
	private static $filters_summary = null;

	public static function init() {
		if ( ! self::$initiated ) {
			
			self::init_default_posts_query();
			
			self::$initiated = true;

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {
		add_action( 'wp_ajax_'.AGDP_TAG.'_show_more', array(__CLASS__, 'on_wp_ajax_covoiturages_show_more_cb') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_show_more', array(__CLASS__, 'on_wp_ajax_covoiturages_show_more_cb') );
		add_action( 'wp_ajax_'.AGDP_TAG.'_covoiturages_action', array(__CLASS__, 'on_wp_ajax_covoiturages') );
		add_action( 'wp_ajax_nopriv_'.AGDP_TAG.'_covoiturages_action', array(__CLASS__, 'on_wp_ajax_covoiturages') );
	}
	/*
	 * Hook
	 ******/
	
	public static function get_url(){
		$url = get_permalink(AgendaPartage::get_option('agenda_page_id')) . '#main';
		// $url = home_url();
		return $url;
	}
	
	public static function init_default_posts_query() {
		
		self::$default_posts_query = array(
			'post_type' => AgendaPartage_Covoiturage::post_type,
			'post_status' => 'publish',
			
			'meta_query' => [
				'relation' => 'OR',
				'cov-date-debut' => [
					'key' => 'cov-date-debut',
					'value' => wp_date('Y-m-d'),
					'compare' => '>=',
					'type' => 'DATE'
				],
				'cov-date-fin' => [
					'relation' => 'AND',
					[
						'key' => 'cov-date-fin',
						'value' => '',
						'compare' => '!='
					],
					[
						'key' => 'cov-date-fin',
						'value' => wp_date('Y-m-d'),
						'compare' => '>=',
						'type' => 'DATE'
					]
				]
			],
			'orderby' => [
				'cov-date-debut' => 'ASC',
				'cov-heure-debut' => 'ASC',
			],
			
			'posts_per_page' => self::$default_posts_per_page
		);

	}
	
	/**
	* Retourne les paramètres pour WP_Query avec les paramètres par défaut.
	* N'inclut pas les filtres.
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
	 * Recherche des covoiturages d'un mois
	 */
	public static function get_month_posts($month){
		$today_month = date('Y-m');
		if(!$month) $month = $today_month;
		$date_min = substr($month, 0,4) . '-' . substr($month, 5,2) . '-' . ($month === $today_month ? date('d') : '01');
		if(substr($month, 5,2) === '12')
			$date_max = ((int)substr($month, 0,4) + 1) . '-01-01';
		else
			$date_max = substr($month, 0,4) . '-' . sprintf('%02d', ((int)substr($month, 5,2) + 1 )) . '-01';
		$query = array(
			'meta_query' => [
				'relation' => 'AND',
				[ [
					'key' => 'cov-date-debut',
					'value' => $date_min,
					'compare' => '>=',
					'type' => 'DATE',
				],
				'cov-date-debut' => [
					'key' => 'cov-date-debut',
					'value' => $date_max,
					'compare' => '<',
					'type' => 'DATE',
				] ]
			],
			'orderby' => [
				'cov-date-debut' => 'ASC',
				'cov-heure-debut' => 'ASC',
			],
			'nopaging' => true
			
		);
		
		$posts = self::get_posts($query, self::get_filters_query(false));
		
		return $posts;
    }
	
	/**
	 * Recherche de covoiturages
	 */
	public static function get_posts(...$queries){
		foreach($queries as $query)
			if(is_array($query) && array_key_exists('posts_where_filters', $query)){
				if( ! $query['posts_where_filters']){
					unset($query['posts_where_filters']);
					continue;
				}
				add_filter('posts_where', array(__CLASS__, 'on_posts_where_filters'),10,2);
				$posts_where_filters = true;
				// debug_log('get_posts $posts_where_filters = true;');
				break;
			}
		$query = self::get_posts_query(...$queries);

		// debug_log('get_posts $queries ', $queries);

        $the_query = new WP_Query( $query );
		// debug_log('get_posts ' . '<pre>'.$the_query->request.'</pre>');
        
		if( ! empty($posts_where_filters))
			remove_filter('posts_where', array(__CLASS__, 'on_posts_where_filters'),10,2);
		
		return $the_query->posts; 
    }
	/**
	* Filtre WP_Query sur une requête
	*/
	public static function on_posts_where_filters($where, $wp_query){
		// debug_log('on_posts_where_filters', $where , $wp_query->get( 'posts_where_filters' ));
		if($filters_sql = $wp_query->get( 'posts_where_filters' )){
			global $wpdb;
			$where .= ' AND ' . $wpdb->posts . '.ID IN ('.$filters_sql.')';
		}
		return $where;
	}

	/**
	 * Recherche de tous les mois contenant des covoiturages mais aussi les mois sans.
	 * Return array($month => $count)
	 */
	public static function get_posts_months(){
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		$sql_filters = self::get_filters_query(true);
		
		//Find this in other blog 
		$sql = "SELECT DISTINCT DATE_FORMAT(meta.meta_value, '%Y') as year
				, DATE_FORMAT(meta.meta_value, '%m') as month
				, COUNT(post_id) as count
				FROM {$blog_prefix}posts posts
				INNER JOIN {$blog_prefix}postmeta meta
					ON posts.ID = meta.post_id
					AND meta.meta_key = 'cov-date-debut'
					AND meta.meta_value >= CURDATE()
				WHERE posts.post_status = 'publish'
					AND posts.post_type = '". AgendaPartage_Covoiturage::post_type ."'
		";
		if($sql_filters)
			$sql .= " AND posts.ID IN ({$sql_filters})";
		
		$sql .= "GROUP BY year, month
				ORDER BY year, month
				";
		$result = $wpdb->get_results($sql);
		// debug_log('get_posts_months', $sql);
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
	 * Retourne les filtres
	 */
	public static function get_filters($filters = null){
		// debug_log('get_filters IN $_REQUEST', $_REQUEST);
		if( ! $filters){
			if(isset($_REQUEST['action'])
			&& $_REQUEST['action'] === 'filters'){
				$filters = $_REQUEST;
				unset($filters['action']);
			}
			elseif( isset($_REQUEST['data']) &&  isset($_REQUEST['data']['filters'])){
				return $_REQUEST['data']['filters'];
			}
			else
				return [];
			//possible aussi avec $_SERVER['referer']
			
		}
		if( isset($filters['data']) &&  isset($filters['data']['filters']))
			return $filters['data']['filters'];
		// debug_log('get_filters RETURN $filters', $filters);
		return $filters;
	}

	/**
	 * Ajoute un filtre sur une taxonomie
	 */
	public static function add_tax_filter($taxonomy, $term_id){
		if($term_id == -1)
			return;
		if(empty($_REQUEST['data']))
			$_REQUEST['data'] = ['filters'=>[]];
		elseif(empty($_REQUEST['data']['filters']))
			$_REQUEST['data']['filters'] = [];
		if(empty($_REQUEST['data']['filters'][$taxonomy . 's']))
			$_REQUEST['data']['filters'][$taxonomy . 's'] = [];
		$_REQUEST['data']['filters'][$taxonomy . 's'][$term_id . ''] = 'on';
	}

	/**
	 * Retourne les filtres de requête soit en sql soit array('posts_where_filters'=>sql) que get_posts() traite.
	 */
	public static function get_filters_query($return_sql = false, $filters = null){
		$filters = self::get_filters($filters);
		// debug_log('get_filters_query IN ', $filters);
		if(count($filters)){
			$query_tax_terms = [];
			foreach( AgendaPartage_Covoiturage_Post_type::get_taxonomies() as $tax_name => $taxonomy){
				$field = $taxonomy['filter'];
				if(isset($filters[$field])){
					$query_tax_terms[$tax_name] = ['IN'=>[]];
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
					if($tax_name === AgendaPartage_Covoiturage::taxonomy_city
					&& count($tax_data['IN'])){
						$sql .=   "\n INNER JOIN {$blog_prefix}postmeta as localisation"
									. "\n ON post.ID = localisation.post_id"
									. "\n AND localisation.meta_key = 'cov-localisation'"
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
						if($tax_name === AgendaPartage_Covoiturage::taxonomy_city){
							$terms_like = AgendaPartage_Covoiturage_Post_type::get_terms_like($tax_name, $tax_data['IN']);
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
		// debug_log('get_filters_query', isset($sql) ? $sql : '', $query);
		if($return_sql)
			return isset($sql) ? $sql : '';
		if( ! empty($sql))
			return [ 'posts_where_filters' => $sql ];
		return [];
	}
	
	/**
	* Rendu Html des covoiturages sous forme d'arborescence par mois
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
				'months' => -1,
				'mode' => 'list' //list|email|text|calendar|TODO...
			), $options);
		if( $options['mode'] == 'email' ){
			$options['ajax'] = false;
		}
		
		$option_ajax = (bool)$options['ajax'];
		
		$months = self::get_posts_months();
		
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
		
		$requested_id = array_key_exists(AGDP_ARG_COVOITURAGEID, $_GET) ? $_GET[AGDP_ARG_COVOITURAGEID] : false;
		$requested_month = false;
		if($requested_id){
			$date_debut = get_post_meta($requested_id, 'cov-date-debut', true);
			if($date_debut)
				$requested_month = substr($date_debut, 0, 7);
		}
		
		$html = sprintf('<div class="agdp-covoiturages agdp-covoiturages-%s">', $options['mode']);
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
						'action' => AGDP_TAG.'_show_more',
						'data' => $data
					)))
				);
			} else
				$ajax = false;
			$html .= sprintf(
				'<li><div class="month-title toggle-trigger %s %s" %s>%s <span class="nb-items">(%d)</span></div>
				<ul id="month-%s" class="covoiturages-month toggle-container">'
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
		
		if( $options['mode'] !== 'email' )
			$html .= self::download_links();
		
		$html .= '</div>' . $content;
		return $html;
	}
	
	/**
	* Rendu Html des covoiturages destinés au corps d'un email (newsletter)
	*
	*/
	public static function get_list_for_email($content = '', $options = false){
		if(!isset($options) || !is_array($options))
			$options = array();
		$options = array_merge(
			array(
				'ajax' => false,
				'months' => date('d') < 10 ? 1 : 2, //à partir du 10, on met le mois suivant aussi
				'mode' => 'email'
			), $options);
		
		if(AgendaPartage_Covoiturage_Post_type::is_diffusion_managed()){
			$term_id = AgendaPartage::get_option('newsletter_diffusion_term_id');
			self::add_tax_filter(AgendaPartage_Covoiturage::taxonomy_diffusion, $term_id);
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
	white-space: pre-line;
}
.agdp-covoiturages-email .month-title {
	margin-top: 1em;
	margin-bottom: 1em;
	font-size: larger;
	font-weight: bold;
	text-decoration: underline;
	text-transform: uppercase;
} 
.agdp-covoiturages-email .covoiturage .dates {
	font-size: larger;
	font-weight: bold;
} 
.agdp-covoiturages-email a-li a-li {
	margin-left: 1em;
	padding-top: 2em;
}
.agdp-covoiturages-email div.titre, .agdp-covoiturages-email div.localisation, .agdp-covoiturages-email div.cov-cities {
	font-weight: bold;
}
.agdp-covoiturages-email i {
	font-style: normal;
}
.footer {
	border-bottom: solid gray 2px;
	margin-bottom: 2em;
}
'
			. '</style>';
		$html = self::get_list_html($content, $options );
		$html = $css . $html;

		foreach([
			'agdp-covoiturages'=> 'aevs'
			, 'covoiturages'=> 'evs'
			, 'covoiturage-'=> 'cov-'
			, 'covoiturage '=> 'ev '
			, 'toggle-trigger' => 'tgt'
			, 'toggle-container' => 'tgc'
			
			, '<ul' => '<div class="a-ul"'
			, '</ul' => '</div'
			, '<li' => '<div class="a-li"'
			, '</li' => '</div'
			
			] as $search=>$replace)
			$html = str_replace($search, $replace, $html);
		foreach([
			'/\scovoiturage="\{[^\}]*\}"/' => '',
			'/\sid="\w*"/' => '',
			'/([\}\>\;]\s)\s+/m' => '$1'
			] as $search=>$replace)
			$html = preg_replace($search, $replace, $html);
		return $html;
	}
	
	/**
	* Rendu Html des covoiturages sous forme d'arborescence par mois
	*
	* Optimal sous la forme https://.../agenda-local/?eventid=1207#eventid1207
	*/
	public static function get_list_header($requested_month = false, $options = false){
		//TODO header('Location:index.php#main');
		
		$filters_summary = [];
		$all_selected_terms = [];
		if( ! current_user_can('manage_options') || AgendaPartage::get_option('newsletter_diffusion_term_id') == -1)
			$except_tax = AgendaPartage_Covoiturage::taxonomy_diffusion;
		else
			$except_tax = '';
		$taxonomies = AgendaPartage_Covoiturage_Post_type::get_taxonomies($except_tax);
		foreach( $taxonomies as $tax_name => $taxonomy){
			$taxonomy['terms'] = AgendaPartage_Covoiturage_Post_type::get_all_terms($tax_name);
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
		$html = '<div class="agdp-covoiturages-list-header">'
			. '<div id="agdp-filters" class="toggle-trigger">'
			. '<table><tr><th>'. __('Filtres', AGDP_TAG).'</th>'
			. '<td>'
			. '<p class="agdp-title-link">'
				. '<a href="'. get_page_link( AgendaPartage::get_option('new_covoiturage_page_id')) .'" title="Cliquez ici pour ajouter un nouveau covoiturage"><span class="dashicons dashicons-welcome-add-page"></span></a>'
				. '<a href="reload:" title="Cliquez ici pour recharger la liste"><span class="dashicons dashicons-update"></span></a>'
			. '</p>'
			. (count($filters_summary) ? '<div class="filters-summary">' 
				. implode(', ', $filters_summary)
				. AgendaPartage::icon('no', '', 'clear-filters', 'span', __('Efface les filtres', AGDP_TAG))
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
	* Rendu Html des covoiturages d'un mois sous forme de liste
	*/
	public static function get_month_events_list_html($month, $requested_id = false, $options = false){
		
		$events = self::get_month_posts($month);
		
		if(is_wp_error( $events)){
			$html = sprintf('<p class="alerte no-events">%s</p>%s', __('Erreur lors de la recherche de covoiturages.', AGDP_TAG), var_export($events, true));
		}
		elseif($events){
			$html = '';
			if(count($events) === 0){
				$html .= sprintf('<p class="alerte no-events">%s</p>', __('Aucun covoiturage trouvé', AGDP_TAG));
			}
			else {
				foreach($events as $event){
					$html .= '<li>' . self::get_item_list_html($event, $requested_id, $options) . '</li>';
				}
				
				//Ce n'est plus nécessaire, les mois sont chargés complètement
				//TODO post_per_page
				if(count($events) == self::$default_posts_per_page){
					$html .= sprintf('<li class="show-more"><h3 class="covoiturage toggle-trigger" ajax="show-more">%s</h3></li>'
						, __('Afficher plus de résultats', AGDP_TAG));
				}
			}
		}
		else{
				$html = sprintf('<p class="alerte no-events">%s</p>', __('Aucun covoiturage trouvé', AGDP_TAG));
			}
			
		return $html;
	}
	
	public static function get_item_list_html($event, $requested_id, $options){
		$email_mode = is_array($options) && isset($options['mode']) && $options['mode'] == 'email';
			
		$date_debut = get_post_meta($event->ID, 'cov-date-debut', true);
					
		$url = AgendaPartage_Covoiturage::get_post_permalink( $event );
		$html = '';
		
		if( ! $email_mode )
			$html .= sprintf(
					'<div class="show-post"><a href="%s">%s</a></div>'
				, $url
				, AgendaPartage::icon('media-default')
			);
			
		$html .= sprintf('<div id="%s%d" class="covoiturage toggle-trigger %s" covoiturage="%s">'
			, AGDP_ARG_COVOITURAGEID, $event->ID
			, $event->ID == $requested_id ? 'active' : ''
			, esc_attr( json_encode(['id'=> $event->ID, 'date' => $date_debut]) )
		);
		
		$cities = AgendaPartage_Covoiturage::get_covoiturage_cities ($event, 'names');
		
		$value = $event->post_title;
		$localisation = htmlentities(get_post_meta($event->ID, 'cov-localisation', true));
		if($cities){
			$cities = htmlentities(implode(', ', $cities));
			if(self::cities_in_localisation( $cities, $localisation ) === false)
				$localisation .= sprintf('<div class="covoiturage-cities" title="%s"><i>%s</i></div>', 'Communes', $cities);
		}
		$dates = AgendaPartage_Covoiturage::get_covoiturage_dates_text($event->ID);
		$html .= sprintf(
				'<div class="dates">%s</div>'
				.'<div class="titre">%s</div>'
				.'<div class="localisation">%s</div>'
			.''
			, htmlentities($dates), htmlentities($value), $localisation);
			
		
		$categories = AgendaPartage_Covoiturage::get_covoiturage_categories ($event, 'names');
		// var_dump($categories); die();
		if($categories)
			$html .= sprintf('<div class="covoiturage-categories" title="%s"><i>%s</i></div>', 'Catégories', htmlentities(implode(', ', $categories)));
		
		$html .= '</div>';
		
		$html .= '<div class="toggle-container">';
		
		
		$value = $event->post_content;
		if($value)
			$html .= sprintf('<pre>%s</pre>', htmlentities($value) );
		
		$value = get_post_meta($event->ID, 'cov-organisateur', true);
		if($value){
			$html .= sprintf('<div>Organisé par : %s</div>',  htmlentities($value) );
		}
		
		$value = get_post_meta($event->ID, 'cov-phone', true);
		if($value){
			$html .= sprintf('<div class="cov-phone">Téléphone : %s</div>',  antispambot($value) );
		}
		
		$value = get_post_meta($event->ID, 'cov-siteweb', true);
		if($value){
			$html .= sprintf('<div class="cov-siteweb">%s</div>',  make_clickable( esc_html($value) ) );
		}
		
		$html .= '<div class="footer">';
				
			$html .= '<table><tbody><tr>';
			
			if(is_user_logged_in()){
				global $current_user;
				//Rôle autorisé
				if(	$current_user->has_cap( 'edit_posts' ) ){
				
					$html .= '<td/><td>';
					$creator = new WP_User($event->post_author);
					$html .= 'créé par "' . $creator->get('user_nicename') . '"';
					
					$html .= '</td></tr><tr>';
				}
			}
			
			if( ! $email_mode )
				$html .= '<td class="trigger-collapser"><a href="#replier">'
					.AgendaPartage::icon('arrow-up-alt2')
					.'</a></td>';

			$url = AgendaPartage_Covoiturage::get_post_permalink($event);
			$html .= sprintf(
				'<td class="post-edit"><a href="%s">'
					.'Afficher la page du covoiturage'
					. ($email_mode  ? '' : AgendaPartage::icon('media-default'))
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
		//$terms_like = AgendaPartage_Covoiturage_Post_type::get_terms_like($tax_name, $tax_data['IN']);
		$cities = str_ireplace('saint', 'st'
					, preg_replace('/\s|-/', '', $cities)
		);
		$localisation = str_ireplace('saint', 'st'
					, preg_replace('/\s|-/', '', $localisation)
		);
		//TODO accents ?
		return stripos( $localisation, $cities );
	}

	/**
	 * Show more
	 */
	public static function on_wp_ajax_covoiturages_show_more_cb() {
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
	/* public static function on_wp_ajax_covoiturages_show_more_cb() {
		if(!array_key_exists("last-post", $_POST)){
			$ajax_response = '';
		}
		else {
			$last_post = $_POST["last-post"];
			$date_min = $last_post['date'];
			$last_post_id = $last_post['id'];
			$query = array(
				'meta_query' => [
					'cov-date-debut' => [
						'key' => 'cov-date-debut',
						'value' => $date_min,
						'compare' => '>=',
						'type' => 'DATE',
					],
					// 'cov-heure-debut' => [
						// 'key' => 'cov-heure-debut',
						// 'compare' => 'EXISTS', # we don't actually want any restriction around time
						// 'type' => 'TIME',
					// ],
				],
				'orderby' => [
					'cov-date-debut' => 'ASC',
					'cov-heure-debut' => 'ASC',
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
			$ajax_response = self::get_covoiturages_list_html($posts);
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	} */
	
	
	
	public static function download_links(){
		$html = '';
		$data = [
			'file_format' => 'ics'
		];
		$title = 'Télécharger les covoiturages au format ICS';
		if( count($_GET) && self::$filters_summary ){
			$title .= ' filtrés (' . self::$filters_summary . ')';
			$data['filters'] = $_GET;
		}
		$html .= AgendaPartage::get_ajax_action_link(false, ['covoiturages','download_file'], 'download', '', $title, false, $data);
		
		$meta_name = 'download_link';
		foreach(AgendaPartage_Covoiturage_Post_type::get_all_terms(AgendaPartage_Covoiturage::taxonomy_diffusion) as $term_id => $term){
			$file_format = get_term_meta($term->term_id, $meta_name, true);
			if( $file_format ){
				$data = [
					'file_format' => $file_format,
					'filters' => [ AgendaPartage_Covoiturage::taxonomy_diffusion => $term_id]
				];
				$title = sprintf('Télécharger les covoiturages pour %s (%s)', $term->name, $file_format);
				$html .= AgendaPartage::get_ajax_action_link(false, ['covoiturages','download_file'], 'download', '', $title, false, $data);
			}
		}
		return $html;
	}

	/**
	 * Requête Ajax
	 */
	public static function on_wp_ajax_covoiturages() {
		if( ! AgendaPartage::check_nonce()
		|| empty($_POST['method']))
			wp_die();
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = $_POST['data'];
		
		try{
			//cherche une fonction du nom "on_ajax_action_{method}"
			$function = array(__CLASS__, sprintf('on_ajax_action_%s', $method));
			$ajax_response = call_user_func( $function, $data);
		}
		catch( Exception $e ){
			$ajax_response = sprintf('Erreur dans l\'exécution de la fonction :%s', var_export($e, true));
		}
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}

	/**
	 * Requête Ajax de téléchargement de fichier
	 */
	public static function on_ajax_action_download_file($data) {
		
		$filters = empty($data['filters']) ? false : $data['filters'];
		$query = array(
			'meta_query' => [[
				'key' => 'cov-date-debut',
				'value' => wp_date('Y-m-d', strtotime(wp_date('Y-m-d') . ' + 1 year')),
				'compare' => '<=',
				'type' => 'DATE',
			], 
			'relation' => 'AND',
			[
				'key' => 'cov-date-debut',
				'value' => wp_date('Y-m-01'),
				'compare' => '>=',
				'type' => 'DATE',
			]],
			'orderby' => [
				'cov-date-debut' => 'ASC',
				'cov-heure-debut' => 'ASC',
			],
			'nopaging' => true
		);
			
		$query = self::get_filters_query(false, $filters);
		$posts = self::get_posts($query);
		// $filters_sql = self::get_filters_query(true, $filters);
		
		// global $wpdb;
		// $blog_prefix = $wpdb->get_blog_prefix();
		// $sql = "SELECT posts.*, date_debut.meta_value AS dt_debut, heure_debut.meta_value as h_debut"
			// . "\n FROM {$blog_prefix}posts posts"
			// . "\n INNER JOIN {$blog_prefix}postmeta date_debut"
			// . "\n ON date_debut.post_id = posts.ID"
			// . "\n AND date_debut.meta_key = 'cov-date-debut'"
			// . "\n AND date_debut.meta_value BETWEEN '" . wp_date('Y-m-01') . "'"
				// . "\n AND '" . wp_date('Y-m-d', strtotime(wp_date('Y-m-d') . ' + 1 year')) . "'"
			// . "\n LEFT JOIN {$blog_prefix}postmeta heure_debut"
			// . "\n ON heure_debut.post_id = posts.ID"
			// . "\n AND heure_debut.meta_key = 'cov-heure-debut'"
			// . "\n WHERE posts.post_status = 'publish'"
			// . ($filters_sql ? "\n AND posts.ID IN (" . $filters_sql . ")" : '')
			// . "\n ORDER BY date_debut.meta_value, heure_debut.meta_value";
		// $result = $wpdb->get_results($sql);
		
		// if( is_wp_error($result)){
			// debug_log('on_ajax_action_download_file wp_error ',$sql, $result->request);
			// return 'Erreur sql';
		// }
        // $posts = $result; 
		
		if( ! $posts)
			return sprintf('Aucun covoiturage à exporter');
		
		$file_format = $data['file_format'];
		
		require_once( dirname(__FILE__) . '/class.agendapartage-covoiturages-export.php');
		$url = AgendaPartage_Covoiturages_Export::do_export($posts, $file_format, 'url');
		
		return 'download:' . $url;
	}
}
