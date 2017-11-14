import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'AclService';

export class AclService {
    static $inject = ['$q', '$http'];
    private isAllowedCache: Map<string, boolean> = new Map<string, boolean>();
    private isAllowedPromises: Map<string, ng.IPromise<boolean>> = new Map<string, ng.IPromise<boolean>>();
    private inheritsRoleCache: Map<string, boolean> = new Map<string, boolean>();
    private inheritsRolePromises: Map<string, ng.IPromise<boolean>> = new Map<string, ng.IPromise<boolean>>();
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public inheritsRole(role: string, rejectError?: any): ng.IPromise<boolean> {
        var self = this;
      
        var promise = this.isAllowedPromises.get(role);
        if (promise) {
            return promise;
        }
      
        promise = this.$q(function(resolve: ng.IQResolveReject<boolean>, reject: ng.IQResolveReject<any>) {
          
            if (self.inheritsRoleCache.has(role)) {
                if (self.inheritsRoleCache.get(role)) {
                    resolve(true);
                } else {
                    reject(rejectError);
                }
                return;
            }
          
            self.$http({
                method: 'GET',
                url: '/api/acl/inherit-roles',
                params: {
                    roles: role
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                let value = response.data[role];
                self.inheritsRoleCache.set(role, value);
                self.isAllowedPromises.delete(role);
                if (value) {
                    resolve(true);
                } else {
                    reject(rejectError);
                }
            }, function() {
                self.inheritsRoleCache.set(role, false);
                self.isAllowedPromises.delete(role);
                reject(rejectError);
            });
        });
      
        this.isAllowedPromises.set(role, promise);
      
        return promise;
    };
    
    public isAllowed(resource: string, privilege: string, rejectError?: any): ng.IPromise<boolean> {
        var self = this;
      
        var key = resource + '.' + privilege;
      
        var promise = this.inheritsRolePromises.get(key);
        if (promise) {
            return promise;
        }
      
        promise = this.$q(function(resolve: ng.IQResolveReject<boolean>, reject: ng.IQResolveReject<any>) {
            
            if (self.isAllowedCache.has(key)) {
                if (self.isAllowedCache.get(key)) {
                    resolve(true);
                } else {
                    reject(rejectError);
                }
                return;
            }

            self.$http({
                method: 'GET',
                url: '/api/acl/is-allowed',
                params: {
                    resource: resource,
                    privilege: privilege
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                
                self.isAllowedCache.set(key, response.data.result);
                self.isAllowedPromises.delete(key);
                if (response.data.result) {
                    resolve(true);
                } else {
                    reject(rejectError);
                }
            }, function() {
                self.isAllowedCache.set(key, false);
                self.isAllowedPromises.delete(key);
                resolve(false);
            });
           
        });
      
        this.inheritsRolePromises.set(key, promise);
      
        return promise;
    };
    
};

angular.module(Module).service(SERVICE_NAME, AclService);

