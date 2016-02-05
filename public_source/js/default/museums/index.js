define(
    ['jquery', 'googlemaps'],
    function($, googlemaps) {
        return {
            init: function(options) {
                
                $('#google-map').each(function() {
                    
                    var startPosition = new googlemaps.LatLng(52.48, 13.45);
                    var museums = options.museums;
                    
                    var map = new googlemaps.Map(this, {
                        zoom: 2,
                        center: startPosition,
                        mapTypeId: googlemaps.MapTypeId.ROADMAP
                    });
                    
                    $.each(museums, function(key, museum) {
                        if (museum.location) {
                            var position = new googlemaps.LatLng(museum.location.lat, museum.location.lng);
                            var marker = new googlemaps.Marker({
                                position: position,
                                map: map,
                                title: museum.name
                            });
                            
                            var info = $('<div />').append(
                                $('<p />').append($('<strong />').text(museum.name))
                            );
                            if (museum.desc) {
                                info.append($('<p />').text(museum.desc))
                            }
                            if (museum.url) {
                                info.append(
                                    $('<p />').append(
                                        $('<a />').text(museum.url).attr('href', museum.url)
                                    )
                                );
                            }
                            if (museum.address) {
                                info.append($('<p />').text(museum.address))
                            }
                            
                            var infowindow = new googlemaps.InfoWindow({
                                content: info[0]
                            });
                            
                            googlemaps.event.addListener(marker, 'click', function() {
                                infowindow.open(map, marker);
                            });
                            
                            $('#museum'+museum.id+'maplink').on('click', function() {
                                infowindow.open(map, marker);
                                map.setCenter(position);
                                map.setZoom(18);
                                $(window).scrollTop(0);
                                return false;
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
            }
        }
    }
);