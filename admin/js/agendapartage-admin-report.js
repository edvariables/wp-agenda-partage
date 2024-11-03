const AGDP_BLOG_PREFIX = '@.';

jQuery( function( $ ) {
	
	/**
	 * agdpreport-edit 
	 * 
	 */
	$( document ).ready(function() {
		
		
		var input_types = {
			'text' : 'Texte',
			'bool' : 'Case à cocher',
			'integer' : 'Nombre entier',
			'float' : 'Nombre réel',
			'range' : 'Entier dans interval',
			'date' : 'Date',
			'time' : 'Heure',
			'datetime' : 'Date et heure',
			'select' : 'Sélection',
			'radio' : 'Cases d\'options',
			'checkboxes' : 'Options multiples', 
			'password' : 'Mot de passe',
			'email' : 'E-mail',
			// 'month' : 'Mois',
			// 'week' : 'Semaine',
			// 'search' : 'Recherche',
			'longtext' : 'Texte long',
			'color' : 'Couleur',
			'tel' : 'Téléphone',
			'forum' : 'Forum',
			'newsletter' : 'Lettre-info',
			'report' : 'Sous-requête',
			'field' : 'Champ de requête',
			'table' : 'Table de requête',
			'column' : 'Colonne de table de requête',
			'asc_desc' : 'Ordre de tri',
		};
		var input_options_types = ['select', 'radio', 'checkboxes', 'range', 'field', 'table'];
		
		//Init inputs
		$('#agdp_report-inputs').each(function(e){
			var $this = $(this);
			var $sql = $this.find('textarea#sql');
			var $variables = $('#agdp_report-variables').find('textarea#sql_variables');
			
			//Liste de tables
			$this.find('.sql-helper-tables a')
				.on('click', function(e){
					var $sql = $this.find('textarea#sql');
					$sql.get(0).insertAtCaret( $(this).text() );
				})
				// .after($('<span class="dashicons-before dashicons-edit"></span>')
			;
			//Liste de colonnes
			$this.on('click', '.table_columns li', function(e){
				var $sql = $this.find('textarea#sql');
				$sql.get(0).insertAtCaret( $(this).text() );
			});
			
		});
		
		//Init variables
		$('#agdp_report-variables').each(function(e){
			var $this = $(this);
			var $sql = $('#agdp_report-inputs').find('textarea#sql');
			var $variables = $this.find('textarea#sql_variables');
			
			//Affiche les variables
			$sql.on('change', refresh_variables)
				.trigger('change');
			
			//Sauvegarde des variables vers le textarea
			$this.on('change', '.var_value', function(e){
				var var_values = {};
				$variables.nextAll('.sql_variables_wrap:first')
					.find('.sql_variable').each(function(e){
						var data = {};
						var $this = $(this);
						var $value = $this.find('.var_value');
						var var_name = $value.attr('var_name');
						if( v = $value.attr('var_type') )
							data['type'] = v;
						if( v = $value.attr('var_options') )
							data['options'] = v;
						
						if( $value.is('label') )
							$value = $this.find('.var_value input');
						var v;
						if( $value.is('input[type="checkbox"][name*=\\[\\]]') ){
							v = '';
							$value.filter(':checked').each(function(){
								if( v )
									v += '\n';
								v += this.value;
							});
						}
						else if( $value.is('input[type="checkbox"]') )
							v = $value.prop('checked');
						else if( $value.is('input[type="radio"]') )
							v = $value.filter(':checked').val();
						else
							v = $value.val();
						if( v )
							data['value'] = v;
						
						var_values[ var_name ] = data;
					})
				;
				$variables.text( JSON.stringify( var_values ) );
			});
			
			//Editeur d'une variable
			$this.on('click', '.var_edit', function(e){
				var $input = $(this).parents('.sql_variable:first').find('.var_value:first');
				var variable = $input.attr('var_name');
				var var_type = $input.attr('var_type');
				var var_options = $input.attr('var_options');
				var $wrap = $(this).parents('.sql_variables_wrap:first');
				var $editor = $wrap.find('.var_editor:first');
				if( $editor.length === 0 ){
					$editor = $('<div class="var_editor"></div>')
						.appendTo( $wrap )
					;
				}
				else if( $editor.is(':visible[var_name="' + variable + '"]')){
					$editor.hide();
					return false;
				}
				$editor
					.attr('var_name', variable)
					.html($('<div class="var_editor_header">' + variable + '</div>')
						.append($('<a class="close-box" href=""><span class="dashicons-before dashicons-no"></span></a>')
							.on('click', function(){ $editor.remove(); return false; })
						)
					).show();
				
				var $type = $('<select/>')
					.appendTo(
						$('<div class="var_type"><label>Type</label></div>')
							.appendTo($editor)
					)
					.on('change', function(e){
						$input
							.attr('var_type', var_type = this.value)
							.trigger('change')
						;
						$options.parent().toggle( input_options_types.includes(this.value) );
						refresh_variables.call( this );
					})
				;
				for( var type in input_types){
					$type.append('<option value="' + type + '"'
							+ (var_type == type ? ' selected' : '') + '>'
							+ input_types[type]
						+ '</option>')
					;
				}
				
				var helper;
				var rows = 3;
				switch( var_type ){
				case 'range' :
					rows = 2;
					helper = '1ère ligne : mini'
							+ '<br>2nde ligne : maxi'
							+ '<br>ou une seule ligne : le maxi';
					break;
				case 'report' :
					helper = 'Pour une inclusion dans un IN,'
						+ '<br>ajoutez le format %IN à la variable.';
					break;
				case 'asc_desc':
					var_options = '';
					break;
				default :
					if( input_options_types.includes(var_type) ){
						helper = 'Un élément par ligne.'
								+ '<br>Séparez les valeurs des labels par <code>:</code>';
					}
				}
				var $options = $('<textarea rows="' + rows + '"></textarea>')
					.appendTo(
						$('<div class="var_options"><label>Options</label></div>')
							.appendTo($editor)
							.toggle( input_options_types.includes(var_type) )
					)
					.text(var_options)
					.on('change', function(e){
						$input
							.attr('var_options', this.value)
							.trigger('change')
						;
						refresh_variables.call( this );
					})
				;
				if( helper )
					$editor.append('<div class="learn-more">' + helper + '</div>')
				
				return false;
			});
		
			//refresh_variables
			function refresh_variables(){
				var $this = $(this);
				if( $this.is('.var_editor :input') ){
					var $editor = $this.parents('.var_editor:first');
					var current_variable = $editor.attr('var_name');
				}
				
				var sql = $sql.val();
				
				//comments
				var pattern = "(\\/\\*[\s\S]+\\*\\/)"; 
				sql = sql.replaceAll( new RegExp(pattern, "g"), ' ' );
				
				//strings
				pattern = '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/';//"\"([^\"]+)\""; //TODO simple quote
				sql = sql.replaceAll( new RegExp(pattern, "sg"), '""' );
				
				
				//Valeurs actuelles des variables
				var var_values = $variables.val();
				if( var_values ){
					try{
						var_values = JSON.parse(var_values);
					}
					catch(ex){
						alert("Erreur de format des variables.\n" + ex);
						var_values = {};
					}
				}
				else
					var_values = {};
				
				//container
				$container = $variables.nextAll('.sql_variables_wrap:first');
					if( $container.length === 0 )
						$container = $('<div class="sql_variables_wrap"></div>').appendTo( $variables.parent() );
					else
						$container.html('');
					
				//Variables présentes dans la requête
				var allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?';
				pattern = "\:([a-zA-Z_][a-zA-Z0-9_]*)(%("+allowed_format+")?[sdfFiIKJ][NLRT]?)?";
				var matches = sql.matchAll( new RegExp(pattern, "g") );
				if( matches )
					matches = matches.toArray();
				if( matches && matches.length > 0 ){
					var variables = {};
					var index = 0;
					for(var i in matches){
						var variable = matches[i][1];
						if( ! variables[variable] ){
							if( variables[variable] === undefined )
								variables[variable] = {};
							var value = var_values[variable];//get_post_meta( $report_id, $meta_key, true );
							if( typeof value === 'object' )
								variables[variable] = value;
							if( matches[i][2] )
								variables[variable]['format'] = matches[i][2];
						}
					}
					
					//Affichage des variables
					for(var variable in variables){
						var value, type, options;
						if( variables[variable] ){
							value = variables[variable]['value'];
							type = variables[variable]['type'];
							if( options = variables[variable]['options'] )
								options = options.split('\n');
						}
						else {
							value = type = options = undefined;
						}
						if( options === undefined )
							options = [];
						//create input
						var $input;
						switch(type){
							case 'table' :
								if( ! options || options.length === 0 )
									options = ['posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'users', 'usermeta', ''];
							case 'select' :
								$input = $('<select></select>');
								$input = add_input_options($input, options, variable, value);
								break;
							case 'field' :
								$input = $('<select></select>');
								if( ! options || options.length === 0 )
									options = get_sql_fields;
								
								$input = add_input_options($input, options, variable, value);
								break;
							case 'bool' :
							case 'boolean' :
							case 'checkbox' :
								$input = $('<input type="checkbox">')
									.prop( "checked", value );
								break;
							case 'radio' :
								$input = $('<input type="radio">');
								$input = add_input_options($input, options, variable, value);
								break;
							case 'checkboxes' :
								if( value && typeof value === "string" )
									value = value.split('\n');
								$input = $('<input type="checkbox">');
								$input = add_input_options($input, options, variable, value);
								break;
							case 'integer' :
							case 'number' :
								$input = $('<input type="number">')
									.val( value );
								break;
							case 'decimal' :
							case 'float' :
								$input = $('<input type="float">')
									.val( value );
								break;
							case 'range' :
								var min = 0;
								var max = 100;
								if( typeof options === 'object' ){
									if( options.length > 1 ){
										min = options[0];
										max = options[1];
									}
									else {
										max = options[0];
									}
								}
								else if( jQuery.isNumeric( options ) )
									max = options;
								$input = $('<input type="range" min="' + min + '" max="' + max + '">')
									.val( value );
								break;
							case 'date' :
								$input = $('<input type="date">')
									.val( value );
								break;
							case 'datetime' :
								$input = $('<input type="datetime-local">')
									.val( value );
								break;
							case 'time' :
							case 'hour' :
								$input = $('<input type="time">')
									.val( value );
								break;
							case 'forum' :
							case 'newsletter' :
							case 'report' :
								var options = {};
								var post_type;
								
								switch(type){
									case 'forum' :
										post_type = 'page';
										break;
									case 'newsletter' :
										post_type = 'agdpnl';
										break;
									case 'report' :
										post_type = 'agdpreport';
										break;
								}
								$input = $('<select></select>');
								//Ajax asynchronous fill
								if( ! value ){ //chargement asynchrone lors du mousedown
									value = '';
									$input = add_input_options($input, admin_report_get_posts, variable, value
										, [ post_type, options, add_input_options,
											{ 	'variable' : variable,
												'value' : value,
												'$input' : $input,
											}
										]);
								}
								else { //chargement asynchrone, maintenant
									$input.append($('<option selected>' + value + '</option>').attr('value', value));//temporairement
									admin_report_get_posts( post_type, options, add_input_options, { '$input': $input, 'variable': variable, 'value': value } );
								}
								options = false;
								break;
							case 'asc_desc' :
								$input = $('<select></select>');
								$input = add_input_options($input, {ASC: 'Croissant', DESC: 'Décroissant'}, variable, value);
							
							case 'longtext' :
								$input = $('<textarea></textarea>')
									.val( value );
							
								break;
							default:
								$input = $('<input type="' + type + '"/>')
									.val( value );
						}
						if( ! options
						 || typeof options === "function")
							options = '';
						else if( typeof options === "object" ){
							if( options.join !== undefined )
								options = options.join('\n');
							else
								options = Object.keys(options).join('\n');
						}
						
						$input
							.addClass( 'var_value' )
							.attr( 'var_name', variable )
							.attr( 'var_type', type )
							.attr( 'var_options', options )
						;
						$('<div class="sql_variable"></div>')
							.append('<label>:' + variable + '</label>')
							.append('<a class="var_edit" href=""><span class="dashicons-before dashicons-edit"></span></a>')
							.append($input)
							.appendTo( $container )
						;
					}
					
					if( current_variable ){
						//click sur l'édition de la variable
						$('.var_value[var_name="' + current_variable + '"]')
							.parents('.sql_variable:first')
								.find('.var_edit')
									.trigger('click');
					}
				}
				else {
					$container.html('<small>(aucune variable)</small>');
				}
			};
		
			//get_posts
			function admin_report_get_posts( post_type, options, callback, callback_options ){
				if( options && options['clear_cache']
				 || ! admin_report_get_posts._cache)
					admin_report_get_posts._cache = {};
					
				if( ! options ) options = {};
				options['post_type'] = post_type;
					
				if( admin_report_get_posts._cache[post_type] ){ //cache
					if( callback ){
						if( callback === add_input_options )
							return callback.call( this, callback_options['$input'], admin_report_get_posts._cache[post_type], callback_options['variable'], callback_options['value']);
						else
							return callback.call( this, admin_report_get_posts._cache[post_type], callback_options );
					}
					return admin_report_get_posts._cache[post_type];
				}
				if( callback_options['$input'] )
					callback_options['$input'].addClass('cursor_pointer');
				
				//Ajax request
				var data = {
					action : "agendapartage_posts_action",
					method : "get_posts",
					contentType: "application/json; charset=utf-8",
					data: JSON.stringify(options) //needs stripslashes() at server side
				};
				jQuery.ajax({
					url : agdp_ajax.ajax_url,
					method : 'POST',
					data : Object.assign(data, {
						_nonce : agdp_ajax.check_nonce
					}),
					success : function( response ) {
						admin_report_get_posts._cache[post_type] = response;
						if( response[''] === undefined )
							response[''] = '';
						if( callback === add_input_options )
							callback.call( this, callback_options['$input'], response, callback_options['variable'], callback_options['value']);
						else
							callback.call( this, response, callback_options );
						
						if( callback_options['$input'] )
							callback_options['$input'].removeClass('cursor_pointer');
						
					},
					fail : function( response ){
						console.log( "agdp-admin-report.js / ajax.get_posts : \n" + response );
					}
				});
				return false;
			}
			////////////////////
			// add_input_options
			function add_input_options($input, options, variable, value, callback_options){
				var value_found = false;
				var $last_input = $input;
				
				if( typeof options === 'function' ){
					if( $input.is('select') ){
						//async load on mousedown
						$input.on('mousedown', function(){
							if( typeof options !== 'function' )
								return;
							if( ! callback_options )
								callback_options = [];
							var callback = options;
							options = callback.apply( $input, callback_options );
							if( options && options.jquery )
								return;
							add_input_options($input, options, variable, value);
						});
					}
					else
						options = options.call($input);
				}
				
				$input.html('');
				
				if( typeof options === 'object' ){
					var is_associative = options.join === undefined;
					for(var i in options){
						var opt, label;
						if( is_associative /* ! jQuery.isNumeric( i ) */ ){
							opt = i;
							label = options[i];
						}
						else {
							opt = options[i];
							var separator = opt.indexOf(':');
							if( separator >= 0 ){
								label = opt.substr(separator+1).trim();
								opt = separator ? opt.substr(0, separator).trim() : '';
							}
							else
								label = opt;
						}
						var select_option = false;
						if( typeof value === 'object' ){
							if( value.indexOf( opt ) >= 0 ){
								select_option = true;
								value_found = true;
							}
						}
						else if( opt == value ){
							select_option = true;
							value_found = true;
						}
						if( $input.is('select') ){
							$input.append('<option value="' + opt + '"'
								+ ( select_option ? ' selected' : '')
								+ '>' + label + '</option>');
						}
						else if( $input.is('input[type="radio"]') ){
							$input = $input.add('<label><input type="radio" value="' + opt + '"'
								+ ' name="__' + variable + '"'
								+ ( select_option ? ' checked' : '')
								+ '>' + label + '</label>');
						}
						else if( $input.is('input[type="checkbox"]') ){ //TODO non fonctionnel coté serveur
							$input = $input.add('<label><input type="checkbox" value="' + opt + '"'
								+ ' name="__' + variable + '[]"'
								+ ( select_option ? ' checked' : '')
								+ '>' + label + '</label>');
						}
					}
				}
				if( value && ! value_found ){
					if( $input.is('select') ){
						$input.append('<option value="' + value + '"'
							+ ' selected'
							+ '>' + value + '</option>');
					}
					else if( $input.is('input[type="radio"]') ){
						$input = $input.add('<label><input type="radio" value="' + value + '"'
							+ ' name="__' + variable + '"'
							+ ' checked'
							+ '>' + value + '</label>');
					}
					else if( $input.is('input[type="checkbox"]') ){
						$input = $input.add('<label><input type="checkbox" value="' + value + '"'
							+ ' name="__' + variable + '"'
							+ ' checked'
							+ '>' + value + '</label>');
					}
				}
				if( $input.is('input[type="radio"],input[type="checkbox"]') && $input.length > 1 )
					$input = $input.filter(function( index ) { return index > 0; } );
				
				return $input;
			}
			//add_input_options
			///////////////////
			
			//get_sql_fields
			function get_sql_fields(){
				var fields = {};
				$('.agdpreport tr.report_fields th[field]').each(function(){
					var name = this.getAttribute('field');
					if( name )
						fields[name] = this.textContent ;
				});
				return fields;
			}
		});
		
		//Rendu
		$('#agdp_report-render').each(function(e){
			$(this).on('click', '.report_refresh a', function(e){
				
				var $actionElnt = $(this);
				var $form = $actionElnt.parents('form:first');
				var post_id = $form.find('#post_ID').val();
				var sql = $form.find('#sql').val();
				var sql_variables = $form.find('#sql_variables').val();
				var report_show_sql = $form.find('#report_show_sql').val();
				var $destination = $form.find('.agdpreport');
				if( $destination.length === 0 ){
					var $dest_container = $form.find('#agdp_report-render .inside:first');
					$destination = $('wpbody-content .agdpreport');
					if( $destination.length === 0 )
						$destination = $('<div class="agdpreport"></div>');
					$dest_container.append( $destination );
				}
				var data = {
					action : "agendapartage_report_action",
					method : "report_html",
					post_id : post_id,
					contentType: "application/json; charset=utf-8",
					data: JSON.stringify({ //needs stripslashes() at server side
						sql : sql,
						sql_variables : sql_variables ? JSON.parse(sql_variables) : 0,
						report_id : post_id,
						report_show_sql : report_show_sql,
						skip_styles : true,
					})
				};
				var report_id = $actionElnt
				jQuery.ajax({
					url : agdp_ajax.ajax_url,
					method : 'POST',
					data : Object.assign(data, {
						_nonce : agdp_ajax.check_nonce
					}),
					success : function( response ) {
						if(response){
							if(typeof response === 'string' || response instanceof String){
								if(response.endsWith('null'))
									response = substr(response, 0, response.length-4);
								var $response = $(response);
								var id;
								if( ! (id = $response.attr('id') ) ){
									id = 'report_' + uniqid(6);
									$response.attr('id', id);
								}
								var css = get_report_css( $form, id );
								if( css )
									$response.append( '<style>' + css + '</style>' );
								$form.find('.agdpreport').replaceWith($response);
							}
						}
						else
							$form.find('.agdpreport').html('!');
						$spinner.remove();
					},
					fail : function( response ){
						$spinner.remove();
						var $msg = $('<div class="ajax_action-response alerte"><span class="dashicons dashicons-no-alt close-box"></span>'+response+'</div>')
							.click(function(){$msg.remove()});
						$actionElnt.after($msg);
						// $msg.get(0).scrollIntoView();
					}
				});
				var $spinner = $actionElnt.next('.wpcf7-spinner');
				if($spinner.length == 0)
						$actionElnt.after($spinner = $('<span class="wpcf7-spinner" style="visibility: visible;"></span>'));
				event.preventDefault();  
				return false;
			});
			$(this).on('change', 'textarea#report_css, .report_css :input', refresh_report);
		});

		/**
		 * get_report_css : compile les styles (textarea + terms)
		 */
		function get_report_css( $form, id ){
			var css = $form.find('#agdp_report-render :input[name="report_css"]').val();
			$form.find('#agdp_report-render .report_style_terms :input:checked[data-report-style]').each(function(){
				if( css )
					css += '\n';
				css += '/* report_style_term ' + $(this).text() + ' */\n'
						+  this.getAttribute('data-report-style');
			});
			const regexp = /(\s|,\s?)(table(\s|.))/g;
			css = css.replaceAll( regexp, '$1#' + id + ' > $2' );
			
			return css;
		}
	});

	/**
	 * get_report_css : compile les styles (textarea + terms)
	 */
	function refresh_report(){
		$(this)
			.parents('form:first')
				.find('#agdp_report-render .report_refresh a')
					.trigger('click'); 
	}
});


/**
 * insertAtCaret in textarea
 */
if( ! HTMLTextAreaElement.prototype.insertAtCaret )
HTMLTextAreaElement.prototype.insertAtCaret = function (text) {
  text = text || '';
  if (document.selection) {
    // IE
    this.focus();
    var sel = document.selection.createRange();
    sel.text = text;
  } else if (this.selectionStart || this.selectionStart === 0) {
    // Others
    var startPos = this.selectionStart;
    var endPos = this.selectionEnd;
    this.value = this.value.substring(0, startPos) +
      text +
      this.value.substring(endPos, this.value.length);
    this.selectionStart = startPos + text.length;
    this.selectionEnd = startPos + text.length;
  } else {
    this.value += text;
  }
};