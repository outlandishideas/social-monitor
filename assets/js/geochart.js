var app = app || {};

/**
 * D3 chart functions
 */
app.geochart = {
	map: null,
	data: null,
	metrics: {},
    groupData: null,
	setup:function ($mapDiv) {
		$mapDiv.on('click', '.country .close', function(e) {
			e.preventDefault();
			$(this).closest('.country').remove();
		});

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

        app.geochart.drawGroups();
        app.geochart.colorGroups();

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
    drawGroups: function() {
        $groupContainer = $('#group-map');

        app.geochart.groupData = groupData;


        for(var i in app.geochart.groupData){
            var group = app.geochart.groupData[i];

            $groupContainer.append('<div id="'+ i +'" class="group-display"><div class="group-display-title">'+ group.name +'</div><div class="group-display-score"></div></div>')
            var g = {
                $group: $('#'+i),
                groupData: group
            }
            app.state.groupCharts[i] = g;
        }

    },
    colorGroups: function() {
        var day = app.geochart.currentDay();
        var metric = app.geochart.currentMetric();
        console.log(day);
        console.log(metric);

        for(var i in app.state.groupCharts){
            var g = app.state.groupCharts[i];
            var color = g.groupData[metric][day].color;
            var score = Math.round(g.groupData[metric][day].score);

            g.$group.css('background-color', color);
            g.$group.find('.group-display-score').empty().append(score);

        }
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
                $country.removeClass('hide');
			})
			.always(function() {
				$loading.hide();
			});
	},
	buildDataTable:function(){
		if (typeof mapData == 'undefined' || mapData.length == 0) {
			return;
		}

		// define the columns
		app.geochart.data = new google.visualization.DataTable();
		app.geochart.data.addColumn('string', 'Country');
		app.geochart.data.addColumn('string', 'Display Name');
		app.geochart.data.addColumn('number', 'Presences');
		app.geochart.data.addColumn('number', 'id');
		var columnIndex = app.geochart.data.getNumberOfColumns();

		// add 2 columns per metric (value & label)
		for (var m in app.geochart.metrics) {
            var metric = app.geochart.metrics[m];
            app.geochart.metrics[m].days = [];
            for(var i = 1; i < 31; i++ ){
                app.geochart.metrics[m].days[i] = {};
                app.geochart.metrics[m].days[i].columnIndex = columnIndex;
                app.geochart.data.addColumn('number', metric.label);
                app.geochart.data.addColumn('string', metric.key + '_' + i + '-label');
                columnIndex += 2;
            }
		}

		// add one row per country
		for (var c in mapData) {
			var country = mapData[c];
			var row = [country.country, country.name, country.presenceCount, country.id];
			for (var m in app.geochart.metrics) {
                for(var i = 1; i < 31; i++ ){
                    if(typeof country[m][i] !="undefined"){
                        row.push(country[m][i].score);
                        row.push('' + country[m][i].label);
                    } else {
                        row.push(null);
                        row.push(null);
                    }
                }

			}
			app.geochart.data.addRow(row);
		}

		// apply the tooltip formatters for each metric
		var titleFormatter = new google.visualization.PatternFormat('{1} (Presences: {2})');
		titleFormatter.format(app.geochart.data, [0, 1, 2], 0);
		var kpiFormatter = new google.visualization.PatternFormat('{1}');
		for (var m in app.geochart.metrics) {
			var metric = app.geochart.metrics[m];
            for(var i = 1; i < 31; i++ ){
                var ci = metric.days[i].columnIndex;
                kpiFormatter.format(app.geochart.data, [ci, ci+1]);
            }
		}
	}
};