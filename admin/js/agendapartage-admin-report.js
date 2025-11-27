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
			var $variables = $('#agdp_report-variables textarea#sql_variables');
			
			//Liste de tables
			$this.on('click', '.sql-helper-tables a', function(e){
					$sql.get(0).insertAtCaret( $(this).text() );
				})
				// .after($('<span class="dashicons-before dashicons-edit"></span>')
			;
			//Liste de colonnes
			$this.on('click', '.table_columns li', function(e){
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
			$this.on('change', '.var_value', save_vars_values);
			function save_vars_values(){
				var var_values = {};
				$variables.nextAll('.sql_variables_wrap:first')
					.find('.sql_variable:not(.unused)').each(function(e){
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
			}
		
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
				var $options = $('<textarea rows="' + rows + '" spellcheck="false"></textarea>')
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
				var var_values = get_input_jso.call( $variables );
				var var_values_saved = var_values;
				
				//container
				$container = $variables.nextAll('.sql_variables_wrap:first');
					if( $container.length === 0 )
						$container = $('<div class="sql_variables_wrap"></div>').appendTo( $variables.parent() );
					else
						$container.html('');
					
				//Variables présentes dans la requête
				var allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?';
				pattern = "\(?<!\\\\):([a-zA-Z_][a-zA-Z0-9_]*)(%("+allowed_format+")?[sdfFiIKJ][NLRT]?)?";
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
							delete var_values_saved[variable] ;
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
								$input = $('<textarea spellcheck="false"></textarea>')
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
				
				//Variables restantes
				var $notice = $('<ul class="unused_variables"><label>Variables non utilisées</label></ul>');
				var counter = 0;
				for(var variable in var_values_saved){
					if( variable === undefined ) continue;
					if( value = var_values_saved[variable]['value'] )
						value = '= ' + value;
					if( value === undefined ) value = '';
					if( ( options = var_values_saved[variable]['options'] ) && options.length > 50 )
						options = options.substr(0, 50) + '...';
					else if( options === undefined ) options = '';
					if( options ) options = '(' + options + ')';
					if( value && options ) value += ', ';
					$notice
						.append('<li class="sql_variable unused"><label><var>' + variable + '</var></label><code>'+ value + options+'</code></li>');
					counter++;
				}
				if( counter ){
					$notice.prepend( $('<a class="delete"><span class="dashicons-before dashicons-trash"></span></a>')
						.on('click', function(){
							save_vars_values.apply(this);
							$notice.remove();
							return false;
						})
					);
					$container.append($notice);
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
			$(this)
				.on('click', '.report_refresh a', refresh_report )
				.on('change', 'textarea#report_css, .report_css :input', refresh_report)
				.each(refresh_report_menu)
				.each(refresh_report_table_designer)
				// montre le css si il n'est pas vide et que le designer est affiché
				.find('#report_show_table_designer:checked').each(function(){
					$(this).parents('.inside:first')
						.find('.toggle-container.report_css textarea#report_css:not(:empty)')
							.parents('.toggle-container:first')
								.prevAll('.toggle-trigger:not(.active)')
									.trigger('toggle-active')
					;
				}).end()
			;
		});
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

	/**
	 * refresh_report : get data + designer
	 */
	function refresh_report(){
		var $actionElnt = $(this);
		var $form = $actionElnt.parents('form:first');
		var post_id = $form.find('#post_ID').val();
		var sql = $form.find('#sql').val();
		var sql_variables = get_input_jso.call( $form, '#sql_variables' );
		var table_render = get_input_jso.call( $form, '#table_render');
		var report_options = {};
		$form.find('#agdp_report-render :input[name]').each(function(){
			var val;
			if( this.getAttribute('type') === 'checkbox' )
				val = $(this).prop('checked');
			else
				val = this.value;
			report_options[ this.getAttribute('name') ] = val;
		});
		var $destination = $form.find('.agdpreport');
		if( $destination.length === 0 ){
			var $dest_container = $form.find('#agdp_report-render .inside:first');
			$destination = $('wpbody-content .agdpreport');
			if( $destination.length === 0 )
				$destination = $('<div class="agdpreport"></div>');
			$dest_container.append( $destination );
		}
		report_options = Object.assign({}, report_options, {
			sql : sql,
			sql_variables : sql_variables,
			table_render : table_render,
			report_id : post_id,
			skip_styles : true, /* later use of get_report_css() */
		});
		var data = {
			action : "agendapartage_report_action",
			method : "report_html",
			post_id : post_id,
			contentType: "application/json; charset=utf-8",
			data: JSON.stringify(report_options /* //needs stripslashes() at server side */)
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
						$form.find('.agdpreport:eq(0)')
							.nextAll('.agdpreport')
								.remove()
								.end()
							.replaceWith($response);
						$('#wpbody-content > .wrap > .agdpreport.error').remove();
						//masque le menu
						$form.find('#agdp_report-render-menu.active > a.toggler').trigger('click');
						$response.each(refresh_report_table_designer);
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
	}

	/**
	 * refresh_report_menu 
	 */
	function refresh_report_menu(){
		var $this = $(this);
		var $render = $this.is('#agdp_report-render') ? $this : $this.parents('#agdp_report-render:first');
		var $menu_items = $render.find('.report_menu_item');
		var $menu = $menu_items.parent('#agdp_report-render-menu');
		if( $menu.length === 0 ){
			$menu = $('<div id="agdp_report-render-menu"></div>')
				.html( $('<a class="toggler dashicons-before dashicons-menu"></a>')
					.on('click', function(){
						$(this).parent().toggleClass('active');
					})	
				)
				.insertBefore( $menu_items.first() )
				.append( $menu_items )
				
				.append( $('<a class="report_menu_item reset-designer dashicons-before dashicons-trash">Réinitialiser</a>')
					.on('click', function(){ //reset all inputs
						$render.find('textarea[name="table_render"]').text('');
						refresh_report.call($render);
					})
				)
			;
		}
		//hidden_columns
		var table_render = get_input_jso.call($render, 'textarea[name="table_render"]');
		var table_columns = table_render["columns"] ? table_render["columns"] : {};
		var $hidden_columns = $menu.find('.hidden_columns:first');
		$hidden_columns.find('option:gt(0)').remove();
		for( table_column in table_columns ){
			if( table_columns[table_column]['visible'] === undefined
			|| table_columns[table_column]['visible'] )
				continue;
			if( $hidden_columns.length === 0 ){
				$hidden_columns
					= $('<select class="hidden_columns"><option value=""></option></select>')
						.on('change', function(){
							var column = $hidden_columns.val();
							if( ! column ) return;
							set_table_render_option.call( this, 'columns', column, 'visible', true);
							$hidden_columns.children('option[value="' + column + '"]').remove();
							
							var $options = $hidden_columns.children('option');
							if( $options.length <= 1 ){
								$hidden_columns.hide();
								$menu.toggleClass('active');

							} else if( $options.length === 2 )
								$options.filter(':first').text('1 colonne cachée');
							else
								$options.filter(':first').text(($options.length - 1) + ' colonnes cachées');
							
							var $table = $render.find('.agdpreport > table');
							var $th = $table.find('> thead > tr > th[column="' + column + '"]:first');
							var index = $th.prevAll('th').length;
							$table.find('tr').each(function(){
								$(this).find('th:eq(' + index + '), td:eq(' + index + ')')
									.show();
							});
						})
						.appendTo(
							$('<div class="report_menu_item agdp-metabox-row is_admin"></div>')
								.insertBefore( $menu.find('.report_menu_item.reset-designer') )
						)
				;
			}
			var label = typeof table_columns[table_column] === 'object' 
						? (table_columns[table_column]['label'] ? table_columns[table_column]['label'] : table_column)
						: table_columns[table_column]
			$hidden_columns.append('<option value="' + table_column + '">' + label + '</option>');
		}
		var $options = $hidden_columns.children('option');
		if( $options.length <= 1 )
			$hidden_columns.hide();
		else {
			if( $options.length === 2 )
				$options.filter(':first').text('1 colonne cachée');
			else
				$options.filter(':first').text(($options.length - 1) + ' colonnes cachées');
			$hidden_columns.show();
		}
		return true;
	}

	/**
	 * refresh_report_designer 
	 */
	function refresh_report_table_designer(){
		var $this = $(this);
		var $render = $this.is('#agdp_report-render') ? $this : $this.parents('#agdp_report-render:first');
		var $table = $render.find('.agdpreport > table');
		if( $table.length === 0 )
			return false;
		var show_table_designer = $render.find('input[name="report_show_table_designer"]:checked').length;
		if( ! show_table_designer ){
			if( $table.is('.report_table_designer') )
				$table
					.removeClass('report_table_designer')
					.find('.report_table_designer')
						.remove();
			return false;
		}
		var $table = $render.find('.agdpreport > table');
		if( $table.length === 0
		|| $table.is('.report_table_designer') )
			return false;
		
		var table_render = get_input_jso.call( $render, 'textarea[name="table_render"]' );
		var columns = table_render["columns"] ? table_render["columns"] : {};
		$table
			.addClass('report_table_designer')
			//Caption
			.children('caption').each(function(){
				var column = 'caption';
				var script = table_render[ column ] && table_render[ column ][ 'script' ] 
					? table_render[ column ][ 'script' ] : '@REPORTTITLE';
				var caption_class = table_render[ column ] && table_render[ column ][ 'class' ] 
					? table_render[ column ][ 'class' ] : '';
							
				$(this)
					.append( $('<div class="report_table_designer"></div>' )
						.attr( 'column', column )
						.append( 'Classe: ' )
						.append( $('<textarea rows="1" cols="15" class="caption_class" title="Classe"></textarea>')
							.attr('title', "Classe de la cellule Caption.\nPar exemple, CONCAT(\'percent-\', ROUND(COUNT(`ID`)/100))")
							.val(caption_class)
							.on('change', save_table_designer)
						)
						.append( '&nbsp;&nbsp;Script: ' )
						.append( $('<textarea rows="1" cols="40" class="caption_script"></textarea>')
							.attr('title', "Script SQL du contenu de la cellule Caption.\nLa valeur par défault est @REPORTTITLE.\nPar exemple, CONCAT(@REPORTTITLE, ' - ', NOW())")
							.val(script)
							.on('change', save_table_designer)
						)
					)
				;
			}).end()
			//Columns
			.find('thead > tr').each(function(){
				var column_index = 0;
				$(this)
					.children('th[column]')
						.each(function(){
							this.innerHTML += '<a class="hide_column column-action dashicons-before dashicons-no-alt" title="Masquer la colonne"></a>';
							var column = this.getAttribute('column');
							this.setAttribute( 'title', column );
							
							$table.find('tbody > tr:first > td:eq(' + column_index + ')')
									.attr('column', column);
									
							if( ! columns[ column ] || ! columns[ column ][ 'label' ] )
								this.innerHTML += '<a class="is_new_column column-action dashicons-before dashicons-info-outline" title="Cette colonne est nouvelle"></a>';
							this.innerHTML += '<a class="move_column move_column_right column-action dashicons-before dashicons-arrow-right" title="Décaler la colonne à gauche"></a>';
							this.innerHTML += '<a class="move_column move_column_left column-action dashicons-before dashicons-arrow-left" title="Décaler la colonne à gauche"></a>';
							
							column_index++;
						})
						//hide_column
						.on('click', '.hide_column', function(){
							var $th = $(this).parents('th:first');
							var column = $th.attr('column');
							set_table_render_option.call( this, 'columns', column, 'visible', false );
							var index = $th.prevAll('th').length;
							$th.parents('table:first')
								.find('tr').each(function(){
									$(this).find('th:eq(' + index + '), td:eq(' + index + ')')
										.hide();
								})
							;
							refresh_report_menu.call( this );
						})
						//move_column
						.on('click', '.move_column', function(){
							var $this = $(this);
							var $th = $this.parents('th:first');
							var column = $th.attr('column');
							var index = $th.prevAll('th').length;
							var direction = $this.is('.move_column_left') ? -1 : +1;
							set_table_render_option.call( this, 'columns', column, 'index', index + direction );
							
							var column_swap;
							if( direction === -1 )
								column_swap = $th.prev('th').attr('column');
							else
								column_swap = $th.next('th').attr('column');
							set_table_render_option.call( this, 'columns', column_swap, 'index', index - direction );
							
							$th.parents('table:first')
								.find('tr').each(function(){
									//TODO colspan
									$(this).find('th:eq(' + index + '), td:eq(' + index + ')')
										.each(function(){
											var $cell = $(this);
											if( direction === -1 )
												$cell.insertBefore( $cell.prev( this.tagName ) );
											else
												$cell.insertAfter( $cell.next( this.tagName ) );
										})
									;
								})
							;
						})
						.end()
					.clone()
						.addClass('report_table_designer')
						.insertAfter(this)
						.children('th[column]')
							.attr('class', '')
							.each(function(){
								var column = this.getAttribute('column');
								var label = this.innerText;
								if( columns[ column ] === undefined )
									columns[ column ] = {};
								else if( columns[ column ][ 'visible' ] === false )
									$(this).addClass('hidden');
								columns[ column ][ 'label' ] = label;
								this.innerHTML = '<input class="column_label" value="' + label + '">';
							})
							.on('change', ':input', save_table_designer)
				;
			}).end()
			//Rows
			.find('tbody > tr:first').each(function(){
				//TODO ajouter une colonne de th avec le label "Classe" ou "Cellule"
				$(this)
					.children('td[column]')
						.each(function(){
							var column = this.getAttribute('column');
							var script = columns[ column ]['script'];
							if( ! script )
								script = '';
							this.setAttribute( 'title', script );
						})
						.on('click', '.is_new_column', function(){
						})
						.end()
					.clone()
						.addClass('report_table_designer')
						.insertBefore(this)
						.children('td[column]')
							.attr('class', '')
							.each(function(){
								var column = this.getAttribute('column');
								if( ! column )
									return;
								var attr_class = columns[ column ]['class'];
								if( ! attr_class )
									attr_class = '';
								if( columns[ column ][ 'visible' ] === false )
									$(this).addClass('hidden');
								var $class = $('<div><div>Classe: <div></div>').append(
									$('<textarea rows="1" class="column_class" spellcheck="false"></textarea>')
										.attr('title', "Classe des cellules de la colonne.\nPar exemple, CONCAT(\'color-\', `meta_value`)")
										.val( attr_class )
									)
								;
								$(this)
									.html($class)
								;
							})
							.on('change', ':input', save_table_designer)
							.end()
						.end()
					.clone()
						.addClass('report_table_designer')
						.insertBefore(this)
						.children('td[column]')
							.attr('class', '')
							.each(function(){
								var column = this.getAttribute('column');
								if( ! column )
									return;
								var script = columns[ column ]['script'];
								if( ! script )
									script = '`' + column + '`';
								var $script = $('<textarea class="column_script" spellcheck="false"></textarea>').val( script );
								if( columns[ column ][ 'visible' ] === false )
									$(this).addClass('hidden');
								$(this)
									.html($script)
								;
							})
							.on('change', ':input', save_table_designer)
							.end()
						.end()
				;
			}).end()
		;
			
	}
	
	/**
	 * set_table_render_option (from textarea[name="table_render"] to textarea[name="table_render"])
	 **/
	function set_table_render_option( domain, column, option, value){
		var $this = $(this);
		var $render = $this.is('#agdp_report-render') ? $this : $this.parents('#agdp_report-render:first');
		var $table = $render.find('.agdpreport > table');
		var $input = $render.find(':input[name="table_render"]');
		var jso = $input.val();
		if( ! jso )
			jso = save_table_designer.call( this );
		else
			jso = get_input_jso.call( $input );
		if( domain ){
			if( ! jso[domain] )
				jso[domain] = {};
			if( ! jso[domain][column] )
				jso[domain][column] = {};
			if( ! option )
				jso[domain][column] = value;
			else
				jso[domain][column][option] = value;
		}
		else {
			if( ! jso[column] )
				jso[column] = {};
			if( ! option )
				jso[column] = value;
			else
				jso[column][option] = value;
		}
		$input.text( JSON.stringify( jso ) );
	}
	
	/**
	 * save_table_designer
	 **/
	function save_table_designer(){
		var $this = $(this);
		var $render = $this.is('#agdp_report-render') ? $this : $this.parents('#agdp_report-render:first');
		var $table = $render.find('.agdpreport > table');
		var $input_table_render = get_input.call( this, 'textarea[name="table_render"]');
		var table_render = {};
		var columns = {};
		var column_index = 0;
		$table
			.find('> caption :input.caption_script, > caption :input.caption_class')
				.each(function(){
					if( ! this.value )
						return;
					var $this = $(this);
					var $caption = $this.parent();
					var column = $caption.attr('column');
					var option = $this.is('.caption_script') ? 'script'
								: ($this.is('.caption_class') ? 'class'
								: false);
					if( table_render[ column ] === undefined )
						table_render[ column ] = {};
					table_render[ column ][ option ] = this.value;
				})
				.end()
			.find('> thead > tr.report_table_designer > th input.column_label')
				.each(function(){
					var $th = $(this.parentNode);
					var column = $th.attr('column');
					var label = this.value;
					var visible = $th.is(':visible') && ! $th.is('.hidden');
					columns[ column ] = {
						label: label,
						visible: visible,
						index: column_index++,
					};
				})
				.end()
			.find('> tbody > tr.report_table_designer > td :input.column_script, > tbody > tr.report_table_designer > td :input.column_class')
				.each(function(){
					if( ! this.value )
						return;
					var $this = $(this);
					var column = $this.parents('[column]:first').attr('column');
					var option = $this.is('.column_script') ? 'script'
								: ($this.is('.column_class') ? 'class'
								: false);
					if( columns[ column ] === undefined )
						columns[ column ] = {};
					columns[ column ][ option ] = this.value;
				})
		;
		if( Object.keys(columns).length )
			table_render['columns'] = columns;
		$input_table_render.text( JSON.stringify( table_render ) );
		return columns;
	}
	
	/**
	 * get_input
	 **/
	function get_input( path = '' ){
		var $this = $(this);
		if( ( path === '' && $this.is(':input') )
		|| $this.is( path ) )
			return $this;
		var $form = $this.is('form') ? $this : $this.parents('form:first');
		if( $form.length === 0 )
			$form = $('form');
		return $form.find( path );
	}
	
	/**
	 * get_input_jso
	 **/
	function get_input_jso( path = '' ){
		var $input = get_input.call( this, path );
		var value = $input.val();
		if( ! value )
			return {};
		try{
			return JSON.parse(value);
		}
		catch(ex){
			try{
				return JSON.parse(value.replaceAll('{','{\n ').replaceAll('}','}\n'));//plus lisible avec un n° de ligne
			}
			catch(ex){
				alert("Erreur de format dans '" + path + "'.val() : " + value + "\n" + ex);
			}
		}
		return {};
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