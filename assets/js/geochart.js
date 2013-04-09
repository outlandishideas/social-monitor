var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {

    setup:function () {

        google.load('visualization', '1', {'packages': ['geochart']});
        google.setOnLoadCallback(app.geochart.drawRegionsMap);

        $('#metric-picker')
            .on('change', function () {
                app.geochart.drawRegionsMap();
            });

    },
    currentMetric:function () {
    var select = document.getElementById('metric-picker');
    if(select){
        return select.value;
    } else {
        return 'popularityPercentage';
    }

    },
    kpiSettings:{
        'popularityPercentage':function(map){
            map.options.colorAxis.minValue = 0;
            map.options.colorAxis.maxValue = 100;
            map.columns = [
                {name:'Country', type:'string'},
                {name:'Percent of Target Audience', type:'number'}
            ];
            map.metricData = function(d, target){
                var value = 0;
                var length = d.length;

                for (var p in d){
                    value += parseInt(d[p].value);
                }

                value = value/length;

                return Math.floor((value/target)*100);
            }
        },
        'popularityTime':function(map){
            var min = 12; var max = 24;
            map.options.colorAxis.minValue = 0;
            if(app.geochart.maxValue(map)<24) {
                map.options.colorAxis.maxValue = 24
            } else {
                map.options.colorAxis.maxValue = app.geochart.maxValue(map)
            }
            map.options.colorAxis.values = [map.options.colorAxis.minValue, 12, 24, map.options.colorAxis.maxValue]
            map.options.colorAxis.colors = ['green', 'green', 'orange', 'orange'];
            map.columns = [
                {name:'Country', type:'string'},
                {name:'Months To Hit Target Audience', type:'number'}
            ];
            map.metricData = function(d, target){
                var value = 0;
                var length = d.length;

                for (var p in d){
                    value += parseInt(d[p].value);
                }

                return parseFloat(app.utils.numberFixedDecimal(value/length,2));
            }
        },
        'postsPerDay':function(map){
            map.options.colorAxis.maxValue = app.geochart.maxValue(map)
            map.options.colorAxis.colors = ['orange', 'green'];
            map.columns = [
                {name:'Country', type:'string'},
                {name:'Average Posts Per Day', type:'number'}
            ];
            map.metricData = function(d, target){
                var value = 0;
                var length = d.length;

                for (var p in d){
                    value += parseInt(d[p].value);
                }
                var num = parseFloat(app.utils.numberFixedDecimal(value/length, 2));
                return num;
            }
        }
    },
    maxValue:function (map) {
        var max = 0;
        for(var c in json){
            var kpi = json[c]['kpis'][map.metric];
            for(var i in kpi){
                var num = parseFloat(kpi[i].value);
                if(num > max) max = num;
            }
        }
        return max;
    },
    minValue:function (map) {
        var min = 0;
        for(var c in json){
            var kpi = json[c]['kpis'][map.metric];
            for(var i in kpi){
                if(kpi[i].value < min) min = kpi[i].value;
            }
        }
        return min;
    },
    drawRegionsMap:function (map) {

        var map = new google.visualization.GeoChart(document.getElementById('geo-map'));
        map.metric = app.geochart.currentMetric();
        if (map.metric in app.geochart.kpiSettings) {
            map.options = {
                datalessRegionColor: '#D5D5D5',
                colorAxis: {minValue: 0, maxValue: 15, colors: ['orange', 'green']}
            };
            app.geochart.kpiSettings[map.metric](map);
            var data = new google.visualization.DataTable();
            for(var i in map.columns){
                var column = map.columns[i];
                data.addColumn(column.type, column.name);
            }

            data = app.geochart.buildDataTable(data, map);

            map.draw(data, map.options);

            google.visualization.events.addListener(map, 'select',
                selectHandler);


            function selectHandler(e) {
                var selection = map.getSelection();
                var row=selection[0].row;
                var code = data.getValue(row,0);
                var id = json[code].id;

                var url = 'country/view/id/'+id;

                window.location.href = url;
            }

        } else {

            return;

        }

    },
    buildDataTable:function(data, map){
        for(var c in json){

            var obj = json[c];

            var country = obj['name'];
            var target = obj['target'];
            var kpi = obj['kpis'][map.metric];

            if(country && kpi){
                var metric = map.metricData(kpi,target);
                data.addRow([country, metric]);
            }

        }
        return data;
    }
};