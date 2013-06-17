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
                console.log(response);
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

        for(var i in data.kpis){

            var kpi = data.kpis[i];
            var title = '';

            if ( kpi.target ) {
                title = "Target = " + kpi.target;
            }

            ref += '<dd title="' + title + '">' + Math.round(kpi.score)+ '</dd>';
            ref += '<dt>' + kpi.title + '</dt>';

        }

        b.$badge.find('dl').append(ref);



    }
};
