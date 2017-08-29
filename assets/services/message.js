import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'MessageService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var perspectives = null;
        var promise = null;
        var handlers = {
            sent: [],
            deleted: []
        };
        
        this.clearFolder = function(folder) {
            var self = this;
            
            return $q(function(resolve, reject) {
                
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
        
        this.getSummary = function() {
            return $q(function(resolve, reject) {
                
                $http({
                    method: 'GET',
                    url: '/api/message/summary'
                }).then(function(response) {
                    resolve(response.data);
                }, function(response) {
                    reject(response);
                });
            });
        };
        
        this.send = function(userId, text) {
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
        
        this.bind = function(event, handler) {
            handlers[event].push(handler);
        };
        
        this.unbind = function(event, handler) {
            var index = handlers[event].indexOf(handler);
            if (index !== -1) {
                handlers[event].splice(index, 1);
            }
        };
        
        this.trigger = function(event) {
            angular.forEach(handlers[event], function(handler) {
                handler();
            });
        };
    }]);

export default SERVICE_NAME;
