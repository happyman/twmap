/*!
* A simple jQuery Wrapper for Geolocation API
* Supports Deferreds
*
* @author: Manuel Bieh
* @url: http://www.manuel-bieh.de/
* @documentation: http://www.manuel-bieh.de/blog/geolocation-jquery-plugin
* @version 1.1.0
* @license MIT
*/

(function($) {

	$.extend({

		geolocation: {

			watchIDs: [],

			get: function(arg1, arg2, arg3) {

				var o = {};

				if(typeof arg1 === 'object') {
					o = $.geolocation.prepareOptions(arg1);
				} else {
					o = $.geolocation.prepareOptions({success: arg1, error: arg2, options: arg3});
				}

				return $.geolocation.getCurrentPosition(o.success, o.error, o.options);

			},

			getPosition: function(o) {
				return $.geolocation.get.call(this, o);
			},

			getCurrentPosition: function(arg1, arg2, arg3) {

				var defer = $.Deferred();

				if(typeof navigator.geolocation != 'undefined') {

					if(typeof arg1 === 'function') {

						navigator.geolocation.getCurrentPosition(arg1, arg2, arg3);

					} else {

						navigator.geolocation.getCurrentPosition(function() {
							defer.resolveWith(this, arguments);
						}, function() {
							defer.rejectWith(this, arguments);
						}, arg1 || arg3);

						return defer.promise();

					}

				} else {

					var error = {"message": "No geolocation available"};

					if(typeof arg2 === 'function') {
						arg2(error);
					}

					defer.rejectWith(this, [error]);
					return defer.promise();

				}

			},

			watch: function(o) {

				o = $.geolocation.prepareOptions(o);
				return $.geolocation.watchPosition(o.success, o.error, o.options);

			},

			watchPosition: function(success, error, options) {

				if(typeof navigator.geolocation !== 'undefined') {

					watchID = navigator.geolocation.watchPosition(success, error, options);
					$.geolocation.watchIDs.push(watchID);
					return watchID;

				} else {

					error();

				}

			},

			stop: function(watchID) {

				if(typeof navigator.geolocation != 'undefined') {
					navigator.geolocation.clearWatch(watchID);
				}

			},

			clearWatch: function(watchID) {
				$.geolocation.stop(watchID);
			},

			stopAll: function() {

				$.each(jQuery.geolocation.watchIDs, function(key, value) {
					$.geolocation.stop(value);
				});

			},

			clearAll: function() {
				$.geolocation.stopAll();
			},

			prepareOptions: function(o) {

				o = o || {};

				if(!!o.options === false) {

					o.options = {
						highAccuracy: false,
						maximumAge: 30000, // 30 seconds
						timeout: 60000 // 1 minute
					 }

				}

				if(!!o.win !== false || !!o.done !== false) {
					o.success = o.win || o.done;
				}

				if(!!o.fail !== false) {
					o.error = o.fail;
				}

				return o;

			}

		}

	});

})(jQuery);