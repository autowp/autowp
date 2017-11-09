import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PictureModerVoteTemplateService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var templates: any[] = null;
        
        this.getTemplates = function(): ng.IPromise<any> {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                if (templates === null) {
                    $http({
                        method: 'GET',
                        url: '/api/picture-moder-vote-template'
                    }).then(function(response: ng.IHttpResponse<any>) {
                        templates = response.data.items;
                        resolve(templates);
                    }, function() {
                        reject();
                    });
                } else {
                    resolve(templates);
                }
            });
        };
        
        this.deleteTemplate = function(id: number): ng.IPromise<void> {
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
                $http({
                    method: 'DELETE',
                    url: '/api/picture-moder-vote-template/' + id
                }).then(function() {
                    if (templates) {
                        for (var i=0; i<templates.length; i++) {
                            if (templates[i].id == id) {
                                templates.splice(i, 1);
                                break;
                            }
                        }
                    }
                    resolve();
                }, function() {
                    reject();
                });
            });
        };
        
        this.createTemplate = function(template: any): ng.IPromise<any> {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                $http({
                    method: 'POST',
                    url: '/api/picture-moder-vote-template',
                    data: template
                }).then(function(response) {
                    var location = response.headers('Location');
                    
                    $http({
                        method: 'GET',
                        url: location
                    }).then(function(response: ng.IHttpResponse<any>) {
                        templates.push(response.data);
                        resolve(response.data);
                    }, function() {
                        reject(null);
                    });
                }, function() {
                    reject();
                });
            });
        };
    }]);

export default SERVICE_NAME;
