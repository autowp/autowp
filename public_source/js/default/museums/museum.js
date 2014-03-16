define(
    'default/museums/museum',
    ['jquery', 'googlemaps'],
    function($, googlemaps) {
        return {
            init: function(options) {
                
                if (options.lat && options.lng) {
                
                    $('#google-map').each(function() {
                        
                        $(this).css({
                            width: '100%',
                            height: '300px',
                            margin: '0 0 40px'
                        });
                        
                        var startPosition = new googlemaps.LatLng(options.lat, options.lng);
                        var map = new googlemaps.Map(this, {
                            zoom: 17,
                            center: startPosition,
                            mapTypeId: googlemaps.MapTypeId.ROADMAP
                        });
                        
                        var marker = new googlemaps.Marker({
                            position: startPosition,
                            map: map
                        });
                    });
                }
            }
        }
    }
);