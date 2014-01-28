var app = app || {};

app.geochart = {
	map: null,
    smallMaps: [],
	data: null,
	metrics: {},
	setup:function ($mapDiv) {
		$('#map-sidebar').on('click', '.country .close', function(e) {
			e.preventDefault();

            var currentTab = $('#map-tabs').find('.active').data('val');
            var descBox = '#'+currentTab+'-desc';

			$(this).parents('.country').empty().hide();
            $(descBox).removeClass('hide');
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

			app.geochart.data = app.geochart.buildDataTable(mapData);

            app.geochart.refreshMap();
		});

		app.geochart.drawGroups();
		app.geochart.refreshGroups();

        app.geochart.drawSmallMaps();
        app.geochart.refreshSmallMaps();

        for(var id in app.state.groupCharts){
            var g = app.state.groupCharts[id];

            g.$group.on('click', function(e){
                e.preventDefault();
                app.geochart.loadCampaignStats($(this).attr('id'), 'group');
            });
        }

        for(var id in app.state.smallMapsCharts){
            var r = app.state.smallMapsCharts[id];

            r.$smallMap.on('click', function(e){
                e.preventDefault();
                app.geochart.loadCampaignStats($(this).attr('id'), 'country');
            });
        }

		var $tabs = $('#map-tabs');
		var $active = $tabs.find('li.active');
		if ($active.length == 0) {
			$active.find('li:first').addClass('active');
		}
		$tabs.on('click', 'li:not(.download)', function(event){
			event.preventDefault();

            var badgeType = $(this).data('val');

			if(!$(this).hasClass('active')){
				$(this).addClass('active')
					.siblings('li.active').removeClass('active');
				app.geochart.refreshMap();
				app.geochart.refreshGroups();
                app.geochart.refreshSmallMaps();
                $('.desc-box').each(function(){
                    $(this).addClass('hide');
                })
				var $country = $('#map-sidebar').find('#campaign-stats');
				if ($country.length > 0) {
					var id = $country.data('id');
                    var model = $country.data('model');
					app.geochart.loadCampaignStats(id, model);
				} else {
                    if(badgeType == 'total'){
                        $('#' + badgeType + '-desc').removeClass('hide');
                    } else {
                        $('#' + badgeType + '-desc').removeClass('hide');
                    }
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

			$groupContainer.append('<li id="'+ id +'" class="group-display"><span class="label">'+ group.n +'</span><span class="score"></span></li>');
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
    drawSmallMaps: function() {
        var $smallMapsContainer = $('#small-maps ul');

        var smallMaps = smallMapData;
        for(var id in smallMaps){
            var smallMap = smallMaps[id];

            $smallMapsContainer.append('<li id="'+ id +'" class="small-maps-display"><span class="label">'+ smallMap.n +'</span><span class="score"></span></li>');
            app.state.smallMapsCharts[id] = {
                $smallMap: $('#'+id),
                smallMapData: smallMap
            };
        }
    },
    /**
     * Update the value and colour of each of the region
     */
    refreshSmallMaps: function() {
        var day = app.geochart.currentDay();
        var metric = app.geochart.currentMetric();

        for(var id in app.state.smallMapsCharts){
            var g = app.state.smallMapsCharts[id];
            var color = g.smallMapData.b[metric][day].c;
            var score = Math.round(g.smallMapData.b[metric][day].s);
            var title = g.smallMapData.n +' (Presences: '+ g.smallMapData.p +')\n'+ app.geochart.metrics[metric].label +': '+ g.smallMapData.b[metric][day].l;

            console.log(color,score,title);

            g.$smallMap.css('background-color', color);
            g.$smallMap.attr('title', title);
            g.$smallMap.find('.score').empty().append(score);
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

        for(var i in app.geochart.smallMaps){
            var country = app.geochart.smallMaps[i];
            country.map.options.colorAxis.values = metric.range;
            country.map.options.colorAxis.colors = metric.colors;
            view = new google.visualization.DataView(country.data);
            view.setColumns([0, metric.days[day].columnIndex]);
            country.map.draw(view, country.map.options);
        }
	},
	/**
	 * Called when a country is clicked
	 * @param e
	 */
 	mapClickHandler: function (e) {
		var selection = app.geochart.map.getSelection();
        app.geochart.map.setSelection();
        var data = app.geochart.data;
        if(selection.length == 0){
            for(var i in app.geochart.smallMaps){
                selection = app.geochart.smallMaps[i].map.getSelection();
                if(selection.length > 0) {
                    app.geochart.smallMaps[i].map.setSelection();
                    data = app.geochart.smallMaps[i].data;
                    break;
                }
            }
        }
		if (selection.length > 0) {
			var id = data.getValue(selection[0].row, 3);
            if(id != -1){
                app.geochart.loadCampaignStats(id, 'country');
                $('.desc-box').addClass('hide');
            }
        }
	},
	/**
	 * Fetches the country summary over ajax, and appends it to the map.
	 * @param id
	 * @param type country, group or region
	 */
	loadCampaignStats: function(id, type, score) {
		var $mapSidebar = $('#map-sidebar');
		var $loading = $mapSidebar.find('.loading');
		$loading.show();
		$.get('index/campaign-stats/', {id: id, model: type, metric: app.geochart.currentMetric()})
			.done(function(data) {
				var $campaign = $(data);
				$campaign.data('id', id);
                $mapSidebar.find('.country').empty().append($campaign).show();
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
	buildDataTable:function(mapData){
		if (typeof mapData == 'undefined' || mapData.length == 0) {
			return;
		}

		var i, m, metric;

		// define the columns
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Country');
        data.addColumn('string', 'Display Name');
        data.addColumn('number', 'Presences');
        data.addColumn('number', 'id');
		var columnIndex = data.getNumberOfColumns();

		// add 2 columns per metric (value & label)
		for (m in app.geochart.metrics) {
			metric = app.geochart.metrics[m];
			metric.days = [];
			for(i = 1; i < 31; i++ ){
				metric.days[i] = {};
				metric.days[i].columnIndex = columnIndex;
				data.addColumn('number', metric.label);
                data.addColumn('string', metric.key + '_' + i + '-label');
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
            data.addRow(row);
		}

		// apply the tooltip formatters for each metric
		var titleFormatter = new google.visualization.PatternFormat('{1} (Presences: {2})');
		titleFormatter.format(data, [0, 1, 2], 0);
		var kpiFormatter = new google.visualization.PatternFormat('{1}');
		for (m in app.geochart.metrics) {
			metric = app.geochart.metrics[m];
			for(i = 1; i < 31; i++ ){
				var ci = metric.days[i].columnIndex;
				kpiFormatter.format(data, [ci, ci+1]);
			}
		}

        return data;
	}
};