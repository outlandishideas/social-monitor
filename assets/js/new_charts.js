/**
 * Created by outlander on 14/10/2014.
 */

var app = app || {};

app.newCharts = {

    setup: function() {

        var $metricPicker = $('#metric-picker');
        if ($metricPicker.length > 0) {
            $metricPicker.on('change', app.newCharts.refreshCharts);
        }

        app.newCharts.loadData();

    },

    refreshCharts: function() {
        app.state.chart = null;
        app.newCharts.loadData();
    },

    currentMetric:function () {
        return $('#metric-picker').val();
    },

    loadData: function() {
        var chart = app.newCharts.currentMetric();
        var dateRange = "2014-09-15,2014-10-15";

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
                console.log(response.data);
                if(app.state.chart){
                    app.state.chart.flow({data: response.data.data, duration: 1500});
                } else {
                    app.state.chart = c3.generate(response.data)
                }
            })
            .always(function() {
                //$('.chart-container').hideLoader();
            });
    }




};
