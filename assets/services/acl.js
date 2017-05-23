import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'AclService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var cache = {};
        var isAllowedCache = {};
        
        this.inheritsRole = function(role, rejectError) {
            return $q(function(resolve, reject) {
                if (!cache.hasOwnProperty(role)) {
                    $http({
                        method: 'GET',
                        url: '/api/acl/inherit-roles',
                        params: {
                            roles: role
                        }
                    }).then(function(response) {
                        cache[role] = response.data[role];
                        if (cache[role]) {
                            resolve(true);
                        } else {
                            reject(rejectError);
                        }
                    }, function() {
                        cache[role] = false;
                        reject(rejectError);
                    });
                } else {
                    if (cache[role]) {
                        resolve(true);
                    } else {
                        reject(rejectError);
                    }
                }
            });
        };
        
        this.isAllowed = function(resource, privilege, rejectError) {
            return $q(function(resolve, reject) {
                
                var hasCache = isAllowedCache.hasOwnProperty(resource) && isAllowedCache[resource].hasOwnProperty(privilege);
                
                if (! hasCache) {
                    $http({
                        method: 'GET',
                        url: '/api/acl/is-allowed',
                        params: {
                            resource: resource,
                            privilege: privilege
                        }
                    }).then(function(response) {
                        if (! isAllowedCache.hasOwnProperty(resource)) {
                            isAllowedCache[resource] = {};
                        }
                        isAllowedCache[resource][privilege] = response.data.result;
                        if (isAllowedCache[resource][privilege]) {
                            resolve(true);
                        } else {
                            reject(rejectError);
                        }
                    }, function() {
                        cache[role] = false;
                        reject(rejectError);
                    });
                } else {
                    if (isAllowedCache[resource][privilege]) {
                        resolve(true);
                    } else {
                        reject(rejectError);
                    }
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
