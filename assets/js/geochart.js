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
                colorAxis: {},
				keepAspectRatio: true,
				backgroundColor: "transparent"
            };
			google.visualization.events.addListener(app.geochart.map, 'select', app.geochart.mapClickHandler);

			app.geochart.data = app.geochart.buildDataTable(mapData);

            app.geochart.refreshMap();
		});

		app.state.groupCharts = app.geochart.initCampaigns('#group-map ul', groupData, 'group-display', 'group');
		app.state.smallMapsCharts = app.geochart.initCampaigns('#small-maps ul', smallMapData, 'small-maps-display', 'country');

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
				app.geochart.refreshCampaigns(app.state.groupCharts);
                app.geochart.refreshCampaigns(app.state.smallMapsCharts);
                $('.desc-box').each(function(){
                    $(this).addClass('hide');
                });
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
	 * Creates DOM elements, adds them to the screen and initialises their data
	 * @param selector
	 * @param data
	 * @param className
	 * @param modelType
	 * @returns {Array}
	 */
	initCampaigns: function(selector, data, className, modelType) {
		var $container = $(selector);

		var wrapper = [];
		for(var i in data){
			var campaignData = data[i];
			var id = campaignData.id;

			var $dom = $('<li></li>')
				.addClass(className)
				.data('id', id)
				.append('<span class="label">'+ campaignData.n +'</span>')
				.append('<span class="score"></span>')
				.appendTo($container);

			$dom.on('click', function(e){
				e.preventDefault();
				app.geochart.loadCampaignStats($(this).data('id'), modelType);
			});

			wrapper.push({
				$dom: $dom,
				data: campaignData
			});
		}
		app.geochart.refreshCampaigns(wrapper);
		return wrapper;
	},
	refreshCampaigns: function(campaigns) {
		var day = app.geochart.currentDay();
		var metric = app.home.currentBadge();

		for(var i in campaigns){
			var g = campaigns[i];
			var data = g.data;
			var color = data.b[metric][day].c;
			var score = Math.round(data.b[metric][day].s);
			var title = data.n +' (Presences: '+ data.p +')\n'+ app.geochart.metrics[metric].label +': '+ data.b[metric][day].l;

			g.$dom
				.css('background-color', color)
				.attr('title', title)
				.find('.score').empty().append(score);
		}
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
		var metric = app.geochart.metrics[app.home.currentBadge()];
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
            }
        }
	},
	/**
	 * Fetches the country summary over ajax, and appends it to the map.
	 * @param id
	 * @param type country, group or region
	 */
	loadCampaignStats: function(id, type) {
		var $countryStats = $('#country-stats');
		$countryStats.load('index/country-stats/id/' + id, function(event){
			app.home.update();
		});

	},
	/**
	 * Creates the data structure used by the geochart.
	 * Called when the geochart is ready for data to be added to it
	 */
	buildDataTable: function(mapData){
		// define the columns
		var data = new google.visualization.DataTable();
		data.addColumn('string', 'Country');
		data.addColumn('string', 'Display Name');
		data.addColumn('number', 'Presences');
		data.addColumn('number', 'id');

		if (typeof mapData == 'undefined' || mapData.length == 0) {
			return data;
		}

		var i, m, metric;

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