/**
 * Created by outlander on 14/10/2014.
 */

var app = app || {};

app.newCharts = {

    setup: function() {
        $(document)
            .on('dateRangeUpdated', app.newCharts.loadData);
        var $chartPicker = $('#chart-picker');
        if ($chartPicker.length > 0) {
            $chartPicker.on('change', app.newCharts.refreshCharts);
        }

        app.newCharts.loadData();

    },

    refreshCharts: function() {
        app.state.chart = null;
        app.newCharts.loadData();
    },

    currentMetric:function () {
        return $('#chart-picker').val();
    },

    loadData: function() {
        var chart = app.newCharts.currentMetric();
        var dateRange = app.state.dateRange.map(function(d){
            return $.datepicker.formatDate('yy-mm-dd', Date.parse(d));
        });

        var $chart = $('#new-chart');
        var url = $chart.data('controller') + '/graph-data';
        var id = $chart.data('id');

        var params = {
            dateRange: dateRange,
            chart: chart,
            id: id
        };

        app.api.get(url, params)
            .done(function(response) {
                var data = response.data;
		        $('.chart-description').text(data.description ? data.description : '');
		        var chartArgs = data.chartArgs;
		        if (chartArgs.axis.x.type == 'timeseries') {
			        chartArgs.axis.x.tick = {
				        format: function(x) {
					        return x.getDate() + '/' + (x.getMonth()+1) + '/' + (x.getFullYear()-2000);
				        }
			        };
		        }
                chartArgs.data.onmouseover = (function() {
                    var lastDate = 0;

                    return function(a) {
                        // this in this function refers to the chartInternals
                        var date = new Date(a.x);
                        //check if we have to update the table
                        if (date == lastDate) {
                            //nopes, so return early
                            return;
                        }
                        //save the date for later use
                        lastDate = date;
                        //find the index to use
                        var index = null;
                        var targets = this.data.targets;
                        for (var i = 0, l = targets[0].values.length; i < l; i++) {
                            if (new Date(targets[0].values[i].x) - date === 0) {
                                index = i;
                                break;
                            }
                        }
                        //now update the table
                        document.getElementById('current-chart-date').innerHTML = date.toLocaleDateString(jsConfig.dateLocale, app.dateOptions);
                        for (var i = 0, l = targets.length; i < l; i++) {
                            //$('#chart-table-value-'+targets[i].id).html(targets[i].values[index].value);
                            // go for native JS instead of jQuery here, since it's much faster. jQuery will give a noticable lag
                            // when moveing the mouse over the chart, native JS is much snappier. Profiling confirms that
                            // native JS is indeed the best way to go in this case. (jQuery's html method on elements is the culprit)
                            document.getElementById('chart-table-value-'+targets[i].id).innerHTML = targets[i].values[index].value;
                        }
                    };
                })();
                app.state.chart = c3.generate(chartArgs);
                var targets = app.state.chart.internal.data.targets;
                var shown = targets.length; //keep track of how many series are being shown
                var numCols = Math.ceil((targets.length) / 10);
                if (numCols > 3) {
                    numCols = 3;
                }
                var currentCol = 0;
                var createRow = function(name, color, id, value) {
                    return '<th data-series="'+id+'" class="series-toggle"><span class="color-block" style="background-color:'+color+'"></span>'+name+'</th><td id="chart-table-value-'+id+'">'+value+'</td>';
                };
                // create table
                date = new Date(targets[0].values[0].x);
                var html = '<p>{{ js.chart.message.toggle | translate }}</p>';
                html += '<table class="size-'+numCols+'"><tr><th colspan="'+((numCols * 2) - 1)+'" class="header" id="current-chart-date">'+date.toLocaleDateString(jsConfig.dateLocale, app.dateOptions)+'</th><th class="header"><span id="uncheck-all">{{ js.chart.message.toggle-all | translate }}</span></th></tr>';
                var startRow = true;
                for (var i = 0, l = targets.length; i < l; i++) {
                    var id = targets[i].id;
                    var name = chartArgs.data.names[id], color = app.state.chart.color(id), value = targets[i].values[0].value;
                    html += (startRow ? '<tr>' : '') + createRow(name, color, id, value);
                    currentCol++;
                    startRow = (currentCol % numCols == 0);
                    html += (startRow ? '</tr>' : '');
                }
                if (!startRow) {
                    // we are halfway through a row, so complete it first
                    html += '<td colspan="'+((numCols - (currentCol % numCols)) * 2)+'">&nbsp;</td></tr>';
                }
                html += '</table>';
                $('#new-chart-values').html(html);
                $('.series-toggle').on({
                    mouseenter: function(e) {
                        app.state.chart.focus(''+$(this).data('series'));
                    },
                    mouseleave: function(e) {
                        app.state.chart.revert();
                    },
                    click: function(e) {
                        $this = $(this);
                        app.state.chart.toggle(''+$this.data('series'));
                        $this.toggleClass('inactive');
                        $this.next('td').toggleClass('inactive');
                        $this.hasClass('inactive') ? shown-- : shown++;
                        $('#uncheck-all').html(shown > 0 ? 'Hide all' : 'Show all');
                    }
                });
                $('#uncheck-all').on('click', function() {
                    var $self = $(this);
                    if (shown == 0) {
                        $('.series-toggle').each(function() {
                            $this = $(this);
                            if (!$this.hasClass('inactive')) {
                                return;
                            }
                            app.state.chart.show(''+$this.data('series'));
                            $this.removeClass('inactive');
                            $this.next('td').removeClass('inactive');
                            shown++;
                        });
                    } else {
                        $('.series-toggle').each(function () {
                            var $this = $(this);
                            if ($this.hasClass('inactive')) {
                                return;
                            }
                            app.state.chart.hide(''+$this.data('series'));
                            $this.addClass('inactive');
                            $this.next('td').addClass('inactive');
                            shown--;
                        });
                    }
                    $self.html(shown > 0 ? 'Hide all' : 'Show all');
                });
                var socialMediaType = new RegExp(/^(\[(.+)\])/);
                $('.series-toggle').each(function() {
                    var $toggle = $(this);
                    var text = $toggle.text();
                    var matches = text.match(socialMediaType);

                    if (matches.length == 3) {
                        var newText = text.replace(matches[1], '');
                        var $icon = $('<span>').addClass('fa-fw ' + matches[2]);
                        var $colorBlock = $toggle.find('.color-block');

                        $toggle.text('');
                        $toggle.append($colorBlock).append($icon).append(newText);
                    }

                });
            });
    }




};
