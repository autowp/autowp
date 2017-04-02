import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'VehicleTypeService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var types = null;
        
        this.getTypes = function() {
            return $q(function(resolve, reject) {
                if (types === null) {
                    $http({
                        method: 'GET',
                        url: '/api/vehicle-types'
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
        
    }]);

export default SERVICE_NAME;
