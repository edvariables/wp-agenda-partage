<?php

/**
 * AgendaPartage -> Covoiturages
 * Collection de covoiturages
 */
class Agdp_Covoiturages extends Agdp_Posts {

	const post_type = Agdp_Covoiturage::post_type;
	const postid_argument = Agdp_Covoiturage::postid_argument;
	const page_id_option = Agdp_Covoiturage::posts_page_option;
	
	const icon = 'car';

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
	
	/**
	 * default_posts_query
	 */
	public static function init_default_posts_query() {
		
		self::$default_posts_query = array(
			'post_type' => Agdp_Covoiturage::post_type,
			'post_status' => 'publish',
			
			'meta_query' => [
				'relation' => 'OR', //Bugg 
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
	 * Recherche des covoiturages d'une semaine
	 * $year_week : yyyy-ww
	 */
	public static function get_week_posts($year_week){
		if( ! $year_week) $year_week = date('Y-w');
		
		$dates = get_week_dates(substr($year_week, 0,4), substr($year_week, 5,2));
		$date_min = $dates['start'];
		$date_max = $dates['end'];
		
		$query = array(
			'meta_query' => [
				'relation' => 'AND',
				[	[
						'key' => 'cov-date-debut',
						'value' => $date_min,
						'compare' => '>=',
						'type' => 'DATE',
					],
					'cov-date-debut' => [
						'key' => 'cov-date-debut',
						'value' => $date_max,
						'compare' => '<=',
						'type' => 'DATE',
					],
					'cov-periodique' => [
						'key' => 'cov-periodique',
						'value' => '1',
						'compare' => '!='
				]	]	
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
	 * Recherche des covoiturages périodiques
	 */
	public static function get_periodique_posts(){
		
		$date_min = date('Y-m-d');
		
		$query = array(
			'meta_query' => [
				'relation' => 'AND',
				[	[
						'key' => 'cov-date-debut',
						'value' => $date_min,
						'compare' => '>=',
						'type' => 'DATE',
					],
					'cov-periodique' => [
						'key' => 'cov-periodique',
						'value' => '1',
						'compare' => '='
				]	]	
			],
			'orderby' => [
				'post_date' => 'DESC',
			],
			'nopaging' => true
			
		);
		
		$posts = self::get_posts($query, self::get_filters_query(false));
		
		return $posts;
    }
	
	/**
	 * Recherche de toutes les semaines contenant des covoiturages mais aussi les semaines sans.
	 * Return array($year-$semaine => $count)
	 */
	public static function get_posts_weeks(){
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		$sql_filters = self::get_filters_query(true);
		
		$sql = "SELECT DISTINCT DATE_FORMAT(meta_date.meta_value, '%Y') as year
				/*, DATE_FORMAT(meta_date.meta_value, '%m') as month*/
				, DATE_FORMAT(meta_date.meta_value, '%u') as week
				, COUNT(posts.ID) as count
				FROM {$blog_prefix}posts posts
				INNER JOIN {$blog_prefix}postmeta meta_date
					ON posts.ID = meta_date.post_id
					AND meta_date.meta_key = 'cov-date-debut'
					AND meta_date.meta_value >= CURDATE()
				INNER JOIN {$blog_prefix}postmeta meta_periodique
					ON posts.ID = meta_periodique.post_id
					AND meta_periodique.meta_key = 'cov-periodique'
					AND meta_periodique.meta_value = 0
				WHERE posts.post_status = 'publish'
					AND posts.post_type = '". Agdp_Covoiturage::post_type ."'
		";
		if($sql_filters)
			$sql .= " AND posts.ID IN ({$sql_filters})";
		
		$sql .= "GROUP BY year, week
				ORDER BY year, week
				";
		$result = $wpdb->get_results($sql);
		// debug_log('get_posts_weeks', $sql);
		$weeks = [];
		$prev_row = false;
		foreach($result as $row){
			if($prev_row){
				if($prev_row->year === $row->year){
					for($m = (int)$prev_row->week + 1; $m < (int)$row->week; $m++)
						$weeks[$prev_row->year . '-' . sprintf("%02d",$m)] = 0;
				}
				elseif((int)$prev_row->year === (int)$row->year - 1){
					$max_year_week = get_last_week($prev_row->year);
					for($m = (int)$prev_row->week + 1; $m <= $max_year_week; $m++)
						$weeks[$prev_row->year . '-' . $m] = 0;
					for($m = 1; $m < (int)$row->week; $m++)
						$weeks[$row->year . '-' . sprintf("%02d",$m)] = 0;
				}
				elseif((int)$prev_row->year < (int)$row->year - 1)
					break;
			}
			$weeks[$row->year . '-' . $row->week] = (int)$row->count;
			$prev_row = $row;
		}
		return $weeks;
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
			foreach( Agdp_Covoiturage_Post_type::get_taxonomies() as $tax_name => $taxonomy){
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
		
			global $wpdb;
			$blog_prefix = $wpdb->get_blog_prefix();
			$sql = "SELECT post.ID"
				. "\n FROM {$blog_prefix}posts post";
			$sql_where = '';
			if(count($query_tax_terms)){
				//JOIN
				foreach($query_tax_terms as $tax_name => $tax_data){
					$sql .= "\n LEFT JOIN {$blog_prefix}term_relationships tax_rl_{$tax_name}"
								. "\n ON post.ID = tax_rl_{$tax_name}.object_id"
					;
					if($tax_name === Agdp_Covoiturage::taxonomy_city
					&& count($tax_data['IN'])){
						$sql .=   "\n INNER JOIN {$blog_prefix}postmeta as localisation"
									. "\n ON post.ID = localisation.post_id"
									. "\n AND localisation.meta_key = 'cov-localisation'" //TODO
						;
					}
				}
				//WHERE
				foreach($query_tax_terms as $tax_name => $tax_data){
					if(count($tax_data['IN'])){
						$tax_sql = "\n tax_rl_{$tax_name}.term_taxonomy_id"
								. " IN (" . implode(', ', $tax_data['IN']) . ')'
						;
						if($tax_name === Agdp_Covoiturage::taxonomy_city){
							$terms_like = Agdp_Covoiturage_Post_type::get_terms_like($tax_name, $tax_data['IN']);
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
			}
			
			//Intention
			$field = 'cov-intention';
			if(isset($filters[$field])){
				$intentions = implode(',', array_keys( $filters[$field]));
				$intentions .= ', 3';
				 $sql .=   "\n INNER JOIN {$blog_prefix}postmeta as intention"
							. "\n ON post.ID = intention.post_id"
							. "\n AND intention.meta_key = 'cov-intention'" //TODO
				;
				$sql_where .= ($sql_where ? "\n AND " : "\n WHERE ")
					. '(intention.meta_value IN (' . $intentions . '))';
			}
			
			if($sql_where)
				$sql .= $sql_where;
			else
				unset($sql);
		}
		// debug_log('get_filters_query', isset($sql) ? $sql : '', $query);
		if($return_sql)
			return isset($sql) ? $sql : '';
		if( ! empty($sql))
			return [ 'posts_where_filters' => $sql ];
		return [];
	}
	
	/**
	* Rendu Html des covoiturages sous forme d'arborescence par semaine
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
		
		//Semaines avec indicateurs du nombre de covoiturages, sans les périodiques.
		$weeks = self::get_posts_weeks();
		
		if(isset($options['months']) && $options['months'] !== ANTERIORITY_ALL && $options['months'] > 0 && count($weeks) > $options['months']*4)
			$weeks = array_slice($weeks, 0, $options['months'] * 4, true);
		elseif(isset($options['weeks']) && $options['weeks'] > 0 && count($weeks) > $options['weeks'])
			$weeks = array_slice($weeks, 0, $options['weeks'], true);
		elseif(isset($options['days']) && $options['days'] > 0 && count($weeks) > 1)
			$weeks = array_slice($weeks, 0, 1, true);
		elseif(isset($options['hours']) && $options['hours'] > 0 && count($weeks) > 1)
			$weeks = array_slice($weeks, 0, 1, true);
		
		//Si le premier mois est déjà gros, on diffère le chargement du suivant par ajax
		$events_count = 0;
		$weeks_count = 0;
		foreach($weeks as $week => $week_events_count) {
			$events_count += $week_events_count;
			$weeks_count++;
			if($events_count >= $options['max_events']){
				if($options['start_ajax_at_month_index'] > $weeks_count)
					$options['start_ajax_at_month_index'] = $weeks_count;
				break;
			}
		}
		
		//Covoiturages périodiques
		$periodique_posts = self::get_periodique_posts();
		
		if( $events_count === 0
		&& count($periodique_posts) === 0)
			return false;
		
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
		
		//Covoiturages périodiques
		if( count($periodique_posts) && $events_count ){
			$html .= sprintf(
				'<div class="cov-periodiques-link">%sVous trouverez,%s plus bas,<span class="cov-periodiques"> %d %s</span>.%s</div>'
				, $options['mode'] == 'email' ? '' : '<span class="dashicons dashicons-info"></span>'
				, $options['mode'] == 'email' ? '' : '<a href="#periodiques">'
				, count($periodique_posts)
				, count($periodique_posts) === 1 ? "covoiturage périodique" : "covoiturages périodiques"
				, $options['mode'] == 'email' ? '' : '</a>'
			);
		}
		
		$not_empty_month_index = 0;
		$events_count = 0;
		
		if( $option_ajax )
			$filters = self::get_filters();
		
		$html .= '<ul>';
		foreach($weeks as $week => $week_events_count) {
			if( $option_ajax
			&& ($not_empty_month_index >= $options['start_ajax_at_month_index'])
			&& $week_events_count > 0
			&& $week !== $requested_month) {
				$data = [ 'month' => $week ];
				if($filters && count($filters))
					$data['filters'] = $filters;
				$ajax = sprintf('ajax="once" data="%s"',
					esc_attr( json_encode ( array(
						'action' => Agdp_Covoiturage::post_type.'_show_more',
						'data' => $data
					)))
				);
			} else
				$ajax = false;
			
			$week_summary = '';
			
			$week_dates = get_week_dates(substr($week, 0,4), substr($week, 5,2));
			if( substr($week_dates['start'], 5,2) === substr($week_dates['end'], 5,2)){
				$week_dates['start'] = wp_date('j', strtotime($week_dates['start']));
				$week_dates['end'] = wp_date('j F Y', strtotime($week_dates['end']));
			}
			else {
				$week_dates['start'] = wp_date('j F', strtotime($week_dates['start']));
				$week_dates['end'] = wp_date('j F Y', strtotime($week_dates['end']));
			}
			if(trim(substr($week_dates['start'], 0,2)) == '1')
				$week_dates['start'] = '1er ' . substr($week_dates['start'],3);
			if(trim(substr($week_dates['end'], 0,2)) == '1')
				$week_dates['end'] = '1er ' . substr($week_dates['end'],3);
			
			$week_label = sprintf('du %s au %s', $week_dates['start'], $week_dates['end']);
			
			$html .= sprintf(
				'<li><div class="month-title toggle-trigger %s %s" %s>%s <span class="nb-items">(%d)</span>%s</div>
				<ul id="month-%s" class="covoiturages-month toggle-container">'
				, $week_events_count === 0 ? 'no-items' : ''
				, !$ajax && $week_events_count ? 'active' : ''
				, $ajax ? $ajax : ''
				, $week_label
				, $week_events_count
				, $week_summary
				, $week
			);
			if(!$ajax && $week_events_count){
				$html .= self::get_week_posts_list_html( $week, $requested_id, $options );
			}
		
			$html .= '</ul></li>';
			
			if($week_events_count > 0)
				$not_empty_month_index++;
			$events_count += $week_events_count;
		}
		
		if( count($periodique_posts) ){
			$html .= sprintf(
				'<li><div class="month-title cov-periodiques toggle-trigger active">%s <span class="nb-items">(%d)</span></div>
				<ul id="periodiques" class="covoiturages-periodiques toggle-container">'
				, "Covoiturages périodiques"
				, count($periodique_posts)
			);
			foreach($periodique_posts as $post){
				$html .= '<li>' . self::get_list_item_html( $post, $requested_id, $options ) . '</li>';
			}
		
			$html .= '</ul></li>';
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
			, ANTERIORITY_THREEWEEKS
			, $newsletter
		);
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
				'mode' => 'email'
			), self::filter_anteriority_option($options));
		
		if(Agdp_Covoiturage_Post_type::is_diffusion_managed()){
			$term_id = Agdp::get_option('newsletter_diffusion_term_id');
			self::add_tax_filter(Agdp_Covoiturage::taxonomy_diffusion, $term_id);
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
}
.toggle-container pre {
	background-color: #F5F5F5;
	color: #333;
	white-space: pre-line;
}
.toggle-container .agdevents {
	white-space: collapse;
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
.agdp-covoiturages-email span.cov-nb-places {
	padding-left: 1em;
}
.agdp-covoiturages-email i {
	font-style: normal;
}
.cov-depart, .cov-arrivee, .cov-date-jour-num {
	font-size: larger;
	font-variant-caps: small-caps;
}
.cov-periodiques, .cov-periodiques .nb-items {
	background-color: #dba526;
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
			'agdp-covoiturages'=> 'aevs'
			, 'covoiturage-'=> 'cov-'
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
			'/\scovoiturage="\{[^\}]*\}"/' => '',
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
		if( ! current_user_can('manage_options') || Agdp::get_option('newsletter_diffusion_term_id') == -1)
			$except_tax = Agdp_Covoiturage::taxonomy_diffusion;
		else
			$except_tax = '';
		$taxonomies = Agdp_Covoiturage_Post_type::get_taxonomies($except_tax);
		foreach( $taxonomies as $tax_name => $taxonomy){
			$taxonomy['terms'] = Agdp_Covoiturage::get_all_terms($tax_name);
			if( count($taxonomy['terms']) === 0 ){
				unset($taxonomy['terms']);
				continue;
			}
			if( isset($taxonomy['all_label'])
			&& count($taxonomy['terms']) > 1 )
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
		
		//intention
		$tax_name = 'cov-intention';
		$intentions = [
			'label' => 'Proposition et/ou recherche',
			'filter' => $tax_name,
			'terms' => [
				[
					'term_id' => '1',
					'name' => 'Propose'
				],
				[
					'term_id' => '2',
					'name' => 'Cherche'
				]
			]
		];
		$selected_terms = isset($_GET[$intentions['filter']]) ? $_GET[$intentions['filter']] : false;
		if(is_array($selected_terms))
			foreach($selected_terms as $intentionid => $data)
				$filters_summary[] = Agdp_Covoiturage_Post_type::get_intention_label( $intentionid );
		$all_selected_terms[$tax_name] = $selected_terms;
		$taxonomies = array_merge(['cov-intention' => $intentions], $taxonomies);
		
		// Render
		$html = '<div class="agdp-covoiturages-list-header">'
			. sprintf('<div id="agdp-filters" class="toggle-trigger %s">', count($filters_summary) ? 'active' : '')
			. '<table><tr><th>'. __('Filtres', AGDP_TAG).'</th>'
			. '<td>'
			. '<p class="agdp-title-link">'
				. '<a href="'. get_page_link( Agdp::get_option('new_covoiturage_page_id')) .'" title="Cliquez ici pour ajouter un nouveau covoiturage"><span class="dashicons dashicons-welcome-add-page"></span></a>'
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
			if( count($taxonomy['terms']) === 1
			|| ! isset($taxonomy['plural']) )
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
	public static function get_week_posts_list_html($week, $requested_id = false, $options = false){
		
		$events = self::get_week_posts($week);
		
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
					$html .= '<li>' . self::get_list_item_html($event, $requested_id, $options) . '</li>';
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
	
	public static function get_list_item_html($post, $requested_id, $options){
		$email_mode = is_array($options) && isset($options['mode']) && $options['mode'] == 'email';
			
		$date_debut = get_post_meta($post->ID, 'cov-date-debut', true);
					
		$url = Agdp_Covoiturage::get_post_permalink( $post );
		$html = '';
		
		if( ! $email_mode )
			$html .= sprintf(
					'<div class="show-post"><a href="%s">%s</a></div>'
				, $url
				, Agdp::icon('media-default')
			);
			
		$html .= sprintf('<div id="%s%d" class="covoiturage toggle-trigger %s" covoiturage="%s">'
			, AGDP_ARG_COVOITURAGEID, $post->ID
			, $post->ID == $requested_id ? 'active' : ''
			, esc_attr( json_encode(['id'=> $post->ID, 'date' => $date_debut]) )
		);
		
		//$cities = Agdp_Covoiturage::get_covoiturage_cities ($post, 'names');
		
		$title = Agdp_Covoiturage::get_post_title( $post, false );
		// $dates = Agdp_Covoiturage::get_covoiturage_dates_text($post->ID);
		$html .= sprintf('<div class="titre">%s</div>', $title);
		
		if( $agdpevents = self::get_covoiturage_agdpevents( $post, false )){
			$html .= sprintf('<div class="agdpevents">%s</div>', $agdpevents);
		}
		
		$html .= date_diff_text($post->post_date, true, '<div class="created-since">', '</div>');
		
		$html .= '</div>';
		
		$html .= '<div class="toggle-container">';
		
		
		$value = $post->post_content;
		if($value)
			$html .= sprintf('<pre>%s</pre>', htmlentities($value) );
		
		$value = get_post_meta($post->ID, 'cov-organisateur', true);
		if($value){
			$html .= sprintf('<div>Organisé par : %s</div>',  htmlentities($value) );
		}
		
		$show_phone = /*! is_user_logged_in() &&*/ get_post_meta($post->ID, 'cov-phone-show', true);
		$value = get_post_meta($post->ID, 'cov-phone', true);
		if($value){
			if( $email_mode && ! $show_phone)
				$value = sprintf('<a href="%s">(masqué)</a>', $url);
			else
				$value = Agdp_Covoiturage::get_phone_html($post->ID);
			$html .= sprintf('<div class="cov-phone">Téléphone : %s</div>',  $value);
		}
		
		if( ! $email_mode && ($agdpevents = self::get_covoiturage_agdpevents( $post, true ))){
			$html .= sprintf('<div class="agdevents">%s</div>', $agdpevents);
		}
		
		$html .= '<div class="footer">';
				
			$html .= '<table><tbody><tr>';
			
			if(is_user_logged_in()){
				global $current_user;
				//Rôle autorisé
				if(	$current_user->has_cap( 'edit_posts' ) ){
					$creator = new WP_User($post->post_author);
					if(($user_name = $creator->get('display_name'))
					|| ($user_name = $creator->get('user_login'))){
						$html .= '<td/><td>';
						$html .= 'créé par <a>' . $user_name . '</a>';
						
						$html .= '</td></tr><tr>';
					}
				}
			}
			
			if( ! $email_mode )
				$html .= '<td class="trigger-collapser"><a href="#replier">'
					.Agdp::icon('arrow-up-alt2')
					.'</a></td>';
					
			if( $email_mode && ($agdpevents = self::get_covoiturage_agdpevents( $post, true ))){
				$html .= sprintf('</td></tr><tr><td class="agdevents">%s</td></tr><tr>',  $agdpevents );
			}
			
			$html .= sprintf(
				'<td class="post-edit"><a href="%s">'
					.'Afficher la page du covoiturage'
					. ($email_mode  ? '' : Agdp::icon('media-default'))
				.'</a></td>'
				, $url);
				
			$html .= '</tr></tbody></table>';
			
		$html .= '</div>';

		$html .= '</div>';
		
		return $html;
	}
	
 	/**
 	 * Retourne les évènements liés au covoiturage
 	 */
	public static function get_covoiturage_agdpevents( $covoiturage, $details = true ) {
		
		if( ! $covoiturage
		 || ! Agdp_Covoiturage::is_managed() )
			return '';
		
		$meta_name = 'cov-periodique';
		if( $is_periodique = get_post_meta($covoiturage->ID, $meta_name, true) )
			return '';
		
		//cache
		static $agdpevents_covoiturage_id;
		static $agdpevents;
		if( ! $agdpevents_covoiturage_id || $agdpevents_covoiturage_id !== $covoiturage->ID ){
			$agdpevents_covoiturage_id = $covoiturage->ID;
		
			$meta_name = 'related_' . Agdp_Evenement::post_type;
			
			$related_agdpevents = get_post_meta( $covoiturage->ID, $meta_name, false );
			if( count($related_agdpevents) === 1 && !$related_agdpevents[0] )
				$related_agdpevents = [];
			
			if( count($related_agdpevents) )
				$agdpevents = [get_post($related_agdpevents[0])];
			else
				$agdpevents = [];
		}
		
		if( $details ) {
			$html = sprintf('<ul class="agdp-agdpevents-list">');
			if( count($agdpevents) === 1 ){
				$agdpevent = $agdpevents[0];
				$html .= sprintf('<span><a href="%s?%s=%d">%s Évènement associé : %s</a></span>'
						, get_post_permalink($agdpevent)
						, AGDP_ARG_COVOITURAGEID, $covoiturage->ID
						, Agdp::icon('calendar-alt')
						, Agdp_Evenement::get_post_title($agdpevent, true)
				);
			}
			elseif( count($agdpevents) ){
				$html .= sprintf('<span>%s %s évènement%s associé%s</span>', Agdp::icon('calendar-alt'), count($agdpevents), count($agdpevents) > 1 ? 's' : '', count($agdpevents) > 1 ? 's' : '');
				foreach($agdpevents as $agdpevent){
					$html .= sprintf('<li><a href="%s?%s=%d">%s</a></li>'
						, get_post_permalink($agdpevent)
						, AGDP_ARG_COVOITURAGEID, $covoiturage->ID
						, $agdpevent->post_title
					);
				}
			}
			
			$html .= '</ul>';
		}
		else {
			$html = '';
			if( count($agdpevents) === 1 ){
				$agdpevent = $agdpevents[0];
				$html .= sprintf('<span>%s Évènement associé : %s</span>'
						, Agdp::icon('calendar-alt')
						, Agdp_Evenement::get_post_title($agdpevent, true)
				);
			}
			elseif( count($agdpevents) ){
				$html .= sprintf('<span>%s %s évènement%s associé%s</span>', Agdp::icon('calendar-alt'), count($agdpevents), count($agdpevents) > 1 ? 's' : '', count($agdpevents) > 1 ? 's' : '');
				foreach($agdpevents as $agdpevent){
					$html .= sprintf('<span title="%s">%s</span>'
						, Agdp_Evenement::get_post_title($agdpevent, true)
						, Agdp::icon('calendar-alt')
					);
				}
			}
			
		}
		return $html;
	}
	
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
		$html .= Agdp::get_ajax_action_link(false, ['covoiturages','download_file'], 'download', '', $title, false, $data);
		
		$meta_name = 'download_link';
		foreach(Agdp_Covoiturage::get_all_terms(Agdp_Covoiturage::taxonomy_diffusion) as $term_id => $term){
			$file_format = get_term_meta($term->term_id, $meta_name, true);
			if( $file_format ){
				$data = [
					'file_format' => $file_format,
					'filters' => [ Agdp_Covoiturage::taxonomy_diffusion => $term_id]
				];
				$title = sprintf('Télécharger les covoiturages pour %s (%s)', $term->name, $file_format);
				
				$href = sprintf('/wp-admin/admin-ajax.php/?action=%s_%s_action&%s'
					, AGDP_TAG
					, 'covoiturages_download'
					, http_build_query(['data' => $data]) );
				$html .= sprintf('<a href="%s"><span class="dashicons-before dashicons-download "></span></a>', $href);
			}
		}
		return $html;
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
		
		if( ! $posts)
			return sprintf('Aucun covoiturage à exporter');
		
		$file_format = $data['file_format'];
		
		require_once( dirname(__FILE__) . '/class.agdp-covoiturages-export.php');
		$url = Agdp_Covoiturages_Export::do_export($posts, $file_format, 'url');
		
		return 'download:' . $url;
	}
}
?>