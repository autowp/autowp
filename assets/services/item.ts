import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ItemService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', '$translate', function($q: ng.IQService, $http: ng.IHttpService, $translate: any) {
        
        this.setItemVehicleTypes = function(itemId: number, ids: number[]) {
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<any>) {
                
                $http.get('/api/item-vehicle-type?item_id=' + itemId).then(function(response: ng.IHttpResponse<any>) {
                    
                    var promises: ng.IPromise<any>[] = [];
                    
                    var currentIds: number[] = [];
                    for (let itemVehicleType of response.data.items) {
                        currentIds.push(itemVehicleType.vehicle_type_id);
                        if (! ids.includes(itemVehicleType.vehicle_type_id)) {
                            promises.push(
                                $http({
                                    method: 'DELETE',
                                    url: '/api/item-vehicle-type/' + itemId + '/' + itemVehicleType.vehicle_type_id
                                })
                            );
                        }
                    }

                    for (let vehicleTypeId of ids) {
                        if (! currentIds.includes(vehicleTypeId)) {
                            promises.push($http.post('/api/item-vehicle-type/' + itemId + '/' + vehicleTypeId, {}));
                        }
                    }
                    
                    $q.all(promises).then(function() {
                        resolve();
                    }, function() {
                        reject();
                    });
                }, function(response) {
                    reject(response);
                });
            });
        };
    }]);

export default SERVICE_NAME;
