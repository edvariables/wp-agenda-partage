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
class Agdp_Admin_Edit_Location extends Agdp_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();
		
		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		foreach( Agdp_Post::get_taxonomies_location() as $post_type => $taxonomy_location){
			add_action( 'saved_' . $taxonomy_location , array(__CLASS__, 'saved_term_cb'), 10, 4 );//appends after 'saved_term')

			add_action( $taxonomy_location . '_add_form_fields', array( __CLASS__, 'on_add_form_fields' ), 10, 1 ); //edit
			add_action( $taxonomy_location . '_edit_form_fields', array( __CLASS__, 'on_edit_form_fields' ), 10, 2); //edit

			//add custom columns for list view
			add_filter( 'manage_edit-' . $taxonomy_location . '_columns', array( __CLASS__, 'manage_columns' ) );
			add_filter( 'manage_' . $taxonomy_location . '_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 3 );
			
		}
	}
	/****************/
	public static function manage_columns($columns){
		$columns['default_checked'] = 'Coché par défaut';
		$columns['default_location'] = 'Localisation par défaut';
		$columns['properties'] = 'Références externes';
		return $columns;
	}
	public static function manage_custom_columns($content, string $column_name, int $term_id){
		switch ( $column_name ) {
			case 'default_checked' :
				if( get_term_meta( $term_id, $column_name, true ) )
					echo 'coché';
				else
					echo 'non';
				break;
			case 'default_location' :
				if( get_term_meta( $term_id, $column_name, true ) )
					echo 'Localisation par défaut';
				else
					echo 'non';
				break;
			case 'properties' :
				$properties = [];
				$coords = '';
				$meta_key = 'latitude';
				if( $value = get_term_meta( $term_id, $meta_key, true ) )
					$coords = $value;
				$meta_key = 'longitude';
				if( $value = get_term_meta( $term_id, $meta_key, true ) )
					$coords .= ' x ' . $value;
				if( $coords )
					$properties[] = $coords;
				$meta_key = 'external_ids';
				if( $value = get_term_meta( $term_id, $meta_key, true ) )
					$properties[] = $value;
				
				echo implode('<br>', $properties);
				break;
		}
		return $content;
	}
	
	public static function get_metabox_all_fields(){}//for abstract

	/**
	 * Callback lors de l'enregistrement d'un term.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function saved_term_cb ( int $term_id, int $tt_id, bool $update, array $args ){
		foreach([ 'default_checked', 'default_location', 'latitude', 'longitude', 'external_ids' ] as $meta_name)
			if(array_key_exists($meta_name, $args) && $args[$meta_name] ){
				if( in_array( $meta_name, ['latitude', 'longitude']) )
					$args[$meta_name] = str_replace( ',', '.', $args[$meta_name] );
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
    $meta_name = 'default_checked';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Coché par défaut</label></th>
        <td><?php
			parent::metabox_html([array('name' => $meta_name,
									'label' => __('Coché par défaut lors de la création d\'un enregistrement.', AGDP_TAG),
									'type' => 'bool',
									// 'default' => $checked
								)], $tag, null);
        ?></td>
    </tr>
	<?php
	$meta_name = 'latitude';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
		<th scope="row"><label for="<?php echo $meta_name;?>">Coord. GPS</label></th>
        <td><table><tr>
			<td style="padding: 0px"><?php
				parent::metabox_html([array('name' => $meta_name,
										'label' => 'latitude x longitude ',
										'type' => 'text',
										'style' => 'width: 6em;',
									)], $tag, null);
			?></td>
			<td style="padding: 0px"><?php
				$meta_name = 'longitude';
				parent::metabox_html([array('name' => $meta_name,
										// 'label' => 'Long.',
										'type' => 'text',
										'style' => 'width: 6em;',
									)], $tag, null);
			?></td>
			</tr></table>
		</td>
    </tr>
	<?php
	$meta_name = 'default_location';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Localisation par défaut</label></th>
        <td><?php
			parent::metabox_html([array('name' => $meta_name,
									'label' => __('Localisation par défaut dans l\'Agenda', AGDP_TAG),
									'type' => 'bool',
									// 'default' => $checked
								)], $tag, null);
        ?></td>
    </tr>
	<?php
	$meta_name = 'external_ids';
    ?><tr class="form-field term-<?php echo $meta_name;?>-wrap">
        <th scope="row"><label for="<?php echo $meta_name;?>">Paramètres externes</label></th>
        <td><?php
			$example_ids = '{location_uid}@{agenda_uid}.openagenda';
			parent::metabox_html([array('name' => $meta_name,
									// 'label' => __('Paramètres.', AGDP_TAG),
									'type' => 'input',
									'input' => 'textarea',
									'learn-more' => '. De la forme : '
										. '<br><code>' . $example_ids
										. '</code>'
								)], $tag, null);
        ?></td>
    </tr><?php
		if( $tag === null)
			echo '<br><br>';
	}


	
}
?>