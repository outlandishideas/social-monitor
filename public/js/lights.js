var app = app || {};

/**
 * D3 stacked bar functions
 */
app.lights = {
	setup:function () {
		var $lights = $('#lights');

        $lights.find('.light').each(function () {
			var selector = '[data-id="' + $(this).data('id') + '"]';
            app.state.lights[selector] = {};

			var bar = app.lights.createStackedBar(selector);
			app.state.lights[selector].bar = bar;
		});

        if (app.state.lineIds.length == 0) {
            $lights.hide();
        } else {
            // fire off an initial data request
            app.api.getLightsData(app.state.lightLineIds);
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

    createStackedBar: function(selector){

        selector += ' .stacked-bar';

        // create an SVG element inside the #graph div that fills 100% of the div
        var l = {
            $element: $(selector)
        };

        l.h = l.$element.height();
        l.w = l.$element.width();

        l.vis = d3.select(selector)
            .append("svg:svg")
            .attr("width", l.w)
            .attr("height", l.h)
            .append('svg:g')
            .attr('class', 'stacked-bar');

        l.x = d3.scale.linear().domain([0, 100]).range([0, l.w]);
        l.y = d3.scale.linear().domain([0, 1]).range([0, l.h]);

        return l;
    },
    renderStackedBar: function(data){

        var selector = data.light_selector;

        var bar = app.state.lights[selector].bar;

        var points = app.lights.pointsToStackedBar(data.points);

        var stack = d3.layout.stack();

        stack(points.dataset);

        var colors = ['#84af5b', '#f1dc63', '#d06959'];

        var group = bar.vis.selectAll("g")
            .data(points.dataset)
            .enter()
            .append("g")
            .style("fill", function(d, i) {
                return colors[i];
            });

        var rects = group.selectAll("rect")
            .data(function(d){return d;})
            .enter()
            .append("rect")
            .attr("x", function(d, i){
                return bar.x(d.y0);
            })
            .attr("y", function(d, i){
                return bar.y(i);
            })
            .attr("height", '100%')
            .attr("width", function(d){
                return bar.x(d.y);
            });


    },
    pointsToStackedBar: function(points){

        var data = {};
        data.green = 0;
        data.orange = 0;
        data.red = 0;
        data.len = points.length;

        for(var i in points){
            if(points[i].health > 66) {
                data.green++;
            } else if (points[i].health > 33) {
                data.orange++;
            } else {
                data.red++;
            }
        }

        data.dataset = [
            [{x:0,y:(data.green/data.len)*100}],
            [{x:0,y:(data.orange/data.len)*100}],
            [{x:0,y:(data.red/data.len)*100}]
        ];

        return data;

    }
};
