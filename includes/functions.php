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

function debug_callback(){
	var_dump(func_get_args());
}

function debug_log_clear(){
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if(file_exists($log_file))
		file_put_contents($log_file, '');
}
function debug_log(...$messages){
	$log_file = WP_CONTENT_DIR . '/debug.log';
	
	file_put_contents($log_file, '[' . wp_date("Y-m-d H:i:s") . '] ', FILE_APPEND);
	foreach($messages as $msg)
		file_put_contents($log_file, var_export($msg, true).PHP_EOL, FILE_APPEND);
}