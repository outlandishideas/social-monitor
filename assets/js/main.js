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
	dateOptions : { year: 'numeric', month: 'short', day: 'numeric' },
	state: {
		charts: {},
		chart: null,
		chartData: [],
		colors: {},
		timestamps: [],
		unloading: false,
        badges: [],
		indexFilters: {
			region: null,
			type: null
		}
	},
	templates: {
		post: '\
			<div>\
			    <p class="more"><a href="<%=permalink%>" target="_blank" title="View update"><span class="icon-external-link icon-large"></span></a></p>\
				<div class="<%=icon%>"></div>\
				<div class="content">\
					<%if(!showResponses){%><a target="_blank" href="/presence/view/id/<%=presence_id%>"><%}%>\
						<h4 class="presence-name" data-presence="<%=presence_id%>"><%=presence_name%><%if (presence_type==="twitter")%> <span class="presence-handle">@<%=presence_handle %></span> <%;%></h4>\
					<%if(!showResponses){%></a><%}%>\
					<%=message.replace(/\\n/g, "<br />")%>\
					<p class="date"><%=date%></p>\
				</div>\
				<div class="engagement">\
					<% if(!_.isUndefined(engagement.likes)) { %><p>{{ js.templates.post.likes | translate }}: <%=engagement.likes%></p><% } %>\
					<% if(!_.isUndefined(engagement.comments)) { %><p>{{ js.templates.post.comments | translate }}: <%=engagement.comments%></p><% } %>\
					<% if(!_.isUndefined(engagement.shares)) { %><p>{{ js.templates.post.shares | translate }}: <%=engagement.shares%></p><% } %>\
				</div>\
			</div>',
		postResponse_needed: '\
			<p class="more">\
				<a href="#" class="require-response" title="{{ js.templates.postResponse_needed.title | translate }}">\
					<span class="icon-comment-alt icon-large"></span>\
				</a>\
			</p>\
			<p class="no-response">{{ js.templates.postResponse_needed.message | translate }} (<%=date_diff%>)...</p>',
		postResponse_notNeeded: '\
			<p class="more">\
				<a href="#" class="require-response" title="{{ js.templates.postResponse_notNeeded.title | translate }}">\
					<span class="icon-comments icon-large"></span>\
				</a>\
			</p>\
			<p class="no-response">{{ js.templates.postResponse_notNeeded.message | translate }}</p>',
		searchArea: '<li class="area">\
						<div class="marker <%=className%>"></div>\
						<input type="hidden" class="lat" name="lat[]" value="<%=lat%>" />\
						<input type="hidden" class="lon" name="lon[]" value="<%=lon%>" />\
						<input type="number" class="radius" name="rad[]" value="<%=radius%>" />\
						<div class="clear"></div>\
					</li>',
		errorPopup: '<div id="popup-holder">\
						<div class="error-popup">\
							<h2>{{ js.templates.errorPopup.message | translate }}</h2>\
							<p class="error-message"><%=message%></p>\
						</div>\
					</div>',
		message: '<li class="<%=type%>"><%=msg%><span class="close icon-remove-circle icon-large"></span></li>',
		linkBox:
			'<li class="link box <%=type%> split">\
				<input type="hidden" value="<%=id%>" name="<%=name%>" />\
				<a href="<%=url%>" title="<%=hover%>" class="first" target="_blank">\
					<span class="<%=icon%> icon-large"></span>\
					<%=label%>\
				</a>' + // don't put whitespace between the links
				'<a href="#" class="remove-item last">\
					<span class="icon-remove"></span>\
				</a>\
			</li>',
		countryBox:
			'<li class="link box country split">\
				<input type="hidden" value="<%=id%>" name="<%=name%>" />\
				<a href="<%=url%>" class="first" target="_blank">\
					<div class="sm-flag flag-<%=flag%>"></div>\
					<%=label%>\
				</a>' + // don't put whitespace between the links
				'<a href="#" class="remove-item last">\
					<span class="icon-remove"></span>\
				</a>\
			</li>',
		countryListItem:
			'<li data-id="<%= id %>" data-badge>\
				<a href="#"><span class="name"><%= n %></span> <span class="score" data-badge-score="%"></span></a>\
			</li>',
		emptyCountryBadge:
			'<div class="badge-small" data-badge>\
			    <h3><%= name %></h3>\
                <div class="badge-score bd-btm">\
	                <h4><span data-badge-title></span> {{ Global.score | translate }}</h4>\
                    <div class="score-value">0</div>\
                        <div class="score-bar"></div>\
                    </div>\
                    <div class="bd-btm">\
	                <h4>{{ Global.presences | translate }}</h4>\
	                <p>{{ js.templates.emptyCountryBadge.message | translate }}</p>\
                </div>\
            </div>',
		globalScore:
			'<div id="overall-score" class="badge-small" data-country-id="0" data-badge data-score="0">\
				<h3> {{ js.templates.globalScore.title | translate | replaceCompany }}</h3>\
				<div class="badge-score bd-btm">\
					<h4><span data-badge-title>{{ badge.total.title | translate }}</span> {{ Global.score | translate }}</h4>\
					<div class="score-value" data-badge-score="%"></div>\
				</div>\
			</div>\
			<div id="overall-fans" class="badge-small" data-country-id="0" data-badge data-score="0">\
				<div class="badge-score">\
					<h4><span data-badge-title>{{ badge.total.title | translate }}</span> {{ js.templates.globalScore.fans | translate }}</h4>\
					<div class="score-value" data-badge-score></div>\
				</div>\
				<div class="bd-btm">\
					<div>\
						{{ js.templates.globalScore.description | translate | replaceCompany }}\
					</div>\
				</div>\
			</div>\
			<div id="total-presences" class="badge-small" data-country-id="0" data-badge data-score="N/A">\
				<div class="badge-score bd-btm">\
					<h4>{{ js.templates.globalScore.total-presences-description | translate }}</h4>\
					<div class="score-value" data-badge-score></div>\
				</div>\
			</div>'
	}

});

/**
 * Initialisation functions
 */
app.init = {
	//application entry point, called at end of this file
	bootstrap: function() {
		$('a.autoConfirm, .button-delete').on('click', app.autoConfirm.ask);

		//randomise order of colours
		app.colors.sort(function () { return 0.5 - Math.random() });

		$(window).on('beforeunload', function(){
			app.state.unloading = true;
		});

		app.datatables.init();

		//run conditional init functions if selector exists on page
		for (var selector in app.init.selectors) {
			var $item = $(selector);
			if ($item.length) {
				app.init.selectors[selector]($item);
			}
		}

	},

	//selector-based init functions, called from bootstrap
	selectors: {

		// hide modal when click outside the feedback form
		'body': function($item) {
			$item.mouseup(function (e)
			{
				var container = $("#feedback-form,ol.messages");

				if (!container.is(e.target) // if the target of the click isn't the container...
					&& container.has(e.target).length === 0) // ... nor a descendant of the container
				{
					app.modal.hide();
				}
			});
		},

		"#joyride-presence": function($item) {
			var id = $item.attr('id');
			$item.foundation('joyride', 'start', app.joyride.config(id));
		},

		"#joyride-home": function($item) {
			var id = $item.attr('id');
			$(document).foundation('joyride', 'start', app.joyride.config(id));
		},

		'#feedback button': function($item) {
		    $item.on('click', app.modal.show);
		},

		'.downloadChart': function($item) {
			$item.on('click', '.link', app.charts.grabSvgElement);
		},

		'#homepage-tabs': function ($item) {
			app.home.setup()
		},

		'#filter-region': function ($item) {
			app.utils.setupTableFilter($item, 'region', 'filter-region', ['', 'No Region'], '{{ js.datatables.filter.region | translate }}');
		},

		'#filter-group' : function ($item) {
			app.utils.setupTableFilter($item, 'group', 'filter-group', [''], '{{ js.datatables.filter.group | translate }}');
		},

		'#filter-presence-type': function ($item) {
			app.utils.setupTableFilter($item, 'presence-type', 'filter-presence-type', ['', 'N/A'], '{{ js.datatables.filter.presence-type | translate }}');
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

        '.desc-box': function($items) {
            $items.each(function(){
                var $item = $(this);
                $item.on('click', 'h3 [class^="icon-"]', function(){
                    $items.toggleClass('min').find('h3 [class^="icon-"]').toggleClass('icon-rotate-180');
                });
            });
        },

		'.social-monitor-multi-select-box': function($items) {
			$items.each(function() {
				var $box = $(this);
				var $select = $box.find('select.social-monitor-multi-select');
				var multiple = ($select.attr('multiple') === 'multiple');
				var showFilters = $select.data('show-filters');

				var onChange = function() {
					if(multiple) { // we don't need to show the summary for single value selects
						var summary = app.utils.summariseSelectedOptions($select);
						$box.find('.selected-summary').html(summary);
					}

					var value = $select.val();
					if (value && value.length == $select.find('option').length) {
						value = 'all';
					}
					if (showFilters) {
						// only show the corresponding other filter
						_.each(showFilters, function(filterId, key) {
							var active = key == value;
							var $otherSelect = $('#' + filterId);
							$otherSelect.closest('.social-monitor-multi-select-box').toggle(active);
							if (active) {
								var otherValue = $otherSelect.val();
								if (otherValue && otherValue.length == $otherSelect.find('option').length) {
									otherValue = 'all';
								}
								app.statuses.search($otherSelect.attr('name'), otherValue);
							}
						});
					}
					app.statuses.search($select.attr('name'), value);
				};

				$select.multipleSelect({
					single: !multiple,
					selectAllDelimiter: ['<span>', '</span>'],
					onClose: function() {
						// bug with multi-select component – wait for $select.val() to update
						setTimeout(onChange, 1);
					}
				});
				if(multiple) {
					$select.multipleSelect('checkAll');
					var $button = $('<button></button>');
					$button.text('Done');
					$button.click(function() {
						$select.multipleSelect('close');
					});
					var $component = $box.find('.ms-drop');
					$component.append($button);

					var summary = app.utils.summariseSelectedOptions($select);
					$box.find('.selected-summary').html(summary);
				}

				onChange();
			});

			$(document).trigger('dataChanged');
		},

		'#date-picker': function($item) {
            var quarter = app.date.lastQuarter();
			$item.daterangepicker({
				dateFormat:jsConfig.dateFormat,
				onClose:app.date.updated,
				presets: {
					specificDate: '{{ js.date-picker.specific-date | translate }}',
					dateRange: '{{ js.date-picker.date-range | translate }}'
				},
				presetRanges: [
					{
						text:'{{ js.date-picker.last-30-days | translate }}',
						dateStart:function () {
							return Date.today().addMonths(-1);
						},
						dateEnd:'Today'
					},
					{
						text:'{{ js.date-picker.last-7-days | translate }}',
						dateStart:'-6 days',
						dateEnd:'Today'
					},
					{
						text:'{{ js.date-picker.month-to-date | translate }}',
						dateStart:'1',
						dateEnd:'Today'
					},
					{
						text:'{{ js.date-picker.last-month | translate }}',
						dateStart:function () {
							return Date.today().moveToFirstDayOfMonth().addMonths(-1);
						},
						dateEnd:function () {
							return Date.today().moveToFirstDayOfMonth().addDays(-1);
						}
					},
                    {
                        text: '{{ js.date-picker.last-quarter | translate }}',
                        dateStart: function() {
                            return quarter[0];
                        },
                        dateEnd: function() {
                            return quarter[1]
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

		'#new-chart': function($item) {
			app.newCharts.setup();
		},

		'input#query, input#slug': function($item) {
			//set list/search name to slug/query value when creating it
			$item.blur(function () {
				$('#name:text[value=""]').val($(this).val())
			});
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
				var name = (type ? 'assigned['+type+'][]' : 'assigned[]');
				for (var i=0; i<currentValues.length; i++) {
					if (currentValues[i].name == name && currentValues[i].value == id) {
						return false;
					}
				}
				return true;
			};
			var updateNoneFound = function() {
				$form.find('.none-found').toggle($form.find('.link.box').length == 0);
			};
			var updateAddButton = function() {
				var type = $(this).data('type');
				var val = $(this).val();
				$(this).closest('.row').find('.add-item').css('visibility', (val != '' && canAdd(val, type)) ? 'visible' : 'hidden');
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
					var $select = $(this).closest('.row').find('select');
					var $selected = $select.find('option:selected');
					var type = $select.data('type');
					var id = $selected.val();
					if (id != '' && canAdd(id, type)) {
						var args = $selected.data();
						args.id = id;
						args.name = (type ? 'assigned['+type+'][]' : 'assigned[]');
						args.icon = $select.data('icon');
						args.type = type;
						var template = (type ? app.templates.linkBox : app.templates.countryBox);
						$(_.template(template, args)).appendTo($('#assigned'));
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
		'#edit-user': function ($form) {
			$form.submit(function() {
				$form.find('select').removeAttr('disabled');
			});
		},
		'#edit-group': function($form) {
			$form.on('click', '.link.box .remove-item', function(e) {
				e.preventDefault();
				$(this).closest('.link.box').remove();
			});
		},
		'.entity-list-toggle': function($toggle) {
			$toggle.on('click', function(e) {
				$(this).closest('td').find('.entity-list')
					.slideToggle('fast');
			});
			$toggle.trigger('click');
		},
		'.domain-link .toggle-expand': function($togglers) {
			$togglers.on('click', function(e) {
				e.preventDefault();
				var $container = $(this).closest('.domain-link');
				var $list = $container.find('.status-list');
				if ($list.length == 0) {
					$list = $('<div class="status-list"></div>').appendTo($container).hide();
					var url = app.utils.baseUrl() + 'domain/status-list/id/' + $container.data('domain-id') + '?url=' + $container.data('url');
					$list.load(url, function(event) {
						console.log(event);
					});
				}
				$(this).toggleClass('open');
				$list.slideToggle();
			});
		},
		'#campaign-switcher, #presence-switcher': function($select) {
			$select.on('change', function() {
				window.location = $select.data('url-template').replace('the_id', $select.val());
			});
		},
		'ol.messages .close': function($closeBtn) {
			$closeBtn.on('click', function() {
				$(this).parent('li').remove();
			})
		},
		'#report-download': function($downloadBtn) {
            $(document)
                .ready(function() {
                    updateDownloadButton($downloadBtn);
                })
                .on('dateRangeUpdated', function() {
                updateDownloadButton($downloadBtn);

            });
            var updateDownloadButton = function(element) {
                var dateRange = app.state.dateRange.map(function(d){
                    return $.datepicker.formatDate('yy-mm-dd', Date.parse(d));
                });

                var href = element.data('href');
                var from = dateRange[0];
                var to = dateRange[1];

                href += '/from/' + from;
                href += '/to/' + to;

                element.attr('href', href);
            }
		},
		'#presence-edit-form .presence-types input[type=radio]': function($inputs) {
			// only show the hint that is relevant to the presence type
			var $hints = $('.presence-handle-hints .formHint');
			$inputs.on('change', function() {
				$hints.hide();
				$hints.filter('.hint-' + $(this).val()).show();
			});
		}

	}
};


app.date = {
    lastQuarter: function() {
        var month = Date.parse('today').toString('MM');
        var quarter = Math.floor(((month-1)/3));
        var lastQuarter = quarter == 0 ? 4 : quarter-1;
        var quarterMonth = quarter;
        if (lastQuarter == 0) {
            quarterMonth = 0
        } else if (lastQuarter == 1) {
            quarterMonth = 3;
        } else if (lastQuarter == 2) {
            quarterMonth = 6;
        } else if (lastQuarter == 3) {
            quarterMonth = 9;
        }
        var year = lastQuarter == 4 ? Date.parse('today').add(-1).years().toString('yyyy') : Date.parse('today').toString('yyyy');

        var firstDate = new Date(year, quarterMonth, 1);
        var lastDate = new Date(year, quarterMonth, 1).add(2).months().moveToLastDayOfMonth();

        return [firstDate, lastDate];
    },
	/**
	 * Parse dates in text box, convert to timestamps and save to server
	 */
	updated: function() {
		//need to delay update a tick to allow input box to update
		setTimeout(function(){
			//parse input date
			var dates = $('#date-picker').val().split(' - ').map(Date.parse);
			//duplicate if only one date
			if (dates.length == 1) {
                dates[1] = new Date(dates[0]);
                //move second date to end of day
                dates[1].add(1).days();
            }

			var dateRange = dates.map(function(d){ return $.datepicker.formatDate('yy-mm-dd', d);});

			//save new date
			app.api.post('index/date-range', { dateRange: dateRange });

			//update chart(s)
			app.state.dateRange = dateRange;
			$(document).trigger('dateRangeUpdated');

            // update statuses table
            app.statuses.search('dateRange',dateRange);

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
		if (app.state.unloading) {
			return; //don't show errors caused by navigation away from page
		}

		var data = $.parseJSON(response.responseText);
		var message = '';
		if (!data) {
//			message = 'AJAX error';
		} else if ($.isArray(data.error)) {
			message = data.error.join("<br />");
		} else {
			message = data.error;
		}
		if (message) {
			app.flashMessenger.show(message, 'error');
		}
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
		var $form = $('<form method="post" action="'+$(this).attr('href')+'">' +
			'{{ js.auto-confirm.are-you-sure | translate }} <input type="submit" value="'+$(this).text()+'"'+title+' class="button-bc inline"> ' +
			'<a href="#">{{buttons.common.cancel | translate}}</a>' +
			'</form>');
		$form.on('click', 'a', app.autoConfirm.cancel);
		$(this).hide().after($form);
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
		$form.prev().show();
		$form.remove();
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
		var $el = $(_.template(app.templates.message, {type: type, msg: msg}));
		var $closeBtn = $el.find('.close');
		$closeBtn.on('click', function() {
			$(this).parent('li').remove();
		});
		$el.appendTo($container).hide().fadeIn();
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
    },
	summariseSelectedOptions: function($select) {
		var texts = $select.multipleSelect('getSelects', 'text');
		var summary = $('<div></div>');
		var total = $select.find('option').length;
		if(texts && texts.length > 0 && texts.length < total) { // don't show a summary when all selected
			texts = texts.map(function(text) {
				return text.trim();
			});

			// if we have selected options, we know that the summary starts with the first option
			var summaryText = texts[0];
			var summaryAnchor = '';
			var hiddenText = ''; // hidden behind <a>

			// we have this many texts left to add, which is coincidentally the index of the last element
			var lastIndex = texts.length - 1;

			// set this to 1 so we don't repeat the first album
			var i = 1;
			var hidden = 0;

			// this adds on all texts up to and NOT including the last text. limit to 3.
			while(i < lastIndex && i<3) {
				summaryText += ', ' + texts[i];
				i++;
			}

			// this puts the rest of the texts in the hidden span revealed by clicking the <a>
			while(i < lastIndex) {
				hiddenText += ', ' + texts[i];
				i++;
				hidden++;
			}

			// finally, if the last index is not 0 we add the last text on (if it is 0, it means we only have one text)
			if(lastIndex > 0) {
				// don't show more for just one item
				if(hidden === 0) {
					summaryText += ' and ' + texts[lastIndex];
				} else {
					hiddenText += ' and ' + texts[lastIndex];
					hidden++; // we have one more hidden item: texts[lastIndex]
					summaryAnchor = $(document.createElement('span'));
					var showMore = $(document.createElement('a'));
					showMore.attr('href','#');
					showMore.text(' and '+(hidden)+' more');
					showMore.click(function() {
						summaryAnchor.text(hiddenText);
					});
					summaryAnchor.append(showMore);
				}
			}
			summary.append(summaryText);
			summary.append(summaryAnchor);
		} else if(texts && texts.length===0) {
			summary = '';
		} else {
			summary = '{{ js.summariseSelectedOptions.all | translate }} ' + $select.data('label');
		}
		return summary;
	},
	setupTableFilter: function($parent, dataName, selectName, emptyValues, defaultValue) {
		var $table = $('table.dataTable').dataTable();
		var options = [];
		var column = null;

		$table.api().columns().every( function () {
			if ($(this.header()).data('name') == dataName) {
				column = this;

				column.data().unique().sort().each( function ( d ) {
					if (emptyValues.indexOf(d) < 0) {
						options.push(d);
					}
				} );
			}
		} );

		if (options.length > 1) {
			var $select = $('<select class="button-bc" name="' + selectName + '"></select>');
			$select.on('change', function () {
				var val = $.fn.dataTable.util.escapeRegex(
					$(this).val()
				);

				column
					.search(val ? '^' + val + '$' : '', true, false)
					.draw();
			});
			$select.append('<option value="">' + defaultValue + '</option>');
			options.forEach(function(option) {
				$select.append('<option value="' + option + '">' + option + '</option>');
			});
			$select.appendTo($parent)
		} else {
			$parent.closest('li').remove();
		}
	}
};

app.statuses = {
	init: function($div) {
		// setup query
		var types = $div.find('select#type').val();
		if(types) {
			app.datatables.query['presence-type'] = types.join();
		}
	},
	search: function(queryParam, value) {
		if (value != app.datatables.query[queryParam]) {
			app.datatables.query[queryParam] = value;
			$(document).trigger('dataChanged');
		}
    }
};

app.modal = {
	show: function() {
		$('#modal-container,#modal-backdrop').fadeIn();
	},
	hide: function() {
		$('#modal-container,#modal-backdrop').fadeOut();
	}
};

app.joyride = {
	config: function(id) {
		return {
			cookie_monster: !!$.cookie(id),
			cookie_name: id,
			cookie_domain: true,
			abort_on_close: false,
			post_ride_callback: function() {
				$.post('/user/joyride/ride/' + id);
				!$.cookie(id) ? $.cookie(id, 'ridden', { expires: 365 }) : null;
			}
		}
	}
};

app.table = {
	filter: function () {
		var $table = $('table');
		var $rows = $table.find('tbody tr');
		$rows.show();

		var filters = app.state.indexFilters;

		if (filters.region || filters.type) {
			$rows.hide();

			if (filters.region) {
				$rows = $rows.filter('[data-region="' + filters.region +'"]');
			}

			if (filters.type) {
				$rows = $rows.filter('[data-type="' + filters.type +'"]');
			}

			$rows.show();
		}

		$rows
			.removeClass('odd').removeClass('even')
			.filter(':visible')
			.filter(':odd').addClass('odd').end()
			.filter(':even').addClass('even');
	}
};

app.feedbackForm = {
	clear: function() {
		var form = $('#feedback-form');

		form.find('#name, #from, #body').val('');
	},
	validate: function() {
		var form = $('#feedback-form');

		var name = form.find('#name').val();
		var from = form.find('#from').val();
		var body = form.find('#body').val();

		if(!name) {
			return '{{ js.feedbackForm.missing.name | translate }}';
		}
		if(!from) {
			return '{{ js.feedbackForm.missing.email | translate }}';
		}
		if(!body) {
			return '{{ js.feedbackForm.missing.body | translate }}';
		}
		return null;
	},
	send: function() {
		var error = app.feedbackForm.validate();
		if(error) {
			app.flashMessenger.show(error,'error');
			return;
		}

		var form = $('#feedback-form');

		var url = form.find('#url').val();

		var data = {
			name: form.find('#name').val(),
			from: form.find('#from').val(),
			body: form.find('#body').val(),
			url: url
		}

		var tokenField = form.find('#token');
		data[tokenField.attr('name')] = tokenField.val();

		$.post(url, data, function(response) {
			if(response.data.success) {
				app.flashMessenger.show('{{ js.feedbackForm.message.success | translate }}');
				app.feedbackForm.clear();
				app.modal.hide();
			} else {
				app.flashMessenger.show(response.data.error || '{{ js.feedbackForm.message.error | translate }}','error');
			}
		});
	}
};

//start it up
app.init.bootstrap();