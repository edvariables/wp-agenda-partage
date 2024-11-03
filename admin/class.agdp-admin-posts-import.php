<?php 

/**
 * Importation de posts
 */
class Agdp_Admin_Posts_Import {

	static $initialized = false;

	public static function init() {
		if(self::$initialized)
			return;
		self::$initialized = true;
		self::init_includes();
		self::init_hooks();
	}

	public static function init_includes() {
	}

	public static function init_hooks() {
	}

	/**
	 * Import Action
	 */
	public static function agdp_import_page_html() {
		$action = 'import';

		if( ! empty($_POST['action-data']) ){
			
			$is_confirmed_action = ! isset($_REQUEST['is_confirmed_action']) ? false : $_REQUEST['is_confirmed_action'];
			if( $is_confirmed_action )
				$confirm_action = false;
			else
				$confirm_action = ! isset($_REQUEST['confirm_action']) ? false : $_REQUEST['confirm_action'];
			$add_title_suffix = empty($_REQUEST['add_title_suffix']) ? false : $_REQUEST['add_title_suffix'];
			$title_suffix = empty($_REQUEST['title_suffix']) ? ' (importé)' : $_REQUEST['title_suffix'];
			$create_news = empty($_REQUEST['create_news']) ? false : $_REQUEST['create_news'];
			$update_existing = empty($_REQUEST['update_existing']) ? false : $_REQUEST['update_existing'];
			$import_terms = empty($_REQUEST['import_terms']) ? false : $_REQUEST['import_terms'];
			
			$post_id = empty($_REQUEST['post']) ? false : $_REQUEST['post'];
			$post_referer = $post_id ? get_post( $post_id ) : 0;

			Agdp_Admin_Edit_Post_Type::check_rights( $action, $post_referer );
			
			$data_str = stripslashes($_POST['action-data']);
			$data_str = htmlspecialchars_decode($data_str, ENT_QUOTES);
			$data = json_decode($data_str, true);
			if( $data ){
				
				if( $confirm_action ){
					$url = wp_nonce_url( $_SERVER['REQUEST_URI'], Agdp_Admin_Edit_Post_Type::get_nonce_name( $action, $post_id ) );
					//FORM
					?><form class="agdp-import-posts confirmation" action="<?php echo $url;?>" method="post">
						<div class="hidden">
							<textarea name="action-data"><?php echo isset($data_str) ? $data_str : '' ?></textarea>
							<input type="hidden" name="is_confirmed_action" value="1">
						</div>
						<div><h3>Importation en attente de confirmation</h3>
							<label><input type="checkbox" name="update_existing"<?php if( $update_existing ) echo ' checked';?>> Mettre à jour les enregistrements pré-existants (sur la base du titre) </label>
							<br><label><input type="checkbox" name="create_news"<?php if( $create_news ) echo ' checked';?>> Créer de nouveaux enregistrements</label>
							<br>&nbsp;&nbsp;<label><input type="checkbox" name="add_title_suffix"<?php if( $add_title_suffix ) echo ' checked';?>> Ajouter un suffixe aux titres </label>
								<input type="text" name="title_suffix" value="<?php echo esc_attr($title_suffix);?>">
							<br><label><input type="checkbox" name="import_terms"<?php if( $import_terms ) echo ' checked';?>> Importer les taxonomies</label>
							
						</div>
						<div class="confirm_action_posts"><div class="info"><label>Veuillez confirmer les mises à jours et importations.</div>
						
					<?php
					
				}
				else
					echo "<h3>Importation</h3>";
				
				$posts = self::agdp_import_posts( $data, $_REQUEST );
				if( $posts === false ) {
					echo sprintf('<div class="error"><label>%s</label><pre>%s</pre></div>'
						, 'Impossible de reconnaitre les données à importer'
						, json_encode( $data )
					);
				}
				elseif( is_array($posts) ){
					if( ! $confirm_action )
						echo sprintf('<div class="info"><label>%d importation%s</label>'
							, count($posts)
							, count($posts) > 1 ? 's' : ''
						);
					elseif( count($posts) )
						echo '';
					else
						echo '<div>rien à faire</div>';
					
				}
				
				if( $confirm_action ){
					?>
					</div>
					<input type="submit" name="action_<?php echo Agdp_Admin_Edit_Post_Type::get_action_name($action);?>" value="Confirmer l'importation">
						<input type="hidden" name="post_referer" value="<?php echo $post_id;?>">
					</form>
					<?php
					
				}
			}
			else {
				echo "Aucune donnée importable.";
				var_dump(($_POST['action-data']));
			}
			
			if( $is_confirmed_action )
				$confirm_action = true;
		}
		else { 
			$confirm_action = ! isset($_REQUEST['confirm_action']) ? true : $_REQUEST['confirm_action'];
			$add_title_suffix = empty($_REQUEST['add_title_suffix']) ? true : $_REQUEST['add_title_suffix'];
			$title_suffix = empty($_REQUEST['title_suffix']) ? ' (importé)' : $_REQUEST['title_suffix'];
			$create_news = empty($_REQUEST['create_news']) ? true : $_REQUEST['create_news'];
			$update_existing = empty($_REQUEST['update_existing']) ? false : $_REQUEST['update_existing'];
			$import_terms = empty($_REQUEST['import_terms']) ? false : $_REQUEST['import_terms'];
		}
		
		$post_type = empty($_REQUEST['post_type']) ? false : $_REQUEST['post_type'];
		if( ! $post_type ){
			$post_id = empty($_REQUEST['post']) ? false : $_REQUEST['post'];
			$post = $post_id ? get_post( $post_id ) : false;
			$post_type = $post ? $post->post_type : false;
		}
		
		$url = wp_nonce_url( $_SERVER['REQUEST_URI'], Agdp_Admin_Edit_Post_Type::get_nonce_name( $action, $post ? $post->ID : 0) );
		//FORM
		?><form class="agdp-import-posts" action="<?php echo $url;?>" method="post">
			<div><h3>Coller ici les données à importer</h3>
				<textarea name="action-data" rows="5" cols="100"><?php echo isset($data_str) ? $data_str : '' ?></textarea>
			</div>
			<label><input type="checkbox" name="confirm_action"<?php if( $confirm_action ) echo ' checked';?>> Confirmer chaque importation (TODO) </label>
			<div>
				<br><label><input type="checkbox" name="update_existing"<?php if( $update_existing ) echo ' checked';?>> Mettre à jour les enregistrements pré-existants (sur la base du titre) </label>
				<br><label><input type="checkbox" name="create_news"<?php if( $create_news ) echo ' checked';?>> Créer de nouveaux enregistrements</label>
				<br>&nbsp;&nbsp;<label><input type="checkbox" name="add_title_suffix"<?php if( $add_title_suffix ) echo ' checked';?>> Ajouter un suffixe aux titres </label>
					<input type="text" name="title_suffix" value="<?php echo esc_attr($title_suffix);?>">
				<br><label><input type="checkbox" name="import_terms"<?php if( $import_terms ) echo ' checked';?>> Importer les taxonomies</label>
				
			</div>
			<br>
			<br>
			<input type="submit" name="action_<?php echo Agdp_Admin_Edit_Post_Type::get_action_name($action);?>" value="Importer">
			<input type="hidden" name="post_referer" value="<?php echo $post_id;?>">
		</form>
		<?php
		return;
		
	}
	/**
	 * Import post
	 * $data['post'], $data['metas']
	 */
	private  static function agdp_import_posts( $data, &$options = false ) {
		if( ! is_array($options) )
			$options = [];
		if( empty($data['post']) ){
			$new_posts = [];
			$options['original_ids'] = [];
			foreach( $data as $post_data )
				if( ! empty($post_data['post']) )
					$options['original_ids'][$post_data['post']['ID'].''] = $post_data['post']['ID'];
			
			if( ! empty($data['taxonomies']) ){
				$options['taxonomies'] = $data['taxonomies'];
			}
			
			if( ! empty($data['terms']) ){
				$import_terms = empty($options['import_terms']) ? false : $options['import_terms'];
				if( $import_terms )
					$new_ids = static::agdp_import_terms( $data, $options );
			}
			
			// agdp_import_posts callback
			foreach( $data as $index => $post_data )
				if( is_numeric($index)
				&& ! empty($post_data['post']) ){
					$original_id = $post_data['post']['ID'];
					$new_id = static::agdp_import_posts( $post_data, $options );
					if( $new_id ){
						$options['original_ids'][$original_id.''] = $new_id;
						$new_posts[] = $new_id;
					}
				}
			return $new_posts;
		}
		$confirm_action 		= isset($options['confirm_action']) && $options['confirm_action'];
		$is_confirmed_action 	= isset($options['is_confirmed_action']) && $options['is_confirmed_action'];
		$add_title_suffix 		= isset($options['add_title_suffix']) && $options['add_title_suffix'];
		$title_suffix 			= empty($options['title_suffix']) ? ' (importé)' : $options['title_suffix'];
		$create_news			= isset($options['create_news']) && $options['create_news'];
		$update_existing		= empty($options['update_existing']) ? false : $options['update_existing'];
		$import_terms 			= empty($options['import_terms']) ? false : $options['import_terms'];
		
		if( empty($data['post'])
		 || empty($data['post']['post_type']) ){
				// echo sprintf( '<div class="error">Données incomplètes.<pre>%s</pre></div>'
					// , htmlspecialchars( json_encode($data) )
				// );
			return false;
		}
		if( ! Agdp_Admin_Edit_Post_Type::has_cap( 'import', $data['post']['post_type'] ) ){
				echo sprintf( '<div class="error">Le type %s ne peut pas être importé.<pre>%s</pre></div>'
					, $data['post']['post_type']
					, htmlspecialchars( json_encode($data['post']) )
				);
			return false;
		}
		
		$original_id = $data['post']['ID'];
		
		foreach([ 'ID',
			'post_password', 
			'guid', 
			'post_modified', 
			'post_modified_gmt', 
			'post_name'
		 ] as $key)
			unset($data['post'][$key]);
		
		//post_parent 
		if( ! empty($data['post']['post_parent']) ){
			//TODO il y a un bug avec des mises à jour avec parent foireux
			if( isset($options['original_ids'])
			 && isset($options['original_ids'][$data['post']['post_parent'].'']) )
				$data['post']['post_parent'] = $options['original_ids'][$data['post']['post_parent'].''];
			else
				unset($data['post']['post_parent']);
		}
		// metas in meta_input
		if( ! empty($data['metas']) ){
			foreach($data['metas'] as $meta_key => $meta_value){
				if( $meta_value ){
					//TODO addslashes ? (nécessaire pour les json mais fait disparaitre \ dans un title)
					$data['metas'][$meta_key] = addslashes( $meta_value );
				}
			}
			
			//TODO pour agdpreport, tenter de conserver les sql_variables->value d'origine
			
			$data['post']['meta_input'] = $data['metas'];
		}
		// terms in tax_input
		if( ! empty($data['terms']) ){
			$post_terms = [];
			foreach($data['terms'] as $tax_name => $term){
				foreach($term as $term_id => $term_slug){
					if( isset($options['original_term_ids'])
					 && isset($options['original_term_ids'][$term_id.'']) ){
						if( ! isset($post_terms[$tax_name]) )
							$post_terms[$tax_name] = [];
						$post_terms[$tax_name][] = $options['original_term_ids'][$term_id.''];
					}
				}
			}
			// $data['post']['tax_input'] = $post_terms; // cf plus loin
		}
		
		//update_existing, search
		if( $update_existing ){
			$existing = get_posts([
				'post_type' => $data['post']['post_type'],
				'post_status' => ['publish', 'pending', 'draft', 'private', 'future', $data['post']['post_status']],
				'title' => $data['post']['post_title'],
				'numberposts' => 2
			]);
			if( $existing ){
				$update_existing = $existing[0];
				if( count($existing) > 1 ){
					echo sprintf( '<div>%s Plus d\'un enregistrement existent, la mise à jour est impossible. 1er : <a href="%s">%s</a></div>'
						, Agdp::icon('help')
						, get_edit_post_link( $update_existing->ID )
						, htmlspecialchars( $data['post']['post_title'] )
					);
					return false;
				}
				foreach([ 
					'post_status', 
					'post_date', 
					'post_date_gmt', 
				 ] as $key)
					unset($data['post'][$key]);
			}
			elseif( ! $create_news ){
				return false;
			}
			else
				$update_existing = false;
		}
		
		// update_existing
		if( $update_existing ) {
			$data['post']['ID'] = $update_existing->ID;
			$data['post']['post_name'] = $update_existing->post_name;
			if( $confirm_action || $is_confirmed_action ) {
				$confirm_key = sprintf('confirm_%s_%s_%s'
					, 'update'
					, $data['post']['post_type']
					, $original_id
				);
			}
			if( $confirm_action ) {
				echo sprintf( '<div><label><input type="checkbox" name="%s" %s>%s Mise à jour de <a href="%s">%s</a></div>'
					, $confirm_key
					, 'checked'
					, Agdp::icon('update')
					, get_edit_post_link( $update_existing->ID )
					, htmlspecialchars( $data['post']['post_title'] )
				);
				return $original_id;
			}
			
			if( $is_confirmed_action
			&& empty( $options[ $confirm_key ] ) ){
				// echo sprintf( '<div>%s Ignore %s</div>'
					// , Agdp::icon('no-alt')
					// , htmlspecialchars( $data['post']['post_title'] )
				// );
				return $update_existing->ID; // pour original_ids
			}
			
			// wp_update_post
			$new_post_id = wp_update_post( $data['post'], true );
		}
		// create_news
		elseif( $create_news ){
			// suffix
			if( $add_title_suffix && $title_suffix )
				$data['post']['post_title'] .= $title_suffix ;
			if( $confirm_action || $is_confirmed_action ) {
				$confirm_key = sprintf('confirm_%s_%s_%s'
					, 'create'
					, $data['post']['post_type']
					, $original_id
				);
			}
			if( $confirm_action ) {
				echo sprintf( '<div><label><input type="checkbox" name="%s" %s>%s Création de %s [%s]</div>'
					, $confirm_key
					, 'checked'
					, Agdp::icon('plus')
					, htmlspecialchars( $data['post']['post_title'] )
					, $data['post']['post_type']
				);
				return $original_id;
			}
			
			if( $is_confirmed_action
			&& empty( $options[ $confirm_key ] ) ){
				// echo sprintf( '<div>%s Ignore %s</div>'
					// , Agdp::icon('no-alt')
					// , htmlspecialchars( $data['post']['post_title'] )
				// );
				return false;
			}
			
			// wp_insert_post
			$new_post_id = wp_insert_post( $data['post'], true );
		}
		else
			$new_post_id = 0;
		
		if( $new_post_id ){
			if( $import_terms ){
				//Taxonomies
				if( ! empty($post_terms) ) {
					foreach($post_terms as $tax_name => $tax_inputs){
						$result = wp_set_post_terms($new_post_id, $tax_inputs, $tax_name, false);
						if(is_a($result, 'WP_Error') || is_string($result)){
							if( is_a( $new_term_id, 'WP_Error' ) ){
								echo sprintf( '<div>%sEchec d\'affectation des termes %s -> %s : %s</a></div>'
									, Agdp::icon('error')
									, $taxonomy
									, implode('+', $tax_inputs)
									, is_a($result, 'WP_Error') ? $result->get_error_message() : $result
								);
							}
						}
					}
				}
			}
				
			$icon = $update_existing ? 'update' : 'plus';
			echo sprintf( '<div><a href="%s">%s %s</a></div>'
				, get_edit_post_link( $new_post_id )
				, Agdp::icon($icon)
				, htmlspecialchars( $data['post']['post_title'] )
			);
		}
		return $new_post_id;
	}
	
	/**
	 * Import terms
	 * $data['term'], $data['metas']
	 */
	private  static function agdp_import_terms( $data, &$options = false ) {
		if( ! is_array($options) )
			$options = [];
		if( empty($data['term']) ){
			$new_terms = [];
			
			$options['original_term_ids'] = [];
			if( ! empty($data['terms']) ){
				foreach( $data['terms'] as $term_data )
					if( ! empty($term_data['term']) ){
						$original_id = $term_data['term']['term_id'];
						$new_id = static::agdp_import_terms( $term_data, $options );
						if( $new_id ){
							$options['original_term_ids'][$original_id.''] = $new_id;
							$new_terms[] = $new_id;
						}
					}
			}
			return $new_terms;
		}
		$confirm_action = isset($options['confirm_action']) && $options['confirm_action'];
		$add_title_suffix = false;//isset($options['add_title_suffix']) && $options['add_title_suffix'];
		$title_suffix = '';//empty($options['title_suffix']) ? ' (importé)' : $options['title_suffix'];
		
		if( empty($data['term'])
		 || empty($data['term']['slug'])
		 || empty($data['term']['taxonomy']) ){
				// echo sprintf( '<div class="error">Données incomplètes.<pre>%s</pre></div>'
					// , htmlspecialchars( json_encode($data) )
				// );
			return false;
		}
		$taxonomy = $data['term']['taxonomy'];
		
		if( $existing_term = get_term_by( 'slug', $data['term']['slug'], $taxonomy, OBJECT ) ){
			// echo sprintf( '<div><a href="%s">Le terme %s -> %s existe déjà</a></div>'
				// , get_edit_term_link( $existing_term->term_id )
				// , $taxonomy
				// , htmlspecialchars( $data['term']['name'] )
			// );
			// TODO update ?
			return $existing_term->term_id;
		}
		
		//term_parent 
		if( ! empty($data['term']['parent']) ){
			if( isset($options['original_term_ids'])
			 && isset($options['original_term_ids'][$data['term']['parent'].'']) )
				$data['term']['parent'] = $options['original_term_ids'][$data['term']['parent'].''];
			else
				unset($data['term']['parent']);
		}
		// metas
		if( ! empty($data['metas']) ){
			foreach($data['metas'] as $meta_key => $meta_value){
				if( $meta_value ){
					//TODO addslashes ? (nécessaire pour les json mais fait disparaitre \ dans un title)
					$data['metas'][$meta_key] = addslashes( $meta_value );
				}
			}
			// $data['term']['meta_input'] = $data['metas']; cf plus loin
		}
		if( $add_title_suffix && $title_suffix )
			$data['term']['name'] .= $title_suffix ;
		
		foreach( ['term_taxonomy_id', 'term_id'] as $prop )
			unset( $data['term'][$prop] );
		// var_dump($data);
		// debug_log(__FUNCTION__, $data);
		
		// wp_insert_term
		$new_term_id = wp_insert_term( $data['term']['name'], $taxonomy, $data['term'] );
		
		if( is_a( $new_term_id, 'WP_Error' ) ){
			echo sprintf( '<div>%sEchec de création du terme %s -> %s : %s</a></div>'
				, Agdp::icon('error')
				, $taxonomy
				, htmlspecialchars( $data['term']['name'] )
				, $new_term_id->get_error_message()
			);
			return false;
		}
		if( $new_term_id ){
			if( is_array($new_term_id) )
				$new_term_id = $new_term_id['term_id'];
			
			if( isset($data['metas']) ){
				foreach($data['metas'] as $meta_key => $meta_value){
					update_term_meta( $new_term_id, $meta_key, $meta_value );
				}
			}
			echo sprintf( '<div><a href="%s">%s Création du terme %s -> %s</a></div>'
				, get_edit_term_link( $new_term_id )
				, Agdp::icon('plus')
				, $taxonomy
				, htmlspecialchars( $data['term']['name'] )
			);
		}
		return $new_term_id;
	}
}

?>