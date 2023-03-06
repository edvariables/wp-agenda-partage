
jQuery( function( $ ) {
	
	/**
	 * agdpevent-edit shortcode
	 * cf class.agendapartage-agdpevent-edit.php
	 * Hack des form wpcf7
	 */
	$( document ).ready(function() {
		$( 'body' ).on('agdpevent_edit_form-init', function(){
			$("input.agdpevent_edit_form_data").each(function(){
				// Complète les champs de formulaires avec les valeurs fournies
				var $agdpDataInput = $(this);
				var $form = $agdpDataInput.parents('form.wpcf7-form:first');
				if($form.length == 0) return;
				var fields = JSON.parse($agdpDataInput.attr("data"));
				for(var field_name in fields){
					var $input = $form.find(':input[name="' + field_name + '"]');
					if($input.attr('type') == 'checkbox')
						$input.prop('checked', !! fields[field_name]);
					else
						$input.val(fields[field_name]);
				}
				
				//Bloque l'effacement du formulaire
				$form.on('reset', 'form.wpcf7-form', function(e) {
					e.preventDefault();
				});
				
				//En réponse d'enregistremement, le message mail_sent_ok contient l'url du post créé ou modifié
				document.addEventListener( 'wpcf7mailsent', function( event ) {
					var post_url = event.detail.apiResponse['message'];
					if(post_url && post_url.startsWith('redir:')){
						post_url = post_url.substring('redir:'.length);
						if(post_url){
							event.detail.apiResponse['message'] = 'La page de l\'évènement va être rechargée. Merci de patienter.';
							document.location = post_url;
						}
					}
				}, false );
				//En réponse d'enregistremement : autorise le html dans le message de réponse
				['wpcf7invalid','wpcf7mailfailed', 'wpcf7submit', 'wpcf7mailsent'].forEach( function(evt) {
						document.addEventListener( evt, function( event ) {
						var response = event.detail.apiResponse['message'];
						//Si le message contient du html
						if(response && response.indexOf('<') >= 0 && response.indexOf('<script') == -1){
							//pas jojo mais wpcf7 affecte le texte par .innerText, on préfèrerait innerHTML
							setTimeout(function(){$form.find(".wpcf7-response-output").html(response);}, 200);
						}
					}, false );
				});
				
				
				//Si la localisation est vide, la sélection d'une commune affecte la valeur
				$form.find('.wpcf7-form-control-wrap[data-name="ev-cities"] input[type="checkbox"]').on('click', function(event){
					var $localisation = $form.find('input[name="ev-localisation"]');
					if( ! $localisation.val()){
						var cities = '';
						$form.find('.wpcf7-form-control-wrap[data-name="ev-cities"] input[type="checkbox"]:checked').each(function(e){
							cities += (cities ? ', ' : '') + this.parentElement.innerText;
						});
						$localisation.attr('placeholder', cities );
					}
				});
			});
		}).trigger('agdpevent_edit_form-init');
		
		/**
		 * A cause du reCaptcha, désactivation de la validation du formulaire par la touche Enter pour la remplacer par un Tab
		 */
		$('body').on('keypress', 'form.wpcf7-form input', function(e) {
			if(e.keyCode == 13) {
				var input = this;
				var $form = $(this).parents('form:first');
				var found = false;
				$form.find('input:visible, textarea, select').each(function(e){
					if(found){
						this.focus();
						return false;
					}
						
					if(this === input)
						found = true;
				});
				e.preventDefault();
				return false;
			}
		});
		
		//
		$( 'body' ).on('reset', 'form.wpcf7-form.preventdefault-reset', function(e) {
			e.preventDefault();
		});

		/**
		 * Scroll jusqu'au #hash de la forme #eventid%d (correction de la hauteur du menu)
		 *
		 */
		if( window.location.hash
		&& /eventid[0-9]+/.test(window.location.hash)) {
			$( 'body ').ready(function(){
			 
			var matches = window.location.hash.match(/eventid[0-9]+/);
			var $dom = $('#' + matches[0]);
			if( $dom.length === 0)
				return;
			var offset = $dom.offset().top;
			$('html,body').animate({scrollTop: offset - window.innerHeight/4}, 200);
			$dom.addClass('agdp-scrolled-to');
			return false;
			 
		 });
		}

		/**
		 * Liste d'évènements
		 *
		 */
		//Filtres de l'agenda
		$(' .agdp-agdpevents-list-header form').each(function(event){
			var $form = $(this);
			$form.find('label:not([for]) > input[type="checkbox"]').on('click', function(event){
				var name = this.getAttribute('name');
				const regexp = /(\w+)\[(.*)\]/g;
				var matches = [...name.matchAll(regexp)][0];
				var tax_name = matches[1];
				var id = matches[2];
				var $checkboxes = $form.find('.taxonomy.'+tax_name+' label:not([for])').children();
				if(id == '*')
					$checkboxes.not('[name="'+name+'"]').prop("checked", ! this.checked );
				else if(id != '*' && this.checked)
					$checkboxes.filter('[name="'+tax_name+'[*]"]').prop("checked", false );
				else if(id != '*' && ! this.checked){
					if ( $checkboxes.not('[name="'+tax_name+'[*]"]').filter(':checked').length == 0)
						$checkboxes.filter('[name="'+tax_name+'[*]"]').prop("checked", true );
				}
			});
		}); 
		
		/* //Show more posts
		$(' body ').on('click', ".agdp-agdpevents-list .show-more .toggle-trigger", function(event){
			//Load more posts via Ajax
			var $showMore = $(this).parents('.show-more');
			var $list = $showMore.parents('.agdp-agdpevents-list');
			var $lastPost = $list.find('.agdpevent[agdpevent]:last');
			if($lastPost.length > 0){
				var agdpevent = JSON.parse( $lastPost.attr('agdpevent') );
				jQuery.ajax({
					url : agendapartage_ajax.ajax_url,
					type : 'post',
					data : {
						action : AGDP_TAG + '_show_more',
						_nonce : agendapartage_ajax.check_nonce,               
						'last-post' : agdpevent
					},
					success : function( response ) {
						var $newList = $(response);
						//TODO merge mois incomplet
						var $firstMonth = $newList.find('.agdpevents-month:first');
						var monthId = $firstMonth.attr('id');
						$existingMonth = $list.children().find('.agdpevents-month[id="'+monthId+'"]:first');
						if($existingMonth.length){
							var $parent = $firstMonth.parent('li:first');
							$firstMonth.children().appendTo($existingMonth);
							$parent.remove();
							//Déplie le parent TODO attention en mode ajax
							$existingMonth.prev('.toggle-trigger:not(.active)').click();
						}
						$newList.children().children()
							.appendTo($list.children());
						$( 'body' ).trigger('toggle-init');
						$showMore.remove();
					},
					fail : function( response ){
						$showMore.remove();
					}
				});
				$(this)
					.html("Chargement en cours")
					.toggleClass("active");
			}
			else
				$showMore.remove();
			event.preventDefault();  
			return false;
		}); */

		/**
		 * Abonnement à la lettre-info : la saisie d'une adresse email met à jour les options d'abonnement)
		 *
		 */
		$('form.wpcf7-form input[name="nl-email"]').on('change', function(event){
			var $actionElnt = $(this);
			var email = $actionElnt.val();
			if( ! email )
				return;
			var $form = $actionElnt.parents('form:first');
			var post_id = $actionElnt.parents('article[id]:first').attr('id');
			if( ! post_id || ! post_id.startsWith('post-'))
				return;
			post_id = post_id.substr('post-'.length);
			jQuery.ajax({
				url : agendapartage_ajax.ajax_url,
				type : 'post',
				data : {
					'action' : 'agdpnl_get_subscription',
					'post_id' : post_id,
					'email' : email,
					'_nonce' : agendapartage_ajax.check_nonce
				},
				success : function( response ) {
					if(response){
						if((typeof response === 'string' || response instanceof String)
						&& response ){
							response = response.replace(/^(.*)\|(.*)$/, '$2');
							var $radio = $form.find('input[name="nl-period"][value="' + response + '"]');
							$radio.prop("checked", true);
						}
					}
					$spinner.remove();
				},
				fail : function( response ){
					$spinner.remove();
					if(response) {
						var $msg = $('<div class="ajax_action-response alerte"><span class="dashicons dashicons-no-alt close-box"></span>'+response+'</div>')
							.click(function(){$msg.remove()});
						$actionElnt.after($msg);
					}
				}
			});
			var $spinner = $actionElnt.next('.wpcf7-spinner');
			if($spinner.length == 0)
				$actionElnt.after($spinner = $('<span class="wpcf7-spinner" style="visibility: visible;"></span>'));
		}); 
	});
});
