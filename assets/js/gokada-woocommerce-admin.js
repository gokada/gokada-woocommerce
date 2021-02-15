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
                results_el.html('');
                $('.autocomplete-results', parent_context).slideDown('fast');
                data.data.forEach(result => {
                    if (result.lat && result.lng) {
                        $div = $('<div></div>');
                        $div.addClass('entry');
                        $div.html(result.address);

                        $div.on('click', function() {
                            let address = $(this).text();
                            $("#woocommerce_gokada_delivery_pickup_base_address").val(address);
                            _this.val(address);
                            $('.autocomplete-results', parent_context).slideUp('fast');
                        });

                        results_el.append($div);
                    }
                });
            },
            dataType: 'json'
        });
    }
    
    $(document).ready(function() {
        $('#woocommerce_gokada_delivery_pickup_state').attr('readonly', true).val("Lagos");
        $('#woocommerce_gokada_delivery_pickup_base_address').attr('autocomplete', 'off')
            .after(`
                <input type="text" value="" name="pickup_address" id="pickup_address" />
                <div class="autocomplete-results"></div>
            `);
        
        $('#pickup_address').donetyping(function($callback) {
            autocomplete.call(this);
        });

        if($('#woocommerce_gokada_delivery_pickup_base_address').val() != '') {
            $('#pickup_address').val($('#woocommerce_gokada_delivery_pickup_base_address').val());
        }
    });
})(jQuery);