import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ContactsService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {

        this.deleteMessage = function(id: number): ng.IPromise<void> {
            var self = this;
            
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<any>) {
                
                $http({
                    method: 'DELETE',
                    url: '/api/message/' + id
                }).then(function() {
                    
                    self.trigger('deleted');
                    
                    resolve();
                }, function(response: any) {
                    reject(response);
                });
            });
        };
        
        this.isInContacts = function(userId: number): ng.IPromise<boolean> {
            return $q(function(resolve, reject) {
                
                $http({
                    method: 'GET',
                    url: '/api/contacts/' + userId
                }).then(function(response: ng.IHttpResponse<any>) {
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
