import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PageService';

interface pageCallbackType { (): void }

export class PageService {
    static $inject = ['$q', '$http'];
    private pages: Map<number, any> = new Map<number, any>();
    private current: number;
    private args: any = {};
    private promises: Map<number, any> = new Map<number, any>();
  
    private handlers: { [key: string]: pageCallbackType[] } = {
        currentChanged: []
    };
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public setCurrent = function(id: number, newArgs: any) {
        if (this.current != id || !angular.equals(this.args, newArgs)) {
            this.current = id;
            this.args = newArgs;
            this.trigger('currentChanged');
        }
    };
    
    public getCurrent = function(): number {
        return this.current;
    };
    
    public getCurrentArgs = function(): any {
        return this.args;
    };
    
    public isActive = function(id: number): ng.IPromise<boolean> {
        return this.isDescendant(this.current, id);
    };
    
    private isDescendantPrivate(id: number, parentId: number): boolean {
        var pageId: number = id;
        while(pageId) {
            if (this.pages.get(pageId).parent_id == parentId) {
                return true;
            }
            
            pageId = this.pages.get(pageId).parent_id;
        }
        
        return false;
    }
    
    private loadTree(id: number): ng.IPromise<void> {
        if (this.promises.has(id)) {
            return this.promises.get(id);
        }
      
        var self = this;
      
        let promise = this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            self.$http({
                method: 'GET',
                url: '/api/page/parents',
                params: {
                    id: id
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                
                angular.forEach(response.data.items, function(page) {
                    self.pages.set(page.id, page);
                });
                
                resolve();
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
        
        this.promises.set(id, promise);
        
        return promise;
    }
    
    public isDescendant = function(id: number, parentId: number): ng.IPromise<boolean> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            self.loadTree(id).then(function() {
                
                if (id == parentId) {
                    resolve(true);
                    return;
                }
                
                var result = self.isDescendantPrivate(id, parentId);
                resolve(result);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public getPath = function(id: number) {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            self.loadTree(id).then(function() {
                
                var pageId = id;
                var result = [];
                while(pageId) {
                    if (! self.pages.has(pageId)) {
                        throw "Page " + pageId + " not found";
                    }
                    
                    result.push(self.pages.get(pageId));
                    
                    pageId = self.pages.get(pageId).parent_id;
                }
                
                resolve(result.reverse());
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public bind = function(event: string, handler: pageCallbackType) {
        this.handlers[event].push(handler);
    };
    
    public unbind = function(event: string, handler: pageCallbackType) {
        var index = this.handlers[event].indexOf(handler);
        if (index !== -1) {
            this.handlers[event].splice(index, 1);
        }
    };
    
    public trigger = function(event: string) {
        angular.forEach(this.handlers[event], function(handler) {
            handler();
        });
    };
};

angular.module(Module).service(SERVICE_NAME, PageService);
