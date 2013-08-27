/**
 * Global app object holds all functions and app state
 */
var app = app || {};

/**
 * JS config should be defined in page
 */
var jsConfig = jsConfig || {};

$.extend(app, {
	colors: ['CD3667', '177AB9', '22B5E9', '47918E', '8EBD3D', '645691', 'FF0000', '0000FF', 'FFFF00', 'FF00FF'],
	state: {
		charts: {},
		chartData: [],
		colors: {},
		timestamps: [],
		unloading: false,
        barDate: [],
        badges: [],
        groupCharts: [],
        regionCharts: []
	},
	templates: {
		legendLabel: '<div class="dataset <%=className%>" data-line-id="<%=line_id%>">\
				<span class="icon" style="background-color: <%=color%>"></span>\
				<span class="text"><%=name%><% if(count) {%> (<%=count%>)<% } %></span>\
				<div class="buttons"><span class="choose"></span><span class="close"></span></div></div>',
		statusOverlay: '<div id="status-overlay"><b>Twitter API status for <%=type%></b><br>'+
				'<%if(status!="unknown"){%><%=hits%> hits remaining<br>Reset in <%=reset%> minutes<%}else{%>Status is unknown because there have been no recent requests<%}%><br>' +
				'<a href="<%=url%>">Deauthorise <%=type%></a></div>',
		addTextSearch: '<tr><td valign="top" colspan="<%=colspan%>" class="dataTables_empty"><span class="add-manual-search link">Add manual search</span></td></tr>',
		tweet:'<p class="more"><a href="<%=twitter_url%>" target="_blank">View on Twitter</a></p>' +
				'<p><%=message.replace(/\\n/g, "<br />")%></p>',
		post:'<p class="more"><a href="<%=facebook_url%>" target="_blank">View on Facebook</a></p>' +
			'<%if(actor_name){%>' +
				'<h4><%if(profile_url){%><a href="<%=profile_url%>" target="_blank"><%=actor_name%></a><%}else{%><%=actor_name%><%}%></h4>' +
				'<%}else{%>' +
				'<h4>Unknown author</h4>' +
				'<%}%><p><%=message.replace(/\\n/g, "<br />")%></p>',
		searchArea: '<li class="area">\
						<div class="marker <%=className%>"></div>\
						<input type="hidden" class="lat" name="lat[]" value="<%=lat%>" />\
						<input type="hidden" class="lon" name="lon[]" value="<%=lon%>" />\
						<input type="number" class="radius" name="rad[]" value="<%=radius%>" />\
						<div class="clear"></div>\
					</li>',
		errorPopup: '<div id="popup-holder">\
						<div class="error-popup">\
							<h2>Error</h2>\
							<p class="error-message"><%=message%></p>\
						</div>\
					</div>',
		message: '<li class="<%=type%>"><%=msg%></li>',
		linkBox:
			'<li class="link box <%=type%> split">\
				<input type="hidden" value="<%=id%>" name="assigned[<%=type%>][]" />\
				<a href="<%=url%>" title="<%=hover%>" class="first" target="_blank">\
					<span class="<%=icon%> icon-large"></span>\
					<%=label%>\
				</a>' + // don't put whitespace between the links
				'<a href="#" class="remove-item last">\
					<span class="icon-remove"></span>\
				</a>\
			</li>',
		audienceTargetRates:
			'<table>\
				<tr><th>Target date</th><% _.each(requiredRates, function(r){ %><td><%=r.date%></td><%})%></tr>\
				<tr><th>Required gain<br />per day</th><% _.each(requiredRates, function(r){ %><td><%=app.utils.numberFixedDecimal(r.rate)%></td><%})%></tr>\
			</table>'
	}

});

/**
 * Initialisation functions
 */
app.init = {
	//application entry point, called at end of this file
	bootstrap: function() {
		$('a.autoConfirm').live('click', app.autoConfirm.ask);
		$('form.autoConfirm a').live('click', app.autoConfirm.cancel);

		//run conditional init functions if selector exists on page
		for (var selector in app.init.selectors) {
			var $item = $(selector);
			if ($item.length) app.init.selectors[selector]($item);
		}

		//randomise order of colours
		app.colors.sort(function () { return 0.5 - Math.random() });

		$(window).on('beforeunload', function(){
			app.state.unloading = true;
		});

		app.datatables.init();
	},

	//selector-based init functions, called from bootstrap
	selectors: {

		'.downloadChart': function($item) {
			$item.on('click', '.link', app.charts.grabSvgElement);
		},

		'form.uniForm': function ($item) {
			$item.uniform();
		},

        '.button.compare': function ($button) {
	        var updateComparison = function() {
		        var $list = $('.compare.list');
		        var $checked = $('input.compare-checkbox:checked');

		        $list.empty().addClass('empty');
		        $button.hide();

		        var ids = [];
		        $checked.each(function() {
			        var id = $(this).val();
			        ids.push(id);
			        $button.show();
			        $list.removeClass('empty');
			        $list.append('<li data-id="'+ id +'">'+$(this).data("name")+'</li>')
		        });
		        $button.attr('href', $button.data('href') + '/id/' + ids.join(','));
	        };
	        updateComparison();
	        $('input.compare-checkbox').on('change', updateComparison);
        },

        '.accordion-btn': function ($item) {

            $item.on('click', function(event){
                event.preventDefault();
                var id = $(this).data('id');
                var $div = $("#"+id);
                var $icon = $(this).find('span');

                $div.slideToggle(function() {
	                $icon.toggleClass('icon-caret-down').toggleClass('icon-caret-up')
                });
            })
        },

		'#date-picker': function($item) {
			$item.daterangepicker({
				dateFormat:jsConfig.dateFormat,
				onClose:app.date.updated,
				presets: {
					specificDate: 'Specific date',
					dateRange: 'Date range'
				},
				presetRanges: [
					{
						text:'Last 30 days',
						dateStart:function () {
							return Date.today().addMonths(-1);
						},
						dateEnd:'Today'
					},
					{
						text:'Last 7 days',
						dateStart:'-6 days',
						dateEnd:'Today'
					},
					{
						text:'Month to date',
						dateStart:'1',
						dateEnd:'Today'
					},
					{
						text:'Last month',
						dateStart:function () {
							return Date.today().moveToFirstDayOfMonth().addMonths(-1);
						},
						dateEnd:function () {
							return Date.today().moveToFirstDayOfMonth().addDays(-1);
						}
					}
				],
				latestDate: 'Today'
			});
			app.state.dateRange = $item.data('date-range');
		},

		'#api-calendar': function($item) {
			app.state.dateRange = $item.data('date-range');
		},

		'#charts': function($item) {
			app.charts.setup();
		},

        '#badges': function($item) {
            app.badges.setup();
        },

        '#map': function($item) {
            app.geochart.setup($item);
        },

		'input#query, input#slug': function($item) {
			//set list/search name to slug/query value when creating it
			$item.blur(function () {
				$('#name:text[value=""]').val($(this).val())
			});
		},

		'#api-status': function($item) {
			$item.find('span').mouseover(app.apiStatus.show).mouseout(app.apiStatus.hide);
		},

		'.toggler': function($items) {
			// slide when value changes
			$items.on('change', function() {
				var targetSelector = $(this).data('toggle-target');
				if (targetSelector) {
					if ($(this).data('toggle-show')) {
						$(targetSelector).slideDown();
					} else {
						$(targetSelector).slideUp();
					}
					$(targetSelector).trigger('toggled');
				}
			});

			// just toggle at the beginning
			var $currentValue = $items.filter(':checked');
			if ($currentValue) {
				var targetSelector = $currentValue.data('toggle-target');
				if (targetSelector) {
					$(targetSelector).toggle($currentValue.data('toggle-show') == 1);
				}
			}
		},
		'form.management': function($form) {
			var canAdd = function(id, type) {
				var currentValues = $form.serializeArray();
				for (var i=0; i<currentValues.length; i++) {
					if (currentValues[i].name == 'assigned[' + type + '][]' && currentValues[i].value == id) {
						return false;
					}
				}
				return true;
			};
			var updateNoneFound = function() {
				$form.find('.none-found').toggle($form.find('.link.box').length == 0);
			};
			var updateAddButton = function() {
				var type = $(this).closest('select').data('type');
				var val = $(this).val();
				$(this).closest('.ctrlHolder').find('.add-item').css('visibility', (val != '' && canAdd(val, type)) ? 'visible' : 'hidden');
			};
			var updateAddButtons = function() {
				$form.find('select').each(updateAddButton);
			};

			updateNoneFound();
			updateAddButtons();

			$form
				.on('change', 'select', updateAddButton)
				.on('click', '.remove-item', function(e) {
					e.preventDefault();
					$(this).closest('li').remove();
					updateNoneFound();
					updateAddButtons();
				})
				.on('click', '.add-item', function() {
					var $select = $(this).closest('.ctrlHolder').find('select');
					var $selected = $select.find('option:selected');
					var type = $select.data('type');
					var id = $selected.val();
					if (id != '' && canAdd(id, type)) {
						var args = $selected.data();
						args.id = id;
						args.type = type;
						args.icon = $select.data('icon');
						$(_.template(app.templates.linkBox, args))
							.appendTo($('#assigned'));
						updateNoneFound();
						updateAddButtons();
					}
				});
		},
		'#edit-country': function($form) {
			// automatically copy the selected country from the drop-down, if it doesn't yet have a value
			$form.on('focus', '#display_name', function() {
				if (!$(this).val()) {
					var $select = $form.find('select');
					if ($select.val()) {
						$(this).val($select.find('option:selected').text());
						$(this).select();
					}
				}
			});
		},
		'#edit-group': function($form) {
			$form.on('click', '.link.box .remove-item', function(e) {
				e.preventDefault();
				$(this).closest('.link.box').remove();
			});
		},
		'#date-slider': function($sliderDate) {
			var currentDate = $sliderDate.data('current-date');
			var dayRange = parseInt($sliderDate.data('day-range'));
			$( "#slider" ).slider({
				value: dayRange,
				min: 1,
				max: dayRange,
				step: 1,
				slide: function( event, ui ) {
					var days = dayRange - ui.value;
					var now = (new Date(currentDate)).addDays(-days);
					var then = now.clone();
					then.addDays(-dayRange);
					$( "#slider" ).data('val', ui.value);
					$( "#date-slider" ).val( then.toString('dd MMM yyyy') + ' - ' + now.toString('dd MMM yyyy') );
					app.geochart.refreshMap();
                    app.geochart.refreshGroups();
				}
			});

		}
	}
};


/**
 * Twitter API status display
 */
app.apiStatus = {
	/**
	 * Show API status overlay
	 */
	show: function () {
		$('#status-overlay').remove();
		clearTimeout(app.state.apiOverlayTimer);
	
		var $span = $(this);
		var data = $span.data();
		data.url = app.utils.baseUrl() + data.type+'/deauth?return_url='+location;
		var $overlay = $(parseTemplate(app.templates.statusOverlay, data))
				.appendTo('body')
				.css($span.position())
				.mouseover(function () { clearTimeout(app.state.apiOverlayTimer); })
				.mouseout(app.apiStatus.hide);
	},

	/**
	 * Hide API status overlay after a half second delay
	 */
	hide: function() {
		app.state.apiOverlayTimer = setTimeout(function(){
			$('#status-overlay').remove();
		}, 500);
	}
};


app.date = {
	/**
	 * Parse dates in text box, convert to timestamps and save to server
	 */
	updated: function() {
		//need to delay update a tick to allow input box to update
		setTimeout(function(){
			//parse input date
			var dates = $('#date-picker').val().split(' - ').map(Date.parse);
			//duplicate if only one date
			if (dates.length == 1) {dates[1] = new Date(dates[0]);}
			//move second date to end of day
			dates[1].add(1).days();

			var dateRange = dates.map(function(d){ return $.datepicker.formatDate('yy-mm-dd', d);});

			//save new date
			app.api.post('index/date-range', { dateRange: dateRange });

			//update chart(s)
			app.state.dateRange = dateRange;
			$(document).trigger('dateRangeUpdated');
		}, 1);
	},

	/**
	 * Set text box to the dates stored in app.state.dateRange
	 */
	syncFromState: function() {
		var stringDates = app.state.dateRange.map(function(d){
			return $.datepicker.formatDate(jsConfig.dateFormat, Date.parse(d));
		});
		$('#date-picker').val(stringDates.join(' - '));
	}

};

/**
 * Client-server API convenience functions
 */
app.api = {
	post: function (path, args) {
		return $.post(jsConfig.apiEndpoint + path, args, 'json')
				.done(app.api.callback)
				.fail(app.api.errorCallback);
	},
	get: function (path, args) {
		return $.getJSON(jsConfig.apiEndpoint + path, args)
				.done(app.api.callback)
				.fail(app.api.errorCallback);
	},
	callback: function (response) {
		if (response.messages) {
			_.each(response.messages, function(messageData) {
				var keys = _.keys(messageData);
				var vals = _.values(messageData);
				app.flashMessenger.show(vals[0], keys[0]);
			})
		}
	},
	errorCallback: function(response) {
		if (app.state.unloading) return; //don't show errors caused by navigation away from page

		var data = $.parseJSON(response.responseText);
		var message = '';
		if (!data) {
//			message = 'AJAX error';
		} else if ($.isArray(data.error)) {
			message = data.error.join("<br />");
		} else {
			message = data.error;
		}
		if (message) app.flashMessenger.show(message, 'error');
	}
};

/**
 * Add in-line "Are you sure?" style confirmation forms
 */
app.autoConfirm = {
	ask: function(e) {
		e.preventDefault();
		var title = $(this).attr('title');
		if (title) {
			title = ' title="' + title + '"';
		} else {
			title = '';
		}
		$(this).replaceWith('<form method="post" action="'+$(this).attr('href')+'" class="'+$(this).attr('class')+' uniForms">' +
			'Are you sure? <input type="submit" value="'+$(this).text()+'"'+title+' class="inlineAction"> <a href="" class="secondaryAction inline">Cancel</a></form>');
	},

	cancel: function(e) {
		e.preventDefault();
		var $form = $(this).closest('form');
		var title = $form.find('input').attr('title');
		if (title) {
			title = ' title="' + title + '"';
		} else {
			title = '';
		}
		$form.replaceWith('<a href="'+$form.attr('action')+'" class="'+$form.attr('class')+'"'+title+'>' +
			$form.find('input').attr('value') + '</a>');
	}
};

app.flashMessenger = {
	show: function(msg, type) {
		type = type || 'info';
		var $container = $('ol.messages');
		if (!$container.length) {
			$container = $('<ol class="messages"/>').prependTo('#content');
		} else {
			$container.empty();
		}
		$(_.template(app.templates.message, {type: type, msg: msg})).appendTo($container).hide().fadeIn();
	}
};

app.utils = {
	baseUrl:function () {
		return $('meta[name="baseUrl"]').attr('content');
	},
	numberFormat: function(x) {
		var parts = x.toString().split(".");
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
		return parts.join(".");
	},
    numberFixedDecimal: function (x,n){
	    if (typeof(n) == 'undefined') {
		    n = 1;
	    }
        var p = Math.pow(10,n);
        return parseFloat(Math.round(x * p) / p).toFixed(n);
    }
};

//start it up
app.init.bootstrap();