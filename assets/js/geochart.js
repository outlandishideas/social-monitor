var app = app || {};

app.geochart = {
	map: null,
	data: null,
	metrics: {},
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
			google.visualization.events.addListener(app.geochart.map, 'select', app.geochart.mapClickHandler);
			app.geochart.buildDataTable();
			app.geochart.refreshMap();
		});

		app.geochart.drawGroups();
		app.geochart.refreshGroups();

        app.geochart.drawRegions();
        app.geochart.refreshRegions();

        for(var id in app.state.groupCharts){
            var g = app.state.groupCharts[id];

            g.$group.on('click', function(e){
                e.preventDefault();
                app.geochart.loadCountryStats($(this).attr('id'), 'group');
            });
        }

        for(var id in app.state.regionCharts){
            var r = app.state.regionCharts[id];

            r.$region.on('click', function(e){
                e.preventDefault();
                app.geochart.loadCountryStats($(this).attr('id'), 'region');
            });
        }

		var $tabs = $('#map-tabs');
		var $active = $tabs.find('li.active');
		if ($active.length == 0) {
			$active.find('li:first').addClass('active');
		}
		$tabs.on('click', 'li', function(event){
			event.preventDefault();
			if(!$(this).hasClass('active')){
				$(this).addClass('active')
					.siblings('li.active').removeClass('active');
				app.geochart.refreshMap();
				app.geochart.refreshGroups();
                app.geochart.refreshRegions();
				var $country = $mapDiv.find('.country');
				if ($country.length > 0) {
					var id = $country.data('id');
					$country.remove();
					app.geochart.loadCountryStats(id);
				}
			}
		})
	},
	/**
	 * Add the groups in global groupData to the DOM, and store them in app.state.groupCharts
	 */
	drawGroups: function() {
		var $groupContainer = $('#group-map ul');

		var groups = groupData;
		for(var id in groups){
			var group = groups[id];

			$groupContainer.append('<li id="'+ id +'" class="region-display"><span class="label">'+ group.n +'</span><span class="score"></span></li>');
			app.state.groupCharts[id] = {
				$group: $('#'+id),
				groupData: group
			};
		}
	},
	/**
	 * Update the value and colour of each of the groups
	 */
	refreshGroups: function() {
		var day = app.geochart.currentDay();
		var metric = app.geochart.currentMetric();

		for(var id in app.state.groupCharts){
			var g = app.state.groupCharts[id];
			var color = g.groupData.b[metric][day].c;
			var score = Math.round(g.groupData.b[metric][day].s);
            var title = g.groupData.n +' (Presences: '+ g.groupData.p +')\n'+ app.geochart.metrics[metric].label +': '+ g.groupData.b[metric][day].l;

			g.$group.css('background-color', color);
            g.$group.attr('title', title);
			g.$group.find('.score').empty().append(score);
		}
	},

    /**
     * Add the regions in global groupData to the DOM, and store them in app.state.groupCharts
     */
    drawRegions: function() {
        var $regionContainer = $('#region-map ul');

        var regions = regionData;
        for(var id in regions){
            var region = regions[id];

            $regionContainer.append('<li id="'+ id +'" class="region-display"><span class="label">'+ region.n +'</span><span class="score"></span></li>');
            app.state.regionCharts[id] = {
                $region: $('#'+id),
                regionData: region
            };
        }
    },
    /**
     * Update the value and colour of each of the region
     */
    refreshRegions: function() {
        var day = app.geochart.currentDay();
        var metric = app.geochart.currentMetric();

        for(var id in app.state.regionCharts){
            var g = app.state.regionCharts[id];
            var color = g.regionData.b[metric][day].c;
            var score = Math.round(g.regionData.b[metric][day].s);
            var title = g.regionData.n +' (Presences: '+ g.regionData.p +')\n'+ app.geochart.metrics[metric].label +': '+ g.regionData.b[metric][day].l;

            g.$region.css('background-color', color);
            g.$region.attr('title', title);
            g.$region.find('.score').empty().append(score);
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
	/**
	 * Changes the view of the data used by the geochart
	 */
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
 	mapClickHandler: function (e) {
		var selection = app.geochart.map.getSelection();
		if (selection.length > 0) {
			var id = app.geochart.data.getValue(selection[0].row, 3);
			app.geochart.loadCountryStats(id, 'country');
        }
	},
	/**
	 * Fetches the country summary over ajax, and appends it to the map.
	 * @param id
	 */
	loadCountryStats: function(id, model) {
		var $mapSidebar = $('#map-sidebar');
		var $loading = $mapSidebar.find('.loading');
		$loading.show();
        $mapSidebar.find('.country').empty();
		$.get('index/country-stats/', {id: id, model: model, metric: app.geochart.currentMetric()})
			.done(function(data) {
				var $country = $(data);
				$country.data('id', id);
                $mapSidebar.find('.country').append($country);
				$country.removeClass('hide');
			})
			.always(function() {
				$loading.hide();
                $mapSidebar.find('.instructions').hide();
			});
	},
	/**
	 * Creates the data structure used by the geochart.
	 * Called when the geochart is ready for data to be added to it
	 */
	buildDataTable:function(){
		if (typeof mapData == 'undefined' || mapData.length == 0) {
			return;
		}

		var i, m, metric;

		// define the columns
		app.geochart.data = new google.visualization.DataTable();
		app.geochart.data.addColumn('string', 'Country');
		app.geochart.data.addColumn('string', 'Display Name');
		app.geochart.data.addColumn('number', 'Presences');
		app.geochart.data.addColumn('number', 'id');
		var columnIndex = app.geochart.data.getNumberOfColumns();

		// add 2 columns per metric (value & label)
		for (m in app.geochart.metrics) {
			metric = app.geochart.metrics[m];
			metric.days = [];
			for(i = 1; i < 31; i++ ){
				metric.days[i] = {};
				metric.days[i].columnIndex = columnIndex;
				app.geochart.data.addColumn('number', metric.label);
				app.geochart.data.addColumn('string', metric.key + '_' + i + '-label');
				columnIndex += 2;
			}
		}

		// add one row per country
		for (var c in mapData) {
			var country = mapData[c];
			var row = [country.c, country.n, country.p, country.id];
			for (m in app.geochart.metrics) {
				for(i = 1; i < 31; i++ ){
					if(typeof country.b[m][i] != "undefined"){
						row.push(country.b[m][i].s);
						row.push('' + country.b[m][i].l);
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
		for (m in app.geochart.metrics) {
			metric = app.geochart.metrics[m];
			for(i = 1; i < 31; i++ ){
				var ci = metric.days[i].columnIndex;
				kpiFormatter.format(app.geochart.data, [ci, ci+1]);
			}
		}
	}
};