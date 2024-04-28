<?php

/**
 * AgendaPartage Admin -> Edit -> Evenement/Covoiturage -> diffusion
 * Custom taxonomy term for WordPress in Admin UI.
 * 
 * Edition d'une diffusion
 * Définition des metaboxes et des champs personnalisés des diffusions
 *
 */
class AgendaPartage_Admin_Edit_Diffusion extends AgendaPartage_Admin_Edit_Post_Type {

	public static function init() {
		parent::init();

		self::init_hooks();
	}
	
	public static function init_hooks() {
		
		foreach( [
			AgendaPartage_Evenement::taxonomy_diffusion
			, AgendaPartage_Covoiturage::taxonomy_diffusion
		] as $taxonomy_diffusion){
			add_action( 'saved_' . $taxonomy_diffusion , array(__CLASS__, 'saved_term_cb'), 10, 4 );

			add_action( $taxonomy_diffusion . '_term_new_form_tag', array( __CLASS__, 'on_term_edit_form_tag' ), 10 ); //form attr
			add_action( $taxonomy_diffusion . '_term_edit_form_tag', array( __CLASS__, 'on_term_edit_form_tag' ), 10 ); //form attr
			add_action( $taxonomy_diffusion . '_add_form_fields', array( __CLASS__, 'on_add_form_fields' ), 10, 1 ); //edit
			add_action( $taxonomy_diffusion . '_edit_form_fields', array( __CLASS__, 'on_edit_form_fields' ), 10, 2); //edit

			//add custom columns for list view
			add_filter( 'manage_edit-' . $taxonomy_diffusion . '_columns', array( __CLASS__, 'manage_columns' ) );
			add_filter( 'manage_' . $taxonomy_diffusion . '_custom_column', array( __CLASS__, 'manage_custom_columns' ), 10, 3 );
		}
	}
	/****************/
	public static function manage_columns($columns){
		$columns['default_checked'] = 'Coché par défaut';
		$columns['properties'] = 'Lien / Connexion';
		return $columns;
	}
	public static function manage_custom_columns($content, string $column_name, int $term_id){
		switch ( $column_name ) {
			case 'default_checked' :
				if( get_term_meta( $term_id, $column_name, true ) )
					echo 'Coché par défaut';
				else
					echo 'non';
				break;
			case 'properties' :
				$properties = [];
				$meta_key = 'download_link';
				if( $value = get_term_meta( $term_id, $meta_key, true ) )
					$properties[] = 'Téléchargement ' . $value;
				else
					$properties[] = 'Sans téléchargement';
				
				$meta_key = 'connexion';
				if( $value = get_term_meta( $term_id, $meta_key, true ) )
					$properties[] = $value;
				
				echo implode('<br>', $properties);
				break;
		}
		return $content;
	}
	
	public static function get_metabox_all_fields(){}//for abstract

	/**
	 * Callback lors de l'enregistrement d'un évènement.
	 * A ce stade, les metaboxes ne sont pas encore sauvegardées
	 */
	public static function saved_term_cb ( int $term_id, int $tt_id, bool $update, array $args ){
		foreach([ 'default_checked', 'download_link', 'connexion' ] as $meta_name)
			if(array_key_exists($meta_name, $args) && $args[$meta_name] ){
				update_term_meta($term_id, $meta_name, $args[$meta_name]);
			}
			else {
				delete_term_meta($term_id, $meta_name);
			}
		
		$meta_name = 'download_file_model';
		if(array_key_exists($meta_name, $_FILES) && $_FILES[$meta_name]
		&& $_FILES[$meta_name]['name'] && $_FILES[$meta_name]['error'] === 0 ){
			$file_name = preg_replace('/^(t[0-9]+\-)+/', '', $_FILES[$meta_name]['name']);
			$final_path = sprintf('%st%s-%s', self::get_attachments_path(), $term_id, $file_name);
			if( move_uploaded_file($_FILES[$meta_name]['tmp_name'], $final_path) ){
				$upload_dir = wp_upload_dir();
				$upload_dir = str_replace('\\', '/', $upload_dir['basedir']);
				$final_path = str_replace($upload_dir, '', $final_path);
				update_term_meta($term_id, $meta_name, $final_path);
			}
		}
	}
	
	/**
	 * Retourne le répertoire de stockage des fichiers modèles
	 */
	private static function get_attachments_path(){
		$upload_dir = wp_upload_dir();
		
		$dirname = str_replace('\\', '/', $upload_dir['basedir']);
		// if( is_multisite())
			// $dirname .= '/sites/' . get_current_blog_id();
		
		$dirname .= sprintf('/%s/%s/', date('Y'), date('m'));
		
		if ( ! file_exists( $dirname ) ) {
			wp_mkdir_p( $dirname );
		}

		return $dirname;
	}

	/**
	 * Attribut de la balise form
	 */
	public static function on_term_edit_form_tag( ){
		echo ' enctype="multipart/form-data" ';
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
		
    ?><tr class="form-comment">
        <th scope="row">
        <td><i>La description apparaitra en information complémentaire lors de l'édition.</td>
    </tr><?php
    ?><tr class="form-field">
        <th scope="row"><label for="default_checked">Coché par défaut</label></th>
        <td><?php
			$meta_name = 'default_checked';
			parent::metabox_html([array('name' => $meta_name,
									'label' => __('Coché par défaut lors de la création d\'un enregistrement.', AGDP_TAG),
									'type' => 'bool',
									// 'default' => $checked
								)], $tag, null);
        ?></td>
    </tr><?php
    ?><tr class="form-field">
        <th scope="row"><label for="default_checked">Paramètres de connexion</label></th>
        <td><?php
			$meta_name = 'connexion';
			parent::metabox_html([array('name' => $meta_name,
									// 'label' => __('Paramètres.', AGDP_TAG),
									'type' => 'text',
									// 'default' => $checked
								)], $tag, null);
        ?></td>
    </tr><?php
    ?><tr class="form-field">
        <th scope="row"><label for="download_link">Lien en bas de l'agenda</label></th>
        <td><?php
			$meta_name = 'download_link';
			
			$values = [ '' => '(pas de téléchargement)'
						, 'ics' => 'vCalendar (.ics)'
						, 'txt' => 'texte brut (.txt)'
						, 'bv.txt' => 'texte préformaté BV (.bv.txt)'
						, 'docx' => 'document préformaté (.docx)'
					];
			
			parent::metabox_html([array('name' => $meta_name,
									'label' => __('Téléchargement', AGDP_TAG),
									'input' => 'select',
									'values' => $values
								)], $tag, null);
        ?></td>
    </tr><?php
    ?><tr class="form-field">
        <th scope="row"><label for="download_link"></label></th>
        <td><?php
			$meta_name = 'download_file_model';
				
			parent::metabox_html([array('name' => $meta_name,
									'label' => __('Fichier modèle (.docx)', AGDP_TAG),
									'input' => 'file',
									'type' => 'file',
								)], $tag, null);
			
			if( $tag
			&& ($meta_value = get_term_meta($tag->term_id, $meta_name, true)) ){
				$upload_dir_info = wp_upload_dir();
				$url = $upload_dir_info['baseurl'] . $meta_value;
				
				echo sprintf('<br><label>Fichier actuel : </label><a href="%s">%s</a>', $url, basename($meta_value));
			}
        ?></td>
    </tr><?php
		if( $tag === null)
			echo '<br><br>';
	}


	
}
?>