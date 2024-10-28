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
		add_meta_box('agdp_report-render', __('Rendu', AGDP_TAG), array(__CLASS__, 'metabox_callback'), Agdp_Report::post_type, 'normal', 'high');
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
			
			case 'agdp_report-render':
				parent::metabox_html( self::get_metabox_render_fields(), $post, $metabox );
				
				echo Agdp_Report::get_report_html();
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
			self::get_metabox_render_fields(),
		);
	}
	
	/**
	 * get_metabox_inputs_fields
	 */
	public static function get_metabox_inputs_fields(){
		
		$report = get_post();
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		
		$fields = [
			[	'name' => 'sql',
				'label' => '',//__('Requête', AGDP_TAG),
				'type' => 'text',
				'input' => 'textarea',
				'input_attributes' => 'rows="10" spellcheck="false"',
				'learn-more' => 
						sprintf("Utilisez le préfixe \"%s\" avant chaque nom de table.", AGDP_BLOG_PREFIX)
						.'<br>'.static::get_variables_helper()
						.'<br>'.static::get_sql_helper()
					,
			],
			[	'name' => 'sql_variables',
				'label' => 'Variables',
				'type' => 'text',
				'input' => 'textarea',
				'input_attributes' => 'spellcheck="false"',
				'class' => 'agdpreport-variables',
				'style' => 'display:none',
			],
		];
		return $fields;
				
	}
	
	public static function get_metabox_render_fields(){
		
		$report = get_post();
		
		$data = [];
		// $label = Agdp::get_ajax_action_link( $report, ['report', 'report_html'], 'update'
			// , 'Rafraîchir', 'Rafraîchir le rendu'
			// , /*$confirmation = */false, $data);
		$fields = [
			[	'name' => '',
				'label' => sprintf('<a href="" onclick="return false;">%s%s</a>', Agdp::icon('update'), 'Rafraîchir'),
				'input' => 'link',
				'class' => 'report_refresh', //cf admin-report.js
			],
			[	'name' => 'report_show_sql',
				'label' => 'Afficher le SQL',
				'type' => 'checkbox',
				'container_class' => 'report_show_sql', //cf admin-report.js
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
	public static function get_sql_helper(){
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$tables = [];
		$sql = 'SHOW TABLES';
		foreach($wpdb->get_results($sql) as $row){
			foreach($row as $table){
				if($blog_prefix)
					$table = substr($table, strlen($blog_prefix));
				if( is_numeric($table[0]) )
					continue;
				$tables[] = $table;
				break;
			}
		}
		$html = '<span class="toggle-trigger dashicons-before dashicons-plus">Liste des tables</span>'
			. '<div class="toggle-container sql-helper-tables"><code>';
		foreach($tables as $index => $table){
			if( $index )
				$html .= ' - ';
			$html .= sprintf('<a href="#">%s</a>', $table);
		}
		$html .= '</code></div>';
		return $html;
	}
	
	/**
	 * Retourne le commentaire sur le formatage de variables
	 */
	public static function get_variables_helper(){
		
		$html = '<span class="toggle-trigger dashicons-before dashicons-plus">Formatage des variables</span>'
			. '<div class="toggle-container sql-helper-variables"><ul>';
		
		$html .= '<li>De la forme : <code>:var_name[%format]</code>';
		$html .= '<li><code>%s</code> : type texte (par défaut)';
		$html .= '<li><code>%d</code> : type nombre entier';
		$html .= '<li><code>%+d</code> : -> avec signe';
		$html .= '<li><code>%04d</code> : -> en 4 chiffres, complété par des zéros si besoin est';
		$html .= '<li><code>%f</code> : type nombre réel';
		$html .= '<li><code>%.2f</code> : -> 2 décimales';
		$html .= '<li><code>%i</code> : type identifiant (nom de table, de champ)';
		$html .= '<li><i>cf <a href="https://www.php.net/manual/fr/function.vsprintf.php">https://www.php.net/manual/fr/function.vsprintf.php</a></i>';
		$html .= '<li><code>%IN</code> : type tableau dans une clause IN. ex. : <code>post.post_status IN (:post_status%IN)</code>';
		$html .= '<li><code>%IN</code> : inclut le sql d\'une sous-requête pour une clause IN. ex., pour unne variable <code>:posts</code> de type Sous-requête : <code>INNNER JOIN :posts%IN posts WHERE posts.ID = ...</code>';
		$html .= '<li><code>%K</code> : Ajoute <code>%</code> autour de la valeur de la variable pour un LIKE. ex. : <code>post.post_status LIKE :post_status%IN)</code>';
		$html .= '<li><code>%KL</code> : Ajoute <code>%</code> à droite de la valeur de la variable pour un LIKE ("commence par").';
		$html .= '<li><code>%KR</code> : Ajoute <code>%</code> à gauche de la valeur de la variable pour un LIKE ("se termine par").';
		$html .= '<li>Pour un LIKE, le caractère <code>_</code> doit être précédé de <code>\</code>. ex. : <code>LIKE \'\_%\'</code>. Les formats <code>%K</code> ajoutent cet échappement.';
		$html .= '<li>Les chaînes entre apostrophes ne doivent pas contenir le caractère <code>:</code> ou alors seul.';
		$html .= '<li><code>%I</code> : injection directe. ex. : <code>SHOW COLUMNS FROM `@.:table%I`</code>';
		
		$html .= '</ul></div>';
		return $html;
	}
}
?>