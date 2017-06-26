import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'ContentLanguageService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var cache = null;
        
        this.getList = function() {
            return $q(function(resolve, reject) {
                if (! cache) {
                    $http({
                        method: 'GET',
                        url: '/api/content-language'
                    }).then(function(response) {
                        cache = response.data.items;
                        resolve(cache);
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(cache);
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
