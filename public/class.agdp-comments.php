<?php

/**
 * AgendaPartage -> Forum -> Messages
 * Liste des commentaires d'une page associée à une mailbox
 */
class Agdp_Comments {

	private static $initiated = false;
	public static $default_comments_query = [];
	
	private static $default_comments_per_page = 30;
	private static $default_comments_per_mail = 60;
	
	private static $filters_summary = null;

	public static function init() {
		if ( ! self::$initiated ) {
			
			self::$initiated = true;

			self::init_hooks();
		}
	}

	/**
	 * Hook
	 */
	public static function init_hooks() {

	}
	/*
	 * Hook
	 ******/
	
	/**
	 *
	 * Appelée lors du get_list_html
	 */
	public static function init_default_comments_query( $page ) {
		$sort_date_field = Agdp_Forum::get_forum_sort_date_field($page);
		if( ! $sort_date_field )
			$sort_date_field = 'comment_date';
		
		self::$default_comments_query = array(
			'post_id' => $page->ID,
			'comment_type' => 'comment',
			'status' => '1',
			
			// 'hierarchical' => 'threaded',			
			//Tous, sans s'occuper de la hiérarchie
			'hierarchical' => false,
			'parent' => '',
			
			'orderby' => [
				// $sort_date_field => 'DESC'
			],
			
			'number' => self::$default_comments_per_page
		);
		
		if( is_array($sort_date_field) )
			foreach($sort_date_field as $sort_date_field_u)
				self::$default_comments_query['orderby'][ $sort_date_field_u ] = 'DESC';
		else
			self::$default_comments_query['orderby'][ $sort_date_field ] = 'DESC';

	}
	
	/**
	* Retourne les paramètres pour WP_Query avec les paramètres par défaut.
	* N'inclut pas les filtres.
	*/
	public static function get_comments_query(...$queries){
		$all = self::$default_comments_query;
		// echo "<div style='margin-left: 15em;'>";
		foreach ($queries as $query) {
			if( ! is_array($query)){
				if(is_numeric($query))
					$query = array('number' => $query);
				else
					$query = array();
			}
			if(isset($query['meta_query'])){
				if(isset($all['meta_query'])){
					$all['meta_query'] = array(
						(string)uniqid()=> $all['meta_query']
						, (string)uniqid()=> $query['meta_query']
						, 'relation' => 'AND'
					);
				}
				else
					$all['meta_query'] = $query['meta_query'];
				
				unset($query['meta_query']);
			}
			
			$all = array_merge($all, $query);
		}
			
		if( isset($all['post__in'])
		&& isset($all['post_id']) ){
			unset($all['post_id']);
			$all['orderby'] = array_merge( ['comment_post_ID' => 'ASC'], $all['orderby'] );
		}
		
		// var_dump($all);
		// echo "</div>";
		// debug_log( __FUNCTION__, $all);
		return $all;
		
	}
	
	/**
	 * Recherche des messages d'une semaine donnée
	 */
	public static function get_week_comments($year_week, $options = false){
		if( ! $year_week) $year_week = date('Y-w');
		
		$dates = get_week_dates(substr($year_week, 0,4), substr($year_week, 5,2));
		$date_min = $dates['start'];
		$date_max = $dates['end'];
		
		return self::get_period_comments( $date_min, $date_max, $options );
	}
	
	/**
	 * Recherche des messages d'une periode donnée
	 */
	public static function get_period_comments($date_min, $date_max, $options = false){
		if( is_string($date_min) && preg_match( '/^\d{4}-\d{2}$/', $date_min ) ){
			$dates = get_week_dates(substr($date_min, 0,4), substr($date_min, 5,2));
			$date_min = $dates['start'];
		}
		if( is_string($date_max) && preg_match( '/^\d{4}-\d{2}$/', $date_max ) ){
			$dates = get_week_dates(substr($date_max, 0,4), substr($date_max, 5,2));
			$date_max = $dates['end'];
		}
		elseif( ! $date_max )
			$date_max = date('Y-m-d',strtotime("+1 day"));
			
		if( is_array($options) && ! empty($options['since']) )
			$since_date = $options['since'];
		else
			$since_date = date('Y-m-d',strtotime("-1 month"));
		if( $since_date
		 && ( ! $date_min
			|| $since_date > $date_min )
		)
			$date_min = $since_date;
		if( ! $date_min )
			throw new Exception('Date minimale non fournie');
		
		$query = array(
			'date_query' => [
				'after'     => $date_min,
				'before'    => $date_max,
				'inclusive' => true,
			],
			'orderby' => [
				'comment_date' => 'DESC'
			],
			'nopaging' => true
			
		);
		if( is_array($options) && ! empty($options['orderby']) )
			$query['orderby'] = $options['orderby'];
		else {
			//TODO ?
			// $sort_date_field = Agdp_Forum::get_forum_sort_date_field($page);
			// if( ! $sort_date_field )
				// $sort_date_field = 'comment_date';
			// if( is_array($sort_date_field) )
				// foreach($sort_date_field as $sort_date_field_u)
					// $query['orderby'][ $sort_date_field_u ] = 'DESC';
			// else
				// $query['orderby'][ $sort_date_field ] = 'DESC';

			// $query['orderby'][$sort_date_field] = 'DESC';
		}
		
		$for_email = is_array($options) && ! empty($options['mode']) && ( $options['mode'] === 'email' );
		if( $for_email )
			$query['number'] = self::$default_comments_per_mail;
		
		//Include page children (sub forums)
		if( ! empty($options['include_children']) ){
			foreach( $options['include_children'] as $forum )
				$post_IDs[] = $forum[1];
			if( count($post_IDs) > 1 )
				$query['post__in'] = $post_IDs;
		}
		
		$comments = self::get_comments($query);
	
		return $comments;
    }
	
	/**
	 * Recherche de messages
	 */
	public static function get_comments(...$queries){
		$query = self::get_comments_query(...$queries);
		
		add_filter( 'comments_clauses', array(__CLASS__, 'on_get_comments_clauses_cb' ), 10, 2);
        $the_query = new WP_Comment_Query( $query );
		remove_filter( 'comments_clauses', array(__CLASS__, 'on_get_comments_clauses_cb' ), 10);
		
		// debug_log( __FUNCTION__, $query/* , $the_query->comments */);
		
		if( ! is_array($the_query->comments) )
			return $the_query->comments;
		$comments = self::get_comments_hierarchy( $the_query->comments );
		return $comments; 
    }
	
	/**
	 * Génère la hierarchie des commentaires et des réponses.
	 * Marque les commentaires parents par ->child_needs = true, si ils ont été uniquement ajouté en tant que parents;
	 */
	private static function get_comments_hierarchy( $comments ){
		
		$comment_ids = [];
		foreach( $comments as $comment ){
			$comment_ids[ $comment->comment_ID.'' ] = $comment;
		}
		$_comments = [];
		foreach( $comment_ids as $comment ){
			if( $comment->comment_parent ){
				//Fill hierarchy
				$comment_child = $comment;
				while( $comment_child->comment_parent ){
					if( ! empty( $comment_ids[ $comment_child->comment_parent.'' ] ) ){
						$comment_parent = $comment_ids[ $comment_child->comment_parent.'' ];
						$comment_parent->add_child( $comment_child );
						break;
					}
					$comment_parent = get_comment( $comment_child->comment_parent );
					if( ! $comment_parent ){
						$_comments[ $comment_child->comment_ID.'' ] = $comment_child;
						break;
					}
					$comment_parent->child_needs = true;
					
					$comment_parent->add_child( $comment_child );
					
					$comment_ids[ $comment_parent->comment_ID.'' ] = $comment_parent;
					
					if( ! $comment_parent->comment_parent ){
						$_comments[ $comment_parent->comment_ID.'' ] = $comment_parent;
						break;
					}
					
					//loop
					$comment_child = $comment_parent;
				}
				if( ! empty( $comment_ids[ $comment->comment_parent.'' ] ) ){
					continue;
				}
			}
			$_comments[ $comment->comment_ID.'' ] = $comment;
		}
		return $_comments; 
    }
	
	/**
	 * on_get_comments_clauses_cb
	 */
	public static function on_get_comments_clauses_cb($clauses, $query){
		global $wpdb;
		// debug_log('on_get_comments_clauses_cb', $clauses);
		$status_meta_alias = 'comm_meta_status';
		$clauses['join'] .= " LEFT JOIN {$wpdb->commentmeta} {$status_meta_alias}"
						. " ON ( {$wpdb->comments}.comment_ID = {$status_meta_alias}.comment_id AND {$status_meta_alias}.meta_key = 'status' )";
		$clauses['where'] .= " AND ({$status_meta_alias}.comment_id IS NULL OR {$status_meta_alias}.meta_value != 'ended')";
		return $clauses;
	}
	
	/**
	 * Recherche de messages en attente d'approbation
	 */
	public static function get_pending_comments( $page ){
		$pendings = self::get_comments( array(
			'fields' => 'ids',
			'post_id' => $page->ID,
			'status' => '0',
			'number' => '',
		));
		return $pendings;
    }
	
	/**
	 * Recherche de tous les mois contenant des messages mais aussi les mois sans.
	 * Return array($week => $count)
	 */
	public static function get_comments_weeks( $page, $options = false ){
		
		$post_IDs = [];
		if( ! empty($options['include_children']) ){
			foreach( $options['include_children'] as $forum )
				$post_IDs[] = $forum[1];
			if( count($post_IDs) <= 1 )
				$post_IDs = false;
			else
				$post_IDs = implode( ',', $post_IDs );
		}
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$status_meta_alias = 'comm_meta_status';
		
		if( is_array($options) && ! empty($options['since']) )
			$since_date = $options['since'];
		else
			$since_date = false;
		if( $since_date )
			$since_date = "'" . $since_date . "'";
		else
			$since_date = 'DATE_SUB(NOW(), INTERVAL 1 MONTH)';
		
		//Find this in other blog 
		$sql = "SELECT DISTINCT DATE_FORMAT(comment_date, '%Y') as year
				, DATE_FORMAT(comment_date, '%u') as week
				, COUNT(comments.comment_ID) as count
				FROM {$blog_prefix}comments comments
				LEFT JOIN {$blog_prefix}commentmeta {$status_meta_alias}
					ON comments.comment_ID = {$status_meta_alias}.comment_id
					AND {$status_meta_alias}.meta_key = 'status'
		";
		if( ! $post_IDs )
			$sql .= "\nWHERE comment_post_ID = {$page->ID}";
		else
			$sql .= "\nWHERE comment_post_ID IN ({$post_IDs})";
		$sql .= "\nAND comment_date > {$since_date}
					AND comment_type = 'comment'
					AND comment_approved = '1'
					AND ({$status_meta_alias}.meta_value IS NULL OR {$status_meta_alias}.meta_value != 'ended')
		";
		
		$sql .= "GROUP BY year, week
				ORDER BY year DESC, week DESC
		";
		$result = $wpdb->get_results($sql);
		// debug_log('get_comments_weeks', $sql, $result);
		$weeks = [];
		$prev_row = false;
		foreach($result as $row){
			if($prev_row)
				if($prev_row->year === $row->year){
					for($m = (int)$prev_row->week + 1; $m < (int)$row->week; $m++)
						$weeks[$prev_row->year . '-' . sprintf("%02d",$m)] = 0;
				}
				elseif((int)$prev_row->year === (int)$row->year - 1){
					for($m = (int)$prev_row->week + 1; $m <= 12; $m++)
						$weeks[$prev_row->year . '-' . $m] = 0;
					for($m = 1; $m < (int)$row->week; $m++)
						$weeks[$row->year . '-' . sprintf("%02d",$m)] = 0;
				}
				elseif((int)$prev_row->year < (int)$row->year - 1)
					break;
			$weeks[$row->year . '-' . $row->week] = (int)$row->count;
			$prev_row = $row;
		}
		return $weeks;
    }

	/**
	* Rendu Html des messages sous forme d'arborescence par mois
	*
	*/
	public static function get_list_html($mailbox, $page, $content = '', $options = false){
		
		self::init_default_comments_query($page);
			
		if(!isset($options) || !is_array($options))
			$options = array();
		
		$options = array_merge(
			array(
				'ajax' => false,
				'max_messages' => 30,
				'mode' => 'list' //list|email|text|calendar|TODO...
			), $options);
		$options = self::filter_anteriority_option($options, ANTERIORITY_ALL);
		
		$for_email = $options['mode'] == 'email';
		if( $for_email ){
			$options['ajax'] = false;
			$sort_date_field = Agdp_Forum::get_forum_sort_date_field($page);
			if( ! $sort_date_field )
				$sort_date_field = 'comment_date';
			if( is_array($sort_date_field) )
				foreach($sort_date_field as $sort_date_field_u)
					$options['orderby'][ $sort_date_field_u ] = 'ASC';
			else
				$options['orderby'][ $sort_date_field ] = 'ASC';
		}
		
		$option_ajax = (bool)$options['ajax'];
		
		$use_weeks = false;
		if( $use_weeks ){
			$weeks = self::get_comments_weeks( $page, $options );
			if(isset($options['months']) && ($options['months'] !== ANTERIORITY_ALL) && ($options['months'] > 0) && count($weeks) > $options['months']*4)
				$weeks = array_slice($weeks, 0, $options['months'] * 4, true);
			elseif(isset($options['weeks']) && $options['weeks'] > 0 && count($weeks) > $options['weeks'])
				$weeks = array_slice($weeks, 0, $options['weeks'], true);
			elseif(isset($options['days']) && $options['days'] > 0 && count($weeks) > 1)
				$weeks = array_slice($weeks, 0, 1, true);
			elseif(isset($options['hours']) && $options['hours'] > 0 && count($weeks) > 1)
				$weeks = array_slice($weeks, 0, 1, true);
			
			$messages_count = 0;
			$weeks_count = 0;
			foreach($weeks as $week => $week_messages_count) {
				$messages_count += $week_messages_count;
				$weeks_count++;
				if($messages_count >= $options['max_messages']){
					break;
				}
			}
			
			if( $messages_count === 0)
				return false;
		}
		
		$html = sprintf('<div class="agdp-agdpforummsgs agdp-agdpforummsgs-%s">', $options['mode']);
		
		if( $add_summary = empty( $options['summary'] ) ? false : $options['summary'] )
			unset($options['summary']);
		
		if( ! $use_weeks ){
			$all_messages = $messages = self::get_period_comments( null, null, $options );
			if( ! $messages )
				return false;
			
			$html .= '<ul>';
			$html .= self::get_comments_list_html( $options, $messages );
			$html .= '</ul>';
		}
		else {
			
			$not_empty_week_index = 0;
			$messages_count = 0;
			
			$all_messages = [];
			
			$html .= '<ul>';
			
			foreach($weeks as $week => $week_messages_count) {
				
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
				
				if( $for_email )
					$html .= sprintf(
						'<li><div class="week-title toggle-trigger active"></div><ul id="week-%s" class="agdpforummsgs-week toggle-container">'
						, $week
					);
				else
					$html .= sprintf(
						'<li><div class="week-title toggle-trigger %s %s">%s <span class="nb-items">(%d)</span></div>
						<ul id="week-%s" class="agdpforummsgs-week toggle-container">'
						, $week_messages_count === 0 ? 'no-items' : ''
						, $week_messages_count ? 'active' : ''
						, $week_label
						, $week_messages_count
						, $week
					);
				if( $week_messages_count){
					$messages = self::get_week_comments( $week, $options );
					$all_messages = array_merge( $all_messages, $messages );
					$html .= self::get_week_comments_list_html( $week, $options, $messages );
				}
			
				$html .= '</ul></li>';
				
				if($week_messages_count > 0)
					$not_empty_week_index++;
				$messages_count += $week_messages_count;
			}
			
			$html .= '</ul>';
		}
		
		$html .= '</div>' . $content;
		
		//summary
		if( $add_summary && count($all_messages) > 1 ){
			$options['summary'] = $add_summary;
			$summary = sprintf('<div class="agdp-agdpforummsgs agdp-agdpforummsgs-summary agdp-agdpforummsgs-%s">', $options['mode']);
			$summary .= sprintf('<ul>Au sommaire, %d message%s :', count($all_messages), count($all_messages) > 1 ? 's' : '' );
			foreach($all_messages as $message){
				$summary .= sprintf(
					'<li>%s</li>'
					, self::get_list_item_html( $message, $options )
				);
			}
			$summary .= '</ul></div>' ;
			$html = $summary . $html;
		}
		
		return $html;
	}
	
	/**
	* Affecte l'option d'anteriorité
	*
	*/
	public static function filter_anteriority_option($options, $anteriority = false, $newsletter = false){
		return Agdp_Newsletter::filter_anteriority_option($options, $anteriority
			, ANTERIORITY_ONEDAY
			, $newsletter
		);
	}
	
	/**
	* Rendu Html des messages destinés au corps d'un email (newsletter)
	*
	*/
	public static function get_list_for_email($mailbox, $page, $content = '', $options = false){
		$page = Agdp_Mailbox::get_forum_page($page);
		if( $mailbox
		|| ($page && ( $mailbox = Agdp_Mailbox::get_mailbox_of_page($page) ))){
			$synchronize = Agdp_Mailbox::synchronize($mailbox);
			if( $synchronize === false || is_a($synchronize, 'Exception')){
				debug_log(__CLASS__.'::get_list_for_email init_page, synchronization failed', $synchronize);
			}
		}
		if(!isset($options) || !is_array($options))
			$options = array();
		$options = array_merge(
			array(
				'ajax' => false,
				'mode' => 'email',
			), self::filter_anteriority_option($options))
		;
		
		// Limite les messages à ceux publiés depuis le dernier envoi aux destinataires
		if( ( $newsletter = Agdp_Newsletter::is_sending_email() )
		 && ( $newsletter_to_emails = Agdp_Newsletter::is_sending_email_to() )
		){
			//Jamais plus d'un mois
			$min_date = Agdp_Newsletter::get_anteriority_date( $newsletter, date('Y-m-d',strtotime("-1 Month")) );
			$since_date = Agdp_Newsletter::get_user_mailing_date( $newsletter_to_emails, $newsletter, $min_date );
			
			if( $since_date )
				$options['since'] = $since_date;
		}
		
		// css
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
.agdp-agdpforummsgs-email .week-title {
	margin-top: 1em;
	margin-bottom: 1em;
	font-size: larger;
	font-weight: bold;
	text-decoration: underline;
	text-transform: uppercase;
} 
.agdp-agdpforummsgs-email .agdpcomment .dates {
	font-size: larger;
	font-weight: bold;
} 
.agdp-agdpforummsgs-email a-li a-li {
	margin-left: 1em;
	padding-top: 2em;
}
.agdp-agdpforummsgs-email div.titre {
	font-weight: bold;
}
.agdp-agdpforummsgs-email i {
	font-style: normal;
}
.created-since {
	font-size: smaller;
	font-style: italic;
}

.agdp-agdpforummsgs-summary  {
	border-bottom: gray 1px solid;
	margin-bottom: 2em;
	margin-top: 0.5em;
} 
.agdp-agdpforummsgs-summary .a-li {
	font-size: smaller;
} 
.footer {
	margin-bottom: 2em;
}
div:not(.has-children) > .footer {
	border-bottom: solid gray 2px;
	margin-bottom: 1em;
}
.a-ul img {
  max-width: 200px;
}
.a-ul .a-ul {
  margin-left: 2em;
}
'
			. '</style>';
		$html = self::get_list_html($mailbox, $page, $content, $options );
		
		if( ! $html ){
			if ( Agdp_Newsletter::is_sending_email() )
				Agdp_Newsletter::content_is_empty( true );
			return false;
		}
		
		$html = $css . $html;

		foreach([
			'agdp-agdpforummsgs'=> 'afmsg'
			, 'agdpforummsgs'=> 'fmsg'
			, 'agdpcomment-'=> 'agc-'
			, 'agdpcomment '=> 'agc '
			, 'comment-edit'=> 'c-e '
			, 'created-since'=> 'cr-s '
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
			'/\sagdpcomment="\{[^\}]*\}"/' => '',
			'/\sid="\w*"/' => '',
			'/([\}\>\;]\s)\s+/m' => '$1'
			] as $search=>$replace)
			$html = preg_replace($search, $replace, $html);
		return $html;
	}
	
		
	/**
	* Rendu Html des messages d'un mois sous forme de liste
	*/
	public static function get_week_comments_list_html($week, $options = false, $messages = false){
		
		if( ! $messages )
			$messages = self::get_week_comments($week, $options);
		return self::get_comments_list_html($options = false, $messages = false);
	}
		
	/**
	* Rendu Html des messages sous forme de liste
	*/
	public static function get_comments_list_html($options = false, $messages = false){
		
		if( ! $messages )
			return false;
		
		if(is_wp_error( $messages)){
			$html = sprintf('<p class="alerte no-messages">%s</p>%s', __('Erreur lors de la recherche de messages.', AGDP_TAG), var_export($messages, true));
		}
		elseif($messages){
			$html = '';
			if(count($messages) === 0){
				$html .= sprintf('<p class="alerte no-messages">%s (%s)</p>', __('Aucun message trouvé', AGDP_TAG), $week);
			}
			else {
				$post_ids = [];
				foreach($messages as $message){
					if( ! in_array( $message->comment_post_ID, $post_ids ) )
						$post_ids[] = $message->comment_post_ID;
				}
				if( count($post_ids) <= 1 )
					$post_ids = false;
					
				$post_id = false;
				$comment_parent_id = false;
				foreach($messages as $message){
					if( $post_ids
					&& $post_id !== $message->comment_post_ID ){
						$post_id = $message->comment_post_ID;
						$comment_parent_id = false;
						$forum = get_post( $post_id );
						$html .= '<h4>' . $forum->post_title . '</h4>';
					}
					if( $message->comment_parent
					&& $comment_parent_id !== $message->comment_parent ){
						$comment_parent_id = $message->comment_parent;
						$forum = get_post( $post_id );
						$html .= '<h4>' . $forum->post_title . '</h4>';
					}
					$html .= '<li>' . self::get_list_item_html($message, $options) . '</li>';
				}
				
				//Ce n'est plus nécessaire, les mois sont chargés complètement
				//TODO comment_per_page
				if(count($messages) == self::$default_comments_per_page){
					$html .= sprintf('<li class="show-more"><h3 class="agdpevent toggle-trigger" ajax="show-more">%s</h3></li>'
						, __('Afficher plus de résultats', AGDP_TAG));
				}
			}
		}
		else {
			$html = sprintf('<p class="alerte no-messages">%s (%s)</p>', __('Aucun message trouvé', AGDP_TAG), $week);
		}
			
		return $html;
	}
	
	/**
	 * get_list_item_html
	 */
	public static function get_list_item_html($comment, $options){
		$email_mode = is_array($options) && isset($options['mode']) && $options['mode'] == 'email';
		$summary_dest = is_array($options) && isset($options['summary']) && $options['summary'];
		$hide_author_email = /* $email_mode &&  */is_array($options) && isset($options['hide_author_email']) && $options['hide_author_email'];
			
		$exists_for_children = ! empty( $comment->child_needs ); //ce commentaire n'est là que pour ses enfants
		
		$date_debut = $comment->comment_date;
		
		$url = get_comment_link( $comment );
		$html = '';
		
		if( ! $email_mode )
			$html .= sprintf(
					'<div class="show-comment"><a href="%s">%s</a></div>'
				, $url
				, Agdp::icon('media-default')
			);
			
		$html .= sprintf('<div id="fmsgid%d" class="agdpcomment toggle-trigger" agdpcomment="%s">'
			, $comment->comment_ID
			, esc_attr( json_encode(['id'=> $comment->comment_ID, 'date' => $date_debut]) )
		);
		
		if(mysql2date( 'j', $date_debut ) === '1')
			$format_date_debut = 'l j\e\r M Y';
		else
			$format_date_debut = 'l j M Y';
		$heure_debut = date('H:i', strtotime($date_debut));
		if( $heure_debut === '00:00' )
			$heure_debut = '';
		$title = get_comment_meta($comment->comment_ID, 'title', true);
		$html .= sprintf(
				'<div class="date">%s%s</div>'
				.'<div class="titre">%s</div>'
			.''
			, str_replace(' mar ', ' mars ', strtolower(mysql2date( $format_date_debut, $date_debut)))
			, $heure_debut ? ' à ' . $heure_debut : ''
			, htmlentities($title));
		
		$children = $comment->get_children();
		
		/* TODO il faut compter les descendants en profondeur et ! exists_for_children
		if( $summary_dest && $children ){
			$s = count($children) > 1 ? 's' : '';
			$html .= sprintf('<div class="children">&gt; %d réponse%s récente%s</div>', count($children), $s, $s);
		} */
		
		$html .= '</div>';
		
		if( $summary_dest ){
			return $html;
		}
		
		$html .= sprintf('<div class="toggle-container %s">'
			, count($children) > 0 ? 'has-children' : ''
		);
		
		if( ! $exists_for_children ){
				
			$value = $comment->comment_content;
			if($value){
				$value = preg_replace('/\n[\s\n]+/', "\n", $value);
				$more = $end = '';
				//TODO Tronquer le contenu provoque des erreurs html (balises non fermées)
				/* $max_len = 1500;
				if( strlen($value) > $max_len ){
					
					//bugg sic
					while( strlen(substr($value, 0, $max_len)) === 0 && $max_len < strlen($value)-8)
						$max_len += 7;
					
					$end = substr($value, $max_len);
					
					$value = substr($value, 0, $max_len);
					
					$pos = strpos( $value, '</', -1);
					if( $pos === false )
						$end = '';
					else {
						// Déplace </div dans $end
						$end = substr( $value, $pos ) . $end;
						$value = substr( $value, 0, $pos );

						//Fermeture de la balise fermante
						$pos = strpos( $end, '>');
						if( $pos === false )
							$end = '?!...'; //qq chose comme </coucou puis plus rien
						else {
							// Déplace </div> dans $value
							$value .= substr( $end, 0, $pos );
							$end = substr( $end, $pos );
							
							debug_log( __FUNCTION__, $end );
							foreach( ['p', 'div', 'pre' ] as $tag) {
								$pattern = sprintf('/\<%s[\s\S]+\<\/%s\>/', $tag);
								debug_log( $pattern);
								$end = preg_replace($pattern, '', $end );
							}
							debug_log( $end );
						}
						
					}
					
					$more = sprintf('<a href="%s">... <b><i>[continuez la lecture sur le site]</i></b></a>', $url);
					
				} */
				if( $email_mode )
					$html .= sprintf('<pre>%s%s%s</pre>', $value, $more, $end );
				else
					$html .= sprintf('<pre>%s%s%s</pre>', htmlentities($value), $more, $end );
			}
			
			$html .= Agdp_Comment::get_attachments_links($comment);
		}
		
		$show_author_email = Agdp_Forum::get_property('comment_author_email');
		if( $show_author_email === 'M' ){
			//TODO s'assurer que les destinataires sont bien tous membres
		}
		
		if( ! $exists_for_children ){
			$value = $comment->comment_author;
			if($value){
				if( $show_author_email
				 && $comment->comment_author_email){
					if($comment->comment_author != $comment->comment_author_email)
						$value .= sprintf(' <%s>', $comment->comment_author_email);
					$html .= sprintf('<div>Répondre à <a href="mailto:%s?body=Bonjour,&subject=%s">%s</a></div>'
						, $comment->comment_author_email
						, str_replace('&', '%20', str_replace(' ', '%20', str_replace("'", '%27', 'Re: ' . $title)))
						, htmlentities($value) );
				}
				else
					$html .= sprintf('<div>Publié par : %s</div>',  htmlentities($value) );
			}
		
			$html .= date_diff_text($comment->comment_date, true, '<div class="created-since">', '</div>');
	
			$html .= '<div class="footer">';
					
				$html .= '<table><tbody><tr>';
				
				if( ! $email_mode )
					$html .= '<td class="trigger-collapser"><a href="#replier">'
						.Agdp::icon('arrow-up-alt2')
						.'</a></td>';
					
				$html .= sprintf(
					'<td class="comment-edit"><a href="%s">'
						.'Afficher le message' // (et vérifier qu\'il est toujours d\'actualité)
						. ($email_mode  ? '' : Agdp::icon('media-default'))
						.'</a>'
					.'</td>'
					, $url);
			
				$html .= '</tr></tbody></table>';
				
			$html .= '</div>';
		}
		
		if( $children = $comment->get_children() ){
			// $s = count($children) > 1 ? 's' : '';
			// $html .= sprintf('<ul><i>Réponse%s récente%s</h5>', $s, $s);
			$html .= '<ul>';
			foreach( $children as $child ){
				$html .= '<li>' . self::get_list_item_html($child, $options) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '</div>';
		
		return $html;
	}
	
}?>
