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
 * $extract_links permet de laisser apparents les hyperliens
 */
function html_to_plain_text($html, $extract_links = false){
	$html = html_inner_body( $html, false );
	$html = preg_replace('/(\<(p|div|pre|br|tr|li|ol|br))/', "\n$1", $html);
	if( $extract_links )
		$html = html_links_to_plain_text($html);
	return html_entity_decode(
			htmlspecialchars_decode(
			wp_strip_all_tags($html)
			), ENT_QUOTES | ENT_HTML401);
}

/**
 * Extrait les hyperliens 
 */
function html_links_to_plain_text($html){
	$matches = [];
	preg_match_all('/\<a\s.*href="([^"]*)"[^>]*\>([\s\S]*?)\<\/a\>/i', $html, $matches);
	for($i = 0; $i < count($matches[0]); $i++){
		if( ! trim($matches[1][$i])
		 || trim($matches[1][$i]) === trim($matches[2][$i]) )
			continue;
		if( ! $matches[2][$i]
		|| strpos( $matches[2][$i], 'http' ) === false )
			$replace = $matches[1][$i];
		else
			$replace = sprintf( '%s (%s)', $matches[2][$i], $matches[1][$i] );
		$html = str_replace( $matches[0][$i], $replace, $html);
	}
	return $html;
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
 * Retourne le fichier après en avoir réduit la taille
 */
function image_reduce($filename, $max_width = null, $max_height = null, $new_file = false){
	if( $max_width === null )
		$max_width = AGDP_IMG_MAX_WIDTH;
	if( $max_height === null )
		$max_height = AGDP_IMG_MAX_HEIGHT;
	if( $max_width === 0
	|| $max_height === 0
	|| ! file_exists( $filename) )
		return false;
	
	$use_exif = false;
	$path_parts = pathinfo($filename);
	switch($path_parts['extension']){
		case 'png':
			$source = imagecreatefrompng($filename);
			break;
		case 'bmp':
			$source = imagecreatefrombmp($filename);
			break;
		case 'gif':
			$source = imagecreatefromgif($filename);
			break;
		case 'jpeg':
		case 'jpg':
			$source = imagecreatefromjpeg($filename);
			$use_exif = true;
			break;
		default:
			return $filename;
	}
	
	// Calcul des nouvelles dimensions
	$size = getimagesize($filename);
	$width = $size[0];
	$height = $size[1];
	
	//Correction d'auto-rotation
	if( $use_exif ) {
		$exif = exif_read_data($filename); //JPEG/TIFF images
		if( ! empty($exif['Orientation'])) {
			switch($exif['Orientation']) {
				case 8:
					$source = imagerotate($source,90,0);
					$width = $size[1];
					$height = $size[0];
					break;
				case 3:
					$source = imagerotate($source,180,0);
					break;
				case 6:
					$source = imagerotate($source,-90,0);
					$width = $size[1];
					$height = $size[0];
					break;
			}
		}
	}

	if( $width <= 0 )
		return $filename;
	if( $width > $max_width )
		$percent = $max_width / $width;
	elseif( $height > $max_height )
		$percent = $max_height / $height;
	else
		return $filename;
	// debug_log(__FUNCTION__, $size, $width, $height, $percent);
	$new_width = floor($width * $percent);
	$new_height = floor($height * $percent);
	// debug_log(__FUNCTION__, $new_width, $new_height);
	if( $new_width <= 0 || $new_height <= 0 )
		return $filename;

	if( $new_file ){
		$new_file = sprintf('%s/%s-%s.%s'
			, $path_parts['dirname']
			, $path_parts['filename']
			, sprintf( '%dx%d', $new_height, $new_width)
			, $path_parts['extension']
		);
		if( file_exists($new_file) )
			return $new_file;
		
		if( ! copy($filename, $new_file) )
			return false;
		
		chmod( $new_file, 644 );
	}
	else
		$new_file = $filename;

	// Chargement
	$thumb = imagecreatetruecolor($new_width, $new_height);
	
	// Redimensionnement
	imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
	
	switch($path_parts['extension']){
		case 'png':
			imagepng($thumb, $new_file);
			break;
		case 'bmp':
			imagebmp($thumb, $new_file);
			break;
		case 'gif':
			imagegif($thumb, $new_file);
			break;
		case 'jpeg':
		case 'jpg':
		default:
			imagejpeg($thumb, $new_file);
			break;
	}
	
    imagedestroy($thumb);
    imagedestroy($source);
	
	return $new_file;
}

/**
 * Retourne l'url d'un fichier du répertoire upload_dir_info
 */
function upload_file_url( $filename ){
	
	$upload_dir_info = wp_upload_dir();
	$upload_dir_info['basedir'] = str_replace('\\', '/', $upload_dir_info['basedir']);
		
	return str_replace($upload_dir_info['basedir'], $upload_dir_info['baseurl'], $filename);
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