var app = app || {};

/**
 * custom Badge functions
 */
app.badges = {

	setup: function(){

        var $badges = $('#badges');

        $badges.find('.badge.box').each(function(){
            var selector = '#' + $(this).attr('id');
            app.state.badges[selector] = app.badges.createBadge(selector);
        });

        app.badges.fetchBadgeData();

    },

    createBadge: function(selector){
        var badge = {
            selector: selector,
            $badge: $(selector)
        }
        return badge;
    },

    fetchBadgeData: function () {
        var $badges = $('#badges');

        $badges.showLoader();

        var url = $badges.data('controller') + '/badge-data';
        var args = {
            id:$badges.data('id')
        };

        return app.api.get(url, args)
            .done(function(response) {
                $('#badges').hideLoader();
                for (var i in response.data) {
                    app.badges.renderBadge(response.data[i]);
                }
            })
            .always(function() {
                $('#badges').hideLoader();
            });
    },

    renderBadge: function(data){
        var selector = '#'+ data.type;
        var b = app.state.badges[selector];

        b.$badge.find('.score.ranking')
            .find('.number').html(data.ranking).end()
            .find('.text.denominator').html('of ' + data.rankingTotal);
        b.$badge.find('.score.overall-score')
            .find('.number').html(Math.round(data.score));

        var ref = '';

        if(i != 'total'){
            for(var i in data.metrics){

                var metric = data.metrics[i];
                var title = '';

                if ( metric.target ) {
                    title = "Target = " + metric.target;
                } /*else {
                    title = "Presences";
                    for(var p in data.presences){
                        var presence = data.presences[p];
                        console.log(presence);
                        console.log(presence.handle);
                        console.log(i);
                        console.log(presence.metrics);
                        console.log(presence.metrics[i]);
                        title += "\n"+ presence.handle +": "+ presence.metrics[i].score;
                    }

                }*/

                ref += '<dd title="' + title + '">' + Math.round(metric.score)+ '</dd>';
                ref += '<dt>' + metric.title + '</dt>';

            }
        }

        b.$badge.find('dl').append(ref);



    }
};
