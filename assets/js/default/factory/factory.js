define(
    ['jquery', 'load-google-maps-api'],
    function($, loadGoogleMapsAPI) {
        return {
            init: function(options) {
                loadGoogleMapsAPI().then(function(googlemaps) {
                    if (options.lat && options.lng) {
                        
                        $('#google-map').each(function() {
                            
                            var startPosition = new googlemaps.LatLng(options.lat, options.lng);
                            var map = new googlemaps.Map(this, {
                                zoom: 10,
                                center: startPosition,
                                mapTypeId: googlemaps.MapTypeId.ROADMAP
                            });
                            
                            new googlemaps.Marker({
                                position: startPosition,
                                map: map
                            });
                        });
                    }
                });
            }
        };
    }
);