var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {
	map: null,
	data: null,
	metrics: {},
    groupData: null,
	setup:function ($groupDiv) {

		// copy the provided metrics to app.geochart, and populate values
		app.geochart.metrics = geochartMetrics;
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

		$('#map-tabs').find('li').each(function(){
            if(!$(this).hasClass('active')) {
                $(this).on('click', function() {
                    var $country = $mapDiv.find('.country');
                    if ($country.length > 0) {
                        app.geochart.loadCountryStats($country.data('id'));
                        $country.remove();
                    }
                });
            }
        });
	},
	currentMetric:function () {
		return $('#map-tabs').find('li.active').data('val');
	},
    currentDay:function () {
        var day = $('#slider').data('val');
        if(typeof day == 'undefined'){
            return 30;
        } else {
            return day;
        }
    },
	refreshMap:function () {
		var metric = app.geochart.metrics[app.geochart.currentMetric()];
        var day = app.geochart.currentDay();
		app.geochart.map.options.colorAxis.values = metric.range;
		app.geochart.map.options.colorAxis.colors = metric.colors;

		// make a view of the data, which only consists of the first column and the column for the chosen metric
		var view = new google.visualization.DataView(app.geochart.data);
		view.setColumns([0, metric.days[day].columnIndex]);

		app.geochart.map.draw(view, app.geochart.map.options);
	}
};