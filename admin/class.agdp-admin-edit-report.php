<?php

/**
 * AgendaPartage Admin -> Edit -> Report
 * Custom post type for WordPress in Admin UI.
 * 
 * Edition d'un report
 * Définition des metaboxes et des champs personnalisés des Reportes 
 *
 * Voir aussi Agdp_Report, Agdp_Admin_Report
 */
class Agdp_Admin_Edit_Report extends Agdp_Admin_Edit_Post_Type {

	const post_type = Agdp_Report::post_type;
	
	static $can_duplicate = true;

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		global $pagenow;
		if ( $pagenow === 'post.php' ) {
			add_action( 'add_meta_boxes_' . self::post_type, array( __CLASS__, 'register_report_metaboxes' ), 10, 1 ); //edit
			add_action( 'save_post_' . self::post_type, array(__CLASS__, 'save_post_report_cb'), 10, 3 );
			add_action( 'admin_notices', array(__CLASS__, 'on_admin_notices_cb'), 10);
			add_action( 'admin_enqueue_scripts', array(__CLASS__, 'on_admin_enqueue_styles'), 10, 1);
			add_action( 'admin_enqueue_scripts', array(__CLASS__, 'on_admin_enqueue_scripts'), 10, 1);
		}
		add_action( 'wp_ajax_'.AGDP_TAG.'_admin_edit_report_action', array(__CLASS__, 'on_ajax_action') );
	}
	/****************/
	
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function on_admin_enqueue_scripts( $hook ){
		if ( 'post.php' != $hook ) {
			return;
		}
		wp_register_script( AGDP_TAG . '-report', plugins_url( 'agenda-partage/admin/js/agendapartage-admin-report.js' ), array('jquery'), AGDP_VERSION , false );
	    wp_enqueue_script( AGDP_TAG . '-report' );
	}

	/**
	 * Registers a stylesheet.
	 */
	public static function on_admin_enqueue_styles() {
	    wp_register_style( AGDP_TAG . '-report', plugins_url( 'agenda-partage/admin/css/agendapartage-admin-report.css' ), array(), AGDP_VERSION, false );
	    wp_enqueue_style( AGDP_TAG . '-report');
	}

		
	/**
	 * Register Meta Boxes (boite en édition du forum)
	 */
	public static function on_admin_notices_cb(){
		global $post;
		if( ! $post )
			return;
		
		switch($post->post_type){
			// Edition d'une report
			case Agdp_Report::post_type:
				
				$alerts = [];
				$errors = [];
				//post_status
				if( $post->post_status != 'publish')
					$alerts[] = sprintf('Attention, cette page est marquée "%s".', (get_post_statuses())[$post->post_status]);
		
				//imap_suspend
				// $meta_key = 'imap_suspend';
				// if ( get_post_meta($post->ID, $meta_key, true) )
					// $errors[] = 'Attention, la connexion est suspendue.';
				
				//rendering
				if( $errors ){
					Agdp_Admin::add_admin_notice_now( implode('<br>', $errors)
						, ['type' => 'error']);
					$errors = [];
				}
				if( $alerts ){
					Agdp_Admin::add_admin_notice_now( implode('<br>', $alerts)
						, ['type' => 'warning']);
					$alerts = [];
				}
				
				break;
		}
	}
		
	/**
	 * Register Meta Boxes (boite en édition du report)
	 */
	public static function register_report_metaboxes($post){
		add_meta_box('agdp_report-inputs', __('Requête', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Report::post_type, 'normal', 'high');
		add_meta_box('agdp_report-variables', __('Variables', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Report::post_type, 'normal', 'high');
		add_meta_box('agdp_report-render', __('Rendu', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Report::post_type, 'normal', 'high');
		add_meta_box('agdp_report-shortcode', __('Intégration', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Report::post_type, 'normal', 'high');
	}

	/**
	 * Callback
	 */
	public static function metabox_callback($post, $metabox){
		//var_dump(func_get_args());
		
		switch ($metabox['id']) {
			
			case 'agdp_report-inputs':
				parent::metabox_html( self::get_metabox_inputs_fields(), $post, $metabox );
				break;
			
			case 'agdp_report-variables':
				parent::metabox_html( self::get_metabox_variables_fields(), $post, $metabox );
				break;
			
			case 'agdp_report-render':
				parent::metabox_html( self::get_metabox_render_fields(), $post, $metabox );
				
				echo Agdp_Report::get_report_html();
				break;
			
			case 'agdp_report-shortcode':
				parent::metabox_html( self::get_metabox_shortcode_fields(), $post, $metabox );
				break;
			
			default:
				break;
		}
	}
		
	/**
	 * get_metabox_all_fields
	 */
	public static function get_metabox_all_fields(){
		return array_merge(
			self::get_metabox_inputs_fields(),
			self::get_metabox_variables_fields(),
			self::get_metabox_render_fields(),
			self::get_metabox_shortcode_fields(),
		);
	}
	
	/**
	 * get_metabox_inputs_fields
	 */
	public static function get_metabox_inputs_fields(){
		
		$fields = [
			[	'name' => 'sql',
				'label' => '',//__('Requête', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'input_attributes' => 'rows="10" spellcheck="false"',
				'learn-more' => 
						static::get_variables_helper()
						.'<br>'.static::get_dbtables_helper()
						.'<br>'.static::get_sql_helper()
					,
			],
		];
		return $fields;				
	}
	
	/**
	 * get_metabox_variables_fields
	 */
	public static function get_metabox_variables_fields(){
		
		$fields = [
			[	'name' => 'sql_variables',
				'label' => '',
				'type' => 'json',
				'input' => 'textarea',
				'class' => 'agdpreport-variables',
				'style' => 'display:none',
			],
		];
		return $fields;				
	}
	
	public static function get_metabox_render_fields(){
		
		$report = get_post();
		$tax_report_styles = '';//<div class="report_style_terms">';
		$taxonomy = Agdp_Report::taxonomy_report_style;
		$icon_edit = Agdp::icon('edit');
		foreach( Agdp_Report::get_report_styles( $report, 'all' ) as $style ){
			$tax_report_styles .= sprintf('<div>%s<a href="/wp-admin/term.php?taxonomy=%s&tag_ID=%d&post_type=%s" target="_blank">%s</a></div>'
				, sprintf('<label><input type="checkbox" checked data-report-style="%s">%s</label>'
					, esc_attr( $style->description )
					, $style->name
				)
				, $taxonomy
				, $style->term_id
				, $report->post_type
				, $icon_edit
			);
		}
		// $tax_report_styles .= '</div>';
		
		$data = [];
		// $label = Agdp::get_ajax_action_link( $report, ['report', 'report_html'], 'update'
			// , 'Rafraîchir', 'Rafraîchir le rendu'
			// , /*$confirmation = */false, $data);
		$fields = [
			[	'name' => '',
				'label' => sprintf('<a href="" onclick="return false;">%s%s</a>', Agdp::icon('update'), 'Rafraîchir'),
				'input' => 'link',
				'class' => 'report_refresh', //cf admin-report.js
				'container_class' => 'report_toolbar_item', //cf admin-report.js
			],
			[	'name' => 'report_admin_no_escape',
				'label' => 'Autoriser le html',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
			],
			[	'name' => 'report_admin_no_render',
				'label' => 'Rendu sans mise en forme',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
			],
			[	'name' => 'report_show_sql',
				'label' => 'Afficher le SQL',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
				'fields' => [
					[	'name' => 'report_show_vars',
						'label' => 'Afficher les variables',
						'input' => 'checkbox',
						'container_class' => 'report_menu_item', //cf admin-report.js
					],
					[	'name' => 'report_show_sql_public',
						'label' => 'Sur le site (en tant qu\'Admin)',
						'input' => 'checkbox',
						'container_class' => 'report_menu_item', //cf admin-report.js
					],
				],
			],
			[	'name' => 'report_show_caption',
				'label' => 'Afficher le titre',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
			],
			[	'name' => 'report_show_footer',
				'label' => 'Afficher le pied de tableau',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
			],
			[	'name' => 'report_show_indexes',
				'label' => 'Afficher la colonne d\'index',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
			],
			[	'name' => 'report_show_table_designer',
				'label' => 'Afficher le designer de table',
				'input' => 'checkbox',
				'container_class' => 'report_menu_item', //cf admin-report.js
			],
			[	'name' => '',
				'label' => '',
				'input' => 'none',
				'container_class' => 'report_style_terms',
				'unit' => $tax_report_styles,
			],
			[	'name' => '',
				'label' => 'Style css',
				'type' => 'toggle',
				'fields' => [ 
				[	'name' => 'report_css',
					'class' => '',
					'input' => 'textarea',
					'container_class' => 'toggle-container report_css', //cf admin-report.js
					'learn-more' => 'Commencez chaque sélecteur par <code>table</code> pour que le css soit uniquement appliqué au tableau.'
				] ]
			],
			[	'name' => 'table_render',
				'label' => '',
				'type' => 'json',
				'input' => 'textarea',
				'input_attributes' => 'spellcheck="false"',
				'class' => 'agdpreport-table_render',
				'style' => 'display:none',
			],
		];
		return $fields;
				
	}
		
	/**
	 * get_metabox_shortcode_fields
	 */
	public static function get_metabox_shortcode_fields(){
		
		$report = get_post();
		if( ! $report )
			return;
		
		$variables = [];
		$sql_variables = Agdp_Report_Variables::normalize_sql_variables( $report, null, 'shortcode' );
		foreach($sql_variables as $var=>$variable){
			if( ! is_array($variable) )
				$value = print_r($variable, true);
			elseif( ! isset($variable['value']) )
				$value = $variable ? print_r($variable, true) : '';
			elseif( is_numeric($variable['value']) )
				$value = $variable['value'];
			elseif( is_array($variable['value']) )
				$value = $variable['value'] ? implode( '|', $variable['value'] ) : '';
			else
				$value = '"'. str_replace("\n", '|',$variable['value']) . '"';
			$variables[$var] = ':' . $var . '='. $value;
		}
		
		$shortcode = sprintf('[%s %d|%s %s]'
					, Agdp_Report::shortcode
					, $report->ID
					, get_post_path($report, '/')
					, implode( ' ', $variables )
				);
		$url = Agdp_Post::get_post_permalink($report
				, 'mode=shortcode'
				, 'shortcode=' . esc_attr($shortcode)
		);
		$test_msg = sprintf('Test : <a href="%s" target="_blank">Afficher le test</a>'
				, $url
		);
		
		// debug_log(__FUNCTION__,$shortcode, implode( 'implode ', $variables ));
		
		$fields = [
			[	'name' => 'shortcode',
				'label' => '['.Agdp_Report::shortcode.' ...]',//__('Requête', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'class' => 'shortcode',
				'input_attributes' => 'rows="2" spellcheck="false"',
				'default' => $shortcode,
				'learn-more' => 
						$test_msg 
						. '<br>' . static::get_shortcode_helper()
					,
			],
		];
		return $fields;
				
	}
	/**
	 * Callback lors de l'enregistrement du post.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function save_post_report_cb ($report_id, $report, $is_update){
		if( $report->post_status == 'trashed' ){
			return;
		}
		self::save_metaboxes($report_id, $report);
	}
	
	/**
	 * Retourne la liste de toutes les tables
	 */
	public static function get_dbtables_helper(){
		
		$ajax = toggle_shortcode_cb([
			'tag' => 'label',
			'title' => 'Liste des tables',
			'ajax' => [
				'action' => sprintf('%s_admin_action', AGDP_TAG),
				'method' => __CLASS__ .'::get_dbtables_helper',
				'data' => 'get_content',
				'refresh' => 'once',
			]
		]);
		return $ajax ;
	}
	
	/**
	 * Retourne la liste de toutes les tables
	 */
	public static function get_sql_helper(){
		
		$ajax = toggle_shortcode_cb([
			'tag' => 'label',
			'title' => 'Instructions SQL',
			'ajax' => [
				'action' => sprintf('%s_admin_action', AGDP_TAG),
				'method' => __CLASS__ .'::get_sql_helper',
				'data' => 'get_content',
				'refresh' => 'once',
			]
		]);
		return $ajax ;
	}
	/**
	 * Retourne la liste de toutes les tables
	 */
	public static function on_wp_ajax_action_get_dbtables_helper(){
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$tables = ['posts', 'postmeta'
				, 'comments', 'commentmeta'
				, 'term_relationships', 'term_taxonomy', 'termmeta', 'terms'
				, 'users', 'usermeta'
				, 'site', 'sitemeta'];
		$sql = 'SHOW TABLES';
		foreach($wpdb->get_results($sql) as $row){
			foreach($row as $table){
				if($blog_prefix)
					$table = substr($table, strlen($blog_prefix));
				if( is_numeric($table[0]) )
					continue;
				if( in_array($table, $tables) )
					continue;
				foreach( [ 'wpsbc_', 'actionscheduler_', 'easywpsmtp_' ] as $prefix )
					if( strcmp( $prefix, substr( $table, 0, strlen( $prefix ) ) ) === 0 )
						continue 2;
				$tables[] = $table;
				break;
			}
		}
		$html = '<ul>';
		foreach($tables as $index => $table){
			
			// $html .= sprintf('<a>%s</a>', $table);
			// $html .= Agdp::get_ajax_action_link(false, [ 'admin_edit_report','get_table_columns'], false, $table, false
												// , false, ['table'=>$table]);
			
			$ajax = esc_attr( json_encode ( array(
					'action' => AGDP_TAG.'_admin_edit_report_action',
					'method' => 'get_table_columns',
					'data' => ['table' => $table]
				)));
			$ajax = sprintf(' ajax="once" data="%s"', $ajax);
			$html .= sprintf('<li><a class="toggle-trigger" %s>%s</a></li>'
					, $ajax
					, esc_html( $table )
			);
			// $html .= toggle_shortcode_cbb(false, [ 'admin_edit_report','get_table_columns'], false, $table, false
												// , false, ['table'=>$table]);
		}
		$html .= '</ul>';
		return $html;
	}
	
	/**
	 * Requête Ajax 
	 TODO généraliser une fonction on_ajax_action( $called_class ) SEE get_variables_helper
	 */
	public static function on_ajax_action() {
		if( ! Agdp::check_nonce() )
			wp_die('nonce error');
		if( empty($_POST['method']))
			wp_die('method missing');
		
		$ajax_response = '';
		
		$method = $_POST['method'];
		$data = isset($_POST['data']) ? $_POST['data'] : [];
		
		if( $data && is_string($data) && ! empty($_POST['contentType']) && strpos( $_POST['contentType'], 'json' ) )
			$data = json_decode(stripslashes( $data), true);
		
		try {
			//cherche une fonction du nom "on_ajax_action_{method}"
			$function = array(get_called_class(), sprintf('on_ajax_action_%s', $method));
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
	 * Retourne la liste de toutes les colonnes d'une table
	 */
	public static function on_ajax_action_get_table_columns( ){
		$data = isset($_POST['data']) ? $_POST['data'] : [];
		if( ! empty($data['table']) ){
			$table = $data['table'];
			global $wpdb;
			$blog_prefix = $wpdb->get_blog_prefix();
			$columns = [];
			$sql = 'SHOW COLUMNS FROM ' . $blog_prefix . $table;
			foreach($wpdb->get_results($sql) as $row){
				foreach($row as $column){
					$columns[] = print_r( $column, true);
					break;
				}
			}
			$html = '<ul class="table_columns">';
			foreach($columns as $column)
				$html .= sprintf('<li><a>%s</a></li>', $column);
			$html .= '</ul>';
			$ajax_response = $html;
		}
		else
			$ajax_response = '(nom de table inconnu)';
		
		// Make your array as json
		wp_send_json($ajax_response);
	 
		// Don't forget to stop execution afterward.
		wp_die();
	}
	
	/**
	 * Retourne le commentaire sur le formatage de variables
	 */
	public static function get_variables_helper( ){
		global $post;
		
		$ajax = toggle_shortcode_cb([
			'tag' => 'label',
			'title' => 'Formatage des variables',
			'ajax' => [
				'action' => sprintf('%s_admin_action', AGDP_TAG),
				'method' => __CLASS__ .'::get_variables_helper',
				'data' => 'get_content',
				'refresh' => 'once',
			]
		]);
		$html = sprintf("<span>Utilisez le préfixe <b><code>%s</code></b> avant chaque nom de table WP.</span><br>", AGDP_BLOG_PREFIX);
		return $html . $ajax ;
	}
	
	/**
	 * Retourne le commentaire sur le formatage du shortcode
	 */
	public static function get_shortcode_helper( ){
		$html = '';
		return $html ;
	}
	/**
	 * Retourne le commentaire sur le formatage de variables
	 */
	public static function on_wp_ajax_action_get_variables_helper( $data ){
		global $post;
		
		$html = '<ul>';
		
		$html .= '<li>De la forme : <code>:var_name[%format]</code></li>';
		$html .= '<li><i>cf <a href="https://www.php.net/manual/fr/function.vsprintf.php">https://www.php.net/manual/fr/function.vsprintf.php</a></i></li>';
		$html .= '<li><code>%s</code> : type texte (par défaut)</li>';
		$html .= '<li><code>%d</code> : type nombre entier</li>';
		$html .= '<li><code>%+d</code> : -> avec signe</li>';
		$html .= '<li><code>%04d</code> : -> en 4 chiffres, complété par des zéros si besoin est.</li>';
		$html .= '<li><code>%f</code> : type nombre réel</li>';
		$html .= '<li><code>%.2f</code> : -> 2 décimales</li>';
		$html .= '<li><code>%i</code> : type identifiant (nom de table, de champ) encadré par des <code><b>`</b></code></li>';
		$html .= '<li><code>%IN</code> : type tableau dans une clause IN. ex. : <code>post.post_status IN (:post_status%IN)</code> devient <code>... IN ("pending", "publish")</code></li>';
		$html .= '<li><code>%IN</code> : inclut le sql d\'une sous-requête pour une clause IN. ex., pour une variable <code>:posts</code> de type Sous-requête : <code>INNER JOIN :posts%IN posts ON posts.ID = ...</code></li>';
		$html .= '<li><code>%K</code> : Ajoute <code>%</code> autour de la valeur de la variable pour un LIKE. ex. : <code>post.post_title LIKE :search%K)</code></li>';
		$html .= '<li><code>%KL</code> : Ajoute <code>%</code> à droite de la valeur de la variable pour un LIKE ("commence par").</li>';
		$html .= '<li><code>%KR</code> : Ajoute <code>%</code> à gauche de la valeur de la variable pour un LIKE ("se termine par").</li>';
		$html .= '<li>Pour un LIKE, le caractère <code>_</code> doit être précédé de <code>\</code>. ex. : <code>LIKE \'\_%\'</code>. Les formats <code>%K</code> ajoutent cet échappement.</li>';
		$html .= '<li><code>%I</code> : injection directe. ex. : <code>SHOW COLUMNS FROM `@.:table%I`</code></li>';
		
		$html .= '<li><i>cf <a href="https://dev.mysql.com/doc/refman/8.0/en/json-functions.html">https://dev.mysql.com/doc/refman/8.0/en/json-functions.html</a></i></li>';
		$html .= '<li><code>%J</code> : transforme en objet JSON pour MySQL. ex. : <code>SET @JSON = :json%J</code> qui est remplacé par <code>CAST( [variable] AS JSON )</code></li>';
			$html .= '<li><label><var>Sous-Rapports</var></label> : transforme le résultat de la sous-requête en objet JSON. <code>JSON_SEARCH( :termes%J, \'all\', \'search\' );</code>. <code>%J</code> peut être ignoré dans le cas d\'un <code>SET</code> : <code>SET @TERMS = :termes;</code></li>';
		$html .= '<li><code>%JT</code> : transforme du json en table. ex. : <code>SELECT * FROM :table%JT</code> Ex. de valeur : <code>[{"x":2,"y":"8"},{"x":"3","y":"7"},{"x":"4","y":6}]</code> cf <a href="#TODO">Rapports > Tutoriels > JSON > JSON_TABLE</a></li>';
		$html .= '<li><code>%JKV</code> : transforme du json en table de 2 colonnes : `key` et `value`. ex. : <code>SELECT * FROM :table%JKV</code> Ex. de valeur : <code>{ "small" : "Petit", "big" : "Grand" }</code> cf <a href="#TODO">Rapports > Tutoriels > JSON > Key : Value AS Table</a></li>';
		$html .= '<li><code>SET @DICO = SELECT key, name FROM ...</code> : transforme en dictionnaire JSON utilisable <code>@DICO.`key_field`</code> ou <code>@DICO[`key_field`]</code> ou <code>@DICO[\'key_value\']</code> ou <code>@DICO[@key_value]</code></li>';
		
		$html .= '<li><b>Variables globales</b> affectées par l\'instruction <code>SET <var>@var_name<var> = <var>value<var>;</code></li>';
		$html .= '<li><label><var>'.AGDP_VAR_DBRESULTS.'</var></label> vous donne accès aux résultats de la requête précédente sous forme JSON. ex. <code>'.AGDP_VAR_DBRESULTS.'.post_id</code>.</li>';
		$html .= '<li><label><var>&nbsp;</var></label> sous la forme <code>CONCAT('.AGDP_VAR_DBRESULTS.'[2].post_id, "-", '.AGDP_VAR_DBRESULTS.'.post_title)</code> vous noterez que l\'index de ligne (<code>[2]</code>) est implicite si il n\'est pas précisé.</li>';
		foreach( Agdp_Report::sql_global_vars( $post ) as $var => $value )
			$html .= sprintf('<li><label><var>%s</var></label> = %s</li>', $var, $value);
		$html .= sprintf('<li><label><var>%s</var></label> = { <var>`post_status`</var> : <var>`name`</var>, ... } </li>', AGDP_VAR_POST_STATUSES );
		$html .= sprintf('<li><label><var>%s</var></label> = { <var>`taxonomy`</var> : { <var>`slug`</var> : <var>`name`</var>, ... }, ... } </li>', AGDP_VAR_TAX_TERMS );
		$html .= sprintf('<li><label><var>%s_{taxonomy}</var></label> = { <var>`slug`</var> : <var>`name`</var>, ... } </li>', AGDP_VAR_TAX_TERMS );
		$html .= '<li><code>@:var_name</code> vous permet d\'accèder à des variables globales via un nom variable. Attention, <code>@BLOG:info_name%I</code> dans le cas d\'une partie du nom.</li>';
		
		$html .= '</ul></div>';
		return $html;
	}
	/**
	 * Retourne le commentaire sur les instructions sql
	 */
	public static function on_wp_ajax_action_get_sql_helper( $data ){
		$html = '<ul>';
		
		$html .= '<li><code>STOP;</code> : arrête l\'exécution des requêtes, évite l\'exécution des scripts du rendu.</li>';
		
		$html .= '<li>Les chaînes entre guillemets sont exclues de l\'analyse du code alors que les chaînes entre apostrophes sont analysées pour la recherche de variables.</li>';
		
		$html .= '<li>Les chaînes entre apostrophes ne doivent pas contenir le caractère <code>:</code> ou alors seul.</li>';
		$html .= '<li>Les chaînes entre apostrophes doivent "échapper" le caractère <code>"</code>. Utilisez <code>\'\"\'</code>.</li>';
		
		$html .= '</ul></div>';
		return $html;
	}
}
?>