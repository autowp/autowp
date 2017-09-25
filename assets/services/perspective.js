import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PerspectiveService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var perspectives = null;
        var promise = null;
        
        this.getPerspectives = function() {
            if (promise) {
                return promise;
            }
            promise = $q(function(resolve, reject) {
                if (perspectives === null) {
                    $http({
                        method: 'GET',
                        url: '/go-api/perspective'
                    }).then(function(response) {
                        perspectives = response.data.items;
                        resolve(perspectives);
                        promise = null;
                    }, function() {
                        reject(null);
                        promise = null;
                    });
                } else {
                    resolve(perspectives);
                }
            });
            
            return promise;
        };
        
    }]);

export default SERVICE_NAME;
