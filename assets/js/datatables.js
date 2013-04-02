var app = app || {};

/**
 * DataTable component setup
 * @type {Object}
 */
app.datatables = {
	init:function () {
		// these are used by all server-side datatables
		var commonDatatableArgs = {
			bProcessing:false,
			bServerSide:true,
			bAutoWidth:false,
			fnServerParams:function (aoData) {
				aoData.push({ name:"dateRange", value:app.state.dateRange });
				aoData.push({ name:"line_ids", value:app.state.lineIds });
			},
			fnServerData:function (sSource, aoData, fnCallback) {
				var $wrapper = $(this).closest('.dataTables_wrapper');
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
			iScrollLoadGap:500
		};

		var generateLanguageFn = function (type) {
			return {
				sSearch:'Search ' + type + 's:',
				sEmptyTable:'No ' + type + 's found for this ' + app.state.controllerLabel,
				sZeroRecords:'No matching ' + type + 's',
				sInfo:'Showing ' + type + 's: _START_ to _END_',
				sInfoEmpty:'No ' + type + 's to show',
				sInfoFiltered:''// (from _MAX_ ' + type + 's)'
			};
		};

		// these are specific to each datatable
		var topicsArgs = {
			sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/topics",
			fnRowCallback:function (nRow, aData, iDisplayIndex, iDisplayIndexFull) {
				// make sure that those that are already being graphed are selected
				if (app.state.lineIds.indexOf(aData.line_id) >= 0) {
					$(nRow).addClass('selected');
				}
				// store the normalised_topic in the data-topic field (used to fetch the graph data)
				$(nRow).attr('data-line-id', aData.line_id);
			},
			oLanguage:generateLanguageFn('topic')
		};

		if (app.state.controller == 'trend') {
			topicsArgs.aoColumns = [
				{'mDataProp':'topic'},
				{'mDataProp':'rank'},
				{'mDataProp':'age'}
			];
			topicsArgs.aaSorting = [
				[2, 'asc'],
				[1, 'asc']
			];
		} else {
			topicsArgs.aoColumns = [
				{'mDataProp':'topic'},
				{'mDataProp':'mentions', asSorting:['desc', 'asc']},
				{'mDataProp':'polarity', asSorting:['desc', 'asc']}
			];
			topicsArgs.aaSorting = [
				[1, 'desc']
			];
		}
		var tweetsArgs = {
			sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/statuses",
			aaSorting:[
				[4, 'desc']
			],
			aoColumnDefs:[
				{
					aTargets:['user'],
					mDataProp:'screen_name',
					fnRender:function (o, val) {
						return o.aData.profile_image_url ? '<img data-src="' + o.aData.profile_image_url + '" width="50px" class="async-load" />' : '';
					},
					sClass:'statusPic',
					bUseRendered:false
				},
				{
					aTargets:['tweet'],
					mDataProp:'tweet',
					fnRender:function (o) {
						return parseTemplate(app.templates.tweet, o.aData);
					},
					bSortable:false,
					bUseRendered:false
				},
				{
					aTargets:['retweets'],
					mDataProp:'retweet_count',
					sClass:'retweets lesser',
					asSorting:['desc', 'asc']
				},
				{
					aTargets:['sentiment'],
					mDataProp:'average_sentiment',
					sClass:'retweets lesser',
					asSorting:['desc', 'asc']
				},
				{
					aTargets:['date'],
					mDataProp:'date',
					fnRender:function (o, val) {
						return Date.parse(val).toString('d MMM<br>HH:mm');
					},
					bUseRendered:false,
					sClass:'retweets lesser',
					asSorting:['desc', 'asc']
				}
			],
			oLanguage:generateLanguageFn('tweet')
		};

		//search page has no RT column so date become column 3
		if (app.state.controller == 'search') {
			tweetsArgs.aaSorting = [
				[3, 'desc']
			];
		}

		var postsArgs = {
			sAjaxSource:jsConfig.apiEndpoint + app.state.controller + "/statuses",
			aaSorting:[
				[5, 'desc']
			],
			aoColumns:[
				{
					mDataProp:'actor_name',
					fnRender:function (o, val) {
						return o.aData.pic_url ? '<img data-src="' + o.aData.pic_url + '" width="50px" class="async-load" />' : '';
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
					mDataProp:'likes',
					sClass:'retweets lesser',
					asSorting:['desc', 'asc']
				},
				{
					mDataProp:'average_sentiment',
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
			oLanguage:generateLanguageFn('post')
		};

		//show manual search option
		topicsArgs.fnDrawCallback = function (dtable) {
			if ($('#topics .dataTables_filter input').val()) {
				$(dtable.nTBody).append(parseTemplate(app.templates.addTextSearch, {colspan:3}));
			}
		}

		function reloadAvatars(dtable) {

			$('img.async-load', dtable.nTBody).each(function () {
				var $img = $(this);

				setTimeout(function () { // spawn new thread-ish thing

					$('<img />').load(function () {
						$img.attr('src', $img.data('src'));
					}).error(function () {
								$img.attr('src', 'https://twimg0-a.akamaihd.net/sticky/default_profile_images/default_profile_4_normal.png');
							}).attr('src', $img.data('src'));

				}, 1);

			})
		};


		postsArgs.fnDrawCallback = reloadAvatars;
		tweetsArgs.fnDrawCallback = reloadAvatars;

		var topicsTable = $('#topics .dtable')
				.dataTable($.extend({}, commonDatatableArgs, topicsArgs))
				.fnSetFilteringDelay(250);

		var statusesTable = $('#tweets .dtable')
				.dataTable($.extend({}, commonDatatableArgs, tweetsArgs))
				.fnSetFilteringDelay(250);
		if (!statusesTable.size()) {
			statusesTable = $('#posts .dtable')
					.dataTable($.extend({}, commonDatatableArgs, postsArgs))
					.fnSetFilteringDelay(250);
		}

		$(document)
				.on('dateRangeUpdated', function () {
					// update the topics and statuses tables
					if (topicsTable.length) {
						topicsTable.fnClearTable(false);
						topicsTable.fnDraw();
					}
					if (statusesTable.length) {
						statusesTable.fnClearTable(false);
						statusesTable.fnDraw();
					}
				})
				.on('topicsChanged', function (e) {
					app.charts.updateYAxis();
					if (statusesTable.length) {
						statusesTable.fnClearTable(false);
						statusesTable.fnDraw();
					}
				});

		$('#topics').on('click', '.add-manual-search', function () {
			var $filter = $('#topics .dataTables_filter input');
			var value = $filter.val();
			var bits = app.state.defaultLineId.split(':', 2);
			bits.push('text', value);
			app.charts.addDataset(bits.join(':'));

			//redraw table
			$filter.val('').keyup();
		});

	},
	numberRender: function(o, val) {
		return app.utils.numberFormat(val);
	}
};