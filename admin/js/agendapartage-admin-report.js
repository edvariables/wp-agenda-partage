const AGDP_BLOG_PREFIX = '@WP.';

jQuery( function( $ ) {
	
	/**
	 * agdpreport-edit 
	 * 
	 */
	$( document ).ready(function() {
		$('#agdp_report-inputs').each(function(e){
			var $sql = $(this).find('textarea#sql')
			var $variables = $(this).find('textarea#sql_variables')
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
				pattern = "\@([a-zA-Z0-9_]+)(%(?:"+allowed_format+")?[sdfFi])?";
				if( matches = sql.matchAll( new RegExp(pattern, "g") ) ){
					matches = matches.toArray();
					var variables = {};
					var index = 0;
					for(var i in matches){
						var variable = matches[i][1];
						if( AGDP_BLOG_PREFIX === '@' + variable + '.' )
							continue;
						if( ! variables[variable] ){
							var value = var_values[variable];//get_post_meta( $report_id, $meta_key, true );
							variables[variable] = value;
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
							options = variables[variable]['options'];
						}
						var $input;
						switch(type){
							case 'select' :
								$input = $('<select></select>');
								for(var opt in options.split('\n'))
									$input.append('<option value="' + opt + '"'
										+ ( opt == value ? ' selected' : '')
										+ '>' + opt + '</option>');
								break;
							case 'bool' :
							case 'boolean' :
							case 'checkbox' :
								$input = $('<input type="checkbox">')
									.prop( "checked", value );
								break;
							case 'radio' :
								$input = $('<input type="radio">')
									.prop( "checked", value );
								break;
							case 'checkbox' :
								$input = $('<input type="checkbox">')
									.prop( "checked", value );
								break;
							case 'number' :
							case 'numeric' :
								$input = $('<input type="number">')
									.val( value );
								break;
							case 'date' :
								$input = $('<input type="date">')
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
								
							default:
								$input = $('<input/>')
									.val( value );
						}
						$input
							.addClass( 'var_value' )
							.attr( 'var_name', variable )
							.attr( 'var_type', type )
							.attr( 'var_options', options )
						;
						$('<div class="sql_variable"></div>')
							.append('<label>' + variable + '</label>')
							.append('<a class="var_edit" href=""><span class="dashicons-before dashicons-edit"></span></a>')
							.append($input)
							.appendTo( $container )
						;
					}
				}
			}).trigger('change');
			
			//Sauvegarde des variables vers le textarea
			$(this).on('change', '.var_value', function(e){
				var var_values = {};
				$variables.nextAll('.sql_variables_wrap:first')
					.find('.sql_variable').each(function(e){
						var $this = $(this);
						var $value = $this.find('.var_value');
						var data = {};
						var v;
						if( $value.is('input[type="checkbox"]') )
							v = $value.prop('checked');
						else
							v = $value.val();
						if( v )
							data['value'] = v;
						if( v = $value.attr('var_type') )
							data['type'] = v;
						if( v = $value.attr('var_options') )
							data['options'] = v;
						
						var_values[ $value.attr('var_name') ] = data;
					})
				;
				$variables.text( JSON.stringify( var_values ) );
			});
			
			$(this).on('click', '.var_edit', function(e){
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
				$editor.html('<div class="var_editor_header">' + variable + '</div>');
				
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
						$options.parent().toggle( this.value === 'select' || this.value === 'radio' );
						$sql.trigger('change');
					})
				;
				var types = {
					'text' : 'Texte',
					'bool' : 'Case à cocher',
					'number' : 'Nombre',
					'date' : 'Date',
					'time' : 'Heure',
					'select' : 'Sélection',
					'radio' : 'Cases d\'options (à faire)',
					'checkbox' : 'Options multiples (à faire)',
					'password' : 'Mot de passe',
					'email' : 'email',
					'color' : 'color',
				};
				for( var type in types){
					$type.append('<option value="' + type + '"'
							+ (var_type == type ? ' selected' : '') + '>'
							+ types[type]
						+ '</option>')
					;
				}
				
				var $options = $('<textarea></textarea>')
					.appendTo(
						$('<div class="var_options"><label>Options</label></div>')
							.appendTo($editor)
							.toggle( var_type === 'select' || var_type === 'radio' )
					)
					.text(var_options)
					.on('change', function(e){
						$input
							.attr('var_options', this.value)
							.trigger('change')
						;
					})
					
				;
				
				return false;
			});
		});
		
	});
});