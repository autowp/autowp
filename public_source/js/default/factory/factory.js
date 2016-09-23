define(
    'default/factory/factory',
    ['jquery', 'googlemaps'],
    function($, googlemaps) {
        return {
            init: function(options) {
                
                if (options.lat && options.lng) {
                
                    $('#google-map').each(function() {
                        
                        var startPosition = new googlemaps.LatLng(options.lat, options.lng);
                        var map = new googlemaps.Map(this, {
                            zoom: 10,
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