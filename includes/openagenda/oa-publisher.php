<?php
namespace OpenAgenda;

/**
 * Class for handling publish request to OpenAgenda API.
 */
class Publisher {
	
	protected $events;
	
	protected $secret_key;
	protected $openagenda_uid;
	protected $accessToken;
	
	public function __construct($openagenda_uid = false, $secret_key = false){
		$this->openagenda_uid = $openagenda_uid;
		$this->secret_key = $secret_key;
	}
	
	protected function get_secret_key(){
		return $this->secret_key;
	}
	public function set_secret_key( $secret_key ){
		$this->secret_key = $secret_key;
	}
	
	protected function get_openagenda_uid(){
		return $this->openagenda_uid;
	}
	public function set_openagenda_uid( $openagenda_uid ){
		$this->openagenda_uid = $openagenda_uid;
	}
	
	public function get_events(){
		if( ! $this->events )
			$this->events = [];
		return $this->events;
	}
	
	public function add_event( $event = false ){
		if( $event === false )
			$event = new \StdClass();
		$this->get_events();
		$this->events[] = $event;
		return $event;
	}
	
	/**
	 * publish
	 */
	public function publish(){
		// debug_log( __FUNCTION__, $this->get_events());
		
		foreach( $this->get_events() as $vevent ){
			$event = $this->publish_event( $vevent );
			// if( ! empty($event['uid']) )
				// $vevent->uid = $event['uid'];
		}
		
		return true;
	}
	
	/**
	 * publish
	 */
	public function delete(){
		// debug_log( __FUNCTION__, $this->get_events());
		
		foreach( $this->get_events() as $vevent ){
			$event = $this->delete_event( $vevent );
			// if( ! empty($event['uid']) )
				// $vevent->uid = $event['uid'];
		}
		
		return true;
	}
	
	/**
	 * set_curl_options
	 */
	private function set_curl_options( $ch, $set_authorization = false ){
		$origin_url = get_site_url();
		if( strpos( $origin_url, 'http:') !== false )
			$origin_url = str_replace( 'http:', 'https:', $origin_url);
		$headers = [
			'Accept: */*',
			'Accept-Encoding: deflate',
			'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
			'Cache-Control: no-cache',
			'Host: api.openagenda.com',
			'Origin: ' . $origin_url,
			'Referer: ' . $origin_url,
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
		];
		
		if($set_authorization){
			$accessToken = $this->get_access_token();
			$headers[] = 'access-token: ' . $accessToken;
			$headers[] = 'nonce: ' . $accessToken;
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
	}

	/**
	 * get_access_token
	 */
	private function get_access_token(){
		if( $this->accessToken )
			return $this->accessToken;
		
		$ch = curl_init('https://api.openagenda.com/v2/requestAccessToken'); 
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
		  'grant_type' => 'authorization_code',
		  'code' => $this->get_secret_key()
		));
		$this->set_curl_options( $ch );

		$received_content = curl_exec($ch);
				
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
		  $data = json_decode($received_content, true);
		  $this->accessToken = $data['access_token'];
		  return $this->accessToken;
		  // var_dump($data, $this->accessToken, $received_content);
		}
		else {
			var_dump($ch, curl_getinfo($ch, CURLINFO_HTTP_CODE), $received_content);
		}
	}
	
	/**
	 * publish_event : create or update
	 * Parameter $event : StdClass
	 */
	private function publish_event( $event ){
		$event = $this->sanitize_event( $event );
		// debug_log(__FUNCTION__, $event);
		$agendaUid = $this->openagenda_uid;
		if( $create_new = empty($event->uid) )
			$oa_url = "https://api.openagenda.com/v2/agendas/{$agendaUid}/events";
		else {
			$event_uid = $event->uid;
			$oa_url = "https://api.openagenda.com/v2/agendas/{$agendaUid}/events/{$event_uid}";
		}
		$ch = curl_init( $oa_url );
		
		$this->set_curl_options( $ch, true );
		
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
		  'data' => json_encode( $event )
		));

		$received_content = curl_exec($ch);
				
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
		  // var_dump(__FUNCTION__ . ' received_content', $received_content);
		  $data = json_decode($received_content, true);
		  // debug_log( __FUNCTION__, $data );
		  foreach( $data['event'] as $prop => $val )
			$event->$prop = $val;
			
		  return $event;
		  // var_dump($data, $received_content);
		}
		else {
			debug_log( __FUNCTION__, $oa_url, curl_getinfo($ch, CURLINFO_HTTP_CODE), $received_content );
			// var_dump($ch, curl_getinfo($ch, CURLINFO_HTTP_CODE), $received_content);
		}
	}
	
	/**
	 * delete_event
	 */
	private function delete_event( $event ){
		$agendaUid = $this->openagenda_uid;
		if( empty($event->uid) )
			return true;
		
		$event_uid = $event->uid;
		$oa_url = "https://api.openagenda.com/v2/agendas/{$agendaUid}/events/{$event_uid}";

		$ch = curl_init( $oa_url );
		
		$this->set_curl_options( $ch, true );
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		// curl_setopt($ch, CURLOPT_POSTFIELDS, array(
		  // 'data' => json_encode( $event )
		// ));

		$received_content = curl_exec($ch);
				
		if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
		  // var_dump(__FUNCTION__ . ' received_content', $received_content);
		  $data = json_decode($received_content, true);
		  // debug_log( __FUNCTION__, $data );
			
		  return $event;
		  // var_dump($data, $received_content);
		}
		else {
			debug_log( __FUNCTION__, $oa_url, curl_getinfo($ch, CURLINFO_HTTP_CODE), $received_content );
			// var_dump($ch, curl_getinfo($ch, CURLINFO_HTTP_CODE), $received_content);
		}
	}

	/**
	 * sanitize_event
	 * Parameter $event : StdClass
	 */
	private function sanitize_event( $event ){
		//texts length
		foreach( $event->title as $lang => $value )
			$event->title[ $lang ] = substr( $value, 0, 140 );
		foreach( $event->description as $lang => $value )
			$event->description[ $lang ] = substr( $value, 0, 200 );
		foreach( $event->longDescription as $lang => $value )
			$event->longDescription[ $lang ] = substr( $value, 0, 10000 );
		
		if( empty($event->locationUid) )
			$event->attendanceMode = 2; //(online)
		elseif( is_array($event->locationUid) ){
			foreach($event->locationUid as $openagenda_uid => $locationUid){
				if( $openagenda_uid == $this->openagenda_uid ){
					$event->locationUid = $locationUid;
					break;
				}
			}
			if( is_array($event->locationUid) ){
				$event->attendanceMode = 2; //(online)
				$event->locationUid = '';
			}
		}
		
		//Dates avec interval de 24H max
		// foreach( $event->timings as $index => $timing ){
		for( $index = 0; $index < count($event->timings); $index++){
			$timing = $event->timings[ $index ];
			
			$timing_begin = $timing['begin'];
			$timing_end = $timing['end'];
			$date_begin = new \DateTime( $timing_begin);
				$hour_begin = $date_begin->format('H') * 3600 + $date_begin->format('i') * 60 + $date_begin->format('s') * 1 - $date_begin->getOffset();
				$has_hour_begin = $hour_begin !== 0;
			$date_end = new \DateTime( $timing_end );
				$hour_end = $date_end->format('H') * 3600 + $date_end->format('i') * 60 + $date_end->format('s') * 1 - $date_end->getOffset();
				$has_hour_end = $hour_end !== 0;
				
		// debug_log( __FUNCTION__, $has_hour_begin, $hour_end, ! $has_hour_end );
			
			if( $date_end <= $date_begin ) {
				$date_end = $date_begin->setTime(23,59,59); //TODO tz offset
				$event->timings[$index]['end'] = $this->toUTCDateTime( $date_end );
			}
			elseif( $has_hour_begin && ! $has_hour_end )
				$date_end = $date_end->setTime(23,59,59); //TODO tz offset
			
			$date_diff = date_diff($date_end, $date_begin, false);
			$h = ($date_diff->y * 365 + $date_diff->m * 30 + $date_diff->d) * 24
				+ $date_diff->h + $date_diff->i / 24 + $date_diff->s / 24 / 60;
			if( $h > 24 ){
				$part_counter = 0;
				do {
					if( $h <= 24 ){
						$date_end = $timing_end;
					}
					else
						$date_end = $date_begin->add( new \DateInterval('P1D') );
					if( $part_counter === 0)
						$event->timings[$index]['end'] = $this->toUTCDateTime( $date_end );
					else
						$event->timings[] = [
							'begin' => $timing_begin,
							'end' => $this->toUTCDateTime( $date_end ),
						];
					$timing_begin = $this->toUTCDateTime( $date_end );
					$date_begin = new \DateTime( $timing_begin );
					
					$h -= 24;
					$part_counter++;
				} while( $h > 0 );
			}
		}
		
		// foreach( $event->timings as $index => $timing ){
			// foreach( ['begin', 'end'] as $field )
				// $event->timings[$index][$field] = preg_replace( '/\+\d\d\:\d\d$/', '+00:00', $timing[$field] );
		// }
		
		// debug_log( __FUNCTION__, $event);
		// die();
		return $event;
	}


	/**
	 * Convert local date-time to UTC date-time
	 * 
	 * @param string $sqldate SQL date-time string
	 *
	 * @return string SQL date-time string
	 */
	public function toUTCDateTime($sqldate, $debug = false){
		$timezone = wp_timezone();
		if( is_a( $sqldate, 'DateTime' ))
			$sqldate = $sqldate->getTimestamp();
		else {
			// if(  $debug ){
				// debug_log( $sqldate, wp_date( DATE_ATOM, strtotime( $sqldate ) ));
				// die();
			// }
			$dt = new \DateTime($sqldate, $timezone);
			// debug_log( __FUNCTION__, $dt->getOffset());
			$sqldate = strtotime( $sqldate );
			$sqldate -= $dt->getOffset();
		}
		return wp_date( DATE_ATOM, $sqldate, $timezone );
	}

	/**
	 * Convert local date to UTC date at 23:59
	 * 
	 * @param string $sqldate SQL date-time string
	 *
	 * @return string SQL date-time string
	 */
	public function toUTCMidnight($sqldate){
		if( is_a( $sqldate, 'DateTime' ))
			$date = $sqldate;
		else
			$date = new \DateTime(wp_date( DATE_ATOM, strtotime( $sqldate ), new \DateTimeZone( 'UTC' ) ));
		$date = $date->setTime( 23, 59 );
		return $date->format( DATE_ATOM );
	}
}
?>