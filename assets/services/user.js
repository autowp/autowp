import angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const SERVICE_NAME = 'UserService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var service = this;
        var cache = {};
        var promises = {};
       
        this.getUser = function(id) {
            
            if (promises[id]) {
                return promises[id];
            }
            
            var promise = $q(function(resolve, reject) {
                
                if (cache[id]) {
                    resolve(cache[id]);
                    return;
                }
                
                $http({
                    url: '/api/user/' + id,
                    method: 'GET'
                }).then(function(response) {
                    
                    cache[id] = response.data;
                    
                    resolve(cache[id]);
                    
                    delete promises[id];
                    
                }, function(response) {
                    notify.response(response);
                    reject(null);
                });
            });
            
            promises[id] = promise;
            
            return promise;
        };
    }]);

export default SERVICE_NAME;
