/**
 * Hack de wpcf7-recaptcha-controls.js pour chargement différé d'un recaptcha
 */
jQuery(document).ready( function(e) {
	//TODO wpcf7-recaptcha-controls.js n'est pas chargé si il n'y en a pas dans la page d'origine
	//	tester avec 2 togglers.ajax en cascade
	var ajax_forms = [];
	let recaptchaWidgets = [];
	if( typeof recaptchaCallback != "function")
		return;
	var prev_recaptchaCallback = recaptchaCallback;
	
	recaptchaCallback = function(form = null, call_prev_callback = true) {
		
		let forms = form ? [form] : ajax_forms;
		
		let pattern = /(^|\s)g-recaptcha(\s|$)/;

		for ( let i = 0; i < forms.length; i++ ) {
			let recaptchas = forms[ i ].getElementsByClassName( 'wpcf7-recaptcha' );
			
			for ( let j = 0; j < recaptchas.length; j++ ) {
				let sitekey = recaptchas[ j ].getAttribute( 'data-sitekey' );

				if ( recaptchas[ j ].className && recaptchas[ j ].className.match( pattern ) && sitekey ) {
					let params = {
						'sitekey': sitekey,
						'type': recaptchas[ j ].getAttribute( 'data-type' ),
						'size': recaptchas[ j ].getAttribute( 'data-size' ),
						'theme': recaptchas[ j ].getAttribute( 'data-theme' ),
						'align': recaptchas[ j ].getAttribute( 'data-align' ),
						'badge': recaptchas[ j ].getAttribute( 'data-badge' ),
						'tabindex': recaptchas[ j ].getAttribute( 'data-tabindex' )
					};

					let callback = recaptchas[ j ].getAttribute( 'data-callback' );

					if ( callback && 'function' == typeof window[ callback ] ) {
						params[ 'callback' ] = window[ callback ];
					}

					let expired_callback = recaptchas[ j ].getAttribute( 'data-expired-callback' );

					if ( expired_callback && 'function' == typeof window[ expired_callback ] ) {
						params[ 'expired-callback' ] = window[ expired_callback ];
					}

					let widget_id = grecaptcha.render( recaptchas[ j ], params );
					recaptchaWidgets.push( widget_id );
					break;
				}
			}
		}
		if(call_prev_callback 
		&& typeof prev_recaptchaCallback === 'function')
			prev_recaptchaCallback.call();
	};

	/**
	 * Reset the reCaptcha when Contact Form 7 gives us:
	 *  - Spam
	 *  - Success
	 *  - Fail
	 * 
	 * @return void
	 */
	document.addEventListener( 'wpcf7submit', function( event ) {
		switch ( event.detail.status ) {
			case 'spam':
			case 'mail_sent':
			case 'mail_failed':
				for ( let i = 0; i < recaptchaWidgets.length; i++ ) {
					grecaptcha.reset( recaptchaWidgets[ i ] );
				}
		}
	}, false );
	

	/**
	 * ED
	 * 
	 * @return void
	 */
	document.addEventListener( 'wpcf7_init_after_ajax', function( event ) {
		ajax_forms.push( event.detail );
		recaptchaCallback( event.detail, false );
		for ( let i = 0; i < recaptchaWidgets.length; i++ ) {
			grecaptcha.reset( recaptchaWidgets[ i ] );
		}
	}, false );
	
	
} );
function fire_wpcf7_init_after_ajax (form){
	// Create the event
	var event = new CustomEvent("wpcf7_init_after_ajax", { 'detail': form  });

	// Dispatch/Trigger/Fire the event
	document.dispatchEvent(event);
}