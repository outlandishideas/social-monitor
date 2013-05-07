var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {
	map: null,
	data: null,
	metrics: {},
	setup:function ($mapDiv) {
		$mapDiv.on('click', '.country .close', function(e) {
			e.preventDefault();
			$(this).closest('.country').remove();
		});

		// copy the provided metrics to app.geochart, and populate values
		app.geochart.metrics = metrics;
		for (var i in app.geochart.metrics) {
			var metric = app.geochart.metrics[i];
			metric.key = i;
			for (var j=0; j<metric.range.length; j++) {
				metric.range[j] = parseInt(metric.range[j]);
			}
		}

		google.load('visualization', '1', {'packages': ['geochart']});
		google.setOnLoadCallback(function() {
			app.geochart.map = new google.visualization.GeoChart(document.getElementById('geo-map'));
			app.geochart.map.options = {
				datalessRegionColor: '#D5D5D5',
				colorAxis: {}
			};
			google.visualization.events.addListener(app.geochart.map, 'select', app.geochart.selectHandler);
			app.geochart.buildDataTable();
			app.geochart.refreshMap();
		});

		$('#metric-picker').on('change', function() {
			var $country = $mapDiv.find('.country');
			if ($country.length > 0) {
				app.geochart.loadCountryStats($country.data('id'));
				$country.remove();
			}
			app.geochart.refreshMap();
		});
	},
	currentMetric:function () {
		return $('#metric-picker').val();
	},
	// calculates the average value for a given kpi across the presences for 1 country
	kpiAverage: function(country, m) {
		var presences = country.presences;
		if (presences.length > 0) {
			var count = 0;
			var total = 0;
			for (var i=0; i<presences.length; i++){
				if (m in presences[i]) {
					total += parseFloat(presences[i][m]);
					count++;
				}
			}
			if (count > 0) {
				return total/count;
			}
		}
		throw 'No valid presences';
	},
	refreshMap:function () {
		var metric = app.geochart.metrics[app.geochart.currentMetric()];

		app.geochart.map.options.colorAxis.values = metric.range;
		app.geochart.map.options.colorAxis.colors = metric.colors;

		// make a view of the data, which only consists of the first column and the column for the chosen metric
		var view = new google.visualization.DataView(app.geochart.data);
		view.setColumns([0, metric.columnIndex]);

		app.geochart.map.draw(view, app.geochart.map.options);
	},
	/**
	 * Called when a country is clicked
	 * @param e
	 */
 	selectHandler: function (e) {
		var selection = app.geochart.map.getSelection();
		if (selection.length > 0) {
			var id = app.geochart.data.getValue(selection[0].row, 3);
			app.geochart.loadCountryStats(id);
		}
	},
	/**
	 * Fetches the country summary over ajax, and appends it to the map.
	 * @param id
	 */
	loadCountryStats: function(id) {
		var $map = $('#map');
		var $loading = $map.find('.loading');
		$loading.show();
		$map.find('.country').remove();
		$.get('index/country-stats/', {id: id, metric: app.geochart.currentMetric()})
			.done(function(data) {
				var $country = $(data);
				$country.data('id', id);
				$map.append($country);
			})
			.always(function() {
				$loading.hide();
			});
	},
	buildDataTable:function(){
		if (typeof mapData == 'undefined' || mapData.length == 0) {
			return;
		}

		app.geochart.data = new google.visualization.DataTable();
		app.geochart.data.addColumn('string', 'Country');
		app.geochart.data.addColumn('string', 'Display Name');
		app.geochart.data.addColumn('number', 'Presences');
		app.geochart.data.addColumn('number', 'id');
		var columnIndex = app.geochart.data.getNumberOfColumns();

		var colors = app.geochart.colors;
		for (var m in app.geochart.metrics) {
			var metric = app.geochart.metrics[m];
			switch (m) {
				case 'popularityPercentage':
					metric.format = '{0}{1}';
					break;
				case 'popularityTime':
					metric.format = '{1}';
					break;
				case 'postsPerDay':
					metric.format = '{0}{1}';
					break;
			}

			// convert each hex string to rgb values
//			metric.colorRgb = [];
//			for (var i=0; i<metric.colors.length; i++) {
//				var hex = metric.colors[i];
//				var rgb = [];
//				for (var j=1; j<6; j+=2) {
//					rgb.push(parseInt(hex.substring(j, j+2), 16));
//				}
//				metric.colorRgb.push(rgb);
//			}

			metric.columnIndex = columnIndex;
			app.geochart.data.addColumn('number', metric.label);
			app.geochart.data.addColumn('string', metric.key + '-extra');
			columnIndex += 2;
		}

		for (var c in mapData) {
			var country = mapData[c];
			var row = [country.country, country.name, country.presences.length, country.id];
			for (var m in app.geochart.metrics) {
				try {
					var extra = '';
					var score = app.geochart.kpiAverage(country, m);
					var metric = app.geochart.metrics[m];

					switch (m) {
						case 'popularityPercentage':
							extra = '% (total audience: ' + app.utils.numberFormat(country.targetAudience) + ')';
							break;
						case 'popularityTime':
							if (score == 0) {
								extra = '[target already reached]';
							} else {
								// convert to years and months
								var tmp = Math.floor(score);
								var fraction = score - tmp;
								var months = tmp%12;
								var years = (tmp - months)/12;
								months = Math.round((months + fraction)*10)/10;
								var components = [];
								if (years != 0) {
									components.push('' + years + ' year' + (years == 1 ? '' : 's'));
								}
								if (months != 0) {
									components.push('' + months + ' month' + (months == 1 ? '' : 's'));
								}
								extra = components.join(', ');
							}
							break;
					}
					score = Math.round(100*score)/100;
					row.push(score);
//					var kpi = country.kpis[metric];
//					for (var i=0; i<kpi.length; i++) {
//						extra += "\n" + kpi[i].name + ': ' + kpi[i].value;
//					}
					row.push(extra);
				} catch (err) {
					row.push(null);
					row.push(null);
				}
			}
			app.geochart.data.addRow(row);
		}

		var titleFormatter = new google.visualization.PatternFormat('{1} (Presences: {2})');
		titleFormatter.format(app.geochart.data, [0, 1, 2], 0);
		for (var i in app.geochart.metrics) {
			var metric = app.geochart.metrics[i];
			var ci = metric.columnIndex;
			var kpiFormatter = new google.visualization.PatternFormat(metric.format);
			kpiFormatter.format(app.geochart.data, [ci, ci+1]);
		}
	}
};