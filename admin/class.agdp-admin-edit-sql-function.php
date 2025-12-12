<?php

/**
 * AgendaPartage Admin -> Edit -> Rapport -> Fonction MySQL
 * Custom taxonomy term for WordPress in Admin UI.
 * 
 * Edition d'une function mysql
 * Définition des metaboxes et des champs personnalisés des fonctions
 *
 */
class Agdp_Admin_Edit_SQL_Function extends Agdp_Admin_Edit_Post_Type {

	private const Agdp_Report_Stamp = '/* Fonction générée par Wordpress, extension Agdp_Report. */';		

	public static function init() {
		parent::init();
		
		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		$post_type = Agdp_Report::post_type;
		$taxonomy = Agdp_Report::taxonomy_sql_function;

		add_filter( $taxonomy . '_row_actions', array( __CLASS__, 'on_taxonomy_row_actions' ), 10, 2 );

		add_action( 'saved_' . $taxonomy , array(__CLASS__, 'saved_term_cb'), 10, 4 );//appends after 'saved_term')

		add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'on_add_form_fields' ), 10, 1 ); //edit
		add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'on_edit_form_fields' ), 10, 2); //edit

		//add custom columns for list view
		add_filter( 'manage_edit-' . $taxonomy . '_columns', array( __CLASS__, 'manage_columns' ) );
		add_filter( 'manage_' . $taxonomy . '_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 3 );
		
		add_action( AGDP_TAG . '_' . $taxonomy . '_term_imported', array( __CLASS__, 'on_term_imported' ), 10, 2); //edit
		
		
	}
	/****************/
	public static function manage_columns($columns){
		$columns['parameters'] = 'Paramètres';
		return $columns;
	}
	public static function manage_custom_columns($content, string $column_name, int $term_id){
		switch ( $column_name ) {
			case 'parameters' :
			default:
				if( $value = get_term_meta( $term_id, $column_name, true ) )
					echo $value;
		};
		return $content;
	}
	
	public static function get_metabox_all_fields(){}//for abstract

	/**
	 * Callback lors de l'enregistrement d'un term.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function saved_term_cb ( int $term_id, int $tt_id, bool $update, array $args ){
		foreach([ 'parameters', 'return_type', 'body', 'use_example', 'use_example_execute' ] as $meta_name)
			if(array_key_exists($meta_name, $args) && $args[$meta_name] ){
				update_term_meta($term_id, $meta_name, $args[$meta_name]);
			}
			else {
				delete_term_meta($term_id, $meta_name);
			}
		
		$meta_name = 'body';
		if( empty($args[$meta_name]) ){
			$mysql_script = self::get_current_mysql_function_body( $args['name'] );
			if( $mysql_script )
				update_term_meta($term_id, $meta_name, $mysql_script);
		}
		else
			self::create_sql_function_in_db( $term_id, $args['name'] );
	}
	
	/**
	 * create_sql_function_in_db
	 */
	public static function create_sql_function_in_db( int $term_id, string $term_name, $_return_results = 'admin_notice' ){
		$term_metas = get_term_meta($term_id, false, true);
		global $wpdb;
		$sql = sprintf('DROP FUNCTION IF EXISTS `%s`', $term_name);
		$wpdb->suppress_errors( true );
		$wpdb->hide_errors();
		$wpdb->query($sql);
		
		if( $wpdb->last_error && strpos($wpdb->last_error, 'does not exist') === false )
			Agdp_Admin::add_admin_notice( $wpdb->last_error, 'error', true);
			
		$body = rtrim( $term_metas['body'][0], " ;\r\n" );
		
		if( stripos($term_metas['return_type'][0], ' CHARSET') === false
		 && ( stripos($term_metas['return_type'][0], 'TEXT') !== false
			|| stripos($term_metas['return_type'][0], 'VARCHAR') !== false) )
			$charset = ' CHARSET utf8mb4';
		else
			$charset = '';
		
		//Agdp_Report_Stamp
		if( stripos($body, self::Agdp_Report_Stamp) === false)
			$agdp_stamp = self::Agdp_Report_Stamp;
		//errors
		$wpdb->last_error = false;
		$wpdb->hide_errors();
		$wpdb->suppress_errors( true );
		//sql
		$sql = sprintf("CREATE FUNCTION `%s` (%s) \nRETURNS %s%s %s\nBEGIN\n%s\n%s;\nEND"
			, $term_name
			, $term_metas['parameters'][0]
			, $term_metas['return_type'][0]
			, $charset
			, 'DETERMINISTIC NO SQL' //TODO paramétrables
			, isset($agdp_stamp) ? $agdp_stamp : ''
			, $body
		);
		$result = $wpdb->query($sql);
		if($wpdb->last_error){
			if( is_a($result, 'WP_Error') )
				$msg = $result->message;
			else
				$msg = $wpdb->last_error;
			$sql = htmlentities($sql);
			$msg = "$msg<br><pre><code>$sql</code></pre><br>Attention, la fonction MySQL n'existe pas ou plus.";
			if( $_return_results === 'admin_notice' )
				Agdp_Admin::add_admin_notice( $msg, 'error', true);
			elseif( $_return_results === 'return' )
				return $msg;
		}
		else {
			$msg = "La fonction MySQL a été créée ou mise à jour.";
			if( $_return_results === 'admin_notice' )
				Agdp_Admin::add_admin_notice( $msg, 'info', true);
			elseif( $_return_results === 'return' )
				return $msg;
		}
		return true;
	}
	
	/**
	 * get_current_mysql_function_body
	 */
	public static function get_current_mysql_function_body( string $sql_function_name ){
		if( ! $sql_function_name )
			throw new WP_Error("Le paramètre 'sql_function_name' est vide.");
		global $wpdb;
		
		$wpdb->suppress_errors( true );
		$wpdb->hide_errors();
		
		$sql = "SHOW CREATE FUNCTION $sql_function_name";
		$mysql_function = $wpdb->get_results($sql);
		
		if( $wpdb->last_error ){
			if( $wpdb->last_error && (strpos($wpdb->last_error, 'does not exist') === false) )
				Agdp_Admin::add_admin_notice( 
					sprintf('%s<br><pre><code>%s</code></pre>', $wpdb->last_error, $wpdb->last_query)
					, 'error', true);
			return false;
		}
		
		if( count($mysql_function) === 0 )
			return false;
		$field = "Create Function";
		$mysql_script = empty($mysql_function[0]->$field) ? false : $mysql_function[0]->$field;
		// debug_log(__FUNCTION__, $mysql_function, $mysql_script);
		
		return $mysql_script;
	}
	
	/**
	 * compare_current_mysql_function_body
	 */
	public static function compare_current_mysql_function_body( $tag, $silently = false ){
		if( is_object($tag) ){
			$term_id = $tag->term_id;
			$sql_function_name = $tag->name;
		}
		else {
			$term_id = $tag['term_id'];
			$sql_function_name = $tag['name'];
		}
		$mysql_script = self::get_current_mysql_function_body( $sql_function_name );
		// debug_log(__FUNCTION__, $sql_function_name, $mysql_script);
		if( ! $mysql_script )
			return false;
		$sql_function_body = get_term_meta( $term_id, 'body', true );
		if( strpos( $mysql_script, $sql_function_body ) !== false )
			return true;
			
		if( $silently )
			return false;
		
		$msg = sprintf('Attention, la fonction %s est différente dans la base MySQL et dans cet écran.'
				. '<br><textarea style="width: 95%%;" rows=8 readonly=1>%s</textarea></pre>'
				, $sql_function_name
				, $mysql_script
			);
		
		Agdp_Admin::add_admin_notice_now( $msg, 'error', true);
	}
	
	/**
	 * Actions pour chaque terme dans la liste
	 */
	public static function on_taxonomy_row_actions( $actions, $tag ){
		// debug_log(__FUNCTION__, $actions, $tag);
		
		//Action Dupliquer
		$uri = sprintf('/wp-admin/edit-tags.php?taxonomy=%s&post_type=%s',
					$tag->taxonomy, Agdp_Report::post_type );
		$actions['duplicate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url(
				add_query_arg(
					'source_tag', $tag->term_id,
				add_query_arg(
					'action', 'duplicate',
					$uri
				))
			),
			/* translators: %s: Taxonomy term name. */
			esc_attr( sprintf( 'Dupliquer &#8220;%s&#8221;', $tag->name ) ),
			__( 'Dupliquer' )
		);

		
		return $actions;
	}

	
	/**
	 * Hook de l'import d'un term
	 */
	public static function on_term_imported( string $taxonomy, $term ){
		if( is_array($term) ){
			$term_id = $term['term_id'];
			$term_name = $term['name'];
		}
		else {
			$term_id = $term;
			$term = get_term( $term_id, $taxonomy );
			$term_name = $term->name;
		}
		if( ! self::compare_current_mysql_function_body( $term, true ) ){
			$msg = self::create_sql_function_in_db( $term_id, $term_name, 'return' );
		}
	}
	
	/**
	 * Register Meta Boxes (boite en édition du term)
	 */
	public static function on_add_form_fields( string $taxonomy ){
		if( ! empty($_REQUEST['action']) && $_REQUEST['action'] === 'duplicate' 
		 && ! empty($_REQUEST['source_tag']) ){
			$tag = get_term( $_REQUEST['source_tag'] );
		}
		else
			$tag = null;
		self::on_edit_form_fields($tag, $taxonomy);
	}

	/**
	 * Register Meta Boxes (boite en édition du term)
	 */
	public static function try_example( $tag ){
		if( ! $tag )
			return;
		
		$meta_name = 'use_example_execute';
		if( ! get_term_meta($tag->term_id, $meta_name, true) )
			return;
			
		$meta_name = 'use_example';
		$use_example = get_term_meta($tag->term_id, $meta_name, true);
		
		if( ! $use_example )
			return;
		
		if( stripos($use_example, 'SELECT ') === false )
			$sql = sprintf('SELECT %s `_try_example_`', $use_example);
		else
			$sql = $use_example;
		$sqls = Agdp_Report::get_sql_as_array( $sql );
		
		global $wpdb;
		$wpdb->suppress_errors( true );
		$wpdb->hide_errors();
		$wpdb->last_error = false;
		
		foreach($sqls as $sql_u){
			$results = $wpdb->get_results($sql_u);
		
			if( $wpdb->last_error ){
				if( $wpdb->last_error && (strpos($wpdb->last_error, 'does not exist') === false) )
					Agdp_Admin::add_admin_notice_now( 
						sprintf('<h3>%s</h3><br>%s<br><pre><code>%s</code></pre>'
							, "Erreur dans l'exécution de la requête d'exemple :"
							, $wpdb->last_error
							, $wpdb->last_query
						)
						, 'error'
						, true
					);
				return $wpdb->last_error;
			}
		}
		if( ! $results )
			$html = false;
		elseif( is_array( $results ) ) {
			$html = '';
			$col_index = 0;
			foreach($results[0] as $key=>$value){
				if( $col_index++ !== 0 )
					$html .= "\t";
				if( $key === '_try_example_' )
					$html .= "item";
				else
					$html .= "$key";
			}
			foreach($results as $result){
				$html .= "\n";
				$col_index = 0;
				foreach($result as $key=>$value){
					if( $col_index++ !== 0 )
						$html .= "\t";
					$html .= "$value";
				}
			}
			$html = trim($html, "\t\n");
			if( strpos($html, "\t") ){
				$html = sprintf('<table class="use_example_results"><tr><td>%s</tr></table>'
						, str_replace("\n", '<tr><td>', 
								str_replace("\t", '<td>', $html
						))
				);
			}
		}
		else
			$html = print_r( $results, true );
		if( $html )
			$html = sprintf('<pre><code>%s</code></pre>', $html, true);
		return $html;
	}

	/**
	 * Register Meta Boxes (boite en édition du term)
	 */
	public static function on_edit_form_fields( $tag, string $taxonomy ){
	
	$meta_name = 'parameters';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Paramètres de la fonction</label></th>
        <td><?php
			$example_ids = '`REPORT_ID` VARCHAR(1024), `TITLE` VARCHAR(1024), `VARS_JSON` JSON';
			parent::metabox_html([array('name' => $meta_name,
									// 'label' => __('Paramètres.', AGDP_TAG),
									'type' => 'input',
									'input' => 'textarea',
									'class' => 'sql',
									'learn-more' => 'De la forme : '
										. '<br><code>' . htmlentities( $example_ids )
										. '</code>'
								)], $tag, null);
        ?></td>
    </tr><?php
    $meta_name = 'return_type';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Type du retour de la fonction</label></th>
        <td><?php
			$example_ids = 'VARCHAR(1024) | INT | JSON | ... ';
			parent::metabox_html([array('name' => $meta_name,
									'type' => 'text',
									'class' => 'sql',
									'learn-more' => 'Type de données MySQL'
										. '<br><code>' . htmlentities( $example_ids )
										. '</code>'
								)], $tag, null);
        ?></td>
    </tr>
	<?php
	$meta_name = 'body';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Script de la fonction</label></th>
        <td><?php
			$example_ids = 'RETURN CONCAT(\'<label class="toggle-trigger" ajax=1\', \' data="\', @DATA, \'">\', \'<a href="#">\', `TITLE`, \'</a>\', \'</label>\');';
			$comment = '<i>Les fonctions SQL sont créées comme déterministes et n\'exécutant pas de requête SQL dans leur script.</i>';
			parent::metabox_html([array('name' => $meta_name,
									// 'label' => __('Paramètres.', AGDP_TAG),
									'type' => 'input',
									'input' => 'textarea',
									'class' => 'sql',
									'input_attributes' => [ 'rows' => 12 ],
									'learn-more' => 'Contenu entre BEGIN et END. Doit contenir un RETURN value;'
										. '<br><code>' . htmlentities( $example_ids )
										. '</code>'
										.'<br>' . $comment
								)], $tag, null);
        ?></td>
    </tr><?php
	$meta_name = 'use_example';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Exemple d'utilisation dans une cellule de rapport</label></th>
        <td><?php
			$parameters = $tag ? get_term_meta($tag->term_id, 'parameters', true) : '';
			//retire les types
			$parameters = preg_replace('/(`?\w+`?)(\s\w+(\s*[\(][^\)]*[\)])?)?(\s*,\s*|$)/', '$1$4', $parameters);
			$example = $tag ? "$tag->name($parameters)" : '';
			parent::metabox_html([array('name' => $meta_name,
									// 'label' => __('Paramètres.', AGDP_TAG),
									'type' => 'input',
									'input' => 'textarea',
									'class' => 'sql',
									'learn-more' => '<code>' . $example
										. '</code>'
								)], $tag, null);
        ?></td>
    </tr><?php
	$meta_name = 'use_example_execute';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>"></label></th>
        <td><?php
			$results = self::try_example( $tag );
			parent::metabox_html([array('name' => $meta_name,
									'label' => __('Tester cet exemple', AGDP_TAG),
									'type' => 'checkbox',
									'input' => 'input',
									'comments' => $results
								)], $tag, null);
        ?></td>
    </tr><?php
		if( $tag === null)
			echo '<br><br>';
	}


	
}
?>