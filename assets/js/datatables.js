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
			"fuzzy-numeric-pre": function ( a ) { return parseInt(a.replace(/[^\d]/g, "")); },
			"fuzzy-numeric-asc": function ( a, b ) { return a - b; },
			"fuzzy-numeric-desc": function ( a, b ) { return b - a; }
		});

		// add a 'traffic light' sort type, which uses the value in the traffic light
		$.extend($.fn.dataTableExt.oSort, {
			"traffic-light-pre": function ( a ) {
				var value = $(a).filter('.traffic-light').data('value');
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

		if (typeof app.datatables.statusesTable != 'undefined') {
			$(document)
				.on('dateRangeUpdated', function () {
					if (app.datatables.statusesTable.length) {
						app.datatables.statusesTable.fnClearTable(false);
						app.datatables.statusesTable.fnDraw();
					}
				});
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
			if (typeof sortType != 'undefined') {
				column.sWidth = width;
			}

			columns.push(column);
		});
		return columns;
	},
	selectors: {
		'#all-presences':function($table) {
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
		'#all-countries': function($table) {
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
		'#statuses.facebook': function($div) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/statuses",
				aaSorting:[
					[3, 'desc']
				],
				aoColumns:[
					{
						mDataProp:'actor_name',
						fnRender:function (o, val) {
							return '<img data-src="' + o.aData.pic_url + '" class="facebook-actor async-load" />';
						},
						sClass:'statusPic',
						bUseRendered:false
					},
					{
						mDataProp:'message',
						fnRender:function (o) {
							return parseTemplate(app.templates.post, o.aData);
						},
						bSortable:false,
						bUseRendered:false
					},
					{
						mDataProp:'comments',
						sClass:'retweets lesser',
						asSorting:['desc', 'asc']
					},
					{
						mDataProp:'date',
						fnRender:function (o, val) {
							return Date.parse(val).toString('d MMM<br>HH:mm');
						},
						bUseRendered:false,
						sClass:'retweets lesser',
						asSorting:['desc', 'asc']
					}
				],
				oLanguage:app.datatables.generateLanguage('post')
			};

			app.datatables.statusesTable = $div.find('table')
				.dataTable($.extend({}, app.datatables.serverSideArgs(), args))
				.fnSetFilteringDelay(250);
		},
		'#statuses.twitter': function($div) {
			var args = {
				sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/statuses",
				aaSorting:[
					[1, 'desc']
				],
				aoColumns:[
					{
						mDataProp:'message',
						fnRender:function (o) {
							return parseTemplate(app.templates.tweet, o.aData);
						},
						bSortable:false,
						bUseRendered:false
					},
					{
						mDataProp:'date',
						fnRender:function (o, val) {
							return Date.parse(val).toString('d MMM<br>HH:mm');
						},
						bUseRendered:false,
						sClass:'retweets lesser',
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
			fnServerParams:function (aoData) {
				aoData.push({ name:"dateRange", value:app.state.dateRange });
				aoData.push({ name:"line_ids", value:app.state.lineIds });
			},
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