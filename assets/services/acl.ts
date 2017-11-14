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
          
            var isInherits = self.inheritsRoleCache.get(role);
            if (isInherits !== undefined) {
                if (rejectError && !isInherits) {
                    reject(rejectError);
                } else {
                    resolve(isInherits);
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
                let isInherits = response.data[role];
                self.inheritsRoleCache.set(role, isInherits);
                self.isAllowedPromises.delete(role);
                if (rejectError && !isInherits) {
                    reject(rejectError);
                } else {
                    resolve(isInherits);
                }
            }, function(response: ng.IHttpResponse<any>) {
                if (response.status == 403) {
                    self.inheritsRoleCache.set(role, false);
                    self.isAllowedPromises.delete(role);
                    if (rejectError) {
                        reject(rejectError);
                    } else {
                        resolve(false);
                    }
                } else {
                    reject(response);
                }
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
            
            var isAllowed = self.isAllowedCache.get(key);
            if (isAllowed !== undefined) {
                if (rejectError && !isAllowed) {
                    reject(rejectError);
                } else {
                    resolve(isAllowed);
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
              
                let isAllowed = response.data.result;
                
                self.isAllowedCache.set(key, isAllowed);
                self.isAllowedPromises.delete(key);
                if (rejectError && !isAllowed) {
                    reject(rejectError);
                } else {
                    resolve(isAllowed);
                }
            }, function(response: ng.IHttpResponse<any>) {
                self.isAllowedPromises.delete(key);
                if (response.status == 403) {
                    self.isAllowedCache.set(key, false);
                    if (rejectError) {
                        reject(rejectError);
                    } else {
                        resolve(false);
                    }
                } else {
                  reject(response);
                }
            });
           
        });
      
        this.inheritsRolePromises.set(key, promise);
      
        return promise;
    };
    
};

angular.module(Module).service(SERVICE_NAME, AclService);

