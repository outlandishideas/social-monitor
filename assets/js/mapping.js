var app = app || {};

/**
 * Mapping stuff for geolocated searches
 * @type {Object}
 */
app.mapping = {
	state:{
		map:null,
		editable:true,
		markers:[],
		maxMarkers:10
	},
	/**
	 * Create the google map, add the markers and set up event listeners
	 * @param $domMap
	 * @param editable
	 */
	initialise:function ($domMap, editable) {
		app.mapping.state.editable = editable;
		app.mapping.state.map = new google.maps.Map($domMap.get(0), {
			center:new google.maps.LatLng(0, 0),
			zoom:editable ? 3 : 5,
			mapTypeControl:false,
			mapTypeId:google.maps.MapTypeId.ROADMAP
		});

		if (editable) {
			$('#areas')
					.on('keyup mouseup', '.radius', app.mapping.updateCircle)
					.on('click', '.remove', app.mapping.removeArea)
					.on('click', '.add', app.mapping.addArea)
					.find('.area')
					.each(app.mapping.addMarkerFromDom);

			// if the map isn't shown to begin with, there can be display issues.
			// force a resize when toggled to fix it
			$domMap.closest('.ctrlHolder').on('toggled', function () {
				google.maps.event.trigger(app.mapping.state.map, 'resize');
				app.mapping.state.map.setCenter(app.mapping.markerCenter());
			});
		} else {
			for (var i = 0; i < regionData.areas.length; i++) {
				var area = regionData.areas[i];
				app.mapping.addMarker(area.lat, area.lon, area.radius * 1000, '#3a3');
			}
		}
	},
	/**
	 * Adds a marker and circle to the map, based on an area
	 */
	addMarkerFromDom:function () {
		var $area = $(this);
		app.mapping.addMarker(
				$area.find('.lat').val(),
				$area.find('.lon').val(),
				$area.find('.radius').val() * 1000,
				$area.find('.marker').css('background-color')
		);
	},
	addMarker:function (lat, lon, radius, colour) {
		var marker = new google.maps.Marker({
			position:new google.maps.LatLng(lat, lon),
			clickable:app.mapping.state.editable,
			draggable:app.mapping.state.editable,
			visible:app.mapping.state.editable
		});
		app.mapping.state.markers.push(marker);
		marker.setMap(app.mapping.state.map);

		google.maps.event.addListener(marker, 'dragend', app.mapping.onMarkerDragged);
		google.maps.event.addListener(marker, 'click', app.mapping.onMarkerClicked);

		var circle = new google.maps.Circle({
			map:app.mapping.state.map,
			radius:radius,
			fillColor:colour,
			strokeWeight:app.mapping.state.editable ? 1 : 0
		});
		circle.bindTo('center', marker, 'position');
		google.maps.event.addListener(circle, 'click', app.mapping.onCircleClicked);

		// link the circle and marker to each other
		circle.marker = marker;
		marker.circle = circle;

		app.mapping.state.map.panTo(app.mapping.markerCenter());
		app.mapping.updateButtons();
		return marker;
	},
	/**
	 * Adds an area to the DOM, with its corresponding marker and circle
	 */
	addArea:function (args) {
		var $areas = $('#areas');
		for (var i = 1; i <= app.mapping.state.maxMarkers; i++) {
			var className = 'marker-' + i;
			if ($areas.find('.area .' + className).length == 0) {
				if (typeof args == 'undefined' || !args.lat || !args.lon || !args.radius) {
					var center = app.mapping.state.map.getCenter();
					args = {
						lat:center.lat(),
						lon:center.lng(),
						radius:100
					};
				}
				args.className = className;
				var newArea = parseTemplate(app.templates.searchArea, args);
				$(newArea)
						.appendTo($areas)
						.each(app.mapping.addMarkerFromDom);
				break;
			}
		}
	},
	/**
	 * Removes the given area and marker
	 */
	removeArea:function () {
		var $area = $(this).closest('.area');
		var marker = app.mapping.state.markers.splice($('.area').index($area), 1);
		marker = marker[0];
		marker.circle.setMap(null);
		marker.setMap(null);
		$area.remove();

		app.mapping.state.map.panTo(app.mapping.markerCenter());
		app.mapping.updateButtons();
	},
	/**
	 * Updates the area's circle on the map to have the right radius
	 */
	updateCircle:function () {
		var index = $('.area').index($(this).closest('.area'));
		app.mapping.state.markers[index].circle.setRadius($(this).val() * 1000);
	},
	/**
	 * Adds the correct add/remove buttons to the areas
	 */
	updateButtons:function () {
		var $areas = $('.area');
		$areas.find('.remove, .add').remove();
		if ($areas.length > 1) {
			$areas.each(function () {
				$(this).find('.clear').before('<div class="remove"></div>');
			})
		}
		if ($areas.length < app.mapping.state.maxMarkers) {
			$areas.last().find('.clear').before('<div class="add"></div>');
		}
	},
	/**
	 * Gets the central position (lat/lon) of the markers
	 * @return
	 */
	markerCenter:function () {
		if (app.mapping.state.markers.length == 0) {
			return new google.maps.LatLng(0, 0);
		} else if (app.mapping.state.markers.length == 1) {
			return app.mapping.state.markers[0].getPosition();
		} else {
			// this should probably take into account the circle radius, but that involves tricky maths and isn't really necessary
			var area = new google.maps.LatLngBounds();
			for (var i = 0; i < app.mapping.state.markers.length; i++) {
				area.extend(app.mapping.state.markers[i].getPosition());
			}
			return area.getCenter();
		}
	},
	/**
	 * Update the lat/lon values for the dragged marker, and focus its radius input
	 */
	onMarkerDragged:function () {
		var marker = this;
		var pos = marker.getPosition();
		var $area = $('.area').eq(app.mapping.state.markers.indexOf(marker));
		$area.find('.lat').val(pos.lat());
		$area.find('.lon').val(pos.lng());
		$area.find('.radius').focus();
		app.mapping.state.map.panTo(app.mapping.markerCenter());
	},
	/**
	 * Focus the radius input for the clicked marker
	 */
	onMarkerClicked:function () {
		var marker = this;
		$('.area').eq(app.mapping.state.markers.indexOf(marker)).find('.radius').focus();
	},
	/**
	 * Focus the radius input for the clicked circle
	 */
	onCircleClicked:function () {
		var marker = this.marker;
		$('.area').eq(app.mapping.state.markers.indexOf(marker)).find('.radius').focus();
	},
	/**
	 * Replaces all areas with the given ones
	 * @param areas
	 */
	updateFromPreset:function (areas) {
		$('.area').each(app.mapping.removeArea);
		for (var i = 0; i < areas.length; i++) {
			app.mapping.addArea(areas[i]);
		}
		app.mapping.state.map.setCenter(app.mapping.markerCenter());
		app.mapping.updateButtons();
	}
}