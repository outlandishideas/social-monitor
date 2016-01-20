/**
 * Created by outlander on 16/10/2014.
 */

var app = app || {};

app.home = {
	map: null,
	mapData: null,
	countryData: [],
	groupData: [],
	fanData: [],
	metrics: {},
    totalData: undefined,

    totalScore: function() {
        if (!app.home.totalData) {
            var data = [app.home.countryData, app.home.groupData],
                total = {},
                divideBy = 0,
                i, d, badge, day;


            for (d in data) {
                for (i in data[d]) {
                    if (data[d][i].id == -1) {
                        continue;
                    }
                    divideBy += 1;
                    for (badge in data[d][i].b) {
                        for (day in data[d][i].b[badge]) {
                            if (!total.hasOwnProperty(badge)) {
                                total[badge] = {}
                            }
                            if (!total[badge].hasOwnProperty(day)) {
                                total[badge][day] = {s: 0};
                            }
                            total[badge][day].s += data[d][i].b[badge][day].s
                        }
                    }
                }
            }

            for (badge in total) {
                for (day in total[badge]) {
                    total[badge][day].s /= divideBy;
                }
            }

            app.home.totalData = {b: total};
        }

        return app.home.totalData;
    },

    setup: function(){

	    var mapArgs = window.mapArgs;
	    app.home.groupData = mapArgs.groupData;
	    app.home.countryData = mapArgs.mapData;
	    app.home.geochartMetrics = mapArgs.geochartMetrics;
	    app.home.fanData = mapArgs.fanData;

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

		    app.home.mapData = app.home.buildDataTable(app.home.countryData);

		    app.home.refreshMap();
	    });

        $('.country-list')
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

	    $('.help-text-toggle').on('click', function(e) {
	    	$(this).find('.help-text').toggleClass('open');
	    });

    },
    currentBadge: function(){
	    return $('#homepage-tabs').find('.active').data('badge');
    },
	updateAll: function() {
		app.home.updateDataAttributes();
		$('[data-badge]').each(function() {
			convertToBadge($(this));
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
	},

	/**
	 * Called when a country is clicked
	 * @param e
	 */
	mapClickHandler: function (e) {
		var selection = app.home.map.getSelection();
		app.home.map.setSelection();
		var data = app.home.mapData;
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
            $countryStats.removeClass('global');
			$countryStats.removeClass('loading');
			$('[data-badge-title]').text($('#homepage-tabs').find('dd.active').data('title'));
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
				app.home.updateAll();
			}
		});
	},

	updateDataAttributes: function() {
		var i;

		//find country list
		var data = app.home.countryData;
		$('.country-list li').each(function() {
			var $el = $(this);
			var id = $el.data('id');
			var c = _.find(data,function(c) {
				return c.id === id;
			});
			updateElement($el, c);
		});

		//country popout
		var $countryStats = $('#country-stats');
		var $div = $countryStats.find('[data-badge]');
		if ($div.length > 0) {
			var countryId = parseInt($div.data('country-id'));
			for (i = 0; i < data.length; i++) {
				if (data[i].id == countryId) {
					updateElement($div, data[i]);
					break;
				}
			}
		}

		//sbus
		data = app.home.groupData;
		for (i = 0; i<data.length; i++) {
			var g = data[i];
			$div = $('[data-group-id="'+g.id+'"]');
			updateElement($div, g);
		}

        //totalscore
        var score = app.home.totalScore();
		var stats = $('#country-stats.global');
        $div = stats.find('#overall-score[data-badge]');
        updateElement($div, score);

        //totalscore
        score = app.home.fanData;
        $div = stats.find('#overall-fans[data-badge]');
        updateElement($div, score)
	},

	searchCountries: function() {
		var search = $('.find-country #search-countries').val();
		var $list = $('.find-country .country-list');
		$list.empty();

		if(search) {
			var foundCountries = _.filter(app.home.countryData, function(c) {
				return c.n.substring(0,search.length).toLowerCase() === search.toLowerCase();
			}).slice(0,3);

			_.forEach(foundCountries, function(c) {
				var $el = $(_.template(app.templates.countryListItem, c));
				updateElement($el, c);
				convertToBadge($el);
				$list.append($el);
			});
		}

	}
};

function updateElement($el, d) {
	var day = app.home.currentDay();
	var badge = $('#homepage-tabs').find('dd.active').data('badge');
	var colorArgs = app.home.geochartMetrics[badge];

	var score = d.b[badge][day].s;
	$el.data('score', numberWithCommas(Math.round(score)));
	var color = colorArgs.colors[0];
	for (var j=0; j<colorArgs.colors.length-1; j++) {
		if (score > colorArgs.range[j] && score <= colorArgs.range[j+1]) {
			var lowColor = colorArgs.colors[j];
			var highColor = colorArgs.colors[j+1];
			if (score == lowColor || lowColor == highColor) {
				// score equals lower bound, or lower colour == upper colour
				color = lowColor;
			} else if (score == highColor) {
				// score equals upper bound
				color = highColor;
			} else {
				// score somewhere in the middle, so interpolate the colours
				lowColor = [lowColor.substring(1,3), lowColor.substring(3,5), lowColor.substring(5,7)];
				highColor = [highColor.substring(1,3), highColor.substring(3,5), highColor.substring(5,7)];
				lowColor = lowColor.map(function(e) {return parseInt(e, 16);});
				highColor = highColor.map(function(e) {return parseInt(e, 16);});
				var fraction = (score - colorArgs.range[j])/(colorArgs.range[j+1] - colorArgs.range[j]);
				color = '#';
				for (var k=0; k<lowColor.length; k++) {
					color += Math.floor(lowColor[k] + (fraction * (highColor[k] - lowColor[k]))).toString(16);
				}
			}
			break;
		}
	}
	$el.data('color', color);
}

function numberWithCommas(x) {
	return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function convertToBadge($el) {
	var score = $el.data('score');
	var color = $el.data('color');
	if (!color) {
		color = '#d2d2d2';
	}
	var $score = $el.find('[data-badge-score]');
	var $bar = $el.find('[data-badge-bar]');

	$score.text(score + $score.data('badge-score')).css('color', color);
	$bar.css({
		'width': score + '%',
		'background-color': color
	});
}