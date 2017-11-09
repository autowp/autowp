import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'IpService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        let hostnames: Map<string, string> = new Map<string, string>();
        
        this.getHostByAddr = function(ip: string): ng.IPromise<string> {
            return $q(function(resolve: ng.IQResolveReject<string>, reject: ng.IQResolveReject<any>) {
              
                if (hostnames.has(ip)) {
                    resolve(hostnames.get(ip));
                    return;
                }
              
                $http({
                    method: 'GET',
                    url: '/api/ip/' + ip,
                    params: {
                        fields: 'hostname'
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    hostnames.set(ip, response.data.hostname);
                    resolve(response.data.hostname);
                }, function(response: any) {
                    reject(response);
                });
            });
        };
        
    }]);

export default SERVICE_NAME;
