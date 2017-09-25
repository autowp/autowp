import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'SpecService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var types = null;
        
        var service = this;
        
        this.getSpecs = function() {
            return $q(function(resolve, reject) {
                if (types === null) {
                    $http({
                        method: 'GET',
                        url: '/go-api/spec'
                    }).then(function(response) {
                        types = response.data.items;
                        resolve(types);
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(types);
                }
            });
        };
        
        this.getSpec = function(id) {
            return $q(function(resolve, reject) {
                service.getSpecs().then(function(types) {
                    var spec = findSpec(types, id);
                    if (spec) {
                        resolve(spec);
                    } else {
                        reject(null);
                    }
                }, reject);
            });
        };
        
        function findSpec(specs, id) {
            var spec = null;
            for (var i=0; i<specs.length; i++) {
                if (specs[i].id == id) {
                    spec = specs[i];
                    break;
                }
                spec = findSpec(specs[i].childs, id);
                if (spec) {
                    break;
                }
            }
            return spec;
        }
    }]);

export default SERVICE_NAME;
