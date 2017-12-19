import * as angular from "angular";
import Module from 'app.module';
import notify from 'notify';

const SERVICE_NAME = 'UserService';

export class UserService {
    static $inject = ['$q', '$http'];
    private cache: Map<number, autowp.IUser> = new Map<number, autowp.IUser>();
    private promises: Map<number, ng.IPromise<any>> = new Map<number, ng.IPromise<any>>();
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
    
    private queryUsers(ids: number[]): ng.IPromise<any>
    {
        let toRequest: number[] = [];
        let waitFor: ng.IPromise<any>[] = [];
        for (let id of ids) {
            let oldUser = this.cache.get(id);
            if (oldUser !== undefined) {
                continue;
            }
            let oldPromise = this.promises.get(id);
            if (oldPromise !== undefined) {
                waitFor.push(oldPromise);
                continue;
            }
            toRequest.push(id);
        }
        
        var self = this;
    
        if (toRequest.length > 0) {
            let promise = this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
    
                self.$http({
                    url: '/api/user',
                    method: 'GET',
                    params: {
                        'id[]': toRequest,
                        limit: toRequest.length
                    }
                }).then(function(response: ng.IHttpResponse<autowp.GetUsersResult>) {
                    for (let item of response.data.items) {
                        self.cache.set(item.id, item);
                    }
                    resolve();
                }, function(response) {
                    notify.response(response);
                    reject();
                });
            });
            
            waitFor.push(promise);
            
            for (let id of toRequest) {
                self.promises.set(id, promise);
            }
        }
        
        return self.$q.all(waitFor);
    }
    
    public getUsers(ids: number[]): ng.IPromise<autowp.IUser[]> {
        let self = this;
        return this.$q(function(resolve: ng.IQResolveReject<autowp.IUser[]>, reject: ng.IQResolveReject<void>) {
            self.queryUsers(ids).then(function() {
                let result: autowp.IUser[] = [];
                for (let id of ids) {
                    let user = self.cache.get(id);
                    if (user === undefined) {
                        reject();
                        return;
                    }
                    result.push(user);
                }
                resolve(result);
            }, function() {
                reject();
            });
        });
    }
    
    public getUserMap(ids: number[]): ng.IPromise<Map<number, autowp.IUser>> {
       
        var self = this;

        return this.$q(function(resolve: ng.IQResolveReject<Map<number, autowp.IUser>>, reject: ng.IQResolveReject<string>) {
            self.queryUsers(ids).then(function() {
                let result: Map<number, autowp.IUser> = new Map<number, autowp.IUser>();
                for (let id of ids) {
                    let user = self.cache.get(id);
                    if (user === undefined) {
                        reject("Failed to query user " + id);
                        return;
                    }
                    result.set(id, user);
                }
                resolve(result);
            }, function() {
                reject("Failed to query users " + ids.join(", "));
            });
        });
    }
  
    public getUser(id: number): ng.IPromise<autowp.IUser> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<autowp.IUser>, reject: ng.IQResolveReject<void>) {
            self.getUsers([id]).then(function(users: autowp.IUser[]) {
                if (users.length > 0) {
                    resolve(users[0]);
                    return;
                }
                reject();
            }, function() {
                reject();
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, UserService);

