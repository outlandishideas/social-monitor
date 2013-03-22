var app = app || {};

/**
 * Network page functions
 * @type {Object}
 */
app.network = {
	init:function ($item) {
		$('#network-table').dataTable({
			bPaginate:false,
			aaSorting:[
				[2, 'desc'],
				[0, 'asc']
			],
			bAutoWidth:false,
			bInfo:false,
			sScrollY:$item.width() + 'px',
			fnCreatedRow: function(nRow, aData) {
				$(nRow).attr('data-user-id', aData.id);
			},
			aoColumns:[
				{mData:'name'},
				{
					mData:'screen_name',
					fnRender:function (o, val) {
						return '<a href="http://twitter.com/' + o.aData.screen_name + '">' + val + '</a>';
					}
				},
				{
					mData:'count',
					fnRender:function (o, val) {
						return o.aData.is_root ? '[' + val + ']' : val;
					},
					asSorting: ['desc', 'asc']
				},
				{
					mData:'friends_count',
					sType: 'numeric',
					bUseRendered: false,
					fnRender: app.datatables.numberRender,
					asSorting: ['desc', 'asc']
				},
				{
					mData:'followers_count',
					sType: 'numeric',
					bUseRendered: false,
					fnRender: app.datatables.numberRender,
					asSorting: ['desc', 'asc']
				},
				{mData:'klout'},
				{mData:'peerindex'},
				{
					mData:function(aData) {
						var className = aData.is_root ? 'remove' : 'add';
						return '<span class="icon '+className+'"></span>';
					},
					bSortable:false
				}
			]
		});

		$(document)
				.on('click', '.update-network', function(e){
					e.preventDefault();
					var $link = $(this);
					app.api.get('network/refresh', {id:$item.data('id')}).done(function () {
						app.jobs.listen();
						$link.closest('li').remove();
					});
				}).on('keyup', function(e) {
					//secret keys for manipulating force layout params
					switch (e.keyCode) {
						case 71 : //g increase gravity
							var g = app.state.network.force.gravity();
							app.state.network.force.gravity(g / 0.7);
							app.state.network.force.start();
							break;
						case 72 : //h
							var g = app.state.network.force.gravity();
							app.state.network.force.gravity(g * 0.7);
							app.state.network.force.start();
							break;
						case 74 : //j increase repulsion
							app.state.network.chargeMultiplier /= 0.7;
							app.state.network.force.start();
							break;
						case 75 : //k
							app.state.network.chargeMultiplier *= 0.7;
							app.state.network.force.start();
							break;
					}
				});

		$('#network-chart, #network-table')
				.on('mouseover', 'circle, tr', function (e) {
					app.network.toggleHighlight($(this).data('user-id'), true);
				})
				.on('mouseout', 'circle, tr', function (e) {
					app.network.toggleHighlight($(this).data('user-id'), false);
				});

		$('#network-table')
				.on('click', '.add,.remove', function () {
					var $row = $(this).closest('tr').addClass('loading');
					var $chart = $('#network-chart').addClass('loading');
					var path = $(this).is('.add') ? 'add-user' : 'remove-user';
					app.api.post('network/' + path, {
						id: $chart.data('id'),
						user_id: $row.data('user-id')
					}).done(function () {
						app.network.refresh(function(){
							$row.removeClass('loading');
						});
					});
				});

		$('#network-menu')
				.on('click', '.settings', function(e) {
					e.preventDefault();
					$('#edit-network').slideToggle();
				}).on('click', '.add', function(e) {
					e.preventDefault();
					$('#add-user').slideToggle();
				});

		$('.toggleForm')
				.on('click', '.cancel', function(e) {
					e.preventDefault();
					$(this).closest('form').slideUp();
				}).on('submit', function(e) {
					e.preventDefault();
					var params = $(this).serializeArray();
					var $chart = $('#network-chart').addClass('loading');
					params.push({name:'id', value:$chart.data('id')});
					app.api.post($(this).data('path'), params).done(app.network.refresh);
				});

		app.network.create({
			target:'#network-chart',
			w:$item.width(),
			h:$item.width()
		});

		app.network.refresh();
	},

	/**
	 * Toggles the highlight class on the circle, links and table row cells that
	 * have the given user id
	 * @param {int} userId
	 * @param {boolean} highlight
	 */
	toggleHighlight: function (userId, highlight) {
		//highlight node and links
		var high_lines = d3.selectAll('#network-chart .user_' + userId);
		high_lines.classed('highlight', highlight);

		//move node's links to foreground
		var $high_lines = $('#network-chart line.user_' + userId);
		$high_lines.parent().append($high_lines);

		//highlight row
		$('#network-table tr[data-user-id=' + userId + ']').toggleClass('selected', highlight);
	},

	create:function (args) {
		app.state.network = {};
		app.state.network.chargeMultiplier = 1;
		app.state.network.vis = d3.select(args.target).append("svg")
				.attr("width", args.w)
				.attr("height", args.h);
		$(args.target).append('<div class="load-overlay"></div>');

		//force layout docs https://github.com/mbostock/d3/wiki/Force-Layout
		app.state.network.force = d3.layout.force()
				.charge(function(d){
					return (d.is_root ? -500 : -250) * app.state.network.chargeMultiplier;
				})//how much do the nodes attract/repel
				.linkDistance(40)//how much distance do the nodes try to settle apart
				.linkStrength(0.7)//how rigid are the links
				.gravity(0.1) //how much are nodes attracted towards the center of the frame
				.friction(0.90)//how much should the speed reduce at each tick
				.size([args.w, args.h]);

		//create container groups
		app.state.network.vis.append('g').attr('class', 'links');
		app.state.network.vis.append('g').attr('class', 'nodes');

		//create tick function
		app.state.network.force.on("tick", function () {
			app.state.network.nodeSelection
					.attr("cx", function (d) {
						return d.x;
					})
					.attr("cy", function (d) {
						return d.y;
					});

			app.state.network.linkSelection
					.attr("x1", function (d) {
						return d.source.x;
					})
					.attr("y1", function (d) {
						return d.source.y;
					})
					.attr("x2", function (d) {
						return d.target.x;
					})
					.attr("y2", function (d) {
						return d.target.y;
					});
		});
	},

	refresh: function(cb){
		var $chart = $('#network-chart');
		$chart.addClass('loading');
		app.api.get('network/export', {id:$chart.data('id')}).done(function (response) {
			app.network.draw(response.data.links, response.data.nodes);
			$chart.removeClass('loading');

			//add user form
			if ($('#add-user').is(':visible')) {
				//select text in box
				$('#handle')[0].select();
			} else if (response.data.nodes.length == 0) {
				//show add user form if no users in network
				$('#network-menu .add').click();
			}

			//add data to users table
			var table = $('#network-table').dataTable();
			table.fnClearTable();
			table.fnAddData(app.state.network.force.nodes());
			if (cb && typeof cb == 'function') cb();
		});
	},

	/**
	 * Update network with any new data and (re)start animation
	 */
	draw:function(links, nodes){
		var force = app.state.network.force;
		var vis = app.state.network.vis;

		//update data array containing nodes for force layout
		var oldNodes = force.nodes(); //nodes is now a pointer to force layout nodes

		var width = vis.attr('width');
		var height = vis.attr('height');

		outer:
		for (var i in nodes) {
			//look for each new node among the existing nodes
			for (var j in oldNodes) {
				if (nodes[i].id == oldNodes[j].id) {
					//found node so update properties and push back onto internal array
					nodes[i] = $.extend(oldNodes[j], nodes[i]);
					continue outer;
				}
			}

			//new node not found so place randomly
			nodes[i].x = Math.random() * width;
			nodes[i].y = Math.random() * height;

			//constrain root nodes to centre square
			if (nodes[i].is_root) {
				nodes[i].x = nodes[i].x / 2 + width / 4;
				nodes[i].y = nodes[i].y / 2 + height / 4;
			}
		}

		//create hash table of node IDs
		var nodesById = {};
		for (var i in nodes) {
			nodesById[nodes[i].id] = nodes[i];
		}

		//index the links
		for (var i in links) {
			links[i].source = nodesById[links[i].source_id];
			links[i].target = nodesById[links[i].target_id];
		}

		//update force layout with new nodes and links
		force.links(links).nodes(nodes);

		//update DOM links and nodes
		var linkSelection = vis.select('g.links')
				.selectAll('line').data(links, function(d){
					return d.source_id + '_' + d.target_id;
				});
		linkSelection.enter().append("line");
		linkSelection
				.attr("class", function (d) {
					return 'user_' + d.source_id + ' user_' + d.target_id;
				});
		linkSelection.exit().remove();

		var nodeSelection = vis.select('g.nodes')
				.selectAll('circle').data(nodes, function(d){return d.id;});
		nodeSelection.enter().append("circle")
				.attr("r", 10)
				.call(force.drag);
		nodeSelection
				.attr('data-user-id', function (d) {
					return d.id;
				})
				.attr('data-label', function (d) {
					return d.screen_name + (d.is_root ? ' [root]' : ' (' + d.count + ')');
				})
				.attr("class", function (d) {
					return "node_" + d.group + ' user_' + d.id;
				});
		nodeSelection.exit().remove();

		app.state.network.nodeSelection = nodeSelection;
		app.state.network.linkSelection = linkSelection;

		//add tooltips to nodes
		$('svg .nodes circle').tipsy({
			title:'data-label',
			gravity:'s',
			offsetX:10
		});

		force.start();
	}
};