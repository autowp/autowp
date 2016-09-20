define(
    ['jquery', 'googlemaps'],
    function($, googlemaps) {
        return {
            init: function(options) {
                this.currentInfowindow = null;
                this.items = {};
                
                var self = this;
                
                $('#google-map').each(function() {
                    
                    var startPosition = new googlemaps.LatLng(52.48, 13.45);
                    var xhrTimeout = null;
                    
                    self.map = new googlemaps.Map(this, {
                        zoom: 4,
                        center: startPosition,
                        mapTypeId: googlemaps.MapTypeId.ROADMAP,
                        minZoom: 4
                    });
                    
                    google.maps.event.addListener(self.map, 'bounds_changed', function() {
                        if (xhrTimeout) {
                            clearTimeout(xhrTimeout);
                            xhrTimeout = null;
                        }
                        xhrTimeout = setTimeout(function() {
                            self.loadData();
                        }, 300);
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
                                                self.map.fitBounds(address.geometry.viewport);
                                                self.loadData();
                                                return false;
                                            });
                                        });
                                    }
                                }
                            });
                            
                        });
                    }
                });
            },
            loadData: function() {
                var params = {
                    bounds: this.map.getBounds().toUrlValue()
                }
                var self = this;
                $.getJSON('/map/data', params, function(data) {
                    self.renderData(data);
                });
            },
            renderData: function(data) {
                var self = this;
                
                $.each(this.items, function(key, item) {
                    item.found = false;
                });
                
                $.each(data, function(key, factory) {
                    if (factory.location) {
                        
                        if (self.items[factory.id]) {
                            self.items[factory.id].found = true;
                        } else {
                        
                            var position = new googlemaps.LatLng(factory.location.lat, factory.location.lng);
                            var marker = new googlemaps.Marker({
                                position: position,
                                map: self.map,
                                title: factory.name
                            });
                            
                            var lines = [
                                $('<p />').append($('<strong />').text(factory.name))
                            ];
                            
                            if (factory.image) {
                                lines.push($('<p />').append($('<img />').attr('src', factory.image)));
                            }
                            
                            if (factory.desc) {
                                lines.push(factory.desc);
                            }
                            
                            lines.push($('<p />').append($('<a />').text('подробнее ...').attr('href', factory.url)));
                            
                            var info = $('<div />').append(lines);
                            
                            var infowindow = new googlemaps.InfoWindow({
                                content: info[0]
                            });
                            
                            googlemaps.event.addListener(marker, 'click', function() {
                                if (self.currentInfowindow) {
                                    self.currentInfowindow.close();
                                }
                                self.currentInfowindow = infowindow;
                                infowindow.open(self.map, marker);
                            });
                            
                            self.items[factory.id] = {
                                marker: marker,
                                infowindow: infowindow,
                                found: true
                            };
                        }
                    }
                });
                
                $.each(this.items, function(key, item) {
                    if (!item.found) {
                        if (item.marker) {
                            item.marker.setMap(null);
                        }
                        if (item.infowindow) {
                            item.infowindow = null;
                        }
                        
                        delete self.items[key];
                    }
                });
            }
        }
    }
);