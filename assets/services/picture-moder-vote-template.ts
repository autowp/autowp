import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PictureModerVoteTemplateService';

export class PictureModerVoteTemplateService {
    static $inject = ['$q', '$http'];
    private templates: any[];
    private templatesInitialized: boolean = false;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getTemplates(): ng.IPromise<any[]> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            if (self.templatesInitialized) {
                resolve(self.templates);
                return;
            }
          
            self.$http({
                method: 'GET',
                url: '/api/picture-moder-vote-template'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.templates = response.data.items;
                self.templatesInitialized = true;
                resolve(self.templates);
            }, function() {
                reject();
            });
        });
    };
  
    public deleteTemplate(id: number): ng.IPromise<void> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
            self.$http({
                method: 'DELETE',
                url: '/api/picture-moder-vote-template/' + id
            }).then(function() {
                if (self.templates) {
                    for (var i=0; i<self.templates.length; i++) {
                        if (self.templates[i].id == id) {
                            self.templates.splice(i, 1);
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
    
    public createTemplate(template: any): ng.IPromise<any> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            self.$http({
                method: 'POST',
                url: '/api/picture-moder-vote-template',
                data: template
            }).then(function(response) {
                var location = response.headers('Location');
                
                self.$http({
                    method: 'GET',
                    url: location
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.templates.push(response.data);
                    resolve(response.data);
                }, function() {
                    reject();
                });
            }, function() {
                reject();
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, PictureModerVoteTemplateService);

