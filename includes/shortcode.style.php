<?php

function style_shortcode_cb( $atts, $content = null ) {

	if( count($atts) === 0 )
		return $content;
	$class = 'shortcode-style';
	$tag = 'p';
	$attributes = '';
	$css = '';
	foreach($atts as $att=>$value){
		if( is_numeric($att)){
			$att = $value;
			$value = true;
		}
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
			case 'classe':
			case 'class':
				if( $value )
					$class .= ' ' . $value;
				break;
			case 'tag':
			case 'balise':
				$tag = trim($value);
				break;
			case 'icon':
			case 'icone':
				$content = sprintf('<span class="dashicons-before dashicons-%s"></span>%s', $value, $content);
				break;
			case 'label':
				$content = sprintf('<label>%s</label>%s', $value, $content);
				break;
			case 'if-not-connected':
			case 'si-non-connecte':
			case 'si-non-connecté':
			case 'si-non-connecté-e':
				if( $value )
					$class .= ' if-not-connected';
				break;
			case 'attributes':
				if( $value )
					$attributes = ' if-not-connected';
				break;
		}
	}
	return sprintf('<%s class="%s" style="%s" %s>%s</%s>'
			, $tag
			, trim($class)
			, trim($css)
			, $attributes
			, $content
			, $tag
	);

}
add_shortcode( 'style', 'style_shortcode_cb' );