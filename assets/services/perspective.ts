import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PerspectiveService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var perspectives: any[] = null;
        var promise: ng.IPromise<any> = null;
        
        this.getPerspectives = function(): ng.IPromise<any> {
            if (promise) {
                return promise;
            }
            promise = $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                if (perspectives === null) {
                    $http({
                        method: 'GET',
                        url: '/go-api/perspective'
                    }).then(function(response: ng.IHttpResponse<any>) {
                        perspectives = response.data.items;
                        resolve(perspectives);
                        promise = null;
                    }, function() {
                        reject(null);
                        promise = null;
                    });
                } else {
                    resolve(perspectives);
                }
            });
            
            return promise;
        };
        
    }]);

export default SERVICE_NAME;
