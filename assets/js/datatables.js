var app = app || {};

/**
 * DataTable component setup
 * @type {Object}
 */
app.datatables = {
	statusesTable: null,
	query: {},
	init:function () {
        // add a 'fuzzy numeric' sort type, which just ignores all non-numeric characters
        app.datatables.addSortFunction('fuzzy-numeric', function ( a ) {
			var candidate = parseInt(a.trim());
			return _.isNaN(candidate) ? Number.NEGATIVE_INFINITY : candidate;
        });

        // sort by a numeric value in data-value on the direct child or a descendent of the table cell
        app.datatables.addSortFunction('data-value-numeric', function ( a ) {
			var $element = $(a);
			//if element does not have data-value attribute get a child element with it.
			if (!$element[0].hasAttribute('data-value')) {
				$element = $element.find('[data-value]');
			}
	        var value = $element.data('value');
	        return value ? value : 0;
        });

        // add a 'checkbox' sort type, which sorts by whether a checkbox is checked or not
		app.datatables.addSortFunction('checkbox', function ( a ) {
            var $checkbox = $('#' + $(a).filter('input[type=checkbox]').attr('id'));
            return ($checkbox.is(':checked') ? 1 : 0);
        });

        // add a 'forminput' sort type, which sorts by the contents of a text field or select
		app.datatables.addSortFunction('forminput', function ( a ) {
			var $a = $(a);
			if ($a.find('option').length > 0) {
				return $a.find('option:selected').text();
			} else {
				return $(a).val();
			}
        }, false);

		// add a 'traffic light' sort type, which uses the value in the traffic light
		app.datatables.addSortFunction('traffic-light', function ( a ) {
	        var value = $(a).filter('.icon-circle').data('value');
			if (typeof value == 'undefined') {
				value = -1;
			}
			return value;
		});

		//run conditional init functions if selector exists on page
		for (var selector in app.datatables.selectors) {
			var $item = $(selector);
			if ($item.length) {
				app.datatables.selectors[selector]($item);
			}
		}

		if (typeof app.datatables.statusesTable == 'object' && app.datatables.statusesTable != null) {
			$(document)
				.on('dataChanged', app.datatables.refreshStatuses);
		}
	},
	addSortFunction: function(name, sortFunc, isNumeric) {
		if (typeof isNumeric == 'undefined') {
			isNumeric = true;
		}
		var sort = {};
		sort[name + "-pre"] = sortFunc;
		if (isNumeric) {
			sort[name + "-asc"] = function(a, b) { return a - b; };
			sort[name + "-desc"] = function(a, b) { return b - a; };
		} else {
			sort[name + "-asc"] = function(a, b) { return a < b ? -1 : 1; };
			sort[name + "-desc"] = function(a, b) { return a < b ? 1 : -1; };
		}
		$.extend($.fn.dataTableExt.oSort, sort);
	},
	refreshStatuses: function () {
		if (app.datatables.statusesTable.length) {
			app.datatables.statusesTable.fnClearTable(false);
			app.datatables.statusesTable.fnDraw();
		}
	},
	numberRender: function(o, val) {
		return app.utils.numberFormat(val);
	},
	getStatusColumns: function(showResponses) {
		return [{
			mDataProp:'message',
			render: function(o, type, row, meta) {
				if (typeof row.message != 'string') {
					row.message = '';
				}
				row.date = moment(row.created_time).format('D MMM');
				row.showResponses = showResponses;
				var message = parseTemplate(app.templates.post, row);
				if(showResponses) {
					var templateName = row.needs_response == '1' ? 'postResponse_needed' : 'postResponse_notNeeded';
					message += parseTemplate(app.templates[templateName], row.first_response || {});
				}
				return message;
			},
			sClass: 'message',
			bSortable:false,
			bUseRendered:false
		}];
	},
	listenForResponseNeededToggle: function($div) {
		$div.on('click', '.require-response', function(e) {
			e.preventDefault();
			app.api.post('presence/toggle-response-needed', { id: $(this).closest('tr').data('id') })
				.always(function() {
					$(document).trigger('dataChanged');
				});
		});
	},
	// dynamically constructs columns from the table html
	generateColumns: function($table) {
		var columns = [];
		$table.find('thead th').each(function() {
			var $cell = $(this);
			var column = {
				sName: $cell.data('name')
			};

			var sortType = $cell.data('sort');
			if (typeof sortType != 'undefined' && sortType  != 'none') {
				column.bSortable = true;
				if (sortType != 'auto') {
					column.sType = sortType;
				}
			} else {
				column.bSortable = false;
			}

			var width = $cell.data('width');
			if (typeof width != 'undefined') {
				column.sWidth = width;
			}

			//set data-hidden on th of column that you want to hide
			var hidden = $cell.data('hidden');
			if (typeof hidden != 'undefined') {
				column.hidden = true;
			}

			if (column.sType == 'forminput') {
				column.mRender = function(content, type, c) {
					if (type == 'filter') {
						var $input = $(content);
						if ($input.length > 0) {
							return $input.val();
						}
					}
					return content;
				};
			}

			columns.push(column);
		});
		return columns;
	},
	selectors: {
		'.dtable.standard, #all-sbus, #all-countries, #all-regions, #all-presences, #all-users': function($table) {
			var columns = app.datatables.generateColumns($table);
			var sortCol = 0;
			var sortColName = $table.data('sortCol');
			if (sortColName) {
				for(var i=0; i<columns.length; i++) {
					if (columns[i].sName == sortColName) {
						sortCol = i;
						break;
					}
				}
			}
			var hiddenCols = [];
			for(var i=0; i<columns.length; i++) {
				if (columns[i].hidden) {
					hiddenCols.push(i);
				}
			}

			$table.dataTable({
				aaSorting:[
					[sortCol, 'asc']
				],
				bScrollInfinite: true,
				iDisplayLength: 1000,
				bScrollCollapse: true,
				bPaginate: false,
				bFilter: true,
				bInfo: false,
				aoColumns: columns,
				oLanguage: {
					sSearch: ''
				},
				fixedHeader: true,
				"columnDefs": [
					{
						"targets": hiddenCols,
						"visible": false,
						"searchable": true
					}
				]
			});

			app.datatables.moveSearchBox();
		},
		'table#domains': function($table) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + "domain/list",
				aaSorting:[
					[0, 'asc']
				],
				aoColumns:[
					{
						mDataProp:'domain',
						render:function (val, type, row) {
							if (row.url) {
								val = '<a href="' + row.url + '">' + val + '<a/>';
							}
							return val;
						},
						bUseRendered: false
					},
					{
						mDataProp:'links'
					},
					{
						mDataProp:'is_bc',
						render:function (val, type, row) {
							var d = row;
							var $input = $('<input type="checkbox" />')
								.attr('id', 'domain-' + d.id)
								.attr('name', 'is_bc[' + d.id + ']');
							if (d.is_bc == '1') {
								$input.attr('checked', 'checked');
							}
							if (d.can_edit != '1') {
								$input.attr('disabled', 'disabled');
							}
							return $('<div></div>').append($input).html();
						},
						bUseRendered: false
					}
				],
				oLanguage:app.datatables.generateLanguage('domain')
			};

			app.datatables.statusesTable = $table
				.dataTable($.extend({}, app.datatables.serverSideArgs(), args))
				.fnSetFilteringDelay(250);

			app.datatables.moveSearchBox();
		},

		'table#hashtags': function($table) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + "hashtag/list",
				aaSorting:[
					[0, 'asc']
				],
				aoColumns:[
					{
						mDataProp:'hashtag',
						render:function (val, type, row) {
							return val;
						},
						bUseRendered: false
					},
					{
						mDataProp:'posts'
					},
					{
						mDataProp:'is_relevant',
						render:function (val, type, row) {
							var d = row;
							var $input = $('<input type="checkbox" />')
								.attr('id', 'hashtag-' + d.id)
								.attr('name', 'is_relevant[' + d.id + ']');
							if (d.is_relevant == '1') {
								$input.attr('checked', 'checked');
							}
							if (d.can_edit != '1') {
								$input.attr('disabled', 'disabled');
							}
							return $('<div></div>').append($input).html();
						},
						bUseRendered: false
					}
				],
				oLanguage:app.datatables.generateLanguage('hashtag')
			};

			app.datatables.statusesTable = $table
				.dataTable($.extend({}, app.datatables.serverSideArgs(), args))
				.fnSetFilteringDelay(250);

			app.datatables.moveSearchBox();
		},

		// combined statuses on status controller
		'#statuses .combined-statuses': function($div) {
			app.statuses.init($div);
			app.datatables.initStatusList($div, 'post', app.datatables.getStatusColumns(false));
		},

		// status tables on info tab on presence pages
		'#statuses .youtube, #statuses .facebook': function($div) {
			app.datatables.initStatusList($div, 'post', app.datatables.getStatusColumns(true));
			app.datatables.listenForResponseNeededToggle($div);
		},
		'#statuses .linkedin, #statuses .twitter, #statuses .instagram, #statuses .sina_weibo': function($div) {
			app.datatables.initStatusList($div, 'post', app.datatables.getStatusColumns(false));
			app.datatables.listenForResponseNeededToggle($div);
		}
	},
	/**
	 * Creates a dataTable for an AJAX-backed list of statuses/messages/posts
	 * @param {object} $container
	 * @param {string} statusName What sort of status this table contains
	 * @param {object[]} columns The columns to display
	 * @param {string} [sortColumn]
     */
	initStatusList: function($container, statusName, columns, sortColumn) {
		// default to any sortable column, but try to match the given one
		var sortColumnIndex = undefined;
		for (var i=0; i<columns.length; i++) {
			if (columns[i].bSortable !== false) {
				sortColumnIndex = i;
				if (columns[i].mDataProp == sortColumn) {
					break;
				}
			}
		}

		// initially limit the date range to the last month
		// matches the default dateRangeString in the datepicker
		var $datePicker = $('#date-picker');
		var dates = $datePicker.val();
		var start,end = '';
		if(dates) {
			var parts = dates.split('-');
			if(parts[0]) {
				start = new Date(dates.split('-')[0]).toString('yyyy-MM-dd');
			}
			if(parts[1]) {
				end = new Date(dates.split('-')[1]).toString('yyyy-MM-dd');
			}
		}
		if(!start) {
			start = moment().add(-30, 'days').format('YYYY-MM-DD');
		}
		if(!end) {
			end = moment(start).add(1,'days').format('YYYY-MM-DD');
		}

		var args = {
			oFeatures: {
				bSort: false,
				bSortClasses: false
			},
			bProcessing: true,
			sAjaxSource:jsConfig.apiEndpoint + "statuses/list",
			fnServerParams: function(aoData) {
				if($container.data('presence-id')) {
					aoData.push({name: "id", value: $container.data('presence-id')});
				}
				var queryParams = _.keys(app.datatables.query);
				if(!app.datatables.query.dateRange) {
					aoData.push({ name:"dateRange", value:[start, end] });
				}
				for(var i=0; i<queryParams.length; ++i) {
					aoData.push({name: queryParams[i], value: app.datatables.query[queryParams[i]]});
				}
			},
			fnRowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
				var $el = $(nRow);
				$el.data('id', aData.id);
			},
			aaSorting:[],
			aoColumns:columns,
			oLanguage:app.datatables.generateLanguage(statusName)
		};
		if (typeof sortColumnIndex !== 'undefined') {
			args.aaSorting.push([sortColumnIndex, 'desc']);
		}

		args = $.extend({}, app.datatables.serverSideArgs(), args);

		// fix header cells when switching to the statuses tab
		$(document).foundation({
			tab: {
				callback: function(tab) {
					if (tab.context.hash == '#statuses') {
						$(document).trigger('dataChanged');
					}
				}
			}
		});

		app.datatables.statusesTable = $container.find('table')
			.dataTable(args)
			.fnSetFilteringDelay(250);
		app.datatables.moveSearchBox('{{ js.datatables.searchBox | translate }}');
	},
	moveSearchBox: function(placeholder) {
		var $search = $('div.dataTables_filter');
		$search.find('input').first().attr('placeholder', placeholder || '{{ js.datatables.searchPlaceholder | translate }}');
		$('#search-table').empty().append($search);

		var $filters = $('.statusesDisplay .filters');
		$filters.show();
	},
	generateLanguage: function(type) {
		var language = {
			sSearch: '{{ js.datatables.search | translate }}',
			sEmptyTable: '{{ js.datatables.emptyTable | translate }}',
			sZeroRecords: '{{ js.datatables.zeroRecords | translate }}',
			sInfo: '{{ js.datatables.info | translate }}',
			sInfoEmpty: '{{ js.datatables.infoEmpty | translate }}',
			sInfoFiltered: '{{ js.datatables.infoFiltered | translate }}',// (from _MAX_ ' + type + 's)'
			sProcessing: '<span class="fa fa-refresh fa-spin"></span> {{ js.datatables.loading | translate }}'
		};

		for(var key in language){
			if (language.hasOwnProperty(key)) {
				language[key] = language[key].replace('%type%', type);
			}
		}

		return language;
	},
	serverSideArgs: function() {
		return {
			bProcessing:false,
			bServerSide:true,
			bAutoWidth:false,
			// debounce the request function, to allow multiple rapid changes that only trigger one request
			fnServerData: _.debounce(function (sSource, aoData, fnCallback) {
				$.getJSON(sSource, aoData,
					function (e) {
						//display data
						fnCallback(e.data);
					}).error(function (e) {
						var data = null;
						try {
							data = $.parseJSON(e.responseText);
						} catch (err) {
						}

						if (data) {
							console.log('AJAX error', data.error, data);
						} else {
							if (e.responseText) {
								console.log('AJAX error (no JSON found)', e.responseText, e);
							} else {
								console.log('AJAX error (no JSON found)', e);
							}
						}
					});
			}, 500),
			bPaginate:true,
			iDisplayLength:parseInt(jsConfig.pageSize),
			bInfo:true,
			bScrollInfinite:true,
			bScrollCollapse:false,
			lengthChange: false,
			fnDrawCallback:app.datatables.reloadAvatars
		};
	},
	reloadAvatars: function(dtable) {
		$(dtable.nTable).find('img.async-load').each(function (i) {
			var $img = $(this);
			$img.removeClass('async-load');
			var src = $img.data('src');

			// try to load the image in an anonymous img, then apply to original img if it succeeds
			$('<img />')
				.attr('src', src)
				.load(function () {
					$img.attr('src', src);
				});
		});
	}
};