
jQuery( function( $ ) {
	
	/**
	 * agdpevent-edit shortcode
	 * cf class.agendapartage-agdpevent-edit.php
	 * Hack des form wpcf7
	 * Les valeurs des champs sont dans un input.agdpevent_edit_form_data hidden
	 */
	$( document ).ready(function() {
		$( 'body' ).on('wpcf7_form_fields-init', function(){
			$("input.agdpevent_edit_form_data, input.covoiturage_edit_form_data").each(	function(){
				/** Complète les champs de formulaires avec les valeurs fournies **/
				var $agdpDataInput = $(this);
				var $form = $agdpDataInput.parents('form.wpcf7-form:first');
				if($form.length == 0) return;
				var fields = JSON.parse($agdpDataInput.attr("data"));
				for(var field_name in fields){
					var $input = $form.find(':input[name="' + field_name + '"]');
					if($input.attr('type') == 'checkbox')
						$input.prop('checked', !! fields[field_name]);
					else if($input.attr('type') == 'radio'){
						$input.filter('[value="' + fields[field_name] + '"]')
							.prop('checked', true)
							.parents('label:first').addClass('radio-checked');
					}
					else
						$input.val(fields[field_name]);
				}
				
				/** Bloque l'effacement du formulaire **/
				$form.on('reset', 'form.wpcf7-form', function(e) {
					e.preventDefault();
				});
				
				/** En réponse d'enregistremement, le message mail_sent_ok contient l'url du post créé ou modifié **/
				document.addEventListener( 'wpcf7mailsent', function( event ) {
					var post_url = event.detail.apiResponse['message'];
					if(post_url && post_url.startsWith('redir:')){
						post_url = post_url.substring('redir:'.length);
						if(post_url){
							event.detail.apiResponse['message'] = 'La page va être rechargée. Merci de patienter.';
							document.location = post_url;
						}
					}
				}, false );
				/** En réponse d'enregistremement : autorise le html dans le message de réponse **/
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
				
				/** Si la localisation est vide, la sélection d'une commune affecte la valeur **/
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
				
				/** Récupère les titres des cases à cocher pour ajouter l'attribut title **/
				$form.find('.agdpevents-tax_titles[input][titles]').each(function(event){
					var input = this.getAttribute('input');
					var titles = JSON.parse(this.getAttribute('titles'));
					for(title in titles){
						$form.find('input[name="' + input + '"][value="' + title + '"]').parent('label').attr('title', titles[title]);
					}
					$(this)
						.addClass('dashicons dashicons-info')
							.click(function(){
								var msg = '';
								for(title in titles)
									msg += '- ' + title + ' : ' + titles[title] + '\r\n';
								alert( msg );
							})
						.removeClass('hidden')
				});
				
				/** Options radio sans label **/
				$form.find('.wpcf7-form-control.wpcf7-radio.no-label input[type="radio"]').on('change', function(event){
					$(this).parents('label:first')
						.siblings('label')
							.toggleClass('radio-checked', false)
							.end()
						.toggleClass('radio-checked', this.checked)
					;
				})
					//Init
					.filter(':checked').trigger('change')
				;
				
				/** Covoiturage : Intervertit les lieux de départ et d'arrivée **/
				$form.on('click', '.swap-depart-arrivee', function(e) {
					var $arrivee = $form.find('input[name="cov-arrivee"]');
					var arrivee = $arrivee.val();
					var $depart = $form.find('input[name="cov-depart"]');
					var depart = $depart.val();
					$depart.val(arrivee);
					$arrivee.val(depart);
				});
				
				/** Covoiturage : cov-periodique swappe des zones de saisie **/
				$form.on('change', 'input[name="cov-periodique"]', function(e) {
					var isPeriodique = this.value == '1';
					$form.toggleClass('is-cov-periodique', isPeriodique);
				})
					.find('input[name="cov-periodique"]:checked').change();
				
				
			});
		}).trigger('wpcf7_form_fields-init');
		
		
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
		 */
		if( window.location.hash
		&& /(event|covoiturage)id[0-9]+/.test(window.location.hash)) {
			$( 'body ').ready(function(){
			 
			var matches = window.location.hash.match(/(event|covoiturage)id[0-9]+/);
			var $dom = $('#' + matches[0]);
			if( $dom.length === 0)
				return;
			
			$dom.get(0).scrollIntoView();
			
			$dom.addClass('agdp-scrolled-to');
			return false;
			 
		 });
		}

		/**
		 * Liste d'évènements ou de covoiturages
		 */
		//Filtres de l'agenda
		$('.agdp-agdpevents-list-header form, .agdp-covoiturages-list-header form').each(function(event){
			var $form = $(this);
			/** manage 'all' checkbox **/
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
			/** clear filters link **/
			$('#agdp-filters .clear-filters').on('click', function(event){
				//For each taxonomy, 
				$form.find('label[for]').each(function(e){
					//uncheck all
					if($(this).is('[for="cov-intention[]"]'))
						$(this).siblings('label').children('input[type="checkbox"]:checked').click();
					else
						//check the first checkbox 'All' 
						$(this).next('label:first').children('input[type="checkbox"]:not(:checked)').click();
				});
				$(this)
					.parents('.filters-summary').html('')
						.parents('.toggle-trigger:first')
							.trigger('toggle-active');
				return false;
			});
			/** reload and add links. a.click are skipped due to toggle-trigger **/
			$('#agdp-filters .agdp-title-link a[href]').on('click', function(e){
				e.preventDefault();
				var href = this.getAttribute('href');
				if( href === 'reload:' ){//due to #main, it does not reload
					var tick = parseInt(Date.now()) % 1000000;
					href = document.location.href;
					if( href.indexOf('#') === -1 )
						document.location.href = href;
					else if( href.indexOf('?') === -1 )
						document.location.href = href.replace(/\#/, '?_t='+tick+'#');
					else
						document.location.href = href.replace(/\?(_t=[0-9]+)?/, '?_t='+tick+'&');
				}
				else
					document.location.href = href;
				return false;
			});
		}); 
		
		/**
		 * Abonnement à la lettre-info
		 * la saisie d'une adresse email met à jour les options d'abonnement), masque ou affiche la création de compte
		 */
		$('form.wpcf7-form input[name="nl-email"]').on('change', function(event){
			var $actionElnt = $(this);
			var $form = $actionElnt.parents('form:first');
			var email = $actionElnt.val();
			if( ! email ){
				$form.find('.if-not-connected').show();
				return;
			}
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
						var is_user = false;
						if(typeof response === 'object'){
							for(const nloption in response){
								var subscription = response[nloption];
								if( ! subscription || ! subscription.subscription_name)
									continue;
								var $radio = $form.find('input[name="nl-period-' + subscription.field_extension + '"][value="' + subscription.subscription_name + '"]');
								$radio.prop("checked", true);
							}
							is_user = response.is_user;
						}
						if(is_user)
							$form.find('.if-not-connected').hide().prop("checked", false);
						else
							$form.find('.if-not-connected').show();
						
						if(typeof response === 'string'){
							var $msg = $('<div class="ajax_action-response alerte"><span class="dashicons dashicons-no-alt close-box"></span>'+response+'</div>')
							.click(function(){$msg.remove()});
							$actionElnt.after($msg);
							$msg.get(0).scrollIntoView();
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
						$msg.get(0).scrollIntoView();
					}
				}
			});
			var $spinner = $actionElnt.next('.wpcf7-spinner');
			if($spinner.length == 0)
				$actionElnt.after($spinner = $('<span class="wpcf7-spinner" style="visibility: visible;"></span>'));
		}); 
	
	
		
		// Covoiture : obtention du n° de téléphone masqué
		$( 'body' ).on('click', '#email4phone-title', function(e) {
			$(this).toggleClass('active');
		});
		
		// Forum, commentaire
		$( 'body' ).on('click', 'a.agdp-ajax-mark_as_ended', function(e) {
			var $actionElnt = $(this);
			//rétablit une précédente annulation par le même clic
			if( $actionElnt.attr('data_cancel_ajax')){
				$actionElnt.attr('data', $actionElnt.attr('data_cancel_ajax'));
				$actionElnt.removeAttr('data_cancel_ajax');
			}
			var cancel_ajax;
			var data = $actionElnt.attr('data');
			if( data )
				data = JSON.parse(data);
			if( data )
				data = data.data;
			if( ! data){
				alert("Désolé, vous ne pouvez pas agir sur ce message. Tentez de recharger cette page internet.");
				cancel_ajax = true;
			}
			else {
				var msg;
				if( data.status == 'ended')
					msg = "Ce message est marqué comme n'étant plus d'actualité."
						+ "\n\nEn cliquant sur 'Ok', vous rétablirez ce message comme étant toujours d'actualité.";
				else
					msg = "En cliquant sur 'Ok', vous indiquerez que ce message n'est plus d'actualité."
						+ "\n\nÊtes-vous sûr de vouloir marquer ce message ?";
				
				if( ! confirm( msg )){
					cancel_ajax = true;
				}
			}
			if (cancel_ajax){
				$actionElnt.attr('data_cancel_ajax', $actionElnt.attr('data'));
				$actionElnt.removeAttr('data');
				e.preventDefault();
				return false;
			}
		});
	});
});
