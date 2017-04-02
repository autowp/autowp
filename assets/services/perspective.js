import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PerspectiveService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var perspectives = null;
        
        this.getPerspectives = function() {
            return $q(function(resolve, reject) {
                if (perspectives === null) {
                    $http({
                        method: 'GET',
                        url: '/api/perspective'
                    }).then(function(response) {
                        perspectives = response.data.items;
                        resolve(perspectives);
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(perspectives);
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
