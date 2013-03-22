var app = app || {};

/**
 * D3 chart functions
 */
app.charts = {

	setup:function () {
		app.state.controller = $('#charts').data('controller');
		app.state.defaultLineId = $('#charts').data('default-line-id');
		app.state.controllerLabel = $('#charts').data('controller-label');
		if (!app.state.controllerLabel) {
			app.state.controllerLabel = app.state.controller;
		}

		//add default line if we have one and have no preloaded lines
		if (app.state.lineIds.length==0 && app.state.defaultLineId) {
			app.state.lineIds.push(app.state.defaultLineId);
		}

		if ($('.dtable').length > 0) {
			app.datatables.init();
		}

		$('.lineSelector').on('click', 'tbody tr', app.charts.toggleTopic);
		$('#legend').on('click', '.close', app.charts.toggleTopic);
		$('#legend').on('click', '.choose', app.charts.chooseColor);

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

		app.charts.dateFormat = d3.time.format('%Y-%m-%d %H:%M:%S');

		$('#charts div.chart').each(function () {
			var selector = '#' + $(this).attr('id');
			var chart = app.charts.createChart(selector);
			app.state.charts[selector] = chart;
			if (selector in app.charts.customSetup) {
				app.charts.customSetup[selector](chart);
			}
		});

		//hide charts if we have no lines
		if (app.state.lineIds.length == 0) {
			app.charts.hide();
		} else {
			// fire off an initial data request
			app.api.getGraphData(app.state.lineIds, function () {
				app.api.callback.apply(this, arguments);
			});
		}

	},

	/**
	 * Specific settings for charts
	 */
	customSetup: {
		'#mentions': function(chart) {
			chart.getData = function (d) {
				return d.mentions;
			};
			chart.shouldRescale = true;
			chart.drawBuckets = true;
			chart.drawCircles = true;
		},
		'#sentiment': function(chart) {
			chart.getData = function (d) {
				return d.polarity || 0;
			};

			chart.yMap.domain([-1, 1]);
			chart.vis.transition().select('.axis-y').call(chart.yAxis);

			chart.vis
				.selectAll('.axis-y g text')
				.remove();

			chart.vis
				.append("text")
				.attr("class", "overLabel")
				.attr("text-anchor", "middle")
				.attr("x", -7)
				.attr("y", 5)
				.text('+');

			chart.vis
				.append("text")
				.attr("class", "overLabel")
				.attr("text-anchor", "middle")
				.attr("x", -7)
				.attr("y", 112)
				.text('-');

			chart.drawBuckets = true;
			chart.drawCircles = true;
		},
		'#trends': function(chart) {
			chart.getData = function (d) {
				return d.rank;
			};
			chart.yMap.domain([10, 1]);
			chart.vis.transition().select('.axis-y').call(chart.yAxis);
		},
		'#api-stats' : function(chart) {
			chart.getData = function (d) {
				return d.count;
			};
			chart.shouldRescale = true;
			chart.drawCircles = true;
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

	/**
	 * Show colour picker which allows choosing a new colour for the dataset on which it is invoked
	 */
	chooseColor:function () {
		//remove any stray colour pickers
		$('#colorPicker').remove();

		var $dataset = $(this).closest('.dataset');
		var lineId = $dataset.data('line-id');
		var originalColor = app.charts.getColor(lineId);

		//create colour picker div
		$(parseTemplate(app.templates.colorpicker, {color:originalColor}))
				.appendTo($dataset)
				.on('click', 'button', function () {
					$('#colorPicker').remove();
				})
				.on('click', 'a', function (e) {
					e.preventDefault();
					app.charts.setColor(lineId, originalColor);
					$('#colorPicker').remove();
				});

		//listen for changes to colour input box
		$('#colorValue').bind('change keyup', function () {
			var color = $(this).val();
			app.charts.setColor(lineId, color);
			$.farbtastic('#colorPicker .container').setColor(color);
		})

		//init farbtastic colour picker
		$('#colorPicker .container').farbtastic(function (color) {
			$('#colorValue').val(color);
			app.charts.setColor(lineId, color);
		});
		$.farbtastic('#colorPicker .container').setColor(originalColor);

	},

	/**
	 * Get the colour of a dataset and generate one if none has been chosen
	 * @param line_id
	 * @return string colour
	 */
	getColor:function (line_id) {
		if (!(line_id in app.state.colors)) {
			var numUsedColours = Object.keys(app.state.colors).length;

			if (numUsedColours < app.colors.length) {
				//use next available predefined colour
				var color = '#' + app.colors[numUsedColours];
			} else {
				//randomise a colour
				var letters = '0123456789ABCDEF'.split('');
				var color = '#';
				for (var i = 0; i < 6; i++) {
					color += letters[Math.round(Math.random() * 15)];
				}
			}
			//save colour against line
			app.state.colors[line_id] = color;
		}
		return app.state.colors[line_id];
	},

	/**
	 * Change the colour of a dataset and store it in the state
	 * @param line_id
	 * @param color
	 */
	setColor:function (line_id, color) {
		app.state.colors[line_id] = color;
		for (var selector in app.state.charts) {
			var dataset = app.state.charts[selector].vis.select('.dataset[data-line-id="' + line_id + '"]');
			dataset.selectAll('path').attr('style', 'stroke:' + color);
			dataset.selectAll('circle').attr('style', 'stroke:' + color + '; fill:' + color);
		}
		$('#legend .dataset[data-line-id="'+line_id+'"] .icon').css('backgroundColor', color);
	},

	createChart:function (selector) {
		var borders = {t:10, r:10, b:30, l:40};
		var $chart = $(selector);

		var w = $chart.width() - (borders.l + borders.r);
		var h = $chart.height() - (borders.t + borders.b);

		// create svg 'canvas'
		var vis = d3.select(selector)
				.append('svg:svg')
				.attr('preserveAspectRatio', 'none')
				.attr('viewBox', '0 0 ' + (w + borders.l + borders.r) + ' ' + (h + borders.t + borders.b))
				.append('svg:g')
				.attr('class', 'chart')
				.attr('transform', "translate(" + borders.l + "," + borders.t + ")");

		//create x and y scale mapping functions
		var dates = app.state.dateRange.map(Date.parse);

		var yTicks = $chart.data('y-ticks');
		if (!yTicks) {
			yTicks = 4;
		}

		var xMap = d3.time.scale().range([0, w]).domain(dates);
		var yMap = d3.scale.linear().range([h, 0]);
		var xAxis = d3.svg.axis().scale(xMap).tickSize(4).ticks(10).orient('bottom');
		var yAxis = d3.svg.axis().scale(yMap).tickSize(-w, 0).ticks(yTicks).tickSubdivide(false).orient('left').tickFormat(d3.format('d'));

		var yMaxFunc = function (d) {
			return parseInt(app.state.charts[selector].getData(d));
		};

		vis.append('svg:g')
				.attr('class', 'axis axis-y')
				.call(yAxis);
		vis.append('svg:g')
				.attr('class', 'axis axis-x')
				.attr('transform', 'translate(0, ' + yMap(0) + ')')
				.call(xAxis);

		// add a y-axis label
		vis.select('g.axis-y').append('text')
				.text($chart.data('y-label'))
				.attr('class', 'label')
				.attr('text-anchor', 'middle')
				.attr('transform', 'translate(-' + (borders.l - 16) + ', ' + h / 2 + ')rotate(-90, 0, 0)');

		var line = d3.svg.line()
				.interpolate('monotone')
				.x(function (d) {
					return xMap(app.charts.dateFormat.parse(d.date));
				})
				.y(function (d) {
					return yMap(app.state.charts[selector].getData(d));
				});

		return {
			w:w,
			h:h,
			xMap:xMap,
			yMap:yMap,
			xAxis:xAxis,
			yAxis:yAxis,
			line:line,
			vis:vis,
			yMaxFunc:yMaxFunc,
			$chart:$chart,
			shouldRescale: false,
			drawBuckets: false,
			drawCircles: false
		}

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
	 * Trigger a fetch of line data from server
	 * @param line_id
	 */
	addDataset: function(line_id) {
		app.api.getGraphData(line_id);
		app.state.lineIds.push(line_id);

		//if adding a filtered line, remove all unfiltered lines
		if (app.charts.parseLineId(line_id).filterValue) {
			app.state.lineIds.forEach(function(otherLineId){
				if (!app.charts.parseLineId(otherLineId).filterValue) {
					app.charts.removeDataset(otherLineId);
				}
			});
		}

		$(document).trigger('topicsChanged');
	},

	/**
	 * Add data received from the server to the charts
	 * @param data
	 */
	renderDataset:function (data) {
		$('.chart,#legend').find('.dataset[data-line-id="' + data.line_id + '"]').remove();
		var color = app.charts.getColor(data.line_id);

		var count = 0;
		for (var i in data.points) {
			for (var selector in app.state.charts) {
				app.charts.addLine(selector, data.points[i], data.line_id, color);
			}
			for (var j in data.points[i]) {
				if (data.points[i][j].mentions) {
					count += parseInt(data.points[i][j].mentions, 10);
				}
			}
		}

		$('#legend').append(parseTemplate(app.templates.legendLabel, {
			name:data.name,
			count:count,
			line_id: data.line_id,
			className: data.line_id == app.state.defaultLineId ? 'all-tweets' : '',
			color: color
		}));
	},

	/**
	 * Delete lines from chart and text from legend
	 * @param line_id
	 */
	removeDataset:function (line_id) {
		$('.chart,#legend').find('.dataset[data-line-id="' + line_id + '"]').remove();
		var i = app.state.lineIds.indexOf(line_id);
		app.state.lineIds.splice(i, 1);
		if (app.state.lineIds.length == 0) {
			if (app.state.defaultLineId) {
				app.charts.addDataset(app.state.defaultLineId);
			} else {
				app.charts.hide();
			}
		}

		$(document).trigger('topicsChanged');
	},

	addLine:function (selector, points, line_id, color) {
		var c = app.state.charts[selector];

		//calculate max y value in this dataset (need to cast as int, otherwise 'max' is done alphabetically)
		var yMax = d3.max(points, c.yMaxFunc);

		// create one container per data set
		var group = c.vis
				.append('svg:g')
				.attr('data-line-id', line_id)
				.attr('data-max', yMax)
				.attr('data-points', JSON.stringify(points))
				.attr('class', 'dataset lines');

		group.append("svg:path")
				.attr("d", c.line(points))
				.attr('style', 'stroke-width: 2px; fill: none; stroke:' + color);

		// add bucket rects and circles for mentions and sentiment graphs only
		if (c.drawBuckets || c.drawCircles) {

			if (c.drawBuckets) {
				// translate by half a bucket width
				group.attr('transform', 'translate('+ (c.w / points.length * 0.5) +')');
			}

			if (c.drawCircles) {
				// add a circle for each point
				group.selectAll('path.line')
					.data(points)
					.enter()
					.append("svg:circle")
					.attr('data-mentions', function (d, i) {
						return d.mentions;
					})
					.attr("cx", function (d, i) {
						return c.xMap(app.charts.dateFormat.parse(d.date));
					})
					.attr("cy", function (d, i) {
						return c.yMap(c.getData(d));
					})
					.style('fill', color)
					.style('stroke', color)
					.attr('class', function (d, i) {
						return 'node-' + i;
					})
					.attr('data-line-id', line_id)
					.attr("r", function (d) {
						return c.getData(d) ? 4 : 0;
					});
			}

			if (c.drawBuckets) {
				// add a clickable rectangle for each bucket
				group.selectAll('rect')
					.data(points)
					.enter()
					.append("svg:rect")
					.attr('data-points', JSON.stringify(points))
					.attr("x", function (d, i) {
						return c.xMap(app.charts.dateFormat.parse(d.date));
					})
					.attr('transform', 'translate(-'+ (c.w / points.length * 0.5) +')')
					.attr("y", 0)
					.attr("height", function (d, i) {
						if (d.mentions == 0) return 0;
						return c.h;
					})
					.attr("width", c.w / points.length)
					.attr('class', function (d, i) {
						return 'node-' + i;
					})
					.attr('data-line-id', line_id)
					.on('mouseover', function (d, i) {

						$('div.tipsy').remove();

						//select current rect in all charts
						var rects = app.charts.getAllBlocks(this).filter(function () {
							return $(this).data('line-id') == line_id;
						});
						rects.classed('hovered', true);

						var showDate = function () {
							var startDate = Date.parse(points[i].date).toString('d MMM HH:mm');
							var endDate = Date.parse(points[i+1].date).toString('d MMM HH:mm');
							return startDate + ' to ' + endDate;
						}

						$(rects[0]).tipsy({
							title: showDate,
							gravity:'s',
							offsetX:10,
							trigger:'manual'
						}).tipsy('show');


					}).on('mouseout', function (d, i) {

						var rects = app.charts.getAllBlocks(this).filter(function () {
							return $(this).data('line-id') == line_id;
						});
						rects.classed('hovered', false);
						$(rects).each(function () {
							$(this).tipsy("hide");
						});


					}).on('click', function (d, i) {

						var rects = app.charts.getAllBlocks(this).filter(function () {
							return $(this).data('line-id') == line_id;
						});

						var start_date = points[i].date;
						var end_date = points[i+1].date;

						var selecting = !rects.classed('selected');
						var filterValue = selecting ? 'date:' + start_date + '/' + end_date : '';
						$('.statusesDisplay .dataTables_filter input')
								.val(filterValue)
								.keyup();

						app.charts.getAllBlocks().classed('selected', false);
						rects.classed('selected', selecting);

					});
			}
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
						return c.xMap(app.charts.dateFormat.parse(d.date));
					})
					.style('opacity', 0);

			d3.select(this).selectAll('rect')
				.transition()
				.duration(1000)
				.attr("x", function (d, i) {
					return c.xMap(app.charts.dateFormat.parse(d.date));
				})
				.attr('transform', function () {
					return 'translate(-'+ (c.w / $(this).data('points').length * 0.5) +')';
				})
				.attr("height", function (d, i) {
					if (d.mentions == 0) return 0;
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

		//get the max value of all datasets
		if ($datasets.size()) {
			c.yMax = d3.max($datasets, function (el) {
				return $(el).data('max');
			});
		} else {
			c.yMax = 0;
		}

		//update y scale mapping functions
		c.yMap.domain([0, c.yMax]);

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
							return c.yMap(c.getData(d));
						});
			}
		});

	},

	toggleTopic:function () {
		if ($(this).children('.dataTables_empty').length) return;

		var line_id = $(this).closest('.dataset,tr').data('line-id');
		var $row = $('.lineSelector tbody tr[data-line-id="' + line_id + '"]');

		if (app.state.lineIds.indexOf(line_id) >= 0) {
			app.charts.removeDataset(line_id);
			$row.removeClass('selected');
		} else {
			app.charts.addDataset(line_id);
			$row.addClass('selected');
		}
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

	}
};
