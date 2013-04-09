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
		$charts.find('.chart').each(function() {
			app.state.lineIds.push($(this).data('line-id'));
		});

		$(document)
			.on('dateRangeUpdated', function () {
				var dates = app.state.dateRange.map(Date.parse);
				for (var s in app.state.charts) {
					app.state.charts[s].xMap.domain(dates);
				}
				// refetch the currently-graphed data, but redraw the data we currently have
				app.api.getGraphData(app.state.lineIds);
				app.charts.updateXAxis();
			});

		$charts.find('tr td.chart').each(function () {
			var selector = '#' + $(this).attr('id');
			var chart = app.charts.createChart(selector);
			app.state.charts[selector] = chart;
			if (selector in app.charts.customSetup) {
				app.charts.customSetup[selector](chart);
			}
		});

		//hide charts if we have no lines
		if (app.state.lineIds.length == 0) {
			$charts.hide();
		} else {
			// fire off an initial data request
			app.api.getGraphData(app.state.lineIds);
		}
	},

	/**
	 * Specific settings for charts
	 */
	customSetup: {
//		'#posts': function(chart) {
//			chart.getYValue = function (d) {
//				return d.value;
//			};
//			chart.shouldRescale = true;
//			chart.drawCircles = true;
//		},
		'#popularity': function(chart) {
			chart.getXValue = function (d) {
				return app.charts.datetimeFormat.parse(d.datetime);
			};
			chart.getYValue = function (d) {
				return d.value;
			};
			chart.shouldRescale = true;
			chart.drawCircles = true;
		},
		'#posts_per_day': function(chart) {
			chart.getXValue = function (d) {
				return app.charts.dateFormat.parse(d.date);
			};
			chart.getYValue = function (d) {
				return d.post_count;
			};
			chart.shouldRescale = true;
			chart.drawCircles = true;
		}
	},

	/**
	 * Specific settings for charts
	 */
	healthDisplay: {
		'#posts_per_day': function(health) {
			health.title = 'Posts per Day';
			health.values = '' ;
		},
		'#popularity': function(health) {
			health.title = 'Target Followers';
			health.values = 31500;
			health.key.min = 24400;
			health.key.target = 27700;
			health.key.string = 'followers';
		},
		'#reply-time': function(chart) {
			health.title = 'Response Time';
			health.values = '' ;
		}
	},


	/**
	 * Show charts and legend
	 */
	show:function () {
		$('#charts').slideDown();
	},

	/**
	 * Hide charts and legend
	 */
	hide:function () {
		$('#charts').slideUp();
	},

	createChart:function (selector) {
		var borders = {t:10, r:10, b:30, l:70};
		var c = {
			$chart: $(selector),
			shouldRescale: false,
			drawBuckets: false,
			drawCircles: false
		};

		c.w = c.$chart.width() - (borders.l + borders.r);
		c.h = c.$chart.height() - (borders.t + borders.b);

		// create svg 'canvas'
		c.vis = d3.select(selector)
			.append('svg:svg')
			.attr('preserveAspectRatio', 'none')
			.attr('viewBox', '0 0 ' + (c.w + borders.l + borders.r) + ' ' + (c.h + borders.t + borders.b))
			.append('svg:g')
			.attr('class', 'chart')
			.attr('transform', "translate(" + borders.l + "," + borders.t + ")");

		//create x and y scale mapping functions
		var dates = app.state.dateRange.map(Date.parse);

		var yTicks = c.$chart.data('y-ticks');
		if (!yTicks) {
			yTicks = 4;
		}

		c.xMap = d3.time.scale().range([0, c.w]).domain(dates);
		c.yMap = d3.scale.linear().range([c.h, 0]);

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

		return c;
	},

	parseLineId: function(line_id) {
		var bits = line_id.split(':');
		return {
			modelType: bits[0],
			modelId: bits[1],
			filterType: bits.length > 2 ? bits[2] : '',
			filterValue: bits.length > 3 ? bits[3] : ''
		}
	},

	/**
	 * Add data received from the server to the charts
	 * @param data
	 */
	renderDataset: function(data) {
        var percent = 0;
        var $health = $(data.selector).siblings('.health');
		if(data.selector == '#popularity'){
			var currentValue = data.points[data.points.length-1].value;

			// work out the health of the timeToTarget
			// < 1 year => 100%
			// > 2 years => 0%
			// else somewhere in between
			if (currentValue >= data.target) {
				percent = 100;
			} else if (data.timeToTarget) {
				var minDays = 365;
				var maxDays = 730;
				var targetDate = new Date(data.timeToTarget);
				var now = new Date();
				var daysDiff = (targetDate - now)/(1000*60*60*24);
				if (daysDiff <= minDays) {
					percent = 100;
				} else if (daysDiff < maxDays) {
					percent = 100*(daysDiff - minDays)/(maxDays - minDays);
				}
			}

			$health.empty();
			$health.append('<h3>Target Followers</h3>');
			var $target = $('<p>' + app.utils.numberFormat(currentValue) + '</p>');
			$target.css('color', app.charts.getColorForPercentage(percent));
			if (data.timeToTarget) {
				$target.attr('title', 'Estimated date to reach target: ' + data.timeToTarget)
			}
			$('<div class="fieldset"></div>')
				.append('<h4>Current</h4>')
				.append($target)
				.appendTo($health);
			$health.append('<p class="target">Target Followers: '+ app.utils.numberFormat(data.target) +'</p>');
		} else if (data.selector ='#posts_per_day') {
            var value = 0;
            console.log(data.points);
            for(var i in data.points) {
                value += parseFloat(data.points[i].post_count);
            }
            var average = value/data.points.length;

            $health.empty();
            $health.append('<h3>Posts Per Day</h3>');
            var $target = $('<p>' + parseFloat(app.utils.numberFixedDecimal(average, 2)) + '</p>');
            $target.css('color', app.charts.getColorForPercentage(percent));
            if (data.timeToTarget) {
                $target.attr('title', 'Estimated date to reach target: ' + data.timeToTarget)
            }
            $('<div class="fieldset"></div>')
                .append('<h4>Average</h4>')
                .append($target)
                .appendTo($health);
            //$health.append('<p class="target">Target Followers: '+ app.utils.numberFormat(data.target) +'</p>');
        }
        $('.chart').find('.dataset[data-line-id="' + data.line_id + '"]').remove();
        app.charts.addLine(data.selector, data.points, data.line_id, app.charts.getColorForPercentage(percent));
	},

	addLine: function (selector, points, line_id, color) {
		var c = app.state.charts[selector];

		//calculate min/max y value in this dataset (need to convert to integer, otherwise 'max' is done alphabetically)
		var f = function(d) { return parseInt(c.getYValue(d)); };
		c.yMax = d3.max(points, f);
		c.yMin = d3.min(points, f);

		// make sure the y axis has a decent range of values
		var range = c.yMax - c.yMin;
		var minRange = 12;
		if (range < minRange) {
			c.yMin = Math.max(0, c.yMin-(minRange - range)/2);
			c.yMax = c.yMin + minRange;
		}

		// create one container per data set
		var group = c.vis
			.append('svg:g')
			.attr('data-line-id', line_id)
			.attr('data-max', c.yMax)
			.attr('data-min', c.yMin)
			.attr('data-points', JSON.stringify(points))
			.attr('class', 'dataset lines');

		group.append("svg:path")
			.attr("d", c.line(points))
			.attr('style', 'stroke-width: 2px; fill: none; stroke:' + color);

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
				.style('fill', color)
				.style('stroke', color)
				.attr('class', function (d, i) {
					return 'node-' + i;
				})
				.attr('data-line-id', line_id)
				.attr("r", function (d) {
					return 4;//c.getYValue(d) ? 4 : 2;
				});
		}

	},

	getAllBlocks: function(rect) {
		var classNames = '';
		if (typeof rect !== 'undefined') {
			classNames = '.' + $(rect).attr('class').split(' ')[0];
		}
		return d3.selectAll('#charts rect' + classNames);
	},

	updateXAxis:function () {
		for (var selector in app.state.charts) {
			app.charts.rescaleChartX(app.state.charts[selector]);
		}
	},

	rescaleChartX:function (c) {

		var $datasets = c.$chart.find('.dataset');
		c.vis.transition().select('.axis-x').call(c.xAxis);

		$datasets.each(function () {
			d3.select(this).selectAll('path')
					.transition()
					.duration(1000)
					.attr("d", c.line($(this).data('points')));

			d3.select(this).selectAll('circle')
					.transition()
					.duration(1000)
					.attr("cx", function (d, i) {
						return c.xMap(c.getXValue(d));
					})
					.style('opacity', 0);

			d3.select(this).selectAll('rect')
				.transition()
				.duration(1000)
				.attr("x", function (d, i) {
					return c.xMap(c.getXValue(d));
				})
				.attr('transform', function () {
					return 'translate(-'+ (c.w / $(this).data('points').length * 0.5) +')';
				})
				.attr("height", function (d, i) {
					if (d.value == 0) return 0;
					return c.h;
				})
				.attr("width", function () {
					return c.w / $(this).data('points').length
				})
		});
	},

	updateYAxis:function () {
		for (var selector in app.state.charts) {
			app.charts.rescaleChartY(app.state.charts[selector]);
		}
	},

	rescaleChartY:function (c) {

		if (!c.shouldRescale) {
			return;
		}

		var $datasets = c.$chart.find('.dataset');

		//update y scale mapping functions
		c.yMap.domain([c.yMin, c.yMax]);

		//rescale axes
		c.vis.transition().select('.axis-y').call(c.yAxis);

		//rescale lines
		$datasets.each(function () {
			var points = $(this).data('points');
			if (typeof points != 'undefined') {

				d3.select(this).selectAll('path')
						.transition()
						.duration(600)
						.attr("d", c.line(points));

				d3.select(this).selectAll('circle')
						.transition()
						.duration(600)
						.attr("cy", function (d) {
							return c.yMap(c.getYValue(d));
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
	percentColors:[
		{ pct: 0, color: { r: 0xd0, g: 0x69, b: 0x59 } }, //red
		{ pct: 50, color: { r: 0xf1, g: 0xdc, b: 0x63 } }, //amber
		{ pct: 100, color: { r: 0x84, g: 0xaf, b: 0x5b } } //green
	],
	getColorForPercentage:function(pct) {
		var percentColors = app.charts.percentColors;
		if(pct > 100) pct = 100;
		if(pct < 0) pct = 0;
		for (var i = 0; i < percentColors.length; i++) {
			if (pct <= percentColors[i].pct) {
				var lower = (i === 0) ? percentColors[i] : percentColors[i - 1];
				var upper = (i === 0) ? percentColors[i + 1] : percentColors[i];
				var range = upper.pct - lower.pct;
				var pctUpper = (pct - lower.pct) / range;
				var pctLower = 1 - pctUpper;
				var color = {
					r: Math.floor(lower.color.r * pctLower + upper.color.r * pctUpper),
					g: Math.floor(lower.color.g * pctLower + upper.color.g * pctUpper),
					b: Math.floor(lower.color.b * pctLower + upper.color.b * pctUpper)
				};
				return 'rgb(' + [color.r, color.g, color.b].join(',') + ')';
				// or output as hex if preferred
			}
		}
		return '';
	}
};
