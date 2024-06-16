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
	if( ! Agdp::debug_log_enable() )
		return;
	$data = '[' . wp_date("Y-m-d H:i:s") . '] ';
	if(is_multisite())
		$data = '['. get_bloginfo( 'name' ) .']'.$data;
	foreach($messages as $msg)
		if(is_string($msg))
			$data .= $msg.PHP_EOL;
		else
			$data .= var_export($msg, true).PHP_EOL;
	file_put_contents(debug_log_file(), $data, FILE_APPEND);
}


/**
 * Delete a directory and all files in it
 */

function rrmdir($src) {
    $dir = opendir($src);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if ( is_dir($full) ) {
                rrmdir($full);
            }
            else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

/**
 * Retourne les intervals entre deux dates
 */
function dateDiff($date1, $date2){
	$diff = abs($date1 - $date2); // abs pour avoir la valeur absolute, ainsi éviter d'avoir une différence négative
	$retour = array();
 
	$tmp = $diff;
	$retour['second'] = $tmp % 60;
 
	$tmp = floor( ($tmp - $retour['second']) /60 );
	$retour['minute'] = $tmp % 60;
 
	$tmp = floor( ($tmp - $retour['minute'])/60 );
	$retour['hour'] = $tmp % 24;
 
	$tmp = floor( ($tmp - $retour['hour'])  /24 );
	$retour['day'] = $tmp;
 
	return $retour;
}

/**
 * Retourne le texte littéral de l'intervals entre deux dates
 */
 function date_diff_text ($old_date, $intro = true, $before = '', $after = ''){
	if( is_string($old_date_str = $old_date)){
		$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $old_date, wp_timezone() );
		$old_date = $datetime->getTimestamp();
	}
	$now = time();
	
	if( $intro === true)
		$intro = 'il y a ';
	
	$laps = dateDiff($now, $old_date);
	
	if( $laps['day'] > 0 && $laps['hour'] > 12 ){
		$laps['day'] += 1;
	}
	if( $laps['day'] > 60 ){
		$laps['month'] = round( $laps['day'] / 30 );
	}
	
	if( ! empty($laps['month']) )
		$val = sprintf('%s%d mois', $intro, $laps['month']	 );
	elseif( $laps['day'] )
		$val = sprintf('%s%d jour%s', $intro, $laps['day'], $laps['day'] == 1 ? '' : 's' );
	elseif( $laps['hour'] )
		$val = sprintf('%s%d heure%s', $intro, $laps['hour'], $laps['hour'] == 1 ? '' : 's' );
	elseif( $laps['minute'] )
		$val = sprintf('%s%d minute%s', $intro, $laps['minute'], $laps['minute'] == 1 ? '' : 's' );
	elseif( $laps['second'] )
		$val = sprintf('%s%d seconde%s', $intro, $laps['second'], $laps['second'] == 1 ? '' : 's' );
	else
		$val = 'à l\'instant';
	// $val .= var_export($laps, true);
	// $val .= ", ($old_date_str) ";
	// $val .= date_default_timezone_get();
	// $val .= date("d H:i:s", $old_date);
	// $val .= ", " . wp_date("d H:i:s", $old_date);
	// $val .= ", now " . wp_date("d H:i:s", $now);
	return sprintf('%s%s%s', $before, $val, $after);
 }
 
 /**
  * Retourne le numéro de la dernière semaine de l'année
  */
 function get_last_week($year) {
	$dt = new DateTime($year . '-12-28');
	return (int)$dt->format('W');
}
 
 /**
  * Retourne les dates de début et fin d'une semaine de l'année
  */
 function get_week_dates($year, $week) {
  $dto = new DateTime();
  $ret['start'] = $dto->setISODate($year, $week)->format('Y-m-d');
  $ret['end'] = $dto->modify('+6 days')->format('Y-m-d');
  return $ret;
}

/**
 * Décode le champ -SPAMCAUSE contenu dans les en-têtes de mail.
 */
function decode_spamcause($msg){
	$text = "";
	for ($i = 0; $i < strlen($msg); $i+=2)
		$text .= decode_spamcause_unrot(substr($msg, $i, 2), floor($i / 2));                    # add position as extra parameter
	return $text;
}
function decode_spamcause_unrot($pair, $pos, $key = false){
	if( $key === false )
		$key = ord('x');
	if ($pos % 2 == 0)                                           # "even" position => 2nd char is offset
		$pair = $pair[1] . $pair[0];                               # swap letters in pair
	$offset = (ord('g') - ord($pair[0])) * 16;                     # treat 1st char as offset
	return chr(ord($pair[0]) + ord($pair[1]) - $key - $offset);        # map to original character
}

/**
 * Retourne le texte correspondant au html, sans balise html.
 */
function html_to_plain_text($html){
	$html = html_inner_body( $html, false );
	$html = preg_replace('/(\<(p|div|pre|br|tr|li|ol|br))/', "\n$1", $html);
	return html_entity_decode(
			htmlspecialchars_decode(
			wp_strip_all_tags($html)
			), ENT_QUOTES | ENT_HTML401);
}

/**
 * Retourne le texte contenu dans les balises html body. Supprime aussi la balise style
 */
function html_inner_body($html, $wrapper = 'message', $wrapper_class = false){
	$matches = [];
	if( preg_match('/<body[^>]*\>([\s\S]*)\<\/body\>/', $html, $matches) )
		$html = $matches[1];
	elseif( preg_match('/<html[^>]*\>([\s\S]*)\<\/html\>/', $html, $matches) )
		$html = $matches[1];
	$html = preg_replace('/\<style[^>]*\>[\s\S]*\<\/style\>$/i', '', $html);
	if( $wrapper ){
		$html = sprintf('<%s class="%s">%s</%s>'
			, $wrapper
			, $wrapper_class ? $wrapper_class : ''
			, $html
			, $wrapper
		);
	}
	return $html;
}


/**
 * Crée le fichier zip d'un dossier, sans ou avec dossier racine dans le zip
 */
function zip_create_folder_zip($dir_path, $zip_file, $add_root_folder = false){
	if( file_exists($zip_file) )
		unlink($zip_file);
		
	$zip = new ZipArchive();
	$zip->open($zip_file, ZIPARCHIVE::CREATE);
	if( $add_root_folder )
		$zipFile->addEmptyDir(basename($dir_path));//TODO test
		
	zip_add_folder($dir_path, $zip, strlen("$dir_path/"));

	$zip->close();
}
/**
 * Add files and sub-directories in a folder to zip file.
 * @param string $folder
 * @param ZipArchive $zipFile
 * @param int $exclusiveLength Number of text to be exclusived from the file path.
 */
function zip_add_folder($folder, &$zipFile, $exclusiveLength) {
	$handle = opendir($folder);
	while (false !== $f = readdir($handle)) {
		if ($f != '.' && $f != '..') {
			$filePath = "$folder/$f";
			// Remove prefix from file path before add to zip.
			$localPath = substr($filePath, $exclusiveLength);
			if (is_file($filePath)) {
				$zipFile->addFile($filePath, $localPath);
			} elseif (is_dir($filePath)) {
				// Add sub-directory.
				$zipFile->addEmptyDir($localPath);
				zip_add_folder($filePath, $zipFile, $exclusiveLength);
			}
		}
	}
	closedir($handle);
}

/**
 * Teste si un tableau est associatif
 */
function is_associative_array($array){
	if( ! $array )
		return false;
	$keys = array_keys($array);
	if( $keys[0] !== 0)
		return true;
	return $keys !== $array;
}