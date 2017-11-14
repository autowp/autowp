import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ForumService';

const LIMIT = 20;

export class ForumService {
    static $inject = ['$q', '$http'];
    private promise: ng.IPromise<any> | null = null;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getUserSummary() {
        if (this.promise) {
            return this.promise;
        }
      
        var self = this;
        this.promise = this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<any>) {
            
            self.$http({
                method: 'GET',
                url: '/api/forum/user-summary'
            }).then(function(response: ng.IHttpResponse<any>) {
                resolve(response.data);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
        
        return this.promise;
    };
    
    public getLimit(): number {
        return LIMIT;
    };
    
    public getMessageStateParams(message_id: number) {
        
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<any>) {
        
            self.$http({
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
                
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
            
        });
    };
};

angular.module(Module).service(SERVICE_NAME, ForumService);

