import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'MessageService';

interface messageCallbackType { (): void }

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var perspectives = null;
        var promise = null;
      
        let handlers: { [key: string]: messageCallbackType[] } = {
            sent: [],
            deleted: []
        };
        
        this.clearFolder = function(folder: string): ng.IPromise<void> {
            var self = this;
            
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
                
                $http({
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
        
        this.deleteMessage = function(id: number): ng.IPromise<void> {
            var self = this;
            
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
                
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
        
        this.getSummary = function(): ng.IPromise<any> {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
                
                $http({
                    method: 'GET',
                    url: '/api/message/summary'
                }).then(function(response: ng.IHttpResponse<any>) {
                    resolve(response.data);
                }, function(response) {
                    reject(response);
                });
            });
        };
        
        this.getNewCount = function(): ng.IPromise<number> {
            return $q(function(resolve: ng.IQResolveReject<number>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
                
                $http({
                    method: 'GET',
                    url: '/api/message/new'
                }).then(function(response: ng.IHttpResponse<any>) {
                    resolve(response.data.count);
                }, function(response) {
                    reject(response);
                });
            });
        };
        
        this.send = function(userId: number, text: string): ng.IPromise<any> {
            var self = this;
            
            return $http({
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
        
        this.bind = function(event: string, handler: messageCallbackType) {
            handlers[event].push(handler);
        };
        
        this.unbind = function(event: string, handler: messageCallbackType) {
            var index = handlers[event].indexOf(handler);
            if (index !== -1) {
                handlers[event].splice(index, 1);
            }
        };
        
        this.trigger = function(event: string) {
            angular.forEach(handlers[event], function(handler) {
                handler();
            });
        };
    }]);

export default SERVICE_NAME;
