var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {

    setup:function () {

        google.load('visualization', '1', {'packages': ['geochart']});
        google.setOnLoadCallback(app.geochart.drawRegionsMap);


    },
    currentMetric:function () {
    var select = document.getElementById('select-kpi');
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
        },
        'popularityTime':function(map){

        }
    },
    drawRegionsMap:function (map) {

        var map = new google.visualization.GeoChart(document.getElementById('geo-map'));
        map.metric = app.geochart.currentMetric();
        if (map.metric in app.geochart.kpiSettings) {
            map.options = {
                colorAxis: {minValue: 0, maxValue: 15, colors: ['orange', 'green']}
            };
            app.geochart.kpiSettings[map.metric](map);
            var data = new google.visualization.DataTable();
            for(var i in map.columns){
                var column = map.columns[i];
                data.addColumn(column.type, column.name);
            }

            data = app.geochart.buildDataTable(data, map);

            console.log(data);

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
            for(var key in obj){
                var attrValue = obj[key];
                if(key == 'name'){
                    var country = attrValue;
                } else if (key == 'target') {
                    var target = attrValue;
                } else if (key == 'kpis'){
                    for(var kpi in attrValue){
                        console.log(kpi,map.metric);
                        if(kpi == map.metric){
                            var value = 0;
                            for (var p in attrValue[kpi]){
                                value += parseInt(attrValue[kpi][p].popularity);
                            }
                            var length = attrValue[kpi].length;
                            value = value/length;
                            var metric = Math.floor((value/target)*100);
                        }
                    }
                }

            }
            console.log(country, metric);
            if(country && metric){
                data.addRow([country, metric]);
            }
        }
        return data;
    }
};