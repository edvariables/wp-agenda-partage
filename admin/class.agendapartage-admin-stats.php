<?php 
/**
 * AgendaPartage Admin -> Statistiques
 * 
 */
class AgendaPartage_Admin_Stats {

	
	public static function get_stats_result() {
		ob_start(); // Start output buffering

		self::stats_css();
		self::agdpevents_stats();
		self::covoiturages_stats();
		self::newsletter_stats();
		self::maillog_stats();
		
		$html = ob_get_contents(); 

		ob_end_clean(); 

		return $html;
	}
	
	public static function stats_css() {
		?><style>
		ul.agdp-stats { list-style: none; }
		.agdp-stats h4 { margin-top: 1em; }
		.agdp-stats td { padding-right: 3em; padding-top: 0em; }
		.agdp-stats .entry-header { padding: 0em !important; }
		</style><?php
	}
	
	public static function maillog_stats() {
		?><ul class="agdp-stats">
		<header class="entry-header"><?php 
			echo sprintf('<h3 class="entry-title">Trace(s) mail</h3>');
		?></header><?php
		foreach(array('' => 'Aujourd\'hui', '- 7 day' => 'Sur 7 jours') as $timelaps => $time_name){
			
			?><li>
			<header class="entry-header"><?php 
				echo sprintf('<h4 class="entry-title">%s</h4>', $time_name);
			?></header>
			<table><tr><?php
			
			foreach(['publish' => 'Succès', 'draft' => 'En erreur', 'pending' => 'Spam'] as $post_status => $status_name){
				$agdpmaillogs = new WP_Query( array( 
					'post_type' => AgendaPartage_Maillog::post_type,
					'fields' => 'ids',
					'post_status' => $post_status,
					'date_query' => array(
						'column'  => 'post_date',
						'after' => date('Y-m-d', strtotime(date('Y-m-d') . ' ' . $timelaps)),
						'inclusive' => true
					),
					'nopaging' => true,
				));
				if( ! is_a($agdpmaillogs, 'WP_Query'))
					continue;
				//;
				echo '<td>';
				echo sprintf('<h5 class="entry-title">%s</h5>%d email(s)', $status_name, $agdpmaillogs->found_posts);
				?></header><?php
				echo '</td>';
				
			}
		
			?></tr></table></li><?php
		}
		?></ul><hr>
		<?php
	}

	public static function newsletter_stats() {
		$today = strtotime(wp_date('Y-m-d'));
		
		global $wpdb;
		$blog_prefix = $wpdb->get_blog_prefix();
		$user_prefix = $wpdb->get_blog_prefix( 1 );
		$newsletter = AgendaPartage_Newsletter::get_newsletter( true );
		$mailing_meta_key = AgendaPartage_Newsletter::get_mailing_meta_key($newsletter);
		
						
		/** Historique **/
		$two_months_before_mysql = wp_date('Y-m-d', strtotime(wp_date('Y-m-01', $today) . ' - 2 month'));
		$sql = "SELECT mailing.meta_value AS mailing_date, COUNT(user.ID) AS count"
			. "\n FROM {$user_prefix}users user"
			// . "\n INNER JOIN {$user_prefix}usermeta usermetacap"
			// . "\n ON user.ID = usermetacap.user_id"
			// . "\n AND usermetacap.meta_key = '{$blog_prefix}capabilities'"
			// . "\n AND usermetacap.meta_value != 'a:0:{}'"
			. "\n INNER JOIN {$user_prefix}usermeta mailing"
				. "\n ON user.ID = mailing.user_id"
				. "\n AND mailing.meta_key = '{$mailing_meta_key}'"
				. "\n AND mailing.meta_value >= '{$two_months_before_mysql}'"
			. "\n GROUP BY mailing.meta_value"
			. "\n ORDER BY mailing.meta_value DESC";
		$dbresults = $wpdb->get_results($sql);
		$mailings = [];
		$users = 0;
		foreach($dbresults as $dbresult){
			$mailings[] = ['date' => $dbresult->mailing_date, 'count' => $dbresult->count];
			$users += $dbresult->count;
		}
		if( count($mailings) ){
			echo '<ul class="agdp-stats">';
			echo sprintf("<header class='entry-header'><h3>Envois de la lettre-info \"%s\" depuis le %s</u> : %s %s</h3></header>"
				, $newsletter->post_title
				, wp_date('d/m/Y', strtotime($two_months_before_mysql))
				, $users
				, $users ? ' destinataire(s)' : '');
			if( count($mailings) > 1)
				foreach($mailings as $data)
					echo sprintf("<li><h4>Lettre-info du %s : %d %s</h4>"
						, wp_date('d/m/Y', strtotime($data['date']))
						, $data['count']
						, $data['count'] ? ' destinataire(s)' : ''
						);
			echo '</ul>';
		}
		?></ul><hr>
		<?php
	}

	public static function agdpevents_stats() {
		return self::posts_stats(AgendaPartage_Evenement::post_type);
	}
	public static function covoiturages_stats() {
		return self::posts_stats(AgendaPartage_Covoiturage::post_type);
	}

	public static function posts_stats($post_type) {
		$post_type_labels = get_post_type_labels($post_type);
		?><ul class="agdp-stats">
		<header class="entry-header"><?php 
			echo sprintf('<h3 class="entry-title">%s</h3>', $post_type_labels['name']);
		?></header><?php
		foreach(array('' => 'Aujourd\'hui', '- 7 day' => 'Sur 7 jours') as $timelaps => $time_name){
			
			?><li>
			<header class="entry-header"><?php 
				echo sprintf('<h4 class="entry-title">%s</h4>', $time_name);
			?></header>
			<table><tr><?php
			
			foreach(['publish' => 'Publié', 'pending' => 'En attente'] as $post_status => $status_name){
				$posts = new WP_Query( array( 
					'post_type' => $post_type,
					'fields' => 'ids',
					'post_status' => $post_status,
					'date_query' => array(
						'column'  => 'post_date',
						'after' => date('Y-m-d', strtotime(date('Y-m-d') . ' ' . $timelaps)),
						'inclusive' => true
					),
					'nopaging' => true,
				));
				if( ! is_a($posts, 'WP_Query'))
					continue;
				//;
				$url = get_admin_url(null, sprintf('/edit.php?post_status=%s&post_type=%s', $post_status, $post_type));
				echo '<td>';
				echo sprintf('<h5 class="entry-title">%s</h5><a href="%s">%d %s(s)</a>', $status_name, $url, $posts->found_posts, strtolower( $post_type_labels['singular_name']));
				?></header><?php
				echo '</td>';
				
			}
		
			?></tr></table></li><?php
		}
		?></ul><hr>
		<?php
	}

	public static function agdpevents_stats_eventscounters() {
		return self::stats_postcounters(AgendaPartage_Evenement::post_type);
	}

	public static function covoiturages_stats_covoituragescounters() {
		return self::stats_postcounters(AgendaPartage_Covoiturage::post_type);
	}

	public static function stats_postcounters($post_type) {
		$sCounters = '';
		foreach(array('' => 'Aujourd\'hui', '- 7 day' => 'Sur 7 jours') as $timelaps => $time_name){
			foreach(['publish' => 'Publié', 'pending' => 'En attente'] as $post_status => $status_name){
				$agdpevents = new WP_Query( array( 
					'post_type' => $post_type,
					'fields' => 'ids',
					'post_status' => $post_status,
					'date_query' => array(
						'column'  => 'post_date',
						'after' => date('Y-m-d', strtotime(date('Y-m-d') . ' ' . $timelaps)),
						'inclusive' => true
					),
					'nopaging' => true,
				));
				if( ! is_a($agdpevents, 'WP_Query'))
					continue;
				if( strlen($sCounters) !== 0 )
					$sCounters .= '|';
				$sCounters .= $agdpevents->found_posts;
				
			}
		}
		return $sCounters;
	}
}
?>