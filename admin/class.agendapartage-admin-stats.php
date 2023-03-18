<?php 
/**
 * AgendaPartage Admin -> Statistiques
 * 
 */
class AgendaPartage_Admin_Stats {

	
	public static function maillog_stats() {
		?><ul class="agdpmaillog-stats"><?php
		foreach(array('' => 'Aujourd\'hui', '- 7 day' => 'Sur 7 jours') as $timelaps => $time_name){
			
			?><li>
			<header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title">%s</h3>', $time_name);
			?></header>
			<table><tr><?php
			
			foreach(['publish' => 'Succès', 'draft' => 'En erreur', 'pending' => 'En cours !'] as $post_status => $status_name){
				$agdpmaillogs = new WP_Query( array( 
					'post_type' => AgendaPartage_Maillog::post_type,
					'fields' => 'ids',
					'post_status' => $post_status,
					'post_date' => array(
						'value' => strtotime(date('Y-m-d') . ' ' . $timelaps),
						'compare' => '>='
					),
				));
				if( ! is_a($agdpmaillogs, 'WP_Query'))
					continue;
				//;
				echo '<td>';
				echo sprintf('<h3 class="entry-title">%s</h3>%d email(s)', $status_name, $agdpmaillogs->found_posts);
				?></header><?php
				echo '</td>';
				
			}
		
			?></tr></table></li><?php
		}
		?></ul><?php
	}

	
	public static function newsletter_stats() {
		?><ul class="agdpmaillog-stats"><?php
		foreach(array('' => 'Aujourd\'hui', '- 7 day' => 'Sur 7 jours') as $timelaps => $time_name){
			
			?><li>
			<header class="entry-header"><?php 
				echo sprintf('<h3 class="entry-title">%s</h3>', $time_name);
			?></header>
			<table><tr><?php
			
			foreach(['publish' => 'Succès', 'draft' => 'En erreur', 'pending' => 'En cours !'] as $post_status => $status_name){
				$agdpmaillogs = new WP_Query( array( 
					'post_type' => AgendaPartage_Maillog::post_type,
					'fields' => 'ids',
					'post_status' => $post_status,
					'post_date' => array(
						'value' => strtotime(date('Y-m-d') . ' ' . $timelaps),
						'compare' => '>='
					),
				));
				if( ! is_a($agdpmaillogs, 'WP_Query'))
					continue;
				//;
				echo '<td>';
				echo sprintf('<h3 class="entry-title">%s</h3>%d email(s)', $status_name, $agdpmaillogs->found_posts);
				?></header><?php
				echo '</td>';
				
			}
		
			?></tr></table></li><?php
		}
		?></ul><?php
	}
}
?>