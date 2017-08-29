import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'ForumService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var perspectives = null;
        var promise = null;

        this.getUserSummary = function() {
            if (promise) {
                return promise;
            }
            promise = $q(function(resolve, reject) {
                
                $http({
                    method: 'GET',
                    url: '/api/forum/user-summary'
                }).then(function(response) {
                    resolve(response.data);
                }, function(response) {
                    reject(response);
                });
            });
            
            return promise;
        };
    }]);

export default SERVICE_NAME;
