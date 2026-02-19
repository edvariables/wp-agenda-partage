<?php 

/**
 * Importation de posts
 */
class Agdp_Admin_Posts_Import {

	const import_package_tag = 'agdppackage';

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
		
		if( ! class_exists('Agdp_Admin_Packages') ){
			require_once( AGDP_PLUGIN_DIR . "/admin/class.agdp-admin-packages.php");
			Agdp_Admin_Packages::init();
		}
		
		$import_package = empty($_REQUEST['import-'.self::import_package_tag]) ? false : $_REQUEST['import-'.self::import_package_tag];
		if( $import_package ){
			$data_source = self::import_package_tag;
			$post_type = empty($_REQUEST[self::import_package_tag . '-post_type']) ? false : $_REQUEST[self::import_package_tag . '-post_type'];
		
			$file_name = Agdp_Admin_Packages::get_existing_post_type_package_file( $post_type );
			if( $file_name && file_exists( $file_name ) ){
				$action_data = file_get_contents( $file_name );
				// $action_data = ($action_data);
				// $action_data = htmlspecialchars_decode($action_data, ENT_QUOTES);
				
				$is_confirmed_action = false;
				$confirm_action = true;
				$add_title_suffix = false;
				$title_suffix = '';
				$create_news = true;
				$update_existing = true;
				$import_terms = true;
			}			
		}
		else
			$data_source = empty($_REQUEST['data_source']) ? false : $_REQUEST['data_source'];
		
		if( empty($action_data) ){
			$action_data = empty($_POST['action-data']) ? false : $_POST['action-data'];
			if( $action_data ){
				$action_data = stripslashes($action_data);
				$action_data = htmlspecialchars_decode($action_data, ENT_NOQUOTES);
			
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
			}
		}
		
		if( $action_data ){
			
			$post_id = empty($_REQUEST['post']) ? false : $_REQUEST['post'];
			$post_referer = $post_id ? get_post( $post_id ) : 0;

			Agdp_Admin_Edit_Post_Type::check_rights( $action, $post_referer );
			
			$data_str = $action_data;
			$action_data = maybe_unserialize($action_data); //TODO
			if( is_string($action_data) ){
				$action_data = json_decode($action_data, true);
				if( ! $action_data ){
					debug_log(__CLASS__.'::'.__FUNCTION__, 'json_decode last error', json_last_error_msg(), $data_str);
				}
			}
			if( $action_data ){
				if( empty($action_data['terms']) )
					$import_terms = false;
				
				if( $confirm_action ){
					$url = wp_nonce_url( '/wp-admin/admin.php?page=agendapartage-import'/* $_SERVER['REQUEST_URI'] */, Agdp_Admin_Edit_Post_Type::get_nonce_name( $action, $post_id ) );
					//FORM
					?><form class="agdp-import-posts confirmation" action="<?php echo $url;?>" method="post">
						<div class="hidden">
							<textarea name="action-data"><?php echo isset($data_str) ? htmlspecialchars( $data_str ) : '' ?></textarea>
							<input type="hidden" name="is_confirmed_action" value="1">
							<input type="hidden" name="data_source" value="<?php echo $data_source ?>">
						</div><?php
							if( $data_source === self::import_package_tag
							 && $file_name ){
								$data_source_label = sprintf(' du package <a href="%s">%s</a>'
									, Agdp_Admin_Packages::get_post_type_package_url( $post_type, $file_name )
									, Agdp_Admin_Packages::get_package_file_post_type( $file_name )
								);
							}
							else
								$data_source_label = $data_source ? ' du package <i>' . $data_source . '</i>' : '';
						?><div><h1>Importation<?php echo $data_source_label ?> en attente de confirmation</h1><label><input type="checkbox" name="update_existing"<?php if( $update_existing ) echo ' checked';?>> Mettre à jour les enregistrements pré-existants (sur la base du titre) </label>
							<br><label><input type="checkbox" name="create_news"<?php if( $create_news ) echo ' checked';?>> Créer de nouveaux enregistrements</label>
							<br>&nbsp;&nbsp;<label><input type="checkbox" name="add_title_suffix"<?php if( $add_title_suffix ) echo ' checked';?>> Ajouter un suffixe aux titres </label>
								<input type="text" name="title_suffix" value="<?php echo esc_attr($title_suffix);?>">
							<br><label><input type="checkbox" name="import_terms"<?php if( $import_terms ) echo ' checked';?>> Importer les taxonomies</label>
							
						</div>
						<ul id="confirm_action_posts" class="confirm_action_posts">
							<h3 class="confirm_action_posts-title">Veuillez confirmer les mises à jours et importations.</h3>
					<?php
					
				}
				else {
					echo sprintf("<h1>Importation%s</h1>"
						, $data_source ? ' du package ' . $data_source : ''
					);
				}
				
				//Import ou Cases à cocher de confirmation
				$options = array_merge( $_REQUEST, [
					'data_source' => $data_source,
					'confirm_action' => $confirm_action,
					'is_confirmed_action' => $is_confirmed_action,
					'add_title_suffix' => $add_title_suffix,
					'title_suffix' => $title_suffix,
					'create_news' => $create_news,
					'update_existing' => $update_existing,
					'import_terms' => $import_terms,
				]);
				$posts = self::agdp_import_posts( $action_data, $options );
				if( $posts === false ) {
					echo sprintf('<div class="error"><label>%s</label><pre>%s</pre></div>'
						, 'Impossible de reconnaitre les données à importer'
						, json_encode( $action_data )
					);
				}
				elseif( is_array($posts) ){
					if( ! $confirm_action )
						echo sprintf('<div class="info">%d importation%s ou mise%s à jour</div>'
							, count($posts)
							, count($posts) > 1 ? 's' : ''
							, count($posts) > 1 ? 's' : ''
						);
					elseif( count($posts) )
						echo '';
					else
						echo '<div class="info">rien à faire</div>';
					
				}
				
				if( $confirm_action ){
					?>
					</ul>
					<script>var $ = jQuery;
					var $inputs = $('#confirm_action_posts li input[type="checkbox"][name^="confirm_"]');
					if( $inputs.length > 0 ){
						$('<div></div>')
							.css('position', 'relative')
							.css('top', '-5px')
							.append( $('<a class="check-all dashicons-before dashicons-yes-alt">toutes</a>')
								.click(function(e){
									$inputs.prop('checked', 'checked');
								})
							)
							.append('&nbsp;')
							.append( $('<a class="check-none dashicons-before dashicons-editor-removeformatting">aucune</a>')
								.click(function(e){
									$inputs.removeAttr('checked');
								})
							)
							.insertAfter( $('#confirm_action_posts .confirm_action_posts-title') )
						;
					}
					</script>
					<button type="send" class="button button-primary button-large">
						<span class="dashicons-before dashicons-database-import"></span><?php echo "Confirmer l'importation"?>
					</button>
					<input type="hidden" name="action_<?php echo Agdp_Admin_Edit_Post_Type::get_action_name($action);?>" value="1">
					<input type="hidden" name="post_referer" value="<?php echo $post_id;?>">
					</form>
					<?php
					
				}
			}
			else {
				echo "Aucune donnée importable.";
				echo '<pre>'; var_dump($action_data, $_POST);echo '</pre>'; 
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
		
		$url = wp_nonce_url( '/wp-admin/admin.php?page=agendapartage-import' /* $_SERVER['REQUEST_URI'] */, Agdp_Admin_Edit_Post_Type::get_nonce_name( $action, $post ? $post->ID : 0) );
		//FORM
		?><form class="agdp-import-posts" action="<?php echo $url;?>" method="post">
			<input type="hidden" name="data_source" value="<?php echo $data_source ?>">
			<?php
				if( empty($data_str) ){
					echo '<div class="existing-packages">';
					foreach( Agdp_Admin_Packages::get_packageable_post_types() as $post_type ){
						$package_file = Agdp_Admin_Packages::get_post_type_package_file( $post_type );
						if( ! file_exists($package_file) )
							continue;
						echo Agdp_Admin_Packages::get_import_link( $post_type, "Package ".$post_type, "" );
					}
					echo '</div>';
				}
			?><div><h3>Coller ici les données à importer</h3>
				<textarea name="action-data" rows="5" cols="100"><?php echo isset($data_str) ? htmlspecialchars( $data_str ) : '' ?></textarea>
			</div>
			<label><input type="checkbox" name="confirm_action"<?php if( $confirm_action ) echo ' checked';?>> Confirmer chaque importation</label>
			<div>
				<br><label><input type="checkbox" name="update_existing"<?php if( $update_existing ) echo ' checked';?>> Mettre à jour les enregistrements pré-existants (sur la base du titre) </label>
				<br><label><input type="checkbox" name="create_news"<?php if( $create_news ) echo ' checked';?>> Créer de nouveaux enregistrements</label>
				<br>&nbsp;&nbsp;<label><input type="checkbox" name="add_title_suffix"<?php if( $add_title_suffix ) echo ' checked';?>> Ajouter un suffixe aux titres </label>
					<input type="text" name="title_suffix" value="<?php echo esc_attr($title_suffix);?>">
				<br><label><input type="checkbox" name="import_terms"<?php if( $import_terms ) echo ' checked';?>> Importer les taxonomies</label>
				
			</div>
			<br>
			<br>
			<button type="send" class="button button-primary button-large">
				<span class="dashicons-before dashicons-database-view"></span><?php echo "Préparer l'importation"?>
			</button>
			<input type="hidden" name="action_<?php echo Agdp_Admin_Edit_Post_Type::get_action_name($action);?>" value="1">
			<input type="hidden" name="post_referer" value="<?php echo $post_id;?>">
		</form>
		<?php
		return;
		
	}
	/**
	 * Imports posts or add a confirmation checkbox
	 * $data[]['post']
	 */
	private  static function agdp_import_posts( $data, &$options = false ) {
		if( ! is_array($options) )
			$options = [];
		
		if( ! empty($data['post']) ){
			return static::agdp_import_post( $post_data, $options );
		}
	
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
				$new_term_ids = static::agdp_import_terms( $data, $options );
		}
		
		$new_posts = [];
		$new_posts_post_types = [];
		
		// agdp_import_post of each
		foreach( $data as $index => $post_data )
			if( is_numeric($index)
			&& ! empty($post_data['post']) ){
				$original_id = $post_data['post']['ID'];
				$new_id = static::agdp_import_post( $post_data, $options );
				if( $new_id ){
					$data[$index] = $post_data;
					$options['original_ids'][$original_id.''] = $new_id;
					$new_posts[] = $new_id;
					
					$post_type = $post_data['post']['post_type'];
					if( ! isset($new_posts_post_types[ $post_type ]) )
						$new_posts_post_types[ $post_type ] = [];
					$new_posts_post_types[ $post_type ][ $new_id.'' ] = $post_data;
				}
			}
			
		// set parent
		$is_confirmed_action = isset($options['is_confirmed_action']) && $options['is_confirmed_action'];
		if( $is_confirmed_action ){
			foreach( $data as $index => $post_data ){
				if( is_numeric($index)
				&& ! empty($post_data['post']) ){
					//post_parent 
					if( isset($post_data['_original_data']['post_parent'])
					&& ( ! isset($post_data['post']['post_parent'])
						|| $post_data['post']['post_parent'] == $post_data['_original_data']['post_parent'] )
					){
						if( isset($options['original_ids'])
						 && isset($options['original_ids'][$post_data['_original_data']['post_parent'].'']) ){
							$post_data['post']['post_parent'] = $options['original_ids'][$post_data['_original_data']['post_parent'].''];
							wp_update_post( $post_data['post'], true );
						 }
					}
				}
			}
		}
		
		if( $is_confirmed_action )
			foreach( $new_posts_post_types as $post_type => $posts )
				foreach( $posts as $post_id => $post_data )
					$post_data = apply_filters( AGDP_TAG . '_after_' . $post_type . '_import', $post_data, $post_id, $options );
		
		return $new_posts;
	}

	/**
	 * Imports post or add a confirmation checkbox
	 * $data['post'], $data['metas']
	 */
	private static function agdp_import_post( &$data, &$options = false ) {
		
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
		$post_type = $data['post']['post_type'];
		if( ! Agdp_Admin_Edit_Post_Type::has_cap( 'import', $post_type ) ){
				echo sprintf( '<div class="error">Le type %s ne peut pas être importé.<pre>%s</pre></div>'
					, $post_type
					, htmlspecialchars( json_encode($data['post']) )
				);
			return false;
		}
				
		$original_id = $data['post']['ID'];
		
		$data['_original_data'] = $data['post'];
		foreach([ 'ID',
			'post_password', 
			'guid', 
			'post_modified', 
			'post_modified_gmt', 
			'post_name'
		 ] as $key){
			unset($data['post'][$key]);
		 }
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
					if( is_string($meta_value) )
						$meta_value = addslashes( $meta_value );
					$data['metas'][$meta_key] = $meta_value;
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
			//TODO search with parent slug path
			$existing = get_posts([
				'post_type' => $post_type,
				'post_status' => ['publish', 'pending', 'draft', 'private', 'future', $data['post']['post_status']],
				'title' => wp_slash( $data['post']['post_title'] ),
				'numberposts' => 2
			]);
			if( $existing ){
				$update_existing = $existing[0];
				
				if( $post_type === Agdp_WPCF7::post_type )
					$edit_url = sprintf('/wp-admin/admin.php?page=wpcf7&post=%s&action=edit', $update_existing->ID);
				else
					$edit_url = get_edit_post_link( $update_existing->ID );
				
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
			else {
				$update_existing = false;
			}
		}
		
		// \ problem in post_title
		$data['post']['post_title'] = wp_slash($data['post']['post_title']);
		
		// update_existing
		if( $update_existing ) {
			$data['post']['ID'] = $update_existing->ID;
			$data['post']['post_name'] = $update_existing->post_name;
			if( $confirm_action || $is_confirmed_action ) {
				$confirm_key = sprintf('confirm_%s_%s_%s'
					, 'update'
					, $post_type
					, $original_id
				);
			}
			
			$same_import_package_key = self::compare_import_package_key( $update_existing->ID, $data, $options );
			
			if( $confirm_action ) {
				
				$post_time = strtotime( $update_existing->post_modified );
				$import_time = strtotime( $data['_original_data']['post_modified'] );
				$is_newer = $import_time < $post_time;
				
				//checkbox
				echo sprintf( '<li><label><input type="checkbox" name="%s" %s>%s Mise à jour de <a href="%s">%s</a>%s%s</li>'
					, $confirm_key
					, $same_import_package_key ? '' : 'checked' //TODO $is_newer ?
					, Agdp::icon('update')
					, $edit_url
					, htmlspecialchars( stripslashes($data['post']['post_title']) )
					, $same_import_package_key ? ' <span title="aucun changement depuis le dernier import">(identique)</span>' : ''
					, $same_import_package_key || ! $is_newer ? '' 
						: sprintf('&nbsp;<span class="dashicons-before dashicons-info-outline" title="Plus récent ici que dans l\'import (%s > %s)"></span>'
							, date('d/m/Y H:i:s', $post_time)
							, date('d/m/Y H:i:s', $import_time)
						)
				);
				return $original_id;
			}
			
			if( $is_confirmed_action
			&& empty( $options[ $confirm_key ] ) ){
				// echo sprintf( '<div>%s Ignore %s</div>'
					// , Agdp::icon('no-alt')
					// , htmlspecialchars( $data['post']['post_title'] )
				// );
				$options['original_ids'][$original_id.''] = $update_existing->ID;
				return false;
			}
			
			if( $same_import_package_key ){
				//skip db update
				$new_post_id = $update_existing->ID;
			}
			else
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
					, $post_type
					, $original_id
				);
			}
			if( $confirm_action ) {
				echo sprintf( '<li><label><input type="checkbox" name="%s" %s>%s Création de %s [%s]</li>'
					, $confirm_key
					, 'checked'
					, Agdp::icon('plus')
					, htmlspecialchars( stripslashes( $data['post']['post_title'] ) )
					, $post_type
				);
				return $original_id;
			}
			
			if( $is_confirmed_action
			&& empty( $options[ $confirm_key ] ) ){
				// echo sprintf( '<li>%s Ignore %s</li>'
					// , Agdp::icon('no-alt')
					// , htmlspecialchars( $data['post']['post_title'] )
				// );
				return false;
			}
			
			// wp_insert_post
			$new_post_id = wp_insert_post( $data['post'], true );
			if( $new_post_id )
				$data['post']['ID'] = $new_post_id;
		}
		else
			$new_post_id = 0;
		
		if( $new_post_id
		 && $is_confirmed_action ){
			
			//Store import_package_key
			self::update_import_package_key( $new_post_id, $data, $options );
			
			//Taxonomies
			if( $import_terms ){
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
				, htmlspecialchars( stripslashes( $data['post']['post_title'] ) )
			);
		}
		return $new_post_id;
	}
	
	/**
	 * Import terms
	 * $data[]['term']
	 */
	private  static function agdp_import_terms( $data, &$options = false ) {
		if( ! is_array($options) )
			$options = [];
		
		if( ! empty($data['term']) ){
			return static::agdp_import_term( $term_data, $options );
		}
		
		$new_terms = [];
		
		if( ! empty($data['terms']) ){
			$options['original_term_ids'] = [];
			$confirm_action = isset($options['confirm_action']) && $options['confirm_action'];
			if( $confirm_action ){
				$taxonomies = [];
			}
			//Tri par taxonomy
			usort( $data['terms'], function( $t1, $t2 ){
				if( empty( $t1['term']['taxonomy'] ) || empty( $t2['term']['taxonomy'] ) )
					return 0;
				return strcmp( $t1['term']['taxonomy'], $t2['term']['taxonomy'] );
			});
			foreach( $data['terms'] as $index => $term_data ){
				if( ! empty($term_data['term']) ){
					$original_id = $term_data['term']['term_id'];
					
					if( $confirm_action
					&& ! in_array( $term_data['term']['taxonomy'], $taxonomies ) ){
						if( count($taxonomies) > 0 ){
							echo '</ul></li>';
						}
						$taxonomies[] = $term_data['term']['taxonomy'];
						echo sprintf('<li><label>%s</label><ul>'
							, $term_data['term']['taxonomy']
						);
					}
					//Import ou case à cocher de confirmation
					$new_id = static::agdp_import_term( $term_data, $options );
					if( $new_id ){
						$data['terms'][$index] = $term_data;
						$options['original_term_ids'][$original_id.''] = $new_id;
						$new_terms[] = $new_id;
					}
				}
			}
			if( $confirm_action
			&& count($taxonomies) > 0 ){
				echo '</ul></li>';
			}
		}
		return $new_terms;
	}
	
	/**
	 * Import term
	 * $data['term'], $data['metas']
	 */
	private  static function agdp_import_term( &$data, &$options = false ) {
	
		/***********
		 * WP_Term */
		
		if( empty($data['term'])
		 || empty($data['term']['slug'])
		 || empty($data['term']['taxonomy']) ){
				// echo sprintf( '<div class="error">Données incomplètes.<pre>%s</pre></div>'
					// , htmlspecialchars( json_encode($data) )
				// );
			return false;
		}
		$confirm_action 		= isset($options['confirm_action']) && $options['confirm_action'];
		$is_confirmed_action 	= isset($options['is_confirmed_action']) && $options['is_confirmed_action'];
		$add_title_suffix 		= false;//isset($options['add_title_suffix']) && $options['add_title_suffix'];
		$title_suffix 			= '';//empty($options['title_suffix']) ? ' (importé)' : $options['title_suffix'];
		$import_terms 			= empty($options['import_terms']) ? false : $options['import_terms'];
		
		$original_id = $data['term']['term_id'];
		$data['_original_data'] = $data['term']; //backup
		
		$taxonomy = $data['term']['taxonomy'];
		
		$create_new = false;
		$same_import_package_key = false;
		
		/** existing_term **/
		//TODO search with parent slug path
		if( $existing_term = get_term_by( 'slug', $data['term']['slug'], $taxonomy, OBJECT ) ){
			
			$same_import_package_key = self::compare_import_package_key( $existing_term, $data, $options );
					
			// echo sprintf( '<div><a href="%s">Le terme %s -> %s existe déjà</a></div>'
				// , get_edit_term_link( $existing_term->term_id )
				// , $taxonomy
				// , htmlspecialchars( $data['term']['name'] )
			// );
			// TODO update ?
		}
		else
			$create_new = true;
		
		//term_parent 
		if( ! empty($data['term']['parent']) ){
			if( isset($options['original_term_ids'])
			 && isset($options['original_term_ids'][$data['term']['parent'].'']) )
				$data['term']['parent'] = $options['original_term_ids'][$data['term']['parent'].''];
			else
				unset($data['term']['parent']);
		}
		
		if( ! $import_terms ){
			return;
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
		
		if( $confirm_action || $is_confirmed_action ) {
			$confirm_key = sprintf('confirm_%s_%s_%s'
				, $existing_term ? 'update' : 'create'
				, $data['term']['name']
				, $original_id
			);
		}
		
		// wp_insert_term
		if( $confirm_action ){
			if( $existing_term ){
				//checkbox
				echo sprintf( '<li><label><input type="checkbox" name="%s" %s>%s Mise à jour de <a href="%s">%s</a>%s</li>'
					, $confirm_key
					, $same_import_package_key ? '' : 'checked'
					, Agdp::icon('update')
					, get_edit_post_link( $existing_term->term_id )
					, htmlspecialchars( stripslashes($data['term']['name']) )
					, $same_import_package_key ? ' <span title="aucun changement depuis le dernier import">(identique)</span>' : ''
				);
				return $existing_term->term_id;
			}
			//checkbox
			echo sprintf( '<li><label><input type="checkbox" name="%s" %s>%s Création de %s%s</li>'
				, $confirm_key
				, $same_import_package_key ? '' : 'checked'
				, Agdp::icon('plus')
				, htmlspecialchars( stripslashes($data['term']['name']) )
				, $same_import_package_key ? ' <span title="aucun changement depuis le dernier import">(identique)</span>' : ''
			);
			return -1;
		}
		if( $is_confirmed_action ){
			// Confirmation not checked
			if( $is_confirmed_action
			&& empty( $options[ $confirm_key ] ) ){
				if( $existing_term ){
					return $existing_term->term_id;
				}
				return false;
			}
			if( $existing_term ){
				//$existing_term has been found from slug comparaison
				//TODO update term
				
				$new_term_id = $existing_term->term_id;
			}
			else {
				/* wp_insert_term */
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
			}
			if( $new_term_id ){
				if( is_array($new_term_id) )
					$new_term_id = $new_term_id['term_id'];
				
				/* update_term_meta */
				if( isset($data['metas']) ){
					foreach($data['metas'] as $meta_key => $meta_value){
						update_term_meta( $new_term_id, $meta_key, $meta_value );
					}
				}
				echo sprintf( '<div><a href="%s">%s %s du terme %s -> %s</a></div>'
					, get_edit_term_link( $new_term_id )
					, Agdp::icon( $existing_term ? 'update' : 'plus' )
					, $existing_term ? 'Mise à jour' : 'Création'
					, $taxonomy
					, htmlspecialchars( $data['term']['name'] )
				);
				
				$data['term']['term_id'] = $new_term_id;
				do_action( AGDP_TAG . '_' . $taxonomy . '_term_imported', $taxonomy, $data['term'] );
			}
		}
		return $new_term_id;
	}
	
	/**
	 * get_import_package_key
	 */
	 public static function get_import_package_key( $post_id, $data ){
		 if( is_a($post_id, 'WP_Term') ){
			$term = $post_id;
			$import_package_key = uniqid('TODO');
			// str_replace( '-', '',
			// str_replace( ':', '',
				// sprintf('%s|%s'
					// , $data['_original_data']['post_modified_gmt']
					// , $term->post_modified_gmt
				// )
			// ));
			return $import_package_key;
		 }

		$post = get_post( $post_id );
		$import_package_key = 
		str_replace( '-', '',
		str_replace( ':', '',
			sprintf('%s|%s'
				, $data['_original_data']['post_modified_gmt']
				, $post->post_modified_gmt
			)
		));
		return $import_package_key;
	}
	
	/**
	 * update_import_package_key
	 */
	 public static function update_import_package_key( $post_id, $data, $options ){
		$data_source = empty($options['data_source']) ? false : $options['data_source'];
		if( $data_source !== self::import_package_tag )
			return false;

		$import_package_key = self::get_import_package_key( $post_id, $data );
		
		$meta_key = '_' . self::import_package_tag . '_key';
		
		return update_post_meta( $post_id, $meta_key, $import_package_key );
	}
	
	/**
	 * delete_import_package_key
	 */
	 public static function delete_import_package_key( $post_id, $data ){
		$meta_key = '_' . self::import_package_tag . '_key';
		
		if( is_a($post_id, 'WP_Term') ){
			$term = $post_id;
			return delete_term_meta( $term->term_id, $meta_key );
		}
		return delete_post_meta( $post_id, $meta_key );
	}
	
	/**
	 * compare_import_package_key
	 */
	 public static function compare_import_package_key( $post_id, $data, $options ){
		$data_source = empty($options['data_source']) ? false : $options['data_source'];
		if( $data_source !== self::import_package_tag )
			return false;
		
		$import_package_key = self::get_import_package_key( $post_id, $data );
		
		$meta_key = '_' . self::import_package_tag . '_key';
		
		$current_value = get_post_meta( $post_id, $meta_key, true );
		
		// debug_log( __FUNCTION__, $import_package_key, $current_value , strcmp( $import_package_key, $current_value ) === 0);
		
		return strcmp( $import_package_key, $current_value ) === 0;
	}
}

?>