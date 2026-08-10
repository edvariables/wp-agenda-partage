<?php

function toggle_shortcode_cb( $atts, $content = null ) {

	extract( shortcode_atts( array(
		'title' => __('Cliquez pour afficher', AGDP_TAG),
		'admin_only' => false,
		'color' => '',
		'tag' => 'h3',
	), $atts ) );

	if(array_key_exists('admin_only', $atts)
	&& $atts['admin_only']){
		if( ! current_user_can('manage_options') )
			return '';
	}
	if(isset($atts['ajax']) && $atts['ajax']){
		$data = $content ? wp_kses_post( $content ) : '';
		$action = AGDP_TAG.'_shortcode';
		$method = '';
		$refresh = '1'; //on each collapse 1|once
		if(isset($atts['ajax'])){
			if( is_array($atts['ajax'])){
				if( ! empty($atts['ajax']['refresh']))
					$refresh = $atts['ajax']['refresh'];
				if( ! empty($atts['ajax']['action']))
					$action = $atts['ajax']['action'];
				if( ! empty($atts['ajax']['method']))
					$method = $atts['ajax']['method'];
				if( ! empty($atts['ajax']['data']))
					$data = $atts['ajax']['data'];
			}
			else
				$refresh = $atts['ajax'];
		}
		if( ! empty($atts['ajax-refresh']))
			$refresh = $atts['ajax-refresh'];
		if( ! empty($atts['ajax-action']))
			$action = $atts['ajax-action'];
		if( ! empty($atts['ajax-method']))
			$method = $atts['ajax-method'];
		if( ! empty($atts['ajax-data']))
			$data = $atts['ajax-data'];
		
		$ajax = [ 'action' => $action ];
		if( $method )
			$ajax['method'] = $method;
		if( $data )
			$ajax['data'] = $data;
			
		$ajax = esc_attr( json_encode ( $ajax ));
		
		$ajax = sprintf(' ajax="%s" data="%s"', $refresh, $ajax);
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
	
	if(isset($atts['id']) && $atts['id']){
		$id = sprintf(' id="%s"', $atts['id']);
		unset($atts['id']);
	}
	else {
		$id = '';
	}	
		
	if(isset($atts['title']) )
		$title = $atts['title'];
	else
		$title = '';	
		
	if( ! empty($atts['tag']) )
		$tag = $atts['tag'];
	else
		$tag = 'h3';
	
	return sprintf('<%s class="toggle-trigger %s" %s%s><a class="toggle-trigger-label">%s</a></%s><div class="toggle-container">%s</div>'
			, $tag
			, $class
			, $ajax //TODO check %s%s
			, $id ? ' ' . $id : ''
			, esc_html( $title  )
			, $tag
			, $content
	);

}
add_shortcode( 'toggle', 'toggle_shortcode_cb' );