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
        
        return this;
    }
    
    function autocomplete(init = false) {
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
                results_el.html('');
                if (init) {
                    let address = data.data[0].address;
                    _this.val(address);
                    $("body").trigger("update_checkout");
                } else {
                    $('.autocomplete-results', parent_context).slideDown('fast');                
                    data.data.forEach(result => {
                        if (result.lat && result.lng) {
                            $div = $('<div></div>');
                            $div.addClass('entry');
                            $div.html(result.address);
    
                            $div.on('click', function() {
                                let address = $(this).text();
                                _this.val(address);
                                $("#billing_address_1").val(address);
                                $('.autocomplete-results', parent_context).slideUp('fast');
                                $("body").trigger("update_checkout");
                            });
    
                            results_el.append($div);
                        }
                    });
                }
            },
            dataType: 'json'
        });
    }
    
    $(document).ready(function() {
        $('#billing_address_1')
            .attr('autocomplete', 'off')
            .after(`
                <div class="autocomplete-results"></div>
            `)
            .donetyping(function($callback) {
                autocomplete.call(this);
            });

        if($('#billing_address_1').val() != '') {
            autocomplete.call($('#billing_address_1'), {'init': true});
        }
    });
})(jQuery);