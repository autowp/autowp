import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'IpService';

export class IpService {
    static $inject = ['$q', '$http'];
    private hostnames: Map<string, string> = new Map<string, string>();
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getHostByAddr(ip: string): ng.IPromise<string> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<string>, reject: ng.IQResolveReject<any>) {
          
            var hostname = self.hostnames.get(ip)
            if (hostname !== undefined) {
                resolve(hostname);
                return;
            }
          
            self.$http({
                method: 'GET',
                url: '/api/ip/' + ip,
                params: {
                    fields: 'hostname'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.hostnames.set(ip, response.data.hostname);
                resolve(response.data.hostname);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, IpService);
