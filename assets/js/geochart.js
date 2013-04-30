var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {
	map: null,
	data: null,
	setup:function () {

		google.load('visualization', '1', {'packages': ['geochart']});
		google.setOnLoadCallback(function() {
			app.geochart.map = new google.visualization.GeoChart(document.getElementById('geo-map'));
			app.geochart.map.options = {
				datalessRegionColor: '#D5D5D5',
				colorAxis: {minValue: 0, maxValue: 15, colors: ['orange', 'green']}
			};
			google.visualization.events.addListener(app.geochart.map, 'select', app.geochart.selectHandler);
			app.geochart.refreshMap();
		});

		$('#metric-picker')
			.on('change', app.geochart.refreshMap);

	},
	currentMetric:function () {
		return $('#metric-picker').val();
	},
	refreshMap:function () {
		app.geochart.buildDataTable();
		app.geochart.map.draw(app.geochart.data, app.geochart.map.options);
	},
	selectHandler: function (e) {
		var selection = app.geochart.map.getSelection();
		var row = selection[0].row;
		var code = app.geochart.data.getValue(row, 0);
		var id = app.mapData[code].id;

		window.location.href = 'country/view/id/'+id;
	},
	buildDataTable:function(){
		app.geochart.data = new google.visualization.DataTable();
		app.geochart.data.addColumn('string', 'Country');
		if (app.mapData.length == 0) {
			return;
		}

		var kpiAverage = function(country, m) {
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
		};

		var averageFunc = kpiAverage;

		var metric = app.geochart.currentMetric();
		switch (metric) {
			case 'popularityPercentage':
				averageFunc = function(country, m) {
					return 100*kpiAverage(country, m)/country.target;
				};
				app.geochart.data.addColumn('number', 'Percent of Target Audience');
				app.geochart.map.options.colorAxis.values = [0, 100];
				app.geochart.map.options.colorAxis.colors = ['orange', 'green'];
				break;
			case 'popularityTime':
				var max = 24;
				for(var c in app.mapData){
					try {
						max = Math.max(max, averageFunc(app.mapData[c], metric));
					} catch (err) { }
				}
				app.geochart.data.addColumn('number', 'Months To Hit Target Audience');
				app.geochart.map.options.colorAxis.values = [0, 12, 24, max];
				app.geochart.map.options.colorAxis.colors = ['green', 'green', 'orange', 'orange'];
				break;
			case 'postsPerDay':
				var max = 5;
				for(var c in app.mapData){
					try {
						max = Math.max(max, averageFunc(app.mapData[c], metric));
					} catch (err) { }
				}
				app.geochart.data.addColumn('number', 'Average Posts Per Day');
				app.geochart.map.options.colorAxis.values = [0, 5, max];
				app.geochart.map.options.colorAxis.colors = ['orange', 'green', 'green'];
				break;
		}

		for(var c in app.mapData){
			var obj = app.mapData[c];
			try {
				var score = averageFunc(obj, metric);
				app.geochart.data.addRow([obj.name, Math.round(score*100)/100]);
			} catch (err) { }
		}
	}
};