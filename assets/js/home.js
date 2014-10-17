/**
 * Created by outlander on 16/10/2014.
 */

var app = app || {}

app.home = {
    setup: function(){

        $('.badge-buttons')
            .on('click', 'li a', function(event){
                event.preventDefault();
                window.location.hash = $(this).attr('href');
                app.home.update();
                app.geochart.refreshMap();
            });

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


        //$('[data-badge-score]').text(badgeScore + '%');
        //$('[data-badge-bar]').text(badgeScore + '%');
    }
}
