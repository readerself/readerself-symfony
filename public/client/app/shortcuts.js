var gKey = false;

document.addEventListener('keyup', function(event) {
    var keycode = event.which || event.keyCode;

    if($(event.target).parents('form').length === 0) {
        //g
        if(keycode === 71) {
            gKey = true;
        } else {
            gKey = false;
        }
    }
});

document.addEventListener('keydown', function(event) {
    var keycode = event.which || event.keyCode;

    if($(event.target).parents('form').length === 0) {
        //g then a
        if(gKey && keycode === 65) {
            loadRoute('#items/recent');

        //g then u
        } else if(gKey && keycode === 85) {
            loadRoute('#items/unread');

        //g then s
        } else if(gKey && keycode === 83) {
            loadRoute('#items/starred');

        //g then f
        } else if(gKey && keycode === 70) {
            loadRoute('#feeds/recent');

        //g then c
        } else if(gKey && keycode === 67) {
            loadRoute('#categories/recent');

        //t
        } else if(keycode === 84) {
            event.preventDefault();
            scrollTo('#top');

        //2
        } else if(keycode === 50) {
            event.preventDefault();
            if(itemsDisplay === 'collapse') {
                itemsExpand();
            }

        //v
        } else if(keycode === 86) {
            var href = $('.mdl-grid .card-selected').find('h1').find('a').attr('href');
            var name = $('.mdl-grid .card-selected').attr('id');
            window.open(href, 'window_' + name);

        //m
        } else if(keycode === 77 && $('body').hasClass('connected') && $('body').hasClass('online')) {
            if($('.mdl-grid .card-selected').length > 0) {
                $('.mdl-grid .card-selected').find('.action-read').trigger('click');
            }

        //shift + s
        } else if(event.shiftKey && keycode === 83) {
            if($('.mdl-grid .card-selected').length > 0) {
                $('.mdl-grid .card-selected').find('.action-share').trigger('click');
            }

        //s
        } else if(keycode === 83 && $('body').hasClass('connected') && $('body').hasClass('online')) {
            if($('.mdl-grid .card-selected').length > 0) {
                $('.mdl-grid .card-selected').find('.action-star').trigger('click');
            }

        //o or enter
        } else if(keycode === 79 || keycode === 13) {
            if($('.mdl-grid .card-selected').length > 0) {
                var ref = $( '#' + $('.mdl-grid .card-selected').attr('id') );
                if(ref.hasClass('collapse')) {
                    ref.removeClass('collapse');
                    ref.addClass('expand');
                } else {
                    ref.removeClass('expand');
                    ref.addClass('collapse');
                }
                //$('.mdl-grid .card-selected').find('.action-toggle-unit').trigger('click');
            }

        } else if(keycode === 65 && $('body').hasClass('connected') && $('body').hasClass('online')) {
            //shift + a
            if(event.shiftKey) {
                if($('#dialog-mark_all_as_read').length > 0) {
                    $('#dialog-mark_all_as_read').trigger('click');
                }
            //a
            } else {
                //loadRoute('#feed/create');
            }

        //slash
        } else if(keycode === 191) {
            event.preventDefault();
            if($('input[name="q"]').length > 0) {
                $('input[name="q"]').focus();
            }

        //nothing when meta + k
        } else if(event.metaKey && keycode === 75) {

        //nothing when ctrl + k
        } else if(event.ctrlKey && keycode === 75) {

        //k or p or shift + space
        } else if(keycode === 75 || keycode === 80 || (keycode === 32 && event.shiftKey)) {
            itemUp();

        //j or n or space
        } else if(keycode === 74 || keycode === 78|| keycode === 32) {
            itemDown();

        //r
        } else if(keycode === 82) {
            if(window.location.hash) {
                loadRoute(window.location.hash);
            }
        }
    }
});
