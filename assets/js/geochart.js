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
		var total = 0;
		var kpiValues = country.kpis[m];
		if (kpiValues.length > 0) {
			for (var i=0; i<kpiValues.length; i++){
				total += parseInt(kpiValues[i].value);
			}
			return total/kpiValues.length;
		} else {
			throw 'No KPI values';
		}
	},
	refreshMap:function () {
		// make a view of the data, which only consists of the first column and the column for the chosen metric
		var metric = app.geochart.currentMetric();
		var axis = app.geochart.map.options.colorAxis;
		switch (metric) {
			case 'popularityPercentage':
				axis.values = [0, 100];
				axis.colors = ['orange', 'green'];
				break;
			case 'popularityTime':
				var max = 24;
				for(var c in app.mapData){
					try {
						max = Math.max(max, app.geochart.kpiAverage(app.mapData[c], metric));
					} catch (err) { }
				}
				axis.values = [0, 12, 24, max];
				axis.colors = ['green', 'green', 'orange', 'orange'];
				break;
			case 'postsPerDay':
				var max = 5;
				for(var c in app.mapData){
					try {
						max = Math.max(max, app.geochart.kpiAverage(app.mapData[c], metric));
					} catch (err) { }
				}
				axis.values = [0, 5, max];
				axis.colors = ['orange', 'green', 'green'];
				break;
		}

		var columnIndex = app.geochart.metrics[metric].columnIndex;

		var view = new google.visualization.DataView(app.geochart.data);
		view.setColumns([0, columnIndex]);

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
		app.geochart.data.addColumn('number', 'id');
		var columnIndex = 2;
		for (var m in app.geochart.metrics) {
			var metric = app.geochart.metrics[m];
			metric.columnIndex = columnIndex;
			app.geochart.data.addColumn('number', metric.label);
			app.geochart.data.addColumn('string', metric.key + '-extra');
			columnIndex += 2;
		}

		for (var c in app.mapData) {
			var country = app.mapData[c];
			var row = [country.name, country.id];
			if (country.kpis) {
				for (var metric in app.geochart.metrics) {
					try {
						var score = app.geochart.kpiAverage(country, metric);
						var extra = '';
						if (metric == 'popularityPercentage') {
							score = 100*score/country.target;
							extra = '%';
						}
						row.push(Math.round(100*score)/100);
//						var kpi = country.kpis[metric];
//						for (var i=0; i<kpi.length; i++) {
//							extra += "\n" + kpi[i].name + ': ' + kpi[i].value;
//						}
						row.push(extra);
					} catch (err) {
						row.push(0);
						row.push('');
					}
				}
				app.geochart.data.addRow(row);
			}
		}

		// append the 'extra' column value to the actual value
		var formatter = new google.visualization.PatternFormat('{0}{1}');
		for (var i in app.geochart.metrics) {
			var ci = app.geochart.metrics[i].columnIndex;
			formatter.format(app.geochart.data, [ci, ci+1]);
		}
	}
};