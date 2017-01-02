define(
    ['jquery', 'load-google-maps-api'],
    function($, loadGoogleMapsAPI) {
        return {
            init: function(options) {
                loadGoogleMapsAPI().then(function(googlemaps) {
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