import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'ContactsService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var perspectives = null;
        var promise = null;

        this.deleteMessage = function(id) {
            var self = this;
            
            return $q(function(resolve, reject) {
                
                $http({
                    method: 'DELETE',
                    url: '/api/message/' + id
                }).then(function() {
                    
                    self.trigger('deleted');
                    
                    resolve();
                }, function(response) {
                    reject(response);
                });
            });
        };
        
        this.isInContacts = function(userId) {
            return $q(function(resolve, reject) {
                
                $http({
                    method: 'GET',
                    url: '/api/contacts/' + userId
                }).then(function(response) {
                    resolve(true);
                }, function(response) {
                    if (response.status == 404) {
                        resolve(false);
                    } else {
                    	reject(response);
                    }
                });
            });
        };
    }]);

export default SERVICE_NAME;
