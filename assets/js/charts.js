var app = app || {};

/**
 * D3 chart functions
 */
app.charts = {
	datetimeFormat: d3.time.format('%Y-%m-%d %H:%M:%S'),
	dateFormat: d3.time.format('%Y-%m-%d'),
	setup:function () {
		var $charts = $('#charts');
		app.state.controller = $charts.data('controller');
		app.state.controllerLabel = $charts.data('controller-label');
		if (!app.state.controllerLabel) {
			app.state.controllerLabel = app.state.controller;
		}

		$(document)
			.on('dateRangeUpdated', app.charts.refreshCharts)
			.on('dataChanged', app.charts.refreshCharts);

		var $metricPicker = $('#metric-picker');
		if ($metricPicker.length > 0) {
			$metricPicker.on('change', app.charts.refreshCharts);
		}

		$charts.find('.chart').each(function () {
			var selector = '#' + $(this).attr('id');
			var chart = app.charts.createChart(selector);
			app.state.charts[selector] = chart;
			if (chart.metric in app.charts.customSetup) {
				app.charts.customSetup[chart.metric](chart);
			}
		});

		app.charts.populateChartData();

		if (app.state.chartData.length == 0) {
			$charts.hide();
		} else {
			$('.chart-container').hide();
			for (var i in app.state.chartData) {
				$(app.state.chartData[i].selector).closest('.chart-container').show();
			}

			// fire off an initial data request
			app.charts.fetchChartData();
		}
	},

	refreshCharts: function() {
		var dates = app.state.dateRange.map(Date.parse);
		for (var s in app.state.charts) {
			var chart = app.state.charts[s];
			chart.xMap.domain(dates);
			app.charts.rescaleChartX(chart);
		}

		// refetch the currently-graphed data, but redraw the data we currently have
		app.charts.populateChartData();

		var $inactive = $('.chart-container');
		var $active = $();
		for (var i in app.state.chartData) {
			var $chart = $(app.state.chartData[i].selector).closest('.chart-container');
			$active = $active.add($chart);
			$inactive = $inactive.not($chart);

		}

		// slide up the inactive charts and slide down the active charts, then trigger
		// a refetch (but only one!)
		$inactive.slideUp();
		var fetched = false;
		$active.slideDown(function() {
			if (!fetched) {
				app.charts.fetchChartData();
				fetched = true;
			}
		});
	},

	/**
	 * Specific settings for charts
	 */
	customSetup: {
	},


	currentMetric:function () {
		return $('#metric-picker').val();
	},

	populateChartData: function(){
		app.state.chartData.length = 0;
		var metric = app.charts.currentMetric();
		for (var id in app.state.charts) {
			var c = app.state.charts[id];
			if (typeof metric == 'undefined' || c.$chart.data('metric') == metric) {
				var chartData = {
					presence_id: c.$chart.data('presence-id'),
					metric: c.$chart.data('metric'),
					selector: c.selector
				};
				app.state.chartData.push(chartData);
			}
		}
	},

	createChart:function (selector) {
		var borders = {t:10, r:10, b:30, l:70};
		var c = {
			selector: selector,
			$chart: $(selector),
			shouldRescale: true,
			drawBuckets: false,
			drawCircles: false
		};

		c.metric = c.$chart.data('metric');

		c.w = c.$chart.width() - (borders.l + borders.r);
		c.h = c.$chart.height() - (borders.t + borders.b);

		// create svg 'canvas'
		c.vis = d3.select(selector)
			.append('svg:svg')
			.attr('preserveAspectRatio', 'none')
			.attr('viewBox', '0 0 ' + (c.w + borders.l + borders.r) + ' ' + (c.h + borders.t + borders.b))
			.append('svg:g')
			.attr('class', 'svg-chart')
			.attr('transform', "translate(" + borders.l + "," + borders.t + ")");

		//create x and y scale mapping functions
		var dates = app.state.dateRange.map(Date.parse);

		var yTicks = c.$chart.data('y-ticks');
		if (!yTicks) {
			yTicks = 4;
		}

		c.xMap = d3.time.scale().range([0, c.w]).domain(dates);
		c.yMap = d3.scale.linear().range([c.h, 0]);
		c.yMin = Infinity;
		c.yMax = -Infinity;

		c.xAxis = d3.svg.axis()
			.scale(c.xMap)
			.tickSize(4)
			.ticks(10)
			.orient('bottom');
		c.vis.append('svg:g')
			.attr('class', 'axis axis-x')
			.attr('transform', 'translate(0, ' + c.yMap(0) + ')')
			.call(c.xAxis);

		c.yAxis = d3.svg.axis()
			.scale(c.yMap)
			.tickSize(-c.w, 0)
			.ticks(yTicks)
			.tickSubdivide(false)
			.orient('left')
			.tickFormat(d3.format(',d'));//separate 1000s with commas
		c.vis.append('svg:g')
			.attr('class', 'axis axis-y')
			.call(c.yAxis);

		// add a y-axis label
		c.vis.select('g.axis-y').append('text')
			.text(c.$chart.data('y-label'))
			.attr('class', 'label')
			.attr('text-anchor', 'middle')
			.attr('transform', 'translate(-' + (borders.l - 16) + ', ' + c.h / 2 + ')rotate(-90, 0, 0)');

		c.line = d3.svg.line()
			.interpolate('monotone')
			.x(function (d) {
				return c.xMap(c.getXValue(d));
			})
			.y(function (d) {
				return c.yMap(c.getYValue(d));
			});

		c.getXValue = function(d) {
			return app.charts.dateFormat.parse(d.date);
		};
		c.getYValue = function(d) {
			return d.value;
		};

		return c;
	},

	/**
	 * Add data received from the server to the charts
	 * @param data
	 */
	renderDataset: function(data) {
		var c = app.state.charts[data.chart.selector];
		var $health = c.$chart.siblings('.health');

		// remove the old dataset(s)
		var $datasets = c.$chart.find('.dataset');
		$datasets.each(function(){
			d3.select(this).remove();
		});

		if (data.points.length == 0) {
			$health.find('.value')
				.text('No data found')
				.css('color', data.color);
			$health.find('.legend').text('');
			$health.find('.target').html('');
		} else {
			c.yMin = Infinity;
			c.yMax = -Infinity;
			switch (data.chart.metric) {
				case 'popularity_rate':
					$health.find('.value')
						.text(app.utils.numberFormat(data.current.value))
						.css('color', data.color);
					$health.find('.legend').text('As of ' + data.current.date);
					var targetText = 'Target audience: '+ app.utils.numberFormat(data.target);
					if (data.timeToTarget) {
						var components = [];
						var val = data.timeToTarget.y;
						if (val) {
							components.push(val + ' year' + (val == 1 ? '' : 's'));
						}
						val = data.timeToTarget.m;
						if (val) {
							components.push(val + ' month' + (val == 1 ? '' : 's'));
						}
						targetText += '<br />Projected target achievement: ' + components.join(', ');
						if (data.requiredRates) {
							targetText += _.template(app.templates.audienceTargetRates, data);
						}
					} else {
						targetText += '<br />Target reached';
					}
					$health.find('.target').html(targetText);

					app.charts.addBars(c, data.points, data.chart, data.color);
					break;
				case 'posts_per_day':
					$health.find('.value')
						.text(app.utils.numberFixedDecimal(data.average, 2))
						.css('color', data.color)
						.attr('title', data.timeToTarget ? ('Estimated date to reach target: ' + data.timeToTarget) : '');
					$health.find('.target').text('Target Posts Per Day: ' + data.target);

					app.charts.addBars(c, data.points, data.chart, data.color);
					break;
				case 'response_time':
					$health.find('.value')
						.text(app.utils.numberFixedDecimal(data.average, 2))
						.css('color', data.color);
					$health.find('.target').text('Target Response Time: ' + data.target);

					app.charts.addBars(c, data.points, data.chart, data.color);
					break;
				case 'link_ratio':
					$health.find('.value')
						.text(app.utils.numberFixedDecimal(data.average, 2))
						.css('color', data.color);
					$health.find('.target').text('Target: ' + data.target + '%');

					app.charts.addBars(c, data.points, data.chart, data.color);
					break;
			}
}

	},

	addGroup: function(c, points, data) {
		//calculate min/max y value in this dataset (need to convert to float, otherwise 'max' is done alphabetically)
		var f = function(d) { return parseFloat(c.getYValue(d)); };
		c.yMax = Math.max(c.yMax, d3.max(points, f));
		c.yMin = Math.min(c.yMin, d3.min(points, f));

		// make sure the y axis has a decent range of values
		var range = c.yMax - c.yMin;
		var minRange = 12;
		if (range < minRange) {
			c.yMin = Math.min(c.yMin, Math.max(0, c.yMin-(minRange - range)/2));
			c.yMax = c.yMin + minRange;
		}

		// create one container per data set
		return c.vis
			.append('svg:g')
			.attr('data-metric', data.metric)
			.attr('data-presence', data.presence)
			.attr('data-points', JSON.stringify(points))
			.attr('class', 'dataset lines');
	},

	addLine: function (c, points, data, color) {
		var group = app.charts.addGroup(c, points, data);

		group.append("svg:path")
			.attr("d", c.line(points))
			.attr('style', 'stroke-width: 2px; fill: none; stroke: ' + color);
//			.style('stroke', color)
//			.style('stroke-width', 2)
//			.style('fill', 'none');

		// add bucket rects and circles for mentions and sentiment graphs only
		if (c.drawCircles) {
			// add a circle for each point
			group.selectAll('path.line')
				.data(points)
				.enter()
				.append("svg:circle")
				.attr('data-values', function (d, i) {
					return d.value;
				})
				.attr("cx", function (d, i) {
					return c.xMap(c.getXValue(d));
				})
				.attr("cy", function (d, i) {
					return c.yMap(c.getYValue(d));
				})
				.style('fill', function(d, i) {
					if ('color' in d) {
						return d.color;
					}
					return color;
				})
				.style('stroke', function(d, i) {
					if ('color' in d) {
						return d.color;
					}
					return color;
				})
				.attr('data-metric', data.metric)
				.attr('data-presence', data.presence)
				.attr("r", 4);
		}
	},

	addBars: function (c, points, data, color) {
		var group = app.charts.addGroup(c, points, data);

		var maxWidth = c.w/points.length;
		var width = maxWidth*0.8;

		// translate by half a column width
		group.attr('transform', 'translate('+ maxWidth/2 +')');

		group.selectAll('rect')
			.data(points)
			.enter()
			.append("svg:rect")
			.attr("x", function (d, i) {
				return c.xMap(c.getXValue(d));
			})
			.attr('transform', 'translate(-' + width/2 + ')')
			.attr('y', function(d, i) {
				return c.yMap(Math.max(0, c.getYValue(d)));
			})
			.attr("height", function (d, i) {
				return Math.abs(c.yMap(0) - c.yMap(c.getYValue(d)));
			})
			.attr("width", width)
			.attr('data-metric', data.metric)
			.attr('data-presence', data.presence)
			.style('fill', function(d, i) {
				if ('color' in d) {
					return d.color;
				}
				return color;
			})
			.style('stroke', function(d, i) {
				if ('color' in d) {
					return d.color;
				}
				return color;
			})
			.attr('title', function(d, i) {
				return Date.parse(c.getXValue(d)).toString('d MMM') + ': ' + c.getYValue(d);
			})
			.on('mouseover', function (d, i) {
				$('div.tipsy').remove();

				$(this).tipsy({
					gravity:'s',
					offsetX:10,
					trigger:'manual'
				}).tipsy('show');
			})
			.on('mouseout', function (d, i) {
				$('div.tipsy').remove();
			})
            .on('click', function(d, i){

                console.log(d);

                var $sibling = $(this).next('rect');
                if($sibling.length == 0){
                    $sibling = $(this).prev('rect');
                }

                if($(this).css('opacity') < 1){
                    $(this).fadeTo('slow',1)
                        .siblings('rect').fadeTo('slow',0.2);
                } else {
                    if($sibling.css('opacity') < 1){
                        $(this)
                            .siblings('rect').fadeTo('slow',1);
                    } else {
                        $(this)
                            .siblings('rect').fadeTo('slow',0.2);
                    }

                }
            });
	},

	updateYAxis:function () {
		for (var selector in app.state.charts) {
			app.charts.rescaleChartY(app.state.charts[selector]);
		}
	},

	rescaleChartX:function (c) {

		var $datasets = c.$chart.find('.dataset');
		var duration = 1000;

		c.vis
			.transition()
			.duration(duration)
			.select('.axis-x')
				.call(c.xAxis);

		$datasets.each(function () {
			var points = $(this).data('points');
			var dataset = d3.select(this);
			dataset.selectAll('path')
					.transition()
					.duration(duration)
					.attr("d", c.line(points));

			dataset.selectAll('circle')
					.transition()
					.duration(duration)
					.attr("cx", function (d, i) {
						return c.xMap(c.getXValue(d));
					})
					.style('opacity', 0);

			var width = 0.8*c.w/points.length;
			dataset.selectAll('rect')
				.transition()
				.duration(duration)
				.attr("x", function (d, i) {
					return c.xMap(c.getXValue(d));
				})
				.attr('transform', 'translate(-'+ width/2 +')')
				.attr("width", width);
		});
	},

	rescaleChartY:function (c) {

		if (!c.shouldRescale) {
			return;
		}

		var $datasets = c.$chart.find('.dataset');

		if ($datasets.length == 0) {
			return;
		}

		//update y scale mapping functions
		c.yMap.domain([Math.min(0, c.yMin), c.yMax]);

		var duration = 600;

		//rescale axes
		c.vis
			.transition()
			.duration(duration)
			.select('.axis-y')
				.call(c.yAxis);

		//rescale lines
		$datasets.each(function () {
			var points = $(this).data('points');
			if (typeof points != 'undefined') {
				var dataset = d3.select(this);
				dataset.selectAll('path')
					.transition()
					.duration(duration)
					.attr("d", c.line(points));

				dataset.selectAll('circle')
					.transition()
					.duration(duration)
					.attr("cy", function (d) {
						return c.yMap(c.getYValue(d));
					});

				dataset.selectAll('rect')
					.transition()
					.duration(duration)
					.attr('y', function(d) {
						return c.yMap(Math.max(0, c.getYValue(d)));
					})
					.attr('height', function(d) {
						return Math.abs(c.yMap(0) - c.yMap(c.getYValue(d)));
					});
			}
		});

	},

	grabSvgElement:function () {

		var $link = $(this),
			$chart = $link.closest('.chart'),
			$svg = $chart.find('svg');

		$chart.showLoader({});

		// let's inline the css first
		var properties = ['stroke', 'stroke-width', 'stroke-opacity', 'fill', 'font', 'opacity'];
		$svg.find('.dataset *, .axis *, .link, .node').attr('style', function () {
			var style = '', styleDeclaration = window.getComputedStyle(this);
			for (var i in properties) {
				style += properties[i] + ':' + styleDeclaration.getPropertyValue(properties[i]) + ';';
			}
			return style;
		});

		var $svgClone = $svg.clone().attr({
			version:'1.1',
			xmlns:"http://www.w3.org/2000/svg",
			width:$svg.width(),
			height:$svg.height()
		});

		var b64 = Base64.encode($('<div>').append($svgClone).html());

		$.post(
				app.utils.baseUrl() + 'index/downloadimage',
				{
					svg: b64,
					w: $svg.width(),
					h: $svg.height(),
					type: $link.text().toLowerCase()
				}, function (data) {
					var src = app.utils.baseUrl() + 'index/servefile/?fileName=' + data.filename + '&nicename='+$chart.attr('id');
					$('body').append("<iframe src='" + src + "' style='display: none' />");
					$chart.hideLoader();
				}
		);

	},
	fetchChartData: function () {
		for (var i in app.state.chartData) {
			$(app.state.chartData[i].selector).closest('.chart-container').showLoader();
		}

		var url = app.state.controller + '/graph-data';
		var dateRange = app.state.dateRange.map(function(d){
			return $.datepicker.formatDate('yy-mm-dd', Date.parse(d));
		});
		var args = {
			chartData:app.state.chartData,
			dateRange: dateRange
		};

		return app.api.get(url, args)
			.done(function(response) {
				for (var i in response.data) {
					app.charts.renderDataset(response.data[i]);
				}
				app.charts.updateYAxis();
			})
			.always(function() {
				$('.chart-container').hideLoader();
			});
	}
};
