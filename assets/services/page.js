import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PageService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var pages = {};
        var current;
        var args = {};
        var promises = {};
        
        var handlers = {
            currentChanged: []
        };
        
        this.setCurrent = function(id, newArgs) {
            if (current != id || !angular.equals(args, newArgs)) {
                current = id;
                args = newArgs;
                this.trigger('currentChanged');
            }
        };
        
        this.getCurrent = function() {
            return current;
        };
        
        this.getCurrentArgs = function() {
            return args;
        };
        
        this.isActive = function(id) {
            return this.isDescendant(current, id);
        };
        
        function isDescendant(id, parentId) {
            var pageId = id;
            while(pageId) {
                if (pages[pageId].parent_id == parentId) {
                    return true;
                }
                
                pageId = pages[pageId].parent_id;
            }
            
            return false;
        }
        
        function loadTree(id) {
            if (promises[id]) {
                return promises[id];
            }
            
            promises[id] = $q(function(resolve, reject) {
                $http({
                    method: 'GET',
                    url: '/api/page/parents',
                    params: {
                        id: id
                    }
                }).then(function(response) {
                    
                    angular.forEach(response.data.items, function(page) {
                        pages[page.id] = page;
                    });
                    
                    resolve();
                }, function(response) {
                    reject(response);
                });
            });
            
            return promises[id];
        }
        
        this.isDescendant = function(id, parentId) {
            
            return $q(function(resolve, reject) {
                loadTree(id).then(function() {
                    
                    if (id == parentId) {
                        resolve(true);
                        return;
                    }
                    
                    var result = isDescendant(id, parentId);
                    resolve(result);
                }, function(response) {
                    reject(response);
                });
            });
        };
        
        this.getPath = function(id) {
            return $q(function(resolve, reject) {
                loadTree(id).then(function() {
                    
                    var pageId = id;
                    var result = [];
                    while(pageId) {
                        result.push(pages[pageId]);
                        
                        pageId = pages[pageId].parent_id;
                    }
                    
                    resolve(result.reverse());
                }, function(response) {
                    reject(response);
                });
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
