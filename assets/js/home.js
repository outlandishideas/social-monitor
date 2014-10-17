/**
 * Created by outlander on 16/10/2014.
 */

var app = app || {}

app.home = {
    setup: function(){

        $('.small-country-list')
            .on('click', 'li a', function(event){
                event.preventDefault();
                var id = $(this).parent('li').data('id');
                app.geochart.loadCampaignStats(id);
            });

        $('.badge-buttons')
            .on('click', 'li a', function(event){
                event.preventDefault();
                window.location.hash = $(this).attr('href');
                app.home.update();
                app.geochart.refreshMap();
            });

        $('.badge-presences-buttons')
            .on('click', 'li a', function(event){
                event.preventDefault();
                var $this = $(this);
                var type = $(this).attr('href').replace('#','');
                $this.parents('.badge-presences-buttons')
                    .find('li a').removeClass('active')
                    .filter('[href="#' +type+ '"]').addClass('active');
                $this.parents('.badge-small')
                    .find('.badge-presences').hide()
                    .filter('[data-'+type+'-presences]').show();
            })
            .end().find('.badge-presences').hide();

        var $badgeDescriptions = $('.badge-description');
        var height = 0;
        var $div = null;
        for (var $i = 0; $i < $badgeDescriptions.length; $i++) {
            $div = $($badgeDescriptions[$i]);
            if(height < $div.height()){
                height = $div.height();
            }
        }
        $badgeDescriptions.css('height', height);

        app.home.update()
    },
    currentBadge: function(){
        return window.location.hash.replace("#", "");
    },
    update: function(){
        var badge = app.home.currentBadge();
        if(!badge){
            badge = "total";
        }
        app.state.homeBadge = badge;

        //activate badge buttons
        $('.badge-buttons')
            .find('li a').removeClass('active')
            .filter('[href="#'+ badge +'"]').addClass('active');

        //updating badge Titles
        var $badgeTitles = $('#badge-titles');
        var badgeTitle = $badgeTitles.data(badge + '-title');
        $('[data-badge-title]').text(badgeTitle);

        //show descriptions
        $('.badge-description').hide().filter('[data-badge-description="' + badge + '"]').show();

        $('[data-' + badge + ']').each(function(){
            var $this = $(this);
            var score = $this.data(badge);
            var color = $this.data(badge + '-color');
            var $score = $this.find('[data-badge-score]');
            var $bar = $this.find('[data-badge-bar]');
            $score.text(score + '%').css('color', '#d2d2d2');
            $bar.css('width', score + '%').css('background-color', '#d2d2d2');
            if(color) {
                $score.css('color', color);
                $bar.css('background-color', color);
            }

        });
    }
}
