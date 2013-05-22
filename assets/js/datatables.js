var app = app || {};

/**
 * DataTable component setup
 * @type {Object}
 */
app.datatables = {
	statusesTable: null,
	init:function () {
        // add a 'fuzzy numeric' sort type, which just ignores all non-numeric characters
        $.extend($.fn.dataTableExt.oSort, {
            "fuzzy-numeric-pre": function ( a ) { if (typeof(a) == 'string') { return parseInt(a.replace(/[^\d]/g, "")); } else { return 0; }},
            "fuzzy-numeric-asc": function ( a, b ) { return a - b; },
            "fuzzy-numeric-desc": function ( a, b ) { return b - a; }
        });

        // add a 'checkbox' sort type, which sorts by whether a checkbox is checked or not
        $.extend($.fn.dataTableExt.oSort, {
            "checkbox-pre": function ( a ) {
                var $checkbox = $('#' + $(a).filter('input[type=checkbox]').attr('id'));
                return ($checkbox.is(':checked') ? 1 : 0);
            },
            "checkbox-asc": function ( a, b ) { return a - b; },
            "checkbox-desc": function ( a, b ) { return b - a; }
        });

		// add a 'traffic light' sort type, which uses the value in the traffic light
		$.extend($.fn.dataTableExt.oSort, {
			"traffic-light-pre": function ( a ) {
				var value = $(a).filter('.icon-circle').data('value');
				if (typeof value == 'undefined') {
					value = -1;
				}
				return value;
			},
			"traffic-light-asc": function ( a, b ) { return a - b; },
			"traffic-light-desc": function ( a, b ) { return b - a; }
		});

		//run conditional init functions if selector exists on page
		for (var selector in app.datatables.selectors) {
			var $item = $(selector);
			if ($item.length) app.datatables.selectors[selector]($item);
		}

		if (typeof app.datatables.statusesTable == 'object' && app.datatables.statusesTable != null) {
			$(document)
				.on('dateRangeUpdated', app.datatables.refreshStatuses)
				.on('dataChanged', app.datatables.refreshStatuses);
		}
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
			if (typeof sortType != 'undefined') {
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

			columns.push(column);
		});
		return columns;
	},
	selectors: {
		'.dtable.standard': function($table) {
			$table.dataTable({
				bScrollInfinite: true,
				iDisplayLength: 1000,
				bScrollCollapse: true,
				sScrollY: '400px',
				bFilter: false,
				bInfo: false,
				aoColumns: app.datatables.generateColumns($table)
			});
		},
		'#all-presences': function($table) {
			$table.dataTable({
				aaSorting:[
					[1, 'asc']
				],
				bScrollInfinite: true,
				iDisplayLength: 1000,
				bScrollCollapse: true,
				sScrollY: '400px',
				bFilter: false,
				bInfo: false,
				aoColumns: app.datatables.generateColumns($table)
			});
		},
		'#statuses.facebook': function($div) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/statuses",
				fnServerParams: function(aoData) {
                    aoData.push({ name:"dateRange", value:app.state.dateRange });
					aoData.push({ name:"id", value:$div.data('presence-id') });
				},
				fnRowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
					$(nRow).data('id', aData.id);
				},
				aaSorting:[
					[4, 'desc']
				],
				aoColumns:[
					{
						mDataProp:'actor_name',
						fnRender:function (o, val) {
							return '<img data-src="' + o.aData.pic_url + '" class="facebook-actor async-load" />';
						},
						sClass:'statusPic',
						bSortable:false,
						bUseRendered:false
					},
					{
						mDataProp:'message',
						fnRender:function (o) {
							if (typeof o.aData.message != 'string') {
								o.aData.message = '';
							}
							return parseTemplate(app.templates.post, o.aData);
						},
						sClass: 'message',
						bSortable:false,
						bUseRendered:false
					},
					{
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
						bSortable:false,
						bUseRendered:false
					},
					{
						mDataProp:'first_response',
						fnRender:function(o, response) {
							if (response.message != null) {
								return '<h4>' + Date.parse(response.date).toString('d MMM HH:mm') + ' (' + response.date_diff + ')</h4>' +
									'<p>' + response.message.replace(/\\n/g, '<br />') + '</p>';
							} else if (o.aData.needs_response == '1') {
								return '<p class="more"><a href="#" class="require-response">Does not require a response</a></p>' +
									'<p class="no-response">Awaiting response (' + response.date_diff + ')...</p>';
							} else {
								return '<p class="more"><a href="#" class="require-response">Requires a response</a></p>' +
									'<p class="no-response">No response required</p>';
							}
						},
						sClass: 'message',
						bSortable: false,
						bUseRendered: false
					},
					{
						mDataProp:'date',
						fnRender:function (o, val) {
							return Date.parse(val).toString('d MMM<br>HH:mm');
						},
						bUseRendered:false,
						sClass:'date',
						asSorting:['desc', 'asc']
					}
				],
				oLanguage:app.datatables.generateLanguage('post')
			};

			app.datatables.statusesTable = $div.find('table')
				.dataTable($.extend({}, app.datatables.serverSideArgs(), args))
				.fnSetFilteringDelay(250);

			$div.on('click', '.require-response', function(e) {
				e.preventDefault();
				app.api.post('presence/toggle-response-needed', { id: $(this).closest('tr').data('id') })
					.always(function() {
						$(document).trigger('dataChanged');
					});
			});
		},
		'#statuses.twitter': function($div) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/statuses",
				fnServerParams: function(aoData) {
					aoData.push({ name:"dateRange", value:app.state.dateRange });
					aoData.push({ name:"id", value:$div.data('presence-id') });
				},
				aaSorting:[
					[1, 'desc']
				],
				aoColumns:[
					{
						mDataProp:'message',
						fnRender:function (o) {
							return parseTemplate(app.templates.tweet, o.aData);
						},
						sClass: 'message',
						bSortable:false,
						bUseRendered:false
					},
					{
						mDataProp:'date',
						fnRender:function (o, val) {
							return Date.parse(val).toString('d MMM<br>HH:mm');
						},
						bUseRendered:false,
						sClass:'date',
						asSorting:['desc', 'asc']
					}
				],
				oLanguage:app.datatables.generateLanguage('tweet')
			};

			args = $.extend({}, app.datatables.serverSideArgs(), args);
			app.datatables.statusesTable = $div.find('table')
				.dataTable(args)
				.fnSetFilteringDelay(250);
		}
	},
	generateLanguage: function(type) {
		return {
			sSearch:'Search ' + type + 's:',
			sEmptyTable:'No ' + type + 's found for this ' + app.state.controllerLabel,
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
			fnInfoCallback:function (oSettings, iStart, iEnd, iMax, iTotal, sPre) {
				return sPre + ' <a href="">Download as CSV</a>';
			},
			bPaginate:true,
			iDisplayLength:parseInt(jsConfig.pageSize),
			bInfo:true,
			bScrollInfinite:true,
			bScrollCollapse:false,
			sScrollY:"400px",
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