<?php

/**
 * debug_log
 * works with WP_DEBUG
 **/
function debug_log_file(){
	return 'debug.log';
}
function debug_log_clear(...$messages){
	if( ! Agdp::debug_log_enable() )
		return;
	$log_file = debug_log_file();
	if(file_exists($log_file))
		file_put_contents($log_file, '');
	if(count($messages))
		debug_log(...$messages);
}
function debug_log_callstack(...$messages){
	$backtrace = debug_backtrace();
	// $backtrace = array_slice($backtrace, 1);
	$backtrace[0] = $_SERVER['REQUEST_URI'];
	for($i = 1; $i<count($backtrace);$i++){
		if(isset($backtrace[$i]['object']))
			$backtrace[$i]['object'] = get_class($backtrace[$i]['object']);
		$backtrace[$i] = preg_replace('/\r?\n\s*/', ' ', var_export($backtrace[$i], true));
	}
	array_push($messages, '[callstack]', ...$backtrace);
	debug_log(...$messages);
}
function debug_log(...$messages){
	
	$data = '[' . date("Y-m-d H:i:s") . '] ';
	
	foreach($messages as $msg)
		if(is_string($msg))
			$data .= $msg.PHP_EOL;
		else
			$data .= var_export($msg, true).PHP_EOL;
	file_put_contents(debug_log_file(), $data, FILE_APPEND);
}

debug_log($_GET);


debug_log($_POST);


debug_log($_REQUEST);



?>