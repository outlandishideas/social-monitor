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
				return app.charts.dateFormat.parse(d.date);
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
			$('.chart').find('.dataset[data-line-id="' + data.line_id + '"]').remove();
			if (data.points.length > 0) {
				percent = data.health;

				$health.find('.value')
					.text(app.utils.numberFormat(data.current.value))
					.css('color', app.charts.getColorForPercentage(percent));
				$health.find('.legend').text('As of ' + data.current.date);
				var targetText = 'Target Fans/Followers: '+ app.utils.numberFormat(data.target);
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
						targetText += '<table><thead><tr><th>Target date</th><th>Required increase per day</th></tr></thead><tbody>';
						for (var i=0; i<data.requiredRates.length; i++) {
							targetText += '<tr><td>' + data.requiredRates[i][1] + '</td><td>' + app.utils.numberFixedDecimal(data.requiredRates[i][0], 1) + '</td></tr>';
						}
						targetText += '</tbody></table>';
					}
				} else {
					targetText += '<br />Target reached';
				}
				$health.find('.target').html(targetText);

				app.charts.addBars(data.selector, data.points, data.line_id, app.charts.getColorForPercentage(percent));
			} else {
				$health.find('.value')
					.text('No data found')
					.css('color', app.charts.getColorForPercentage(0));
				$health.find('.legend').text('');
				$health.find('.target').html('');
			}
		} else if (data.selector == '#posts_per_day') {
            var value = 0;
            var target = 5;
//            console.log(data.points);
			var pointCount = data.points.length;
			for (var i=0; i<pointCount; i++) {
                value += parseFloat(data.points[i].post_count);
			}
            var average = value/pointCount;
            if(average>target){
                percent = 100;
            } else {
                percent = (average/target)*100;
            }

			$health.find('.value')
				.text(parseFloat(app.utils.numberFixedDecimal(average, 2)))
				.css('color', app.charts.getColorForPercentage(percent))
				.attr('title', data.timeToTarget ? ('Estimated date to reach target: ' + data.timeToTarget) : '');
			$health.find('.target').text('Target Posts Per Day: ' + target);

			$('.chart').find('.dataset[data-line-id="' + data.line_id + '"]').remove();
			if (data.points.length > 0) {
				app.charts.addLine(data.selector, data.points, data.line_id, app.charts.getColorForPercentage(percent));
			}
        }

	},

	addGroup: function(c, points, line_id) {
		//calculate min/max y value in this dataset (need to convert to integer, otherwise 'max' is done alphabetically)
		var f = function(d) { return parseInt(c.getYValue(d)); };
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
			.attr('data-line-id', line_id)
			.attr('data-max', c.yMax)
			.attr('data-min', c.yMin)
			.attr('data-points', JSON.stringify(points))
			.attr('class', 'dataset lines');
	},

	addLine: function (selector, points, line_id, color) {
		var c = app.state.charts[selector];
		var group = app.charts.addGroup(c, points, line_id);

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
					if ('health' in d) {
						return app.charts.getColorForPercentage(d.health);
					}
					return color;
				})
				.style('stroke', function(d, i) {
					if ('health' in d) {
						return app.charts.getColorForPercentage(d.health);
					}
					return color;
				})
				.attr('data-line-id', line_id)
				.attr("r", 4);
		}
	},

	addBars: function (selector, points, line_id, color) {
		var c = app.state.charts[selector];
		var group = app.charts.addGroup(c, points, line_id);

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
			.attr('data-line-id', line_id)
			.style('fill', function(d, i) {
				if ('health' in d) {
					return app.charts.getColorForPercentage(d.health);
				}
				return color;
			});
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

		var duration = 1000;
		$datasets.each(function () {
			var $dataset = $(this);
			d3.select(this).selectAll('path')
					.transition()
					.duration(duration)
					.attr("d", c.line($(this).data('points')));

			d3.select(this).selectAll('circle')
					.transition()
					.duration(duration)
					.attr("cx", function (d, i) {
						return c.xMap(c.getXValue(d));
					})
					.style('opacity', 0);

			var width = 0.8*c.w/$dataset.data('points').length;
			d3.select(this).selectAll('rect')
				.transition()
				.duration(duration)
				.attr("x", function (d, i) {
					return c.xMap(c.getXValue(d));
				})
				.attr('transform', 'translate(-'+ width/2 +')')
				.attr("width", width);
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

		if ($datasets.length == 0) {
			return;
		}

		//update y scale mapping functions
		c.yMap.domain([Math.min(0, c.yMin), c.yMax]);

		//rescale axes
		c.vis.transition().select('.axis-y').call(c.yAxis);

		//rescale lines
		var duration = 600;
		$datasets.each(function () {
			var points = $(this).data('points');
			if (typeof points != 'undefined') {

				d3.select(this).selectAll('path')
					.transition()
					.duration(duration)
					.attr("d", c.line(points));

				d3.select(this).selectAll('circle')
					.transition()
					.duration(duration)
					.attr("cy", function (d) {
						return c.yMap(c.getYValue(d));
					});

				d3.select(this).selectAll('rect')
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
