import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ItemService';

export class ItemService {
    static $inject = ['$q', '$http', '$translate'];
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService,
        private $translate: any
    ){}
  
    public setItemVehicleTypes(itemId: number, ids: number[]) {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<any>) {
            
            self.$http.get('/api/item-vehicle-type?item_id=' + itemId).then(function(response: ng.IHttpResponse<any>) {
                
                var promises: ng.IPromise<any>[] = [];
                
                var currentIds: number[] = [];
                for (let itemVehicleType of response.data.items) {
                    currentIds.push(itemVehicleType.vehicle_type_id);
                    if (! ids.includes(itemVehicleType.vehicle_type_id)) {
                        promises.push(
                            self.$http({
                                method: 'DELETE',
                                url: '/api/item-vehicle-type/' + itemId + '/' + itemVehicleType.vehicle_type_id
                            })
                        );
                    }
                }

                for (let vehicleTypeId of ids) {
                    if (! currentIds.includes(vehicleTypeId)) {
                        promises.push(self.$http.post('/api/item-vehicle-type/' + itemId + '/' + vehicleTypeId, {}));
                    }
                }
                
                self.$q.all(promises).then(function() {
                    resolve();
                }, function() {
                    reject();
                });
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, ItemService);

