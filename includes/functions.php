<?php
/**
 * Returns html string like <a href="mailto:...
 */
function make_mailto($email, $title = false){
	if(!is_email($email) && is_email($title))
		$email = $title;
	$email = antispambot(sanitize_email($email));
	return sprintf('<a href="mailto:%s">%s</a>', $email, $title ? $title : $email);
}

/**
 * Returns an array of array of emails extracted.
 * [ [email] => ['source' => '[header]: [name]<[user]@[domain]>', header, name, user, domain] ]
 */
function parse_emails ($text){
	$emails = array();
	if(!$text || !is_string($text)) return $emails;
	//Attention, tolerate spaces arround @
	$result = preg_match_all('/\s*((?P<header>[\w-]+)\s*\:\s*)?((?P<name>[^<,;\n\r]+)[<])?\s*(?P<email>(?P<user>[\.\w-]+)\s*@\s*(?P<domain>[\.\w-]+\.[\w-]+))[>]?[\s,;]*/i', $text, $output);
	for ($i=0; $i < count($output[0]); $i++) { 
		$output['email'][$i] = preg_replace('/\s*@\s*/', '@', $output['email'][$i]);
		$emails[] = array(
			'source' => $output[0][$i],
			'header' => $output['header'][$i],
			'name' => $output['name'][$i] ? $output['name'][$i] : $output['email'][$i],
			'email' => strtolower($output['email'][$i]),
			'user' => strtolower($output['user'][$i]),
			'domain' => strtolower($output['domain'][$i]),
		);
	}
	//var_dump($emails);
	return $emails;
}

function base64_decode_if_needed($data){
	//'=?UTF-8?B?' . base64_encode($subject). '?='
	if(str_starts_with($data, '=?UTF-8?B?')){
		$regexp = '/^' . preg_quote('=?UTF-8?B?') . '([\s\S]*)' . preg_quote('?=') . '$/';
	
		return base64_decode( preg_replace( $regexp, '$1', $data) );
	}
	return $data;
}
function is_base64_encoded($data){
	return str_starts_with($data, '=?UTF-8?');
	// if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
		// return TRUE;
	// } else {
		// return FALSE;
	// }
}

function debug_callback(){
	var_dump(func_get_args());
}

/**
 * debug_log
 * works with WP_DEBUG
 **/
function debug_log_file(){
	return WP_CONTENT_DIR . '/debug.log';
}
function debug_log_clear(...$messages){
	if( ! WP_DEBUG )
		return;
	$log_file = debug_log_file();
	if(file_exists($log_file))
		file_put_contents($log_file, '');
	if(count($messages))
		debug_log(...$messages);
}
function debug_log(...$messages){
	if( ! WP_DEBUG )
		return;
	$data = '[' . wp_date("Y-m-d H:i:s") . '] ';
	foreach($messages as $msg)
		if(is_string($msg))
			$data .= $msg.PHP_EOL;
		else
			$data .= var_export($msg, true).PHP_EOL;
	file_put_contents(debug_log_file(), $data, FILE_APPEND);
}