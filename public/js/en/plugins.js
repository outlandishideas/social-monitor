
(function($) {
	var id_count = 0;

	$.fn.getLoaderId = function() {
		var $this = $(this);

		if (typeof $this.data('loader_id') != "undefined") {
			return $this.data('loader_id');
		}
		id_count++;
		var id = 'loader_' + id_count;
		$this.data('loader_id', id);
		return id;
	};

	$.fn.showLoader = function(settings) {
		return this.each(function () {
			var $toCover = $(this);
			var $parent = $toCover.parent();
			$toCover.hide();

			var id = $toCover.getLoaderId();
			var $spinner = $('div#' + id);

			if ($spinner.length == 0) {

				$spinner = $('<div />')
					.attr('id', id)
					.addClass('loader')
					.insertBefore($toCover)
					.spinner({
						colour : '255,255,255',
						spokeCount : 10,
						spokeWidth : 3,
						rotation : 4,
						spokeOffset : {
							inner : 8,
							outer : 15
						},
						centered : false
					});
			}
		});
	};

	$.fn.hideLoader = function(skip) {

		return this.each(function () {
			var id = $(this).getLoaderId();
			var $spinner = $('div#' + id);
			$(this).show();
			$spinner.remove();
		});

	}
})(window.jQuery);

/*
  * jQuery Canvas Spinner Plugin
  * version: 1.1
  * Author: Ollie Relph
  * http://github.com/BBB/jquery-canvasspinner
  * Copyright: Copyright 2010 Ollie Relph
  */ 
(function($) {
	$.fn.spinner = function(settings) {
		
		settings = $.extend({
			sqr: undefined,
			framerate : 10,
			spokeCount : 16,
			rotation : 0,
			spokeOffset : {
				inner : undefined,
				outer : undefined
			},
			spokeWidth : undefined,
			colour : '255,255,255',
			backup : 'images/spinner.gif',
			centered : true
		}, settings || {});
 
		return this.each(function () {
 
			var $this = $(this),
				width = $this.width(),
				height = $this.height(),
				ctx,
				hsqr,
				$wrap,
				$canv;
						
			settings.sqr = Math.round(width >= height ? height : width);
			hsqr = settings.sqr/2;
			// convert from deg to rad
			settings.rotation = settings.rotation/180 * Math.PI
			settings.spokeOffset.inner = settings.spokeOffset.inner || hsqr * 0.3;
			settings.spokeOffset.outer = settings.spokeOffset.outer || hsqr * 0.6;	
			
			$wrap = $('<div id="spinner-' + $.fn.spinner.count + '" class="spinner" />')
			if (settings.centered) {
				$wrap.css({'position' : 'absolute', 'z-index' : 999, 'left' : '50%', 'top' : '50%', 'margin' : hsqr * -1 + 'px 0 0 ' + hsqr * -1 + 'px', 'width' : settings.sqr, 'height' : settings.sqr })
			}
			$canv = $('<canvas />').attr({ 'width' : settings.sqr, 'height' : settings.sqr });
			
			if ( $this.css('position') === 'static' && settings.centered ) {
				$this.css({ 'position' : 'relative' });
			}
			
			$canv.appendTo($wrap);
			$wrap.appendTo($this);
					
			if ( $canv[0].getContext ){  
				ctx = $canv[0].getContext('2d');
				ctx.translate(hsqr, hsqr);
				ctx.lineWidth = settings.spokeWidth || Math.ceil(settings.sqr * 0.025);
				ctx.lineCap = 'round'
				this.loop = setInterval(drawSpinner, 1000/ settings.framerate);
			} else {
				// show a backup image...
				$canv.remove();
				$wrap.css({ 'background-image' : 'url(' + settings.backup + ')', 'background-position' : 'center center', 'background-repeat' : 'none'})
			}

			function drawSpinner () {
				ctx.clearRect(hsqr * -1, hsqr * -1, settings.sqr, settings.sqr);
				ctx.rotate(Math.PI * 2 / settings.spokeCount + settings.rotation );
				for (var i = 0; i < settings.spokeCount; i++) {
					ctx.rotate(Math.PI * 2 / settings.spokeCount);
					ctx.strokeStyle = 'rgba(' + settings.colour + ','+ i / settings.spokeCount +')';
					ctx.beginPath();
					ctx.moveTo(0, settings.spokeOffset.inner);
					ctx.lineTo(0, settings.spokeOffset.outer);
					ctx.stroke();
				}
			}  
			$.fn.spinner.count++;
		});
	};
	$.fn.spinner.count = 0;
	$.fn.spinner.loop;
 
	$.fn.clearSpinner = function() {
		return this.each(function () {
			clearTimeout($.fn.spinner.loop);
			$(this).find('div.spinner').fadeOut().remove().end();
		});
	}
})(window.jQuery);
/*
* Scotty.js
* version: 0.1
* URL: http://github.com/BBB/scotty.js
* Description: A window.location.hash kvp system
* Author: Ollie Relph http://ollie.relph.me/ || ollie@relph.me
*/
var Scotty = function(str) {
	this.fromString(str);
	return this;
};
Scotty.prototype = {
	keyStore: {},
	_listeners: {},
	_triggerListeners: function (key, newValue) {
		for (var i in this._listeners[key]) {
			this._listeners[key][i].apply(null, [newValue]);
		}
		if (this._listeners.hasOwnProperty('*')) {
			for (var i in this._listeners['*']) {
				this._listeners['*'][i].apply(null, [newValue]);
			}
		}
	},
	_hasChanged: function (key, newValue) {
		return this.keyStore[key] != encodeURIComponent(newValue);
	},
	setValue: function (key, value) {
		if (key == '' || value == '' || value == 'undefined') return false;
		if (!this._hasChanged(key, value)) return false;
		this.keyStore[key] = value;
		this._triggerListeners(key, value);
		return true;
	},
	setValues: function (obj) {
		for (var key in obj) {
			if (this._hasChanged(key, obj[key])) {
				this.keyStore[key] = encodeURIComponent(obj[key]);
				this._triggerListeners(key, obj[key]);
			}
		}
	},
	fromString: function (str) {
		if (str.indexOf('?') > -1) {
			str = str.split('?')[1];
		}
		var kps = str.split('&');
		for (var pair in kps) {
			pair = kps[pair].split('=');
			this.setValue(pair[0], pair[1]);
		}
	},
	toString: function () {
		 var str = '#/?';
		for (var p in this.keyStore) {
			if (this.keyStore.hasOwnProperty(p)) {
				if (str.length > 1) str += '&';
				str += p + '=' + this.keyStore[p];
			}
		}
		return window.location.protocol + '//' + window.location.host + window.location.pathname + str;
	},
	addListener: function (key, cb) {
		if (this._listeners.hasOwnProperty(key)) {
			this._listeners[key].push(cb);
		} else {
			this._listeners[key] = [cb];
		}
	},
	getValue: function (key) {
		return decodeURIComponent(this.keyStore[key]);
	}
};

function getStatusRowRenderFunction(showResponses) {
	return function(o, type, row, meta) {
		if (typeof row.message != 'string') {
			row.message = '';
		}
		row.date = moment(row.created_time).format('D MMM');
		var message = parseTemplate(app.templates.post, row);
		if(showResponses) {
			message = appendResponseTemplate(message, row);
		} else {
			message = convertTitleToLink(message, row);
		}
		return message;
	}
}

function parseTemplate(str, data) {
	return _.template(str, data);
}

function convertTitleToLink(message, data) {
	var $el = $(message);
	var title = $el.find('h4').text();
	title = '<a href="/presence/view/id/' + data.presence_id + '">' + title + '</a>';
	$el.find('h4').html(title);
	return $el.html();
}

function appendResponseTemplate(message,aData) {
	var response = aData.first_response;
	var rTitle, rMessage, rIcon;
	if (aData.needs_response == '1') {
		rTitle = 'Does not require a response';
		rMessage = 'Awaiting response (' + response.date_diff + ')...';
		rIcon = 'icon-comment-alt';
	} else {
		rTitle = 'Requires a response';
		rMessage = 'No response required';
		rIcon = 'icon-comments';
	}
	message += '<p class="more"><a href="#" class="require-response" title="' + rTitle + '"><span class="' + rIcon + ' icon-large"></span></a></p>' +
		'<p class="no-response">' + rMessage + '</p>';
	return message;
}

var Base64 = {
	// private property
	_keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",

	// public method for encoding
	encode : function (input) {
		var output = "";
		var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
		var i = 0;

		input = Base64._utf8_encode(input);

		while (i < input.length) {

			chr1 = input.charCodeAt(i++);
			chr2 = input.charCodeAt(i++);
			chr3 = input.charCodeAt(i++);

			enc1 = chr1 >> 2;
			enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
			enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
			enc4 = chr3 & 63;

			if (isNaN(chr2)) {
				enc3 = enc4 = 64;
			} else if (isNaN(chr3)) {
				enc4 = 64;
			}

			output = output +
			Base64._keyStr.charAt(enc1) + Base64._keyStr.charAt(enc2) +
			Base64._keyStr.charAt(enc3) + Base64._keyStr.charAt(enc4);

		}

		return output;
	},

	// public method for decoding
	decode : function (input) {
		var output = "";
		var chr1, chr2, chr3;
		var enc1, enc2, enc3, enc4;
		var i = 0;

		input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

		while (i < input.length) {

			enc1 = Base64._keyStr.indexOf(input.charAt(i++));
			enc2 = Base64._keyStr.indexOf(input.charAt(i++));
			enc3 = Base64._keyStr.indexOf(input.charAt(i++));
			enc4 = Base64._keyStr.indexOf(input.charAt(i++));

			chr1 = (enc1 << 2) | (enc2 >> 4);
			chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
			chr3 = ((enc3 & 3) << 6) | enc4;

			output = output + String.fromCharCode(chr1);

			if (enc3 != 64) {
				output = output + String.fromCharCode(chr2);
			}
			if (enc4 != 64) {
				output = output + String.fromCharCode(chr3);
			}

		}

		output = Base64._utf8_decode(output);

		return output;

	},

	// private method for UTF-8 encoding
	_utf8_encode : function (string) {
		string = string.replace(/\r\n/g,"\n");
		var utftext = "";

		for (var n = 0; n < string.length; n++) {

			var c = string.charCodeAt(n);

			if (c < 128) {
				utftext += String.fromCharCode(c);
			}
			else if((c > 127) && (c < 2048)) {
				utftext += String.fromCharCode((c >> 6) | 192);
				utftext += String.fromCharCode((c & 63) | 128);
			}
			else {
				utftext += String.fromCharCode((c >> 12) | 224);
				utftext += String.fromCharCode(((c >> 6) & 63) | 128);
				utftext += String.fromCharCode((c & 63) | 128);
			}

		}

		return utftext;
	},

	// private method for UTF-8 decoding
	_utf8_decode : function (utftext) {
		var string = "";
		var i = 0;
		var c = c1 = c2 = 0;

		while ( i < utftext.length ) {

			c = utftext.charCodeAt(i);

			if (c < 128) {
				string += String.fromCharCode(c);
				i++;
			}
			else if((c > 191) && (c < 224)) {
				c2 = utftext.charCodeAt(i+1);
				string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
				i += 2;
			}
			else {
				c2 = utftext.charCodeAt(i+1);
				c3 = utftext.charCodeAt(i+2);
				string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
				i += 3;
			}

		}
		return string;
	}
}
