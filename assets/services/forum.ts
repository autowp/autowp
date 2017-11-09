import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ForumService';

const LIMIT = 20;

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var perspectives = null;
        var promise: ng.IPromise<any> = null;

        this.getUserSummary = function() {
            if (promise) {
                return promise;
            }
            promise = $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<any>) {
                
                $http({
                    method: 'GET',
                    url: '/api/forum/user-summary'
                }).then(function(response: ng.IHttpResponse<any>) {
                    resolve(response.data);
                }, function(response) {
                    reject(response);
                });
            });
            
            return promise;
        };
        
        this.getLimit = function(): number {
            return LIMIT;
        };
        
        this.getMessageStateParams = function(message_id: number) {
            
            return $q(function(resolve, reject) {
            
                $http({
                    method: 'GET',
                    url: '/api/comment/' + message_id,
                    params: {
                        fields: 'page',
                        limit: LIMIT
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    
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
