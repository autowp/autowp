import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ContentLanguageService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var cache: any = null;
        
        this.getList = function() {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                if (! cache) {
                    $http({
                        method: 'GET',
                        url: '/api/content-language'
                    }).then(function(response: ng.IHttpResponse<any>) {
                        cache = response.data.items;
                        resolve(cache);
                    }, function() {
                        reject();
                    });
                } else {
                    resolve(cache);
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
