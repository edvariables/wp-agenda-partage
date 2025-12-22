<?php

/**
 * AgendaPartage -> Page abstract
 * Extension des custom post type.
 * Uilisé par Agdp_Evenement et Agdp_Covoiturage
 * 
 */
abstract class Agdp_Page {
	
	const post_type = 'page'; //Must override
	const posts_type = false; //Must override
	const page_type = false; //Must override
	
	const icon = 'text-page';

	/**
	 * Retourne le type de la page
	 */
	public static function is_agdp_post_type( $page_id = false ){
		if( ! $page_id )
			return static::page_type;
		
		if( is_a($page_id, 'WP_Post') )
			$page_id = $page_id->ID;
		switch( $page_id ){
			case Agdp::get_option('agenda_page_id'):
				return Agdp_Evenement::post_type;
			case Agdp::get_option('covoiturages_page_id'):
				return Agdp_Covoiturage::post_type;
			default:
				return 'page';
		}
		return false;
	}

	/**
	 * Retourne le type de la page
	 */
	public static function get_page_type( $page_id = false ){
		if( ! $page_id )
			return static::page_type;
		
		if( is_a($page_id, 'WP_Post') )
			$page_id = $page_id->ID;
		switch( $page_id ){
			case Agdp::get_option('agenda_page_id'):
				return Agdp_Evenement::post_type;
			case Agdp::get_option('covoiturages_page_id'):
				return Agdp_Covoiturage::post_type;
			default:
				return 'page';
		}
		return false;
	}
	
	/**
	 * Retourne le type des posts contenus dans la page
	 */
	public static function get_posts_type( $page_id = false ){
		if( ! $page_id )
			return static::posts_type;
		
		$page_type = static::get_page_type( $page_id );
		
		if( $page_type === 'page' ){
			if( Agdp_Forum::post_is_forum( $page_id ) )
				return 'comment';
			else
				return false;
		}
		return $page_type;
	}
	
	/**
	 * Retourne la classe du type des posts contenus dans la page
	 */
	public static function get_posts_type_class( $page_id_or_post_type ){
		
		if( is_a($page_id_or_post_type, 'WP_Post') ){
			$page_id = $page_id_or_post_type;
			$post_type = $page_id->post_type;
		}
		elseif( is_numeric( $page_id_or_post_type ) ){
			$page_id = $page_id_or_post_type;
			$post_type = self::get_posts_type( $page_id );
		}
		elseif( is_string($page_id_or_post_type) ){
			$post_type = $page_id_or_post_type;
			$page_id = false;
		}
		switch( $post_type ){
			case Agdp_Evenement::post_type:
				return 'Agdp_Evenement';
			case Agdp_Covoiturage::post_type:
				return 'Agdp_Covoiturage';
			default:
				// if( Agdp_Forum::post_is_forum( $page_id ) )
					return 'Agdp_Comment';
				// return 'WP_Comment';
		}
		return false;
	}
	
	/**
	 * Retourne la classe de gestion des posts ou comments contenus dans la page
	 */
	public static function get_posts_class( $page_id ){
		if( $posts_type_class = self::get_posts_type_class( $page_id ) )
			return $posts_type_class . 's';
		return false;
	}
	
	/**
	 * Retourne le nom de l'icon selon le type de posts affichés
	 */
	public static function get_icon( $page_id, $default = false ){
		if( ! $page_id )
			return static::icon;
		
		if( is_a($page_id, 'WP_Post') ){
			switch( $page_id->post_type ){
				case WPCF7_ContactForm::post_type :
					return Agdp_WPCF7::icon;
				case Agdp_Covoiturage::post_type :
					return Agdp_Covoiturage::icon;
				case Agdp_Evenement::post_type :
					return Agdp_Evenement::icon;
			}
		}
		
		foreach([
			'new_agdpevent_page_id' => 'welcome-add-page',
			'new_covoiturage_page_id' => 'welcome-add-page',
			'agenda_page_id' => Agdp_Evenements::icon,
			'covoiturages_page_id' => Agdp_Covoiturages::icon,
		] as $option => $icon ){
			if( $page_id == Agdp::get_option( $option ) ){
				return $icon;
			}
		}
		if( Agdp_Forum::post_is_forum( $page_id ) )
			return Agdp_Forum::icon;
		if( $default && $default !== true )
			return $default;
		return self::icon;
	}
	
	/**
	 * Retourne la classe de gestion des posts ou comments contenus dans la page
	 */
	public static function get_source_key( $page_id ){
		if( is_a($page_id, 'WP_Post') )
			$page_id = $page_id->ID;
		$page_type = static::get_page_type( $page_id );
		switch( $page_type ){
			case Agdp_Evenement::post_type:
			case Agdp_Covoiturage::post_type:
				return $page_type;
			default:
				return sprintf('page.%d', $page_id);
		}
	}

	/**
	 * Retourne les newsletters utilisant une page.
	 * $exclude_sub_newsletters exclut les newsletters qui utilise les abonnés d'une autre lettre-info
	 */
	public static function get_page_newsletters($page_id = false, $exclude_sub_newsletters = false){

		$query = new WP_Query([
			'post_type' => Agdp_Newsletter::post_type,
			'numberposts' => -1,
			'meta_key' => 'source',
			'meta_value' => static::get_source_key( $page_id ),
		]);
		$posts = $query->get_posts();
		
		$meta_key = 'subscription_parent';
		
		$newsletters = [];
		foreach( $posts as $newsletter )
			if( ! get_post_meta( $newsletter->ID, $meta_key, true ) )
				$newsletters[$newsletter->ID.''] = $newsletter;
			
		if( ! $exclude_sub_newsletters ){
			//Ajoute ceux qui ont un subscription_parent
			foreach( $posts as $newsletter )
				if( ! isset($newsletters[$newsletter->ID.'']) )
					$newsletters[$newsletter->ID.''] = $newsletter;
		}
		
		return $newsletters;
	}

	/**
	 * Retourne la première newsletter utilisant une page.
	 */
	public static function get_page_main_newsletter($page_id = false){
		foreach( self::get_page_newsletters($page_id, true) as $newsletter )
			return $newsletter;
		
		return false;
	}
	
	/**
	* Retourne les pages
	*
	*/
	public static function get_pages( $parent_id = 0) {
		return get_posts([ 
			'post_type' => 'page',
			'post_status' => 'publish',
			'numberposts' => -1,
			'post_parent' => $parent_id
		]);
	}
	
	/**
	 * Retourne l'analyse de la page des évènements ou covoiturages
	 * Fonction appelable via Agdp_Evenement, Agdp_Covoiturage ou une page quelconque
	 */
	public static function get_diagram( $blog_diagram, $page ){
		
		$posts_pages = $blog_diagram['posts_pages'];
		$page_id = $page->ID;
		$diagram = [ 
			'type' => 'page', 
			'id' => $page_id, 
			'page' => $page, 
			'newsletters' => static::get_page_newsletters( $page_id ),
		];
		
		//page de posts
		if( isset($posts_pages[$page_id.''])){
			$posts_page = $posts_pages[$page_id.''];
			$diagram['posts_page'] = $posts_page['page'];
			$diagram['posts_type'] = $posts_page['posts_type'];
		}
		else {
			$diagram['posts_page'] = $page;
			$diagram['posts_type'] = static::get_page_type( $page );
		}
		
		$diagram = array_merge( Agdp_Post::get_diagram( $blog_diagram, $diagram['posts_page'] )
							, $diagram );
		$posts_class = static::get_posts_class( $diagram['posts_page'] );
		if( $posts_class === 'Agdp_Comments' )
			$diagram['pending'] = $posts_class::get_pending_comments( $page );
		else
			$diagram['pending'] = $posts_class::get_pending_posts();
		
		if( $children_pages = static::get_pages( $page->ID ) )
			$diagram['pages'] = $children_pages;
		// var_dump($page->post_title . ' #' . $page->ID , count($children_pages));
		
		return $diagram;
	}
	/**
	 * Rendu Html d'un diagram
	 */
	public static function get_diagram_html( $page, $diagram = false, $blog_diagram = false ){
		
		// if( ! static::posts_type
		 // && $blog_diagram
		 // && isset( $blog_diagram['posts_pages'][$page->ID.''] ) ){
			// $post_class = $blog_diagram['posts_pages'][$page->ID.'']['class'];
			// return $post_class::get_diagram_html( $page, $diagram, $blog_diagram );
		// }
		
		if( ! $diagram ){
			if( ! $blog_diagram )
				throw new Exception('$blog_diagram doit être renseigné si $diagram ne l\'est pas.');
			$diagram = self::get_diagram( $blog_diagram, $page );
		}
		$admin_edit = is_admin() ? sprintf(' <a href="/wp-admin/post.php?post=%d&action=edit">%s</a>'
			, $page->ID
			, Agdp::icon('edit show-mouse-over')
		) : '';
		
		$html = '';
		
		$icon = self::get_icon( $page->ID );
		$html .= sprintf('<div class="%s">%s Page <a href="%s">%s</a>%s</div>'
			, __CLASS__
			, Agdp::icon( $icon )
			, get_permalink( $page )
			, $page->post_title
			, $admin_edit
		);
		
		$html .= Agdp_Post::get_diagram_html( $page, $diagram, $blog_diagram );
		
		$posts_type = self::get_posts_type($page);
		
		$property = 'pending';
		if( ! empty($diagram['pending']) ){
			if( is_admin() ){
				if( $posts_type === 'comment' )
					$admin_edit = sprintf(' <a href="/wp-admin/edit-comments.php?p=%d&comment_status=moderate">%s</a>'
						, $page->ID
						, Agdp::icon('edit show-mouse-over')
					);
				else
					$admin_edit = sprintf(' <a href="/wp-admin/edit.php?post_type=%s&post_status=pending">%s</a>'
						, $posts_type
						, Agdp::icon('edit show-mouse-over')
					);
			} else 
				$admin_edit = '';
			$html .= sprintf('<div>%s En attente : %d %s%s%s</div>'
				, Agdp::icon('welcome-comments alert')
				, count($diagram['pending'])
				, strtolower( Agdp_Post::get_post_type_labels( $posts_type )->singular_name )
				, count($diagram['pending']) > 1 ? 's' : ''
				, $admin_edit
			);
		}
		
		$property = 'newsletters';
		$icon = Agdp_Newsletter::icon;
		if( ! empty( $diagram[$property] ) )
			foreach( $diagram[$property] as $newsletter_id => $newsletter ){
				if( $newsletter->post_status !== 'publish' )
					continue;
				$html .= sprintf('<h3 class="toggle-trigger">%s %s</h3>'
					, Agdp::icon($icon)
					, $newsletter->post_title
				);
				$html .= '<div class="toggle-container">';
					$html .= Agdp_Newsletter::get_diagram_html( $newsletter, false, $blog_diagram );
				$html .= '</div>';
			}

		$property = 'pages';
		if( ! empty( $diagram[$property] ) )
			foreach( $diagram[$property] as $child_page ){
				$icon = Agdp_Page::get_icon( $child_page->ID );
				$html .= sprintf('<h3 class="toggle-trigger">%s %s</h3>'
					, Agdp::icon($icon)
					, $child_page->post_title
				);
				$html .= '<div class="toggle-container">';
					$html .= Agdp_Page::get_diagram_html( $child_page, false, $blog_diagram );
				$html .= '</div>';
			}
		return $html;
	}
}
