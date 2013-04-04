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
			app.api.getGraphData(app.state.lineIds, function () {
				app.api.callback.apply(this, arguments);
			});
		}
		/*
		$('#charts tr td.health').each(function () {
			var health = {};
			health.selector = '#' + $(this).data('id');
			console.log(health.selector);
			if( health.selector in app.charts.healthDisplay) {
				app.charts.healthDisplay[health.selector](health);
				app.charts.createHealth(health);
			}
		});
		*/
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
		'#mentions': function(health) {
			health.title = 'Posts per Day';
			health.values = '' ;
		},
		'#followers': function(health) {
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
		var borders = {t:10, r:10, b:30, l:40};
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
		c.yAxis = d3.svg.axis()
			.scale(c.yMap)
			.tickSize(-c.w, 0)
			.ticks(yTicks)
			.tickSubdivide(false)
			.orient('left')
			.tickFormat(d3.format('d'));

		// todo: what do these do?
		c.yMaxFunc = function (d) {
			return parseInt(c.getYValue(d));
		};

		c.yMinFunc = function (d) {
			return parseInt(c.getYValue(d));
		};

		c.vis.append('svg:g')
			.attr('class', 'axis axis-y')
			.call(c.yAxis);
		c.vis.append('svg:g')
			.attr('class', 'axis axis-x')
			.attr('transform', 'translate(0, ' + c.yMap(0) + ')')
			.call(c.xAxis);

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
	renderDataset:function (data) {
		var color = '#000';

		app.charts.addLine(data.selector, data.points, data.line_id, color);



		if(data.selector == '#popularity'){
			var health = $(data.selector).siblings('.health');
			var lastPoint = data.points[data.points.length-1];

			var html = '<h3>Target Followers</h3>';
			html += '<div class="fieldset">';
			html += '<h4>Current</h4>';
			html += '<p><span style="color:'+app.charts.getColorForPercentage(data.timeToTargetPercent)+'">'+ lastPoint.value +'</span></p>';
			html += '</div>';
			html += '<p class="target">Target Followers: '+ data.target +'</p>';

			health.empty().html(html);


		}

	},

	addLine:function (selector, points, line_id, color) {
		var c = app.state.charts[selector];

		//calculate max y value in this dataset (need to cast as int, otherwise 'max' is done alphabetically)
		c.yMax = d3.max(points, c.yMaxFunc);
		c.yMin = d3.min(points, c.yMaxFunc);

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
					return c.getYValue(d) ? 4 : 0;
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

		if(c.yMax && c.yMin){
			//update y scale mapping functions
			c.yMap.domain([c.yMin,c.yMax]);
		}

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

		$chart.showLoader();

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
		{ pct: 0, color: { r: 0x00, g: 0xff, b: 0 } },
		{ pct: 50, color: { r: 0xff, g: 0xff, b: 0 } },
		{ pct: 100, color: { r: 0xff, g: 0x00, b: 0 } }
	],
	getColorForPercentage:function(pct) {
	var percentColors = app.charts.percentColors;
	if(pct > 100) pct = 100;
	for (var i = 0; i < percentColors.length; i++) {
		if (pct <= percentColors[i].pct) {
			var lower = percentColors[i - 1];
			var upper = percentColors[i];
			var range = upper.pct - lower.pct;
			var rangePct = (pct - lower.pct) / range;
			var pctLower = 1 - rangePct;
			var pctUpper = rangePct;
			var color = {
				r: Math.floor(lower.color.r * pctLower + upper.color.r * pctUpper),
				g: Math.floor(lower.color.g * pctLower + upper.color.g * pctUpper),
				b: Math.floor(lower.color.b * pctLower + upper.color.b * pctUpper)
			};
			return 'rgb(' + [color.r, color.g, color.b].join(',') + ')';
			// or output as hex if preferred
		}
	}
}
};
