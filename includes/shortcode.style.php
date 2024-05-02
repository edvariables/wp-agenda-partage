<?php

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
			case 'hidden':
				switch($value){
					case 'if-not-connected':
					case 'si-non-connecte':
					case 'si-non-connecté':
					case 'si-non-connecté-e':
						if( $value )
							$class .= ' if-connected';
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