<?php
function icon_shortcode_cb( $atts, $content = null ) {
	if( count($atts) === 0 )
		return $content;
	$class = 'shortcode-icon';
	$tag = 'span';
	$icon = 'info';
	$title = '';
	$css = '';
	$href = '';
	$attributes = '';
	foreach($atts as $att=>$value){
		if( $att == 0)
			$att = 'icon';
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
			case 'tag':
			case 'balise':
				$tag = trim($value);
				break;
			case 'icon':
			case 'icone':
				if( $value )
					$icon = $value;
				break;
			case 'href':
				if( $value )
					$href = $value;
				break;
			case 'title':
			case 'titre':
				if( $value )
					$title = $value;
				break;
			case 'label':
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
	return sprintf('%s<%s class="dashicons-before dashicons-%s %s"%s%s%s>%s</%s>%s'
			, $href ? sprintf('<a href="%s">', $href) : ''
			, $tag
			, $icon
			, $class
			, $title ? ' title="' . esc_attr($title) . '"' : ''
			, $css ? ' style="' . esc_attr($css) . '"' : ''
			, $attributes
			, $content ? do_shortcode($content) : ''
			, $tag
			, $href ? sprintf('</a>', $href) : ''
	);

}
add_shortcode( 'icon', 'icon_shortcode_cb' );