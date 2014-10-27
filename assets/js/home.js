/**
 * Created by outlander on 16/10/2014.
 */

var app = app || {};

app.home = {
    setup: function(){

        $('.small-country-list')
            .on('click', 'li a', function(event){
                event.preventDefault();
                var id = $(this).parent('li').data('id');
                app.geochart.loadCampaignStats(id);
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

	    var $homepageTabs = $('#homepage-tabs');
	    $homepageTabs.on('click', 'a', function(e) {
		    app.home.setActiveTab($(this).closest('dd'));
	    });

	    var badge = window.location.hash.replace("#", "");
	    if(!badge){
		    badge = "total";
	    }
	    $homepageTabs.find('a[href="#' + badge + '"]').trigger('click');
    },
    currentBadge: function(){
	    return $('#homepage-tabs').find('.active').data('badge');
    },
    setActiveTab: function($tab){
	    var badge = $tab.data('badge');

        $('#homepage-tabs').find('dd').removeClass('active');
	    $tab.addClass('active');

        //updating badge Titles
        $('[data-badge-title]').text($tab.data('title'));

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
        app.geochart.refreshMap();
    }
}
;
