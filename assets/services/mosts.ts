import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'MostsService';

export class MostsService {
    static $inject = ['$q', '$http'];
    private data: any = null;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}

    public getMenu(): ng.IPromise<any> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
      
            if (self.data) {
                resolve(self.data);
                return;
            }
      
            self.$http({
                method: 'GET',
                url: '/api/mosts/menu'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.data = response.data;
                
                resolve(self.data);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, MostsService);

