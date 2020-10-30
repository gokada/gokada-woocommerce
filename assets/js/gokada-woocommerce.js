(function($) {
    $.fn.donetyping = function(callback){
	    var _this = $(this);
	    var x_timer;
	    _this.keyup(function (){
	        clearTimeout(x_timer);
	        x_timer = setTimeout(clear_timer, 250);
	    });

	    function clear_timer(){
	        clearTimeout(x_timer);
	        callback.call(_this);
	    }
    }
    
    function autocomplete() {
        let _this = $(this);
        let val = $(this).val();
        let parent_context = $(this).parent();
        let results_el = $('.autocomplete-results', parent_context);
        let query = val.replace('%', '');
    
        if(val == "")
            return;
        
        let data = {
            'action': 'autocomplete',
            'query': query
        };
    
        $.ajax({
            type: "POST",
            url: obj.ajax_url,
            data: data,
            success: function(data) {
                // $('.loader', parent_context).fadeOut();
                results_el.html('');
                $('.autocomplete-results', parent_context).slideDown('fast');
                for(let i = 0; i < data.length; i++) {
                    $div = $('<div></div>');
                    $div.addClass('entry');
                    $div.attr('data-lat', data[i].lat);
                    $div.attr('data-lng', data[i].lng);
                    $div.html(data[i].address);

                    $div.on('click', function() {
                        let lat = $(this).attr('data-lat');
                        let lng = $(this).attr('data-lng');
                        $('#billing-location-lat').val(lat);
                        $('#billing-location-lng').val(lng);
                        let address = $(this).text();
                        _this.val(address);
                        $('.autocomplete-results', parent_context).slideUp('fast');
                    });

                    results_el.append($div);
                }
            },
            dataType: 'json'
        });
    }
    
    $(document).ready(function() {
        $('#billing_address_1').attr('autocomplete', 'off')
            .after(`
                <div class="autocomplete-results"></div>
                <input type="hidden" value="" name="billing_lat" id="billing-location-lat">
                <input type="hidden" value="" name="billing_lng" id="billing-location-lng">
            `)
            .donetyping(function($callback) {
                autocomplete.call(this);
            });
    });
})(jQuery);