import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'ItemService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', '$translate', function($q, $http, $translate) {
        
        this.setItemVehicleTypes = function(itemId, ids) {
            return $q(function(resolve, reject) {
                
                $http.get('/api/item-vehicle-type?item_id=' + itemId).then(function(response) {
                    
                    var promises = [];
                    
                    var currentIds = [];
                    angular.forEach(response.data.items, function(itemVehicleType) {
                        currentIds.push(itemVehicleType.vehicle_type_id);
                        if (! ids.includes(itemVehicleType.vehicle_type_id)) {
                            promises.push(
                                $http({
                                    method: 'DELETE',
                                    url: '/api/item-vehicle-type/' + itemId + '/' + itemVehicleType.vehicle_type_id
                                })
                            );
                        }
                    });
                    
                    angular.forEach(ids, function(vehicleTypeId) {
                        if (! currentIds.includes(vehicleTypeId)) {
                            promises.push($http.post('/api/item-vehicle-type/' + itemId + '/' + vehicleTypeId));
                        }
                    });
                    
                    $q.all(promises).then(function() {
                        resolve(null);
                    }, function() {
                        reject(null);
                    });
                }, function() {
                    reject(null);
                });
            });
        };
    }]);

export default SERVICE_NAME;
