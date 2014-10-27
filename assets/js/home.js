/**
 * Created by outlander on 16/10/2014.
 */

var app = app || {};

app.home = {
	map: null,
	smallMaps: [],
	mapData: null,
	metrics: {},
    setup: function(){

	    //todo: update SBUs and popup

	    var mapArgs = window.mapArgs;

	    // copy the provided metrics to app.home, and populate values
	    app.home.metrics = mapArgs['geochartMetrics'];
	    for (var i in app.home.metrics) {
		    app.home.metrics[i].key = i;
	    }

	    google.load('visualization', '1', {'packages': ['geochart']});
	    google.setOnLoadCallback(function() {
		    app.home.map = new google.visualization.GeoChart(document.getElementById('geo-map'));
		    app.home.map.options = {
			    datalessRegionColor: '#D5D5D5',
			    colorAxis: {},
			    keepAspectRatio: true,
			    backgroundColor: "transparent"
		    };
		    google.visualization.events.addListener(app.home.map, 'select', app.home.mapClickHandler);

		    app.home.mapData = app.home.buildDataTable(mapArgs['mapData']);

		    app.home.refreshMap();
	    });

        $('.small-country-list')
            .on('click', 'li a', function(event){
                event.preventDefault();
                var id = $(this).parent('li').data('id');
                app.home.loadCampaignStats(id);
            });

        $('.badge-presences-buttons')
            .on('click', 'li a', function(event){
                event.preventDefault();
                var $this = $(this);
                var type = $(this).attr('href').replace('#','');
                $this.parents('.badge-presences-buttons')
                    .find('li a').removeClass('active')
                    .filter('[href="#' +type+ '"]').addClass('active');
                $this.parents('.badge-small')
                    .find('.badge-presences').hide()
                    .filter('[data-'+type+'-presences]').show();
            })
            .end().find('.badge-presences').hide();

	    var $homepageTabs = $('#homepage-tabs');
	    $homepageTabs.on('click', 'a', function(e) {
		    app.home.setActiveTab($(this).closest('dd'));
	    });

	    var badge = window.location.hash.replace("#", "");
	    if(!badge){
		    badge = "total";
	    }
	    $homepageTabs.find('a[href="#' + badge + '"]').trigger('click');

	    app.home.initDateSlider($('#map-date'));

    },
    currentBadge: function(){
	    return $('#homepage-tabs').find('.active').data('badge');
    },
	updateAll: function() {
		var badge = app.home.currentBadge();
		$('[data-' + badge + ']').each(function(){
			var $this = $(this);
			var score = $this.data(badge);
			var color = $this.data(badge + '-color');
			if (!color) {
				color = '#d2d2d2';
			}
			var $score = $this.find('[data-badge-score]');
			var $bar = $this.find('[data-badge-bar]');
			$score.text(score + '%').css('color', color);
			$bar.css({
				'width': score + '%',
				'background-color': color
			});
		});
		app.home.refreshMap();
	},
    setActiveTab: function($tab){
	    var badge = $tab.data('badge');

        $('#homepage-tabs').find('dd').removeClass('active');
	    $tab.addClass('active');

        //updating badge Titles
        $('[data-badge-title]').text($tab.data('title'));

        //show descriptions
        $('.badge-description').hide()
	        .filter('[data-badge-name="' + badge + '"]').show();

	    app.home.updateAll();
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
				app.home.loadCampaignStats($(this).data('id'));
			});

			wrapper.push({
				$dom: $dom,
				data: campaignData
			});
		}
		return wrapper;
	},
//	refreshCampaigns: function(campaigns) {
//		var day = app.home.currentDay();
//		var metric = app.home.currentBadge();
//
//		for(var i in campaigns){
//			var g = campaigns[i];
//			var data = g.data;
//			var color = data.b[metric][day].c;
//			var score = Math.round(data.b[metric][day].s);
//			var title = data.n +' (Presences: '+ data.p +')\n'+ app.home.metrics[metric].label +': '+ data.b[metric][day].l;
//
//			g.$dom
//				.css('background-color', color)
//				.attr('title', title)
//				.find('.score').empty().append(score);
//		}
//	},
	currentDay:function () {
		var day = $('#map-date').find('.range-slider').data('val');
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
		if (!app.home.map) {
			return; // setup has not been called yet
		}
		var metric = app.home.metrics[app.home.currentBadge()];
		var day = app.home.currentDay();
		app.home.map.options.colorAxis.values = metric.range;
		app.home.map.options.colorAxis.colors = metric.colors;

		// make a view of the data, which only consists of the first column and the column for the chosen metric
		var view = new google.visualization.DataView(app.home.mapData);
		view.setColumns([0, metric.days[day].columnIndex]);

		app.home.map.draw(view, app.home.map.options);

		for(var i in app.home.smallMaps){
			var country = app.home.smallMaps[i];
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
		var selection = app.home.map.getSelection();
		app.home.map.setSelection();
		var data = app.home.mapData;
		if(selection.length == 0){
			for(var i in app.home.smallMaps){
				selection = app.home.smallMaps[i].map.getSelection();
				if(selection.length > 0) {
					app.home.smallMaps[i].map.setSelection();
					data = app.home.smallMaps[i].data;
					break;
				}
			}
		}
		if (selection.length > 0) {
			var id = data.getValue(selection[0].row, 3);
			if(id != -1){
				app.home.loadCampaignStats(id);
			}
		}
	},
	/**
	 * Fetches the country summary over ajax, and appends it to the map.
	 * @param id
	 */
	loadCampaignStats: function(id) {
		var $countryStats = $('#country-stats');
		$countryStats.addClass('loading');
		$countryStats.load('country/stats-panel/id/' + id, function(event){
			$countryStats.removeClass('loading');
			app.home.updateAll();
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
		for (m in app.home.metrics) {
			metric = app.home.metrics[m];
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
			for (m in app.home.metrics) {
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
		for (m in app.home.metrics) {
			metric = app.home.metrics[m];
			for(i = 1; i < 31; i++ ){
				var ci = metric.days[i].columnIndex;
				kpiFormatter.format(data, [ci, ci+1]);
			}
		}

		return data;
	},

	initDateSlider: function($dateSlider) {
		var currentDate = Date.parse($dateSlider.data('current-date'));
		var dayRange = parseInt($dateSlider.data('day-range'));
		var $slider = $dateSlider.find('.range-slider');
		var $text = $dateSlider.find('.date-range-text');
		var $input = $dateSlider.find('input[type=hidden]');

		var lastVal = null;
		$slider.on('change.fndtn.slider', function(){
			var value = parseInt($input.val());
			if (!isNaN(value) && value != lastVal) {
				lastVal = value;
				var days = dayRange - value;
				var now = new Date(currentDate);
				now.addDays(-days);
				var then = now.clone();
				then.addDays(-dayRange);
				$slider.data('val', value);
				$text.text( then.toString('dd MMM yyyy') + ' - ' + now.toString('dd MMM yyyy') );
				app.home.refreshMap();
			}
		});
	}
}

;
