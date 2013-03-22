/**
 * Global app object holds all functions and app state
 */
var app = app || {};

app.jobs = {
	listeningXhr: null,
	listen: function() {
		if (!app.jobs.listeningXhr || app.jobs.listeningXhr.readyState == 4) {
			app.jobs.listeningXhr = app.api.get('user/notifications').done(app.jobs.listen);
		}
	}
};