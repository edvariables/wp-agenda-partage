 
const AGDP_TAG = 'agendapartage';
const AGDP_JS_SKIP_FIELD = '_js_skip_field';

jQuery( function( $ ) {
	
	/**
	 * agdpevent-edit shortcode
	 * cf class.agdp-agdpevent-edit.php
	 * Hack des form wpcf7
	 * Les valeurs des champs sont dans un input.agdpevent_edit_form_data hidden
	 */
	$( document ).ready(function() {
		var $body = $( 'body' );
		// on wpcf7_form_fields-init
		$body.on('wpcf7_form_fields-init', function(){
			
			/****************
			 *	Evènements, contacts, covoiturages et messages
			 *******************************/
			$("input.agdpevent_edit_form_data"
			+ ", input.contact_edit_form_data"
			+ ", input.covoiturage_edit_form_data"
			+ ", input.agdpmessage_edit_form_data").each( function(){
				/** Complète les champs de formulaire avec les valeurs fournies via un input **/
				var $agdpDataInput = $(this);
				var $form = $agdpDataInput.parents('form.wpcf7-form:first');
				if($form.length == 0) return;
				var fields = JSON.parse($agdpDataInput.attr("data"));
				for(var field_name in fields){
					var $input = $form.find(':input[name="' + field_name + '"]');
					if( $input.length === 0 ){
						if( fields[field_name] instanceof Array )
							$input = $form.find(':input[name="' + field_name + '[]"]');
						
						if( $input.length === 0 )
							continue;
					}
					if($input.attr('type') == 'checkbox'){
						if( fields[field_name] instanceof Array ){
							$input.prop('checked', false);
							for(var i in fields[field_name])
								$input.filter('[value="' + fields[field_name][i] + '"]')
									.prop('checked', true);
						}
						else
							$input.prop('checked', !! fields[field_name]);
					}
					else if($input.attr('type') == 'radio'){
						if( fields[field_name] instanceof Array ){
							$input.prop('checked', false)
								.parents('label:first').removeClass('radio-checked');
							for(var i in fields[field_name]){
								var value = fields[field_name][i];
								var filter = '[value="' + value + '"]';
								if( value == 1 )
									filter += ',[value="oui"],[value="Oui"]';
								else if( value == 0 )
									filter += ',[value="non"],[value="Non"]';
								$input.filter(filter)
									.prop('checked', true)
									.parents('label:first').addClass('radio-checked');
							}
						}
						else {
							var value = fields[field_name];
							var filter = '[value="' + value + '"]';
							if( value == 1 )
								filter += ',[value="oui"],[value="Oui"]';
							else if( value == 0 )
								filter += ',[value="non"],[value="Non"]';
							$input.filter(filter)
								.prop('checked', true)
								.parents('label:first').addClass('radio-checked');
						}
					}
					else
						$input.val(fields[field_name]);
				}
				
				/** Bloque l'effacement du formulaire **/
				$form.on('reset', 'form.wpcf7-form', function(e) {
					e.preventDefault();
				});
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
				$form.find('.tax_terms_titles[input][titles]').each(function(event){
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
				
			/** En réponse d'enregistremement d'un wpcf7, le message mail_sent_ok contient l'url du post créé ou modifié **/
			document.addEventListener( 'wpcf7mailsent', function( event ) {
				var response = event.detail.apiResponse['message'];
				if(response){
					if(response.startsWith('redir:')){
						var post_url = response.substring('redir:'.length);
						if(post_url){
							event.detail.apiResponse['message'] = 'La page va être rechargée. Merci de patienter.';
							document.location = post_url;
						}
					}
					else if(response.startsWith('js:')){
						var script = response.substring('js:'.length);
						if(script){
							var result = eval(script);
							if( ! result )
								result = '&nbsp;'
							event.detail.apiResponse['message'] = eval(script);
						}
					}
				}
			}, false );
			
			
			/****************
			 *	attachments_manager
			 ****************************/
			$('form.wpcf7-form .attachments_manager:not(.initialized)').each(function(){
				var $this = $(this);
				$this.addClass('initialized');
				var $data = $this.find('textarea[name="attachments"]:first');
				var data = $data.val();
				if( ! data ) 
					return;
				var attachments = data.split(',');
				for(var a in attachments){
					var attachment = attachments[a];
					var label = attachment.split('/');
					label = label[label.length-1];
					$('<li></li>')
						.addClass('attachment')
						.attr('data', attachment)
						.append($('<label></label>')
							.text(label))
						.append($('<span class="tools"></span>')
							.append('<a href="#" class="move_up dashicons dashicons-arrow-up-alt"></a>')
							.append('<a href="#" class="move_down dashicons dashicons-arrow-down-alt"></a>')
							.append('<a href="#" class="delete dashicons dashicons-trash"></a>')
						)
						.appendTo($this);
				}
				$this
					.on('click', '.tools .delete', function(e){
						$(this).parents('.attachment:first').toggleClass('deleted');
						manager_save_attachments();
						return false;
					})
					.on('click', '.tools .move_up', function(e){
						var $attachment = $(this).parents('.attachment:first');
						var $previous = $attachment.prev('.attachment:first');
						if( $previous.length ){
							
							var $tmp = $('<span>').hide();
							$previous.before($tmp);
							$attachment.before($previous);
							$tmp.replaceWith($attachment);
							// $attachment.before($previous);
							manager_save_attachments();
						}
						return false;
					})
					.on('click', '.tools .move_down', function(e){
						var $attachment = $(this).parents('.attachment:first');
						var $next = $attachment.next('.attachment:first');
						if( $next.length ){
							
							var $tmp = $('<span>').hide();
							$attachment.before($tmp);
							$next.before($attachment);
							$tmp.replaceWith($next);
							// $attachment.after($next);
							manager_save_attachments();
						}
						return false;
					})
				;
				function manager_save_attachments(){
					var data = [];
					$this.find('.attachment').each(function(e){
						var $attachment = $(this);
						data.push( this.getAttribute('data')
							+ ( $attachment.is('.deleted') ? '|DELETE' : '' ) );
					});
					$data.val( JSON.stringify(data) );
				}
			});

			
			/****************
			 *	Forums et commentaires
			 ****************************/
			
			//Ajoute des champs d'échappement
			$('form.wpcf7-form .' + AGDP_JS_SKIP_FIELD + ' :input[name]').each(function(){
				var name = this.getAttribute('name');
				if( name.length > 3 && name.substr( name.length - 2 ) === '[]' )
					name = name.substr( 0, name.length - 2) + AGDP_JS_SKIP_FIELD + '[]';
				else
					name = name + AGDP_JS_SKIP_FIELD;
				$('<input name="' + name + '" value="1"/>').insertBefore(this);
			});
			
			//Forum comments
			$("article.use-agdpforum").each( function(){
				/** En réponse d'enregistremement, le message mail_sent_ok contient une fonction **/
				document.addEventListener( 'wpcf7mailsent', function( event ) {
					var post_url = event.detail.apiResponse['message'];
					if(post_url && post_url.startsWith('redir:')){
						post_url = post_url.substring('redir:'.length);
						if(post_url){
							event.detail.apiResponse['message'] = 'La page va être rechargée. Merci de patienter.';
							document.location = post_url;
						}
					}
					else if(post_url && post_url.startsWith('js:')){
						var script = post_url.substring('js:'.length);
						if(script){
							event.detail.apiResponse['message'] = eval(script);
						}
					}
				}, false );
				
			});
			
			//input time
			$('form.wpcf7-form input.input-time').prop('type', 'time');
			
			/**
			 * show_new_comment
			 */
			function show_new_comment(id, nonce){
				var $comments = $('#comments');
				if( $comments.length === 0 ) {
					$comments = $('<div id="comments" class="comments-area"><ol class="comment-list"></ol></div>')
						.appendTo('#main');
				}
				var $comment_list = $('#comments .comment-list');
				if( $comment_list.length === 0)
					return;
				data = { 
					'action' : 'agendapartage_comment_action',
					'method' : 'get',
					'data' : { 'comment_id' : id, 'nonce' : nonce },
				};
				jQuery.ajax({
					url : agdp_ajax.ajax_url,
					type : 'post', 
					data : Object.assign(data, {
						_nonce : agdp_ajax.check_nonce
					}),
					success : function( response ) {
						if(response){
							if(typeof response === 'string' || response instanceof String){
								if( $comment_list.find( '#comment-' + id ).length )
									$comment_list.find( '#comment-' + id ).replaceWith( response );
								else
									$comment_list.prepend( response );
							}
						}
					},
					fail : function( response ){
						var $msg = $('<div class="ajax_action-response alerte"><span class="dashicons dashicons-no-alt close-box"></span>'+response+'</div>')
							.click(function(){$msg.remove()});
						$actionElnt.after($msg);
					}
				});
			}
			
		}).trigger('wpcf7_form_fields-init');
		
		
		/**
		 * A cause du reCaptcha, désactivation de la validation du formulaire par la touche Enter pour la remplacer par un Tab
		 */
		//on keypress return
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
		
		// preventdefault-reset
		$body.on('reset', 'form.wpcf7-form.preventdefault-reset', function(e) {
			e.preventDefault();
		});

		/**
		 * Scroll jusqu'au #hash de la forme #(event|covoiturage|contact)id%d (correction de la hauteur du menu)
		 */
		if( window.location.hash ){
			if( /(event|covoiturage|contact)id[0-9]+/.test(window.location.hash)) {
				$( 'body ').ready(function(){
				 
				var matches = window.location.hash.match(/(event|covoiturage|contact)id[0-9]+/);
				var $dom = $('#' + matches[0]);
				if( $dom.length === 0)
					return;
				
				$dom.get(0).scrollIntoView();
				
				$dom.addClass('agdp-scrolled-to');
				return false;
				 
			 });
			}
			else {
				$(window.location.hash + '.toggle-trigger:not(.active)').addClass('active');
			}
		}
		
		/**
		 * Liste d'évènements ou de covoiturages ou de contacts
		 */
		//Filtres de l'agenda
		$('.agdp-agdpevents-list-header form, .agdp-covoiturages-list-header form, .agdp-agdpcontacts-list-header form').each(function(event){
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
		 * Covoiturage
		 */
		//Filtres de l'agenda
		// Covoiture : obtention du n° de téléphone masqué
		$body.on('click', '#email4phone-title', function(e) {
			$(this).toggleClass('active');
		});
		
		/**
		 * Page Abonnements aux lettres-infos et aux forums
		 */
		// La saisie d'une adresse email met à jour les options d'abonnement, masque ou affiche la création de compte
		$('form.wpcf7-form input[name="nl-email"]').on('change', function(event){
			var $actionElnt = $(this);
			var $form = $actionElnt.parents('form:first');
			var email = $actionElnt.val();
			if( ! email ){
				$form.find('.nl-user-fields').show();
				return;
			}
			$form
				.removeClass('sent')
				.attr('data-status', 'init')
				.find('.wpcf7-response-output')
					.html('')
					.end()
			
			var post_id = $actionElnt.parents('article[id]:first').attr('id');
			if( ! post_id || ! post_id.startsWith('post-'))
				return;
			post_id = post_id.substr('post-'.length);
			
			var form_id = $form.find('input[name="_wpcf7"]:first').val();
			
			jQuery.ajax({
				url : agdp_ajax.ajax_url,
				type : 'post',
				data : {
					'action' : 'agdpnl_get_subscription', //newsletters + forums
					'post_id' : post_id,
					'form_id' : form_id,
					'email' : email,
					'_nonce' : agdp_ajax.check_nonce
				},
				success : function( response ) {
					if(response){
						var is_user = false;
						if(typeof response === 'object'){
							for(const nloption in response){
								var subscription = response[nloption];
								if( ! subscription )
									continue;
								if( ! subscription.subscription_name)
									subscription.subscription_name = subscription.subscription;
								if( ! subscription.input_prefixe )
									subscription.input_prefixe = 'nl-period';
								if( ! subscription.input_type )
									subscription.input_type = 'checkbox';
								switch( subscription.input_type ){
									case 'radio':
									case 'checkbox':
										$form.find('input[name="' + subscription.input_prefixe + '-' + subscription.field_extension + '"][value="' + subscription.subscription_name + '"]')
											.prop("checked", true);
										break;
									case 'select':
										$form.find('select[name="' + subscription.input_prefixe + '-' + subscription.field_extension + '"]')
											.val(subscription.subscription_name, true);
										break;
								}
							}
							is_user = response.is_user;
						}
						if(is_user)
							$form.find('.nl-user-fields').hide();
						else {
							$form.find('.nl-user-fields').show();
							$form.find('.wpcf7-list-item.first input[name^="nl-period-"]').prop('checked', true);
						}
						
						$form.find('input[name^="nl-send_newsletter-now-"]').prop('checked', false);
						$form.find('input[name="nl-create-user[]"]').prop('checked', false);
						$form.find('input[name="nl-user-name"]').val('');
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
	
		/**
		 * Forum et actions sur commentaire
		 */
		// Clic sur toujours d'actualité
		$body.on('click', 'a.agdp-ajax-mark_as_ended', function(e) {
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
						+"\nPour rétablir ce message comme étant toujours d'actualité, cliquez sur OK.";
				else
					msg = "Ce message est marqué comme toujours d'actualité."
						+"\nSi ce message a expiré, cliquez sur OK.";
				
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
		
		// Forum sans formulaire wpcf7 associé : utilisation du formulaire de base des commentaires
		
		// Clic sur Répondre au commentaire
		$body.on('click', 'a.comment-reply-link', function(e) {
			var $actionElnt = $(this);
			var title = $actionElnt.parents('article:first').find('.comment-content .comment-title').text();
			title = 'Re: ' + title;
			$('#respond')
				.trigger('forum_clear_respond')
				.find('.comment-form-title input')
					.val(title)
					.end()
				.find('#cancel-comment-reply-link')
					.text('Annuler la réponse')
					.end()
				.find('.form-submit #submit')
					.val('Envoyer')
					.end()
				.show()
			;
		});
		
		// Clic sur Annuler la réponse au commentaire
		$body.on('click', '#cancel-comment-reply-link', function(e) {
			$('#respond')
				.trigger('forum_clear_respond')
			;
		});
		
		// Clic sur modifier un commentaire
		$body.on('forum_comment_edit', function(e, data){
			var $actionElnt = $(e.target);
			var comment_id = $actionElnt.attr('data-commentid');
			var $article = $actionElnt.parents('article:first');
			var $content = $article.find('.comment-content');
			var $title = $content.find('.comment-title');
			var title = $title.text();
			var text = ($title.length ? $title.nextAll() : $content).node_to_text();
			if( ! data ) data = {};
			var $respond = $article.find('a.comment-reply-link');
			if( $respond.length )
				$respond.get(0).click();
			$('#respond')
				.trigger('forum_clear_respond')
				.addClass('forum-comment-edit')
				.find('.comment-form-title input')
					.val(title)
					.end()
				.find('.comment-form-comment textarea')
					.val(text)
					.end()
				.find('#reply-title #cancel-comment-reply-link').each(function(){
						var $a = $(this);
						$a.text('Annuler la modification');
						$a.parents('#reply-title')
							.text('Modifier le message')
							.append($a)
						;
					})
					.end()
				.find('.form-submit')
					.find('input#comment_parent')
						.val(0)
						.end()
					.append('<input id="update_comment_id" name="update_comment_id" type="hidden" value="' + comment_id + '">')
					.append('<input id="update_comment_nonce" name="update_comment_nonce" type="hidden" value="' + data['nonce'] + '">')
					.end()
				.find('input#author')
					.val(data['user_name'])
					.end()
				.find('input#email')
					.val(data['user_email'])
					.end()
					
			;
		});
		
		// Nettoyage des champs du formulaire de commentaire
		$body.on('forum_clear_respond', function(e){
			var $actionElnt = $(this);
			$('#respond')
				.removeClass('forum-comment-edit')
				.find('.comment-form-title input, .comment-form-comment textarea')
					.val('')
					.end()
				.find('input#update_comment_id, input#update_comment_nonce')
					.remove()
					.end()
				.find('.form-submit #submit')
					.val('Enregistrer')
					.end()
			;
		});
	});
});