<?php
/* shortcode style
[style if-not-connected tag=code]code blabla for not connected user[/style]
[style icon="info" color="green"] green info iconed [/style]

Default values :
	tag : p
	
<style>
	.if-not-connected {
		display:inherit;
	}
	body.logged-in .if-not-connected {
		display:none;
	}
	.if-connected {
		display:none;
	}
	body.logged-in .if-connected {
		display:inherit;
	}
</style>
*/
function style_shortcode_cb( $atts, $content = null ) {

	if( count($atts) === 0 )
		return $content;
	$class = 'shortcode-style';
	$tag = 'p';
	$attributes = '';
	$css = '';
	$title = false;
	if( $content )
		$content = do_shortcode($content);
	foreach($atts as $att=>$value){
		if( $att === 0)
			$att = 'visible';
		switch($att){
			case 'css':
			case 'style':
				if( $value )
					$css .= ' ' . $value . ';';
				break;
			case 'color':
			case 'couleur':
				if( $value )
					$css .= ' color:' . $value . ';';
				break;
			case 'class':
			case 'classe':
				if( $value )
					$class .= ' ' . $value;
				break;
			case 'title':
			case 'titre':
				if( $value )
					$title = $value;
				break;
			case 'tag':
			case 'balise':
				$tag = trim($value);
				break;
			case 'icon':
			case 'icone':
				if( $value )
					$content = sprintf('<span class="dashicons-before dashicons-%s"></span>%s', $value, $content);
				break;
			case 'label':
				if( $value )
					$content = sprintf('<label>%s</label>%s', $value, $content);
				break;
			case 'masque':
			case 'masqué':
			case 'hidden':
				switch($value){
					case 'if-not-connected':
					case 'si-non-connecte':
					case 'si-non-connecté':
					case 'si-non-connecté-e':
						if( $value )
							$class .= ' if-connected';
						break;
					case 'if-connected':
					case 'si-connecte':
					case 'si-connecté':
					case 'si-connecté-e':
						if( $value )
							$class .= ' if-not-connected';
						break;
					}
				break;
			case 'visible':
				if( ! $value )
					$class .= ' hidden';
				else switch($value){
					case 'if-not-connected':
					case 'si-non-connecte':
					case 'si-non-connecté':
					case 'si-non-connecté-e':
						$class .= ' if-not-connected';
						break;
					case 'if-connected':
					case 'si-connecte':
					case 'si-connecté':
					case 'si-connecté-e':
						if( $value )
							$class .= ' if-connected';
						break;
					case 'if-admin':
					case 'si-admin':
					case 'admin-user-only':
						$class .= ' admin-user-only';
						break;
					}
				break;
			case 'attributes':
				if( $value )
					$attributes = $value;
				break;
			case 'date':
				if( $value ){
					$date_format = $value;
					$content .= ' CIICIC';
				}
				break;
			default:
				$attributes .= sprintf(' %s=%s', $att, $value);
				break;
		}
	}
	$html = sprintf('<%s class="%s"%s%s %s>%s</%s>'
			, $tag
			, trim($class)
			, $title ? ' title="' . esc_attr($title) . '"' : ''
			, $css ? ' style="' . esc_attr($css) . '"' : ''
			, $attributes
			, $content
			, $tag
	);
	return $html;

}
add_shortcode( 'style', 'style_shortcode_cb' );