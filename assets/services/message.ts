import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'MessageService';

interface messageCallbackType { (): void }

export class MessageService {
    static $inject = ['$q', '$http'];
    private perspectives: any = null;
    private promise: ng.IPromise<any[]> = null;
  
    private handlers: { [key: string]: messageCallbackType[] } = {
        sent: [],
        deleted: []
    };
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public clearFolder(folder: string): ng.IPromise<void> {
        var self = this;
        
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            
            self.$http({
                method: 'DELETE',
                url: '/api/message',
                params: {
                    folder: folder
                }
            }).then(function() {
                
                self.trigger('deleted');
                
                resolve();
            }, function(response) {
                reject(response);
            });
        });
    };
    
    public deleteMessage(id: number): ng.IPromise<void> {
        var self = this;
        
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            
            self.$http({
                method: 'DELETE',
                url: '/api/message/' + id
            }).then(function() {
                
                self.trigger('deleted');
                
                resolve();
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public getSummary(): ng.IPromise<any> {
        var self = this;
        
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            
            self.$http({
                method: 'GET',
                url: '/api/message/summary'
            }).then(function(response: ng.IHttpResponse<any>) {
                resolve(response.data);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public getNewCount(): ng.IPromise<number> {
        var self = this;
      
        return this.$q(function(resolve: ng.IQResolveReject<number>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            
            self.$http({
                method: 'GET',
                url: '/api/message/new'
            }).then(function(response: ng.IHttpResponse<any>) {
                resolve(response.data.count);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public send(userId: number, text: string): ng.IPromise<any> {
        var self = this;
        
        return this.$http({
            method: 'POST',
            url: '/api/message',
            data: {
                user_id: userId,
                text: text
            }
        }).then(function() {
            self.trigger('sent');
        });
    };
    
    public bind(event: string, handler: messageCallbackType) {
        this.handlers[event].push(handler);
    };
    
    public unbind(event: string, handler: messageCallbackType) {
        var index = this.handlers[event].indexOf(handler);
        if (index !== -1) {
            this.handlers[event].splice(index, 1);
        }
    };
    
    public trigger(event: string) {
        angular.forEach(this.handlers[event], function(handler) {
            handler();
        });
    };
};

angular.module(Module).service(SERVICE_NAME, MessageService);

