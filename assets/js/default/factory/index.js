define(
    ['jquery', 'load-google-maps-api'],
    function($, loadGoogleMapsAPI) {
        return {
            init: function(options) {
                
                $('#google-map').each(function() {
                    loadGoogleMapsAPI().then(function(googlemaps) {
                        var startPosition = new googlemaps.LatLng(52.48, 13.45);
                        var factories = options.factories;
                        
                        var map = new googlemaps.Map(this, {
                            zoom: 2,
                            center: startPosition,
                            mapTypeId: googlemaps.MapTypeId.ROADMAP
                        });
                        
                        var currentInfowindow = null;
                        
                        $.each(factories, function(key, factory) {
                            if (factory.location) {
                                var position = new googlemaps.LatLng(factory.location.lat, factory.location.lng);
                                var marker = new googlemaps.Marker({
                                    position: position,
                                    map: map,
                                    title: factory.name
                                });
                                
                                var info = $('<div />').append([
                                    $('<p />').append($('<strong />').text(factory.name)),
                                    $('<p />').append($('<a />').text('подробнее ...').attr('href', factory.url))
                                ]);
                                
                                var infowindow = new googlemaps.InfoWindow({
                                    content: info[0]
                                });
                                
                                googlemaps.event.addListener(marker, 'click', function() {
                                    if (currentInfowindow) {
                                        currentInfowindow.close();
                                    }
                                    currentInfowindow = infowindow;
                                    infowindow.open(map, marker);
                                });
                            }
                        });
                        
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function(position) {
                                var latLng = new googlemaps.LatLng(position.coords.latitude, position.coords.longitude);
                                var geocoder = new googlemaps.Geocoder();
                                geocoder.geocode({'latLng': latLng}, function(results, status) {
                                    if (status == googlemaps.GeocoderStatus.OK) {
                                        var country = null;
                                        $.each(results, function(i, address) {
                                            $.each(address.address_components, function(j, component) {
                                                $.each(component.types, function(k, type) {
                                                    if (type == 'country') {
                                                        country = component.long_name;
                                                    }
                                                    if (country) {
                                                        return false;
                                                    }
                                                });
                                                if (country) {
                                                    return false;
                                                }
                                            });
                                            if (country) {
                                                return false;
                                            }
                                        });
                                        
                                        if (country) {
                                            geocoder.geocode({'address': country}, function(results, status) {
                                                $.each(results, function(i, address) {
                                                    map.fitBounds(address.geometry.viewport);
                                                    return false;
                                                });
                                            });
                                        }
                                    }
                                });
                                
                            });
                        }
                    });
                });
            }
        };
    }
);