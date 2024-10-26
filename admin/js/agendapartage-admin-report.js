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
			'color' : 'Couleur',
			'forum' : 'Forum',
			'newsletter' : 'Lettre-info',
			'report' : 'Rapport',
			'field' : 'Champ de requête',
			'asc_desc' : 'Ordre de tri',
		};
		var input_options_types = ['select', 'radio', 'checkboxes', 'range', 'field'];
		
		//Init
		$('#agdp_report-inputs').each(function(e){
			var $this = $(this);
			var $sql = $this.find('textarea#sql');
			var $variables = $this.find('textarea#sql_variables');
			
			//Affiche les variables
			$sql.on('change', function(e){
				var sql = this.value;
				
				//comments
				var pattern = "(\\/\\*[\s\S]+\\*\\/)"; 
				sql = sql.replaceAll( new RegExp(pattern, "g"), ' ' );
				
				//strings
				pattern = "\"([^\"]+)\""; //TODO simple quote
				sql = sql.replaceAll( new RegExp(pattern, "g"), '""' );
				
				
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
				
				//Variables présentes dans la requête
				var matches;
				var allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?';
				pattern = "\:([a-zA-Z0-9_]+)(%(?:"+allowed_format+")?[sdfFiI][N]?)?";
				if( matches = sql.matchAll( new RegExp(pattern, "g") ) ){
					matches = matches.toArray();
					var variables = {};
					var index = 0;
					for(var i in matches){
						var variable = matches[i][1];
						if( ! variables[variable] ){
							if( variables[variable] === undefined )
								variables[variable] = {};
							var value = var_values[variable];//get_post_meta( $report_id, $meta_key, true );
							variables[variable] = value;
							if( matches[i][2] )
								variables[variable]['format'] = matches[i][2];
						}
					}
					
					//Affichage des variables
					$container = $variables.nextAll('.sql_variables_wrap:first');
					if( $container.length === 0 )
						$container = $('<div class="sql_variables_wrap"></div>').appendTo( $variables.parent() );
					else
						$container.html('');
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
							case 'select' :
							case 'field' :
								$input = $('<select></select>');
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
							case 'password' :
								$input = $('<input type="password">')
									.val( value );
								break;
							case 'email' :
								$input = $('<input type="email">')
									.val( value );
								break;
							case 'color' :
								$input = $('<input type="color">')
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
								//Asynchronous fill
								admin_report_get_posts( post_type, options, function( posts, options ){
									var value_found = false;
									var value = options.value === undefined ? '' : options.value;
									var $input = options.$input;
									if( posts[''] === undefined )
										posts[''] = '';
									for(var post_id in posts){
										if( post_id == value )
											value_found = true;
										$input.append('<option value="' + post_id + '"'
											+ ( post_id == value ? ' selected' : '')
											+ '>' + posts[post_id] + '</option>');
									}
									if( value && ! value_found )
										$input.append('<option value="' + value + '"'
											+ ' selected'
											+ '>' + value + ' (inconnu !)</option>');
								}, { '$input': $input, 'value': value } );
								options = false;
								break;
							case 'asc_desc' :
								$input = $('<select></select>');
								$input = add_input_options($input, {ASC: 'Croissant', DESC: 'Décroissant'}, variable, value);
							
								break;
							default:
								$input = $('<input/>')
									.val( value );
						}
						if( ! options )
							options = '';
						else if( typeof options === "object" )
							options = options.join('\n');
						
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
				}
			}).trigger('change');
			
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
						$sql.trigger('change');
					})
				;
				for( var type in input_types){
					$type.append('<option value="' + type + '"'
							+ (var_type == type ? ' selected' : '') + '>'
							+ input_types[type]
						+ '</option>')
					;
				}
				
				var helper, rows;
				switch( var_type ){
				case 'range' :
					rows = 2;
					helper = '1ère ligne : mini'
							+ '<br>2nde ligne : maxi'
							+ '<br>ou une seule ligne : le maxi';
					break;
				default :
					rows = 3;
					helper = 'Un élément par ligne.'
							+ '<br>Séparez les valeurs des labels par <code>:</code>';
				}
				var $options = $('<textarea rows="' + rows + '"></textarea>')
					.appendTo(
						$('<div class="var_options"><label>Options</label></div>')
							.appendTo($editor)
							.toggle( input_options_types.includes(var_type) )
					)
					.text(var_options)
					.after('<div class="learn-more">' + helper + '</div>')
					.on('change', function(e){
						$input
							.attr('var_options', this.value)
							.trigger('change')
						;
						$sql.trigger('change');
					})
				;
				
				return false;
			});
		
			//Liste de tables
			$this.on('click', '.sql-helper-tables a', function(e){
				var $sql = $this.find('textarea#sql');
				$sql.get(0).insertAtCaret( $(this).text() );
			});
			
			//get_posts
			function admin_report_get_posts( post_type, options, callback, callback_options ){
				if( options && options['clear_cache']
				 || ! admin_report_get_posts._cache)
					admin_report_get_posts._cache = {};
					
				if( ! options ) options = {};
				options['post_type'] = post_type;
					
				if( admin_report_get_posts._cache[post_type] ){ //cache
					if( callback ){
						return callback.call( this, admin_report_get_posts._cache[post_type], callback_options );
					}
					return admin_report_get_posts._cache[post_type];
				}
				
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
						callback.call( this, response, callback_options );
					},
					fail : function( response ){
						console.log( "agdp-admin-report.js / ajax.get_posts : \n" + response );
					}
				});
				return false;
			}
			
			// add_input_options
			function add_input_options($input, options, variable, value){
				var value_found = false;
				var $last_input = $input;
					
				for(var i in options){
					var opt, label;
					if( ! jQuery.isNumeric( i ) ){
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
			
		});
		
		//Rendu
		$('#agdp_report-render').each(function(e){
			$(this).on('click', '.report_refresh a', function(e){
				
				var $actionElnt = $(this);
				var $form = $actionElnt.parents('form:first');
				var post_id = $form.find('#post_ID').val();
				var sql = $form.find('#sql').val();
				var sql_variables = $form.find('#sql_variables').val();
				var report_show_sql = $form.find('#report_show_sql').prop('checked');
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
						report_show_sql : report_show_sql
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
								$form.find('.agdpreport').replaceWith(response);
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
		});
	});
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