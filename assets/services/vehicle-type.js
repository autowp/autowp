import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'VehicleTypeService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', '$translate', function($q, $http, $translate) {
        
        var types = null;
        var service = this;
        
        function collectNames(types) {
            var result = [];
            walkTypes(types, function(type) {
                result.push(type.name);
            });
            return result;
        }
        
        function applyTranslations(types, translations) {
            walkTypes(types, function(type) {
                type.nameTranslated = translations[type.name];
            });
        }
        
        function walkTypes(types, callback) {
            angular.forEach(types, function(type) {
                callback(type);
                walkTypes(type.childs, callback);
            });
        }
        
        this.getTypes = function() {
            return $q(function(resolve, reject) {
                if (types === null) {
                    $http({
                        method: 'GET',
                        url: '/api/vehicle-types'
                    }).then(function(response) {
                        types = response.data.items;
                        var names = collectNames(types);
                        
                        $translate(names).then(function (translations) {
                            applyTranslations(types, translations);
                            resolve(types);
                        }, function (translationIds) {
                            reject(null);
                        });
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(types);
                }
            });
        };
        
        this.getTypesById = function(ids) {
            return $q(function(resolve, reject) {
                
                if (ids.length <= 0) {
                    resolve([]);
                    return;
                }
                
                service.getTypes().then(function(types) {
                    var result = [];
                    walkTypes(types, function(type) {
                        if (ids.includes(type.id)) {
                            result.push(type);
                        }
                    });
                    resolve(result);
                }, function() {
                    reject(null);
                });
            });
        };
    }]);

export default SERVICE_NAME;
