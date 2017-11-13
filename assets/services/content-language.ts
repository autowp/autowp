import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'ContentLanguageService';

export class ContentLanguageService {
    static $inject = ['$q', '$http'];
    private cache: any = null;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getList() {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            if (! self.cache) {
                resolve(self.cache);
              return;
            }

            self.$http({
                method: 'GET',
                url: '/api/content-language'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.cache = response.data.items;
                resolve(self.cache);
            }, function() {
                reject();
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, ContentLanguageService);
