import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PerspectiveService';

export class PerspectiveService {
    static $inject = ['$q', '$http'];
    private promise: ng.IPromise<any> | null = null;
    private perspectives: any[];
    private perspectivesInitialized: boolean = false;
  
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
            if (self.perspectivesInitialized) {
                resolve(self.perspectives);
                return;
            }
          
            self.$http({
                method: 'GET',
                url: '/go-api/perspective'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.perspectives = response.data.items;
                self.perspectivesInitialized = true;
                resolve(self.perspectives);
                self.promise = null;
            }, function() {
                reject();
                self.promise = null;
            });
        });
        
        return this.promise;
    };
};

angular.module(Module).service(SERVICE_NAME, PerspectiveService);
