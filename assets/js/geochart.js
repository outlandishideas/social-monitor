var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {
	map: null,
	data: null,
	metrics: {},
	setup:function () {
		var $picker = $('#metric-picker');
		$picker.find('option').each(function() {
			app.geochart.metrics[$(this).attr('value')] = {
				label: $(this).text(),
				key: $(this).attr('value')
			};
		});
		google.load('visualization', '1', {'packages': ['geochart']});
		google.setOnLoadCallback(function() {
			app.geochart.map = new google.visualization.GeoChart(document.getElementById('geo-map'));
			app.geochart.map.options = {
				datalessRegionColor: '#D5D5D5',
				colorAxis: {minValue: 0, maxValue: 15, colors: ['orange', 'green']}
			};
			google.visualization.events.addListener(app.geochart.map, 'select', app.geochart.selectHandler);
			app.geochart.buildDataTable();
			app.geochart.refreshMap();
		});

		$picker.on('change', app.geochart.refreshMap);

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
		// make a view of the data, which only consists of the first column and the column for the chosen metric
		var m = app.geochart.currentMetric();
		var metric = app.geochart.metrics[m];
		metric.applyToAxis(app.geochart.map.options.colorAxis);
//		switch (metric) {
//			case 'popularityPercentage':
//				axis.values = [0, 100];
//				axis.colors = ['orange', 'green'];
//				break;
//			case 'popularityTime':
//				axis.values = [0, 12, 24, app.geochart.metrics[metric].max];
//				axis.colors = ['green', 'green', 'orange', 'orange'];
//				break;
//			case 'postsPerDay':
//				axis.values = [0, 5, app.geochart.metrics[metric].max];
//				axis.colors = ['orange', 'green', 'green'];
//				break;
//		}

		var view = new google.visualization.DataView(app.geochart.data);
		view.setColumns([0, metric.columnIndex]);

		app.geochart.map.draw(view, app.geochart.map.options);
	},
	selectHandler: function (e) {
		var selection = app.geochart.map.getSelection();
		if (selection.length > 0) {
			var id = app.geochart.data.getValue(selection[0].row, 1);
			window.location.href = 'country/view/id/'+id;
		}
	},
	buildDataTable:function(){
		if (app.mapData.length == 0) {
			return;
		}

		app.geochart.data = new google.visualization.DataTable();
		app.geochart.data.addColumn('string', 'Country');
		app.geochart.data.addColumn('string', 'Display Name');
		app.geochart.data.addColumn('number', 'Presences');
		app.geochart.data.addColumn('number', 'id');
		var columnIndex = app.geochart.data.getNumberOfColumns();

		app.geochart.metrics.popularityPercentage.max = 100;
		app.geochart.metrics.popularityTime.max = 24;
		app.geochart.metrics.postsPerDay.max = 5;

		for (var m in app.geochart.metrics) {
			var metric = app.geochart.metrics[m];
			switch (m) {
				case 'popularityPercentage':
					metric.max = 100;
					metric.applyToAxis = function(axis) {
						axis.values = [0, this.max];
						axis.colors = ['orange', 'green'];
					};
					break;
				case 'popularityTime':
					metric.max = 24;
					metric.applyToAxis = function(axis) {
						axis.values = [0, 12, this.max, this.presenceMax];
						axis.colors = ['green', 'green', 'orange', 'orange'];
					};
					break;
				case 'postsPerDay':
					metric.max = 5;
					metric.applyToAxis = function(axis) {
						axis.values = [0, this.max, this.presenceMax];
						axis.colors = ['orange', 'green', 'green'];
					};
					break;
			}
			metric.presenceMax = metric.max;
			metric.columnIndex = columnIndex;
			app.geochart.data.addColumn('number', metric.label);
			app.geochart.data.addColumn('string', metric.key + '-extra');
			columnIndex += 2;
		}

		for (var c in app.mapData) {
			var country = app.mapData[c];
			var row = [country.country, country.name, country.presences.length, country.id];
			for (var metric in app.geochart.metrics) {
				try {
					var extra = '';
					var score = app.geochart.kpiAverage(country, metric);
					app.geochart.metrics[metric].presenceMax = Math.max(app.geochart.metrics[metric].presenceMax, score);
					switch (metric) {
						case 'popularityPercentage':
							extra = '% (total audience: ' + app.utils.numberFormat(country.targetAudience) + ')';
							break;
						case 'popularityTime':
							if (score == 0) {
								extra = ' (target already reached)';
							}
							break;
					}
					row.push(Math.round(100*score)/100);
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
		// append the 'extra' column value to the actual value
		var kpiFormatter = new google.visualization.PatternFormat('{0}{1}');
		for (var i in app.geochart.metrics) {
			var ci = app.geochart.metrics[i].columnIndex;
			kpiFormatter.format(app.geochart.data, [ci, ci+1]);
		}
	}
};