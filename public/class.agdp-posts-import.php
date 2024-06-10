<?php

/**
 * AgendaPartage -> Posts abstract -> Import
 * Import de posts
 */
abstract class Agdp_Posts_Import {
	
	const post_type = false; //Must override
	
	
	public static function get_post_statuses(){
		$statuses = get_post_statuses();
		if( ! isset($statuses['trash']) )
			$statuses['trash'] = 'Corbeille';
		return $statuses;
	}
	
	/**
	* import_post_type_ics
	*/
	public static function import_post_type_ics($post_type, $file_name, $default_post_status = 'publish', $original_file_name = null){
		require_once( sprintf('%s/class.agdp-%ss-import.php', dirname(__FILE__), $post_type) );
		$posts_class = Agdp_Page::get_posts_class( $post_type );
		return ($posts_class .'_Import')::import_ics( $file_name, $default_post_status, $original_file_name);
	}
	
	/**
	 * get_vcalendar($file_name)
	 */
	public static function get_existing_post($vevent){
		if( ! empty($vevent['uid']) && $vevent['uid'] ){
			foreach( get_posts([
				'post_type' => static::post_type
				, 'post_status' => ['publish', 'pending', 'draft']
				, 'meta_key' => AGDP_IMPORT_UID
				, 'meta_value' => $vevent['uid']
				, 'meta_compare' => '='
				, 'numberposts' => 1
				]) as $post){
					return $post;
			}
			return false;
		}
	}
	
	/**
	 * get_vcalendar($file_name) for .ics file
	 */
	public static function get_vcalendar($file_name){
		require_once(AGDP_PLUGIN_DIR . "/includes/icalendar/zapcallib.php");	
		$ical= new ZCiCal(file_get_contents($file_name));
		$vcalendar = [];
		
		// debug_log($ical->tree->data);
		
		foreach($ical->tree->data as $key => $value){
			$key = strtolower($key);
			if(is_array($value)){
				$vcalendar[$key] = '';
				for($i = 0; $i < count($value); $i++){
					$p = $value[$i]->getParameters();
					if($vcalendar[$key])
						$vcalendar[$key] .= ',';
					$vcalendar[$key] .= $value[$i]->getValues();
				}
			} else {
				$vcalendar[$key] = $value->getValues();
			}
		}
		
		if( ! empty($vcalendar['x-wr-calname'])){
			if(empty($vcalendar['title']))
				$vcalendar['title'] = $vcalendar['x-wr-calname'];
		}
		
		if(empty($vcalendar['description']))
			$vcalendar['description'] = 'vcalendar_' . wp_date('Y-m-d H:i:s');
		if(empty($vcalendar['title']))
			$vcalendar['title'] = $vcalendar['description'];
		
		$vevents = [];
		if(isset($ical->tree->child)) {
			foreach($ical->tree->child as $node) {
				if($node->getName() == "VEVENT") {
					$vevent = [];
					foreach($node->data as $key => $value) {
						$key = strtolower($key);
						if(is_array($value)){
							if( ! isset($vevent[$key]) )
								$vevent[$key] = [];
							for($i = 0; $i < count($value); $i++) {
								if(is_array($value[$i])){
									array_walk_recursive( $value[$i], function(&$value, $value_key) use(&$vevent, $key){
										if(is_a($value, 'ZCiCalDataNode'))
											$vevent[$key][] = $value->value[0];
										else
											$vevent[$key][] = $value;
									});
								}
								else {
									$vevent[$key][] = $value[$i]->getValues();
									$p = $value[$i]->getParameters();
									if($p){
										if( ! isset($vevent[$key .'[parameters]']) )
											$vevent[$key .'[parameters]'] = [];
										$vevent[$key .'[parameters]'][] = $p;
									}
								}
							}
						} else {
							if( isset($vevent[$key]) ){
								if( ! is_array($vevent[$key])){
									$vevent[$key] = [$vevent[$key]];
									if(isset($vevent[$key .'[parameters]']))
										$vevent[$key .'[parameters]'] = [$vevent[$key .'[parameters]']];
								}
								$vevent[$key][] = $value->getValues();
							}
							else
								$vevent[$key] = $value->getValues();
							$p = $value->getParameters();
							if($p){
								if(!empty($vevent[$key .'[parameters]']) && is_array($vevent[$key .'[parameters]']))
									$vevent[$key .'[parameters]'][] = $p;
								else
									$vevent[$key .'[parameters]'] = $p;
							}
						}
					}
					//if no hour specified, dtend means the day before
					if(isset($vevent['dtend']) && $vevent['dtend']){
						if(strpos($vevent['dtstart'], 'T') === false
						&& strpos($vevent['dtend'], 'T') === false
						&& $vevent['dtend'] != $vevent['dtstart'])
							$vevent['dtend'] = date('Y-m-d', strtotime($vevent['dtend'] . ' - 1 day')); 
						$vevent['dtend'] = date('Y-m-d H:i:s', strtotime($vevent['dtend'])); 
					}
					$vevent['dtstart'] = date('Y-m-d H:i:s', strtotime($vevent['dtstart'])); 
					$vevents[] = $vevent;
				}
			}
		}
		
		$vcalendar['posts'] = $vevents;
		// debug_log($vcalendar);
		return $vcalendar;
	}
	/*
	**/
}
