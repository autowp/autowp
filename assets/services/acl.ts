import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'AclService';

type StringBooleanMap = Map<string, boolean>;

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
      
        let isAllowedCache: Map<string, StringBooleanMap> = new Map<string, StringBooleanMap>();
        let cache: StringBooleanMap = new Map<string, boolean>();
        
        this.inheritsRole = function(role: string, rejectError: any): ng.IPromise<boolean> {
            return $q(function(resolve: ng.IQResolveReject<boolean>, reject: ng.IQResolveReject<any>) {
              
                if (cache.has(role)) {
                    if (cache.get(role)) {
                        resolve(true);
                    } else {
                        reject(rejectError);
                    }
                    return;
                }
              
                $http({
                    method: 'GET',
                    url: '/api/acl/inherit-roles',
                    params: {
                        roles: role
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    let value = response.data[role];
                    cache.set(role, value);
                    if (value) {
                        resolve(true);
                    } else {
                        reject(rejectError);
                    }
                }, function() {
                    cache.set(role, false);
                    reject(rejectError);
                });
            });
        };
        
        this.isAllowed = function(resource: string, privilege: string, rejectError: any): ng.IPromise<boolean> {
            return $q(function(resolve: ng.IQResolveReject<boolean>, reject: ng.IQResolveReject<any>) {
                
                var hasCache = isAllowedCache.has(resource) && isAllowedCache.get(resource).has(privilege);
              
                if (hasCache) {
                    if (isAllowedCache.get(resource).get(privilege)) {
                        resolve(true);
                    } else {
                        reject(rejectError);
                    }
                    return;
                }
              
                if (! isAllowedCache.has(resource)) {
                    isAllowedCache.set(resource, new Map<string, boolean>());
                }
                
                $http({
                    method: 'GET',
                    url: '/api/acl/is-allowed',
                    params: {
                        resource: resource,
                        privilege: privilege
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    isAllowedCache.get(resource).set(privilege, response.data.result);
                    if (response.data.result) {
                        resolve(true);
                    } else {
                        reject(rejectError);
                    }
                }, function() {
                    isAllowedCache.get(resource).set(privilege, false);
                    resolve(false);
                });
               
            });
        };
        
    }]);

export default SERVICE_NAME;
