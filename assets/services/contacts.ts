import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ContactsService';

export class ContactsService {
    static $inject = ['$q', '$http'];
    private hostnames: Map<string, string> = new Map<string, string>();
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public deleteMessage(id: number): ng.IPromise<void> {
        var self = this;
        
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<any>) {
            
            self.$http({
                method: 'DELETE',
                url: '/api/message/' + id
            }).then(function() {
                resolve();
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public isInContacts(userId: number): ng.IPromise<boolean> {
        var self = this;
        return this.$q(function(resolve, reject) {
            
            self.$http({
                method: 'GET',
                url: '/api/contacts/' + userId
            }).then(function(response: ng.IHttpResponse<any>) {
                resolve(true);
            }, function(response: ng.IHttpResponse<any>) {
                if (response.status == 404) {
                    resolve(false);
                } else {
                  reject(response);
                }
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, ContactsService);
