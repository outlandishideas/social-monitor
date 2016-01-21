var app = app || {};

/**
 * DataTable component setup
 * @type {Object}
 */
app.datatables = {
	statusesTable: null,
	init:function () {
        // add a 'fuzzy numeric' sort type, which just ignores all non-numeric characters
        app.datatables.addSortFunction('fuzzy-numeric', function ( a ) {
	        if (typeof(a) == 'string') {
		        return parseInt(a.replace(/[^\d]/g, ""));
	        } else {
		        return 0;
	        }
        });

        // sort by a numeric value in data-value on the direct child of the table cell
        app.datatables.addSortFunction('data-value-numeric', function ( a ) {
	        var value = $(a).data('value');
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
			if ($item.length) app.datatables.selectors[selector]($item);
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
			$table.dataTable({
				aaSorting:[
					[sortCol, 'asc']
				],
				bScrollInfinite: true,
				iDisplayLength: 1000,
				bScrollCollapse: true,
				bFilter: true,
				bInfo: false,
				aoColumns: columns,
				oLanguage: {
					sSearch: ''
				}
			});

			app.datatables.moveSearchBox();
		},
		'table#domains': function($table) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + "domain/list",
				sDom: {

				},
				aaSorting:[
					[0, 'asc']
				],
				aoColumns:[
					{
						mDataProp:'domain',
						fnRender:function (o, val) {
							if (o.aData.url) {
								val = '<a href="' + o.aData.url + '">' + val + '<a/>';
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
						fnRender:function (o, val) {
							var d = o.aData;
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
		'#statuses .youtube': function($div) {
			app.datatables.initStatusList($div, 'post', [
				{
					mDataProp:'message',
					fnRender:function (o) {
						if (typeof o.aData.message != 'string') {
							o.aData.message = '';
						}
						var message = parseTemplate(app.templates.ytpost, o.aData);
						var response = o.aData.first_response;
						var rTitle, rMessage, rIcon;
						if (o.aData.needs_response == '1') {
							rTitle = 'Does not require a response';
							rMessage = 'Awaiting response (' + response.date_diff + ')...';
							rIcon = 'icon-comment-alt';
						} else {
							rTitle = 'Requires a response';
							rMessage = 'No response required';
							rIcon = 'icon-comments';
						}
						message += '<p class="more"><a href="#" class="require-response" title="' + rTitle + '"><span class="' + rIcon + ' icon-large"></span></a></p>' +
							'<p class="no-response">' + rMessage + '</p>';
						return message;
					},
					sClass: 'message',
					bSortable:false,
					bUseRendered:false
				},
				app.datatables.linksColumn(),
				app.datatables.dateColumn()
			]);

			$div.on('click', '.require-response', function(e) {
				e.preventDefault();
				app.api.post('presence/toggle-response-needed', { id: $(this).closest('tr').data('id') })
					.always(function() {
						$(document).trigger('dataChanged');
					});
			});
		},
		'#statuses .linkedin': function($div) {
			app.datatables.initStatusList($div, 'post', [
				{
					mDataProp:'message',
					fnRender:function (o) {
						if (typeof o.aData.message != 'string') {
							o.aData.message = '';
						}
						var message = parseTemplate(app.templates.inpost, o.aData);
						var response = o.aData.first_response;
						var rTitle, rMessage, rIcon;
						if (o.aData.needs_response == '1') {
							rTitle = 'Does not require a response';
							rMessage = 'Awaiting response (' + response.date_diff + ')...';
							rIcon = 'icon-comment-alt';
						} else {
							rTitle = 'Requires a response';
							rMessage = 'No response required';
							rIcon = 'icon-comments';
						}
						message += '<p class="more"><a href="#" class="require-response" title="' + rTitle + '"><span class="' + rIcon + ' icon-large"></span></a></p>' +
							'<p class="no-response">' + rMessage + '</p>';
						return message;
					},
					sClass: 'message',
					bSortable:false,
					bUseRendered:false
				},
				app.datatables.linksColumn(),
				app.datatables.dateColumn()
			]);

			$div.on('click', '.require-response', function(e) {
				e.preventDefault();
				app.api.post('presence/toggle-response-needed', { id: $(this).closest('tr').data('id') })
					.always(function() {
						$(document).trigger('dataChanged');
					});
			});
		},
		'#statuses .facebook': function($div) {
			app.datatables.initStatusList($div, 'post', [
				{
					mDataProp:'message',
					fnRender:function (o) {
						if (typeof o.aData.message != 'string') {
							o.aData.message = '';
						}
						var message = parseTemplate(app.templates.post, o.aData);
						var response = o.aData.first_response;
						if (response.message != null) {
							message += '<div class="first-response">' +
								'<h4>First response: ' + Date.parse(response.date).toString('d MMM HH:mm') + ' <span>(' + response.date_diff + ')</span></h4>' +
								'<p>' + response.message.replace(/\\n/g, '<br />') + '</p>' +
								'</div>';
						} else {
							var rTitle, rMessage, rIcon;
							if (o.aData.needs_response == '1') {
								rTitle = 'Does not require a response';
								rMessage = 'Awaiting response (' + response.date_diff + ')...';
								rIcon = 'icon-comment-alt';
							} else {
								rTitle = 'Requires a response';
								rMessage = 'No response required';
								rIcon = 'icon-comments';
							}
							message += '<p class="more"><a href="#" class="require-response" title="' + rTitle + '"><span class="' + rIcon + ' icon-large"></span></a></p>' +
								'<p class="no-response">' + rMessage + '</p>';
						}
						return message;
					},
					sClass: 'message',
					bSortable:false,
					bUseRendered:false
				},
				app.datatables.linksColumn(),
				app.datatables.dateColumn()
			]);

			$div.on('click', '.require-response', function(e) {
				e.preventDefault();
				app.api.post('presence/toggle-response-needed', { id: $(this).closest('tr').data('id') })
					.always(function() {
						$(document).trigger('dataChanged');
					});
			});
		},
		'#statuses .twitter': function($div) {
			app.datatables.initStatusList($div, 'tweet', [
				{
					mDataProp:'message',
					fnRender:function (o) {
						return parseTemplate(app.templates.tweet, o.aData);
					},
					sClass: 'message',
					bSortable:false,
					bUseRendered:false
				},
				app.datatables.linksColumn(),
				app.datatables.dateColumn()
			]);
		},
		'#statuses .instagram': function($div) {
			app.datatables.initStatusList($div, 'post', [
				{
					mDataProp:'message',
					fnRender:function (o) {
						return parseTemplate(app.templates.igpost, o.aData);
					},
					sClass: 'message',
					bSortable:false,
					bUseRendered:false
				},
				app.datatables.dateColumn()
			]);
		},
		'#statuses .sina_weibo': function($div) {
			app.datatables.initStatusList($div, 'post', [
				{
					mDataProp:'message',
					fnRender:function (o) {
						return parseTemplate(app.templates.swPost, o.aData);
					},
					sClass: 'message',
					bSortable:false,
					bUseRendered:false
				},
				app.datatables.linksColumn(),
				app.datatables.dateColumn()
			]);
		}
	},
	initStatusList: function($container, statusType, columns, sortColumn) {
		if (typeof sortColumn == 'undefined') {
			sortColumn = 'date';
		}
		// default to any sortable column, but try to match the given one
		var sortColumnIndex = 0;
		for (var i=0; i<columns.length; i++) {
			if (columns[i].bSortable !== false) {
				sortColumnIndex = i;
				if (columns[i].mDataProp == sortColumn) {
					break;
				}
			}
		}

		// limit the date range to the last 2 months
		var d = new Date();
		var end = d.toString('yyyy-MM-dd');
		d.setDate(d.getDate() - 60);
		var start = d.toString('yyyy-MM-dd');

		var args = {
			sAjaxSource:jsConfig.apiEndpoint + "statuses/list",
			fnServerParams: function(aoData) {
				aoData.push({ name:"dateRange", value:[start, end] });
				aoData.push({ name:"id", value:$container.data('presence-id') });
			},
			aaSorting:[
				[sortColumnIndex, 'desc']
			],
			fnRowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
				$(nRow).data('id', aData.id);
			},
			aoColumns:columns,
			oLanguage:app.datatables.generateLanguage(statusType)
		};

		args = $.extend({}, app.datatables.serverSideArgs(), args);
		app.datatables.statusesTable = $container.find('table')
			.dataTable(args)
			.fnSetFilteringDelay(250);

		app.datatables.moveSearchBox();

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
	},
	linksColumn: function() {
		return {
			mDataProp:'links',
			fnRender:function (o, links) {
				var linkStrings = [];
				if (links) {
					for (var i=0; i<links.length; i++) {
						var link = links[i];
						linkStrings.push('<a href="' + link.url + '" target="_blank">' + link.domain + '</a> (' + (link.is_bc == '1' ? 'BC' : 'non-BC') + ')');
					}
				}
				return linkStrings.join(', ');
			},
			sClass: 'links',
			bSortable: false,
			bUseRendered: false
		};
	},
	dateColumn: function() {
		return {
			mDataProp:'date',
			fnRender:function (o, val) {
				return Date.parse(val).toString('d MMM<br>HH:mm');
			},
			bUseRendered:false,
			sClass:'date',
			asSorting:['desc', 'asc']
		};
	},
	moveSearchBox: function() {
		var $filter = $('div.dataTables_filter');
		$filter.find('input').first().attr('placeholder', 'Search');
		$('#search-table').empty().append($filter);
	},
	generateLanguage: function(type) {
		return {
			sSearch:'',
			sEmptyTable:'No ' + type + 's found',
			sZeroRecords:'No matching ' + type + 's',
			sInfo:'Showing ' + type + 's: _START_ to _END_',
			sInfoEmpty:'No ' + type + 's to show',
			sInfoFiltered:''// (from _MAX_ ' + type + 's)'
		};
	},
	serverSideArgs: function() {
		return {
			bProcessing:false,
			bServerSide:true,
			bAutoWidth:false,
			fnServerData:function (sSource, aoData, fnCallback) {
				var $wrapper = $(this).closest('.inner');
				$wrapper.showLoader();
				$.getJSON(sSource, aoData,
					function (e) {
						//display data
						fnCallback(e.data);
						//remove start and length parameters
						aoData = $.grep(aoData, function (param) {
							return param.name != 'iDisplayLength' && param.name != 'iDisplayStart';
						});
						aoData.push({name:'format', value:'csv'});
						//update CSV download URL
						$wrapper.find('.dataTables_info a').attr('href', sSource + '?' + $.param(aoData));
						$wrapper.hideLoader();
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
						$wrapper.hideLoader();
					});
			},
//			fnInfoCallback:function (oSettings, iStart, iEnd, iMax, iTotal, sPre) {
//				return sPre + ' <a href="">Download as CSV</a>';
//			},
			bPaginate:true,
			iDisplayLength:parseInt(jsConfig.pageSize),
			bInfo:true,
			bScrollInfinite:true,
			bScrollCollapse:false,
			sScrollY:"600px",
			iScrollLoadGap:500,
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