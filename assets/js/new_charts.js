/**
 * Created by outlander on 14/10/2014.
 */

var app = app || {};

app.newCharts = {

    setup: function() {
        $(document)
            .on('dateRangeUpdated', app.newCharts.loadData)
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
                data = response.data;
                console.log(data);
                app.state.chart = c3.generate(data)
            })
            .always(function() {
                //$('.chart-container').hideLoader();
            });
    }




};
