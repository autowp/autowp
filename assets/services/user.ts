import * as angular from "angular";
import Module from 'app.module';
import notify from 'notify';

const SERVICE_NAME = 'UserService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var service = this;
      
        let cache: Map<number, any> = new Map<number, any>();
        let promises: Map<number, ng.IPromise<any>> = new Map<number, ng.IPromise<any>>();
       
        this.getUser = function(id: number): ng.IPromise<any> {
            
            if (promises.has(id)) {
                return promises.get(id);
            }
            
            var promise = $q(function(resolve: ng.IQResolveReject<string>, reject: ng.IQResolveReject<void>) {
                
                if (cache.has(id)) {
                    resolve(cache.get(id));
                    return;
                }
                
                $http({
                    url: '/api/user/' + id,
                    method: 'GET'
                }).then(function(response: ng.IHttpResponse<any>) {
                    cache.set(id, response.data);
                    
                    resolve(cache.get(id));
                    
                    promises.delete(id);
                    
                }, function(response) {
                    notify.response(response);
                    reject();
                });
            });
            
            promises.set(id, promise);
            
            return promise;
        };
    }]);

export default SERVICE_NAME;
