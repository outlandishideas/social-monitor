/**
 * Created by outlander on 14/10/2014.
 */

var app = app || {};

app.newCharts = {

    setup: function() {
        $(document)
            .on('dateRangeUpdated', app.newCharts.loadData)
        var $chartPicker = $('#chart-picker');
        if ($chartPicker.length > 0) {
            $chartPicker.on('change', app.newCharts.refreshCharts);
        }

        app.newCharts.loadData();

    },

    refreshCharts: function() {
        app.state.chart = null;
        app.newCharts.loadData();
    },

    currentMetric:function () {
        return $('#chart-picker').val();
    },

    loadData: function() {
        var chart = app.newCharts.currentMetric();
        var dateRange = app.state.dateRange.map(function(d){
            return $.datepicker.formatDate('yy-mm-dd', Date.parse(d));
        });

        var $chart = $('#new-chart');
        var url = $chart.data('controller') + '/graph-data';
        var id = $chart.data('id');

        var params = {
            dateRange: dateRange,
            chart: chart,
            id: id
        };

        app.api.get(url, params)
            .done(function(response) {
                var data = response.data;
		        $('.chart-description').text(data.description ? data.description : '');
		        var chartArgs = data.chartArgs;
		        if (chartArgs.axis.x.type == 'timeseries') {
			        chartArgs.axis.x.tick = {
				        format: function(x) {
					        return x.getDate() + '/' + (x.getMonth()+1) + '/' + (x.getFullYear()-2000);
				        }
			        };
		        }
                app.state.chart = c3.generate(chartArgs)
            })
            .always(function() {
                //$('.chart-container').hideLoader();
            });
    }




};
