import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PerspectiveService';

export class PerspectiveService {
    static $inject = ['$q', '$http'];
    private promise: ng.IPromise<any> = null;
    private perspectives: any[] = null;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getPerspectives(): ng.IPromise<any[]> {
        if (this.promise) {
            return this.promise;
        }
      
        var self = this;
      
        this.promise = this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            if (self.perspectives === null) {
                self.$http({
                    method: 'GET',
                    url: '/go-api/perspective'
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.perspectives = response.data.items;
                    resolve(self.perspectives);
                    self.promise = null;
                }, function() {
                    reject(null);
                    self.promise = null;
                });
            } else {
                resolve(self.perspectives);
            }
        });
        
        return this.promise;
    };
};

angular.module(Module).service(SERVICE_NAME, PerspectiveService);
