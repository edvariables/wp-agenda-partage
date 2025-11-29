<?php

/**
 * AgendaPartage Admin -> Edit -> Evenement/Covoiturage -> localisation
 * Custom taxonomy term for WordPress in Admin UI.
 * 
 * Edition d'une localisation
 * Définition des metaboxes et des champs personnalisés des localisations
 *
 * TODO default_location est abusif car une seule localisation devrait être "par défaut". Ce devrait être une sélection dans les options de l'Agenda
 *
 */
class Agdp_Admin_Edit_SQL_Function extends Agdp_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();
		
		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		$post_type = Agdp_Report::post_type;
		$taxonomy = Agdp_Report::taxonomy_sql_function;
		add_action( 'saved_' . $taxonomy , array(__CLASS__, 'saved_term_cb'), 10, 4 );//appends after 'saved_term')

		add_action( $taxonomy . '_add_form_fields', array( __CLASS__, 'on_add_form_fields' ), 10, 1 ); //edit
		add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'on_edit_form_fields' ), 10, 2); //edit

		//add custom columns for list view
		add_filter( 'manage_edit-' . $taxonomy . '_columns', array( __CLASS__, 'manage_columns' ) );
		add_filter( 'manage_' . $taxonomy . '_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 3 );
		
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
		}
		return $content;
	}
	
	public static function get_metabox_all_fields(){}//for abstract

	/**
	 * Callback lors de l'enregistrement d'un term.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function saved_term_cb ( int $term_id, int $tt_id, bool $update, array $args ){
		foreach([ 'parameters', 'return_type', 'body', 'use_example' ] as $meta_name)
			if(array_key_exists($meta_name, $args) && $args[$meta_name] ){
				update_term_meta($term_id, $meta_name, $args[$meta_name]);
			}
			else {
				delete_term_meta($term_id, $meta_name);
			}
	}
	/**
	 * Register Meta Boxes (boite en édition du term)
	 */
	public static function on_add_form_fields( string $taxonomy ){
		self::on_edit_form_fields(null, $taxonomy);
	}

	/**
	 * Register Meta Boxes (boite en édition du term)
	 */
	public static function on_edit_form_fields( $tag, string $taxonomy ){
	// debug_log(__FUNCTION__, $tag, $taxonomy );
		
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
										. '<br><code>' . $example_ids
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
									'type' => 'input',
									'input' => 'text',
									'class' => 'sql',
									'learn-more' => 'Type de données MySQL'
										. '<br><code>' . $example_ids
										. '</code>'
								)], $tag, null);
        ?></td>
    </tr>
	<?php
	$meta_name = 'body';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Script de la fonction</label></th>
        <td><?php
			$example_ids = 'RETURN CONCAT(\'<label class=\"toggle-trigger\" ajax=1\', \' data=\"\', @DATA, \'\">\', \'<a href=\"#\">\', `TITLE`, \'</a>\', \'</label>\');';
			parent::metabox_html([array('name' => $meta_name,
									// 'label' => __('Paramètres.', AGDP_TAG),
									'type' => 'input',
									'input' => 'textarea',
									'class' => 'sql',
									'learn-more' => 'Contenu entre BEGIN et END. Doit contenir un RETURN value;'
										. '<br><code>' . $example_ids
										. '</code>'
								)], $tag, null);
        ?></td>
    </tr><?php
	$meta_name = 'use_example';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Exemple</label></th>
        <td><?php
			$parameters = get_term_meta($tag->term_id, 'parameters', true);
			//retire les types
			$parameters = preg_replace('/(`?\w+`?)(\s\w+(\s*[\(][^\)]*[\)])?)?(\s*,\s*|$)/', '$1$4', $parameters);
			$example = "$tag->name($parameters)";
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
		if( $tag === null)
			echo '<br><br>';
	}


	
}
?>