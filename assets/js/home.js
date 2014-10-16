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
            });

        app.home.update()
    },
    update: function(){
        var badge = window.location.hash.replace("#", "");
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
            $this.find('[data-badge-score]').text(score + '%');
            $this.find('[data-badge-bar]').css('width', score + '%').css('background-color', '#fff');

        });


        //$('[data-badge-score]').text(badgeScore + '%');
        //$('[data-badge-bar]').text(badgeScore + '%');
    }
}
