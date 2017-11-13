import * as angular from "angular";
import Module from 'app.module';
import notify from 'notify';

const SERVICE_NAME = 'UserService';

export class UserService {
    static $inject = ['$q', '$http'];
    private cache: Map<number, any> = new Map<number, any>();
    private promises: Map<number, ng.IPromise<any>> = new Map<number, ng.IPromise<any>>();
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getUser(id: number): ng.IPromise<any> {
        
        if (this.promises.has(id)) {
            return this.promises.get(id);
        }
      
        var self = this;
        
        var promise = this.$q(function(resolve: ng.IQResolveReject<string>, reject: ng.IQResolveReject<void>) {
            
            if (self.cache.has(id)) {
                resolve(self.cache.get(id));
                return;
            }
            
            self.$http({
                url: '/api/user/' + id,
                method: 'GET'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.cache.set(id, response.data);
                
                resolve(self.cache.get(id));
                
                self.promises.delete(id);
                
            }, function(response) {
                notify.response(response);
                reject();
            });
        });
        
        this.promises.set(id, promise);
        
        return promise;
    };
};

angular.module(Module).service(SERVICE_NAME, UserService);

