<?php

function parent_page_shortcode_cb( $atts, $content = null ) {
	global $post;
	if( ! $post )
		return '! $post'.$content;
	$parent = wp_get_post_parent_id($post);
	if( ! $parent )
		return '! $parent'.$content;
	$parent = get_post($parent);
	
	extract( shortcode_atts( array(
		'title' => __('Parent', AGDP_TAG),
	), $atts ) );

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
	
	if(isset($atts['icon']) )
		$icon = $atts['icon'];
	else
		$icon = '';
	
	return sprintf('<span class="parent-page %s">%s%s<a href="%s">%s</a></span>'
			, $class
			, $icon ? Agdp::icon($icon) : ''
			, $title
			, get_permalink( $parent )
			, esc_html( $parent->post_title )
			, $content
	);

}
add_shortcode( 'parent-page', 'parent_page_shortcode_cb' );