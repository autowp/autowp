import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PageService';

interface pageCallbackType { (): void }

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        let pages: Map<number, any> = new Map<number, any>();
        var current: number;
        var args: any = {};
        var promises: Map<number, any> = new Map<number, any>();
      
        let handlers: { [key: string]: pageCallbackType[] } = {
            currentChanged: []
        };
        
        this.setCurrent = function(id: number, newArgs: any) {
            if (current != id || !angular.equals(args, newArgs)) {
                current = id;
                args = newArgs;
                this.trigger('currentChanged');
            }
        };
        
        this.getCurrent = function(): number {
            return current;
        };
        
        this.getCurrentArgs = function(): any {
            return args;
        };
        
        this.isActive = function(id: number): ng.IPromise<boolean> {
            return this.isDescendant(current, id);
        };
        
        function isDescendant(id: number, parentId: number): boolean {
            var pageId: number = id;
            while(pageId) {
                if (pages.get(pageId).parent_id == parentId) {
                    return true;
                }
                
                pageId = pages.get(pageId).parent_id;
            }
            
            return false;
        }
        
        function loadTree(id: number): ng.IPromise<void> {
            if (promises.has(id)) {
                return promises.get(id);
            }
          
            let promise = $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
                $http({
                    method: 'GET',
                    url: '/api/page/parents',
                    params: {
                        id: id
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    
                    angular.forEach(response.data.items, function(page) {
                        pages.set(page.id, page);
                    });
                    
                    resolve();
                }, function(response: ng.IHttpResponse<any>) {
                    reject(response);
                });
            });
            
            promises.set(id, promise);
            
            return promise;
        }
        
        this.isDescendant = function(id: number, parentId: number): ng.IPromise<boolean> {
            
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
                loadTree(id).then(function() {
                    
                    if (id == parentId) {
                        resolve(true);
                        return;
                    }
                    
                    var result = isDescendant(id, parentId);
                    resolve(result);
                }, function(response: ng.IHttpResponse<any>) {
                    reject(response);
                });
            });
        };
        
        this.getPath = function(id: number) {
            return $q(function(resolve, reject) {
                loadTree(id).then(function() {
                    
                    var pageId = id;
                    var result = [];
                    while(pageId) {
                        if (! pages.has(pageId)) {
                            throw "Page " + pageId + " not found";
                        }
                        
                        result.push(pages.get(pageId));
                        
                        pageId = pages.get(pageId).parent_id;
                    }
                    
                    resolve(result.reverse());
                }, function(response) {
                    reject(response);
                });
            });
        };
        
        this.bind = function(event: string, handler: pageCallbackType) {
            handlers[event].push(handler);
        };
        
        this.unbind = function(event: string, handler: pageCallbackType) {
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
