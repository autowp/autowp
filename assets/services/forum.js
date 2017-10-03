import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'ForumService';

const LIMIT = 20;

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
        
        this.getLimit = function() {
            return LIMIT;
        };
        
        this.getMessageStateParams = function(message_id) {
            
            return $q(function(resolve, reject) {
            
                $http({
                    method: 'GET',
                    url: '/api/comment/' + message_id,
                    params: {
                        fields: 'page',
                        limit: LIMIT
                    }
                }).then(function(response) {
                    
                    resolve({
                        topic_id: response.data.item_id,
                        page:     response.data.page
                    });
                    
                }, function(response) {
                    reject(response);
                });
                
            });
        };
    }]);

export default SERVICE_NAME;
