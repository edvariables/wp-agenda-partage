<?php

function toggle_shortcode_cb( $atts, $content = null ) {

	extract( shortcode_atts( array(
		'title' => __('Cliquez pour afficher', AGDP_TAG),
		'admin_only' => false,
		'color' => ''
	), $atts ) );

	if(array_key_exists('admin_only', $atts)
	&& $atts['admin_only']){
		if( ! current_user_can('manage_options') )
			return '';
	}
	if(isset($atts['ajax']) && $atts['ajax']){
		$content = wp_kses_post( $content );
		$ajax = esc_attr( json_encode ( array(
				'action' => AGDP_TAG.'_shortcode',
				'data' => $content
			)));
		$content = '';
	}
	else{
		$ajax = false;
		$content = do_shortcode( wp_kses_post( $content ) );
	}
	if(isset($atts['class']) && $atts['class']){
		$class = $atts['class'];
		unset($atts['class']);
	}
	else {
		$class = '';
	}
	
	if(isset($atts['title']) )
		$title = $atts['title'];
	else
		$title = '';
	return sprintf('<h3 class="toggle-trigger %s" %s><a href="#">%s</a></h3><div class="toggle-container">%s</div>'
			, $class
			, $ajax ? ' ajax=1 data="' . $ajax . '"' : ''
			, esc_html( $title  )
			, $content
	);

}
add_shortcode( 'toggle', 'toggle_shortcode_cb' );