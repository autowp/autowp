import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'SpecService';

export class SpecService {
    static $inject = ['$q', '$http'];
    private types: any[];
    private typesInitialized: boolean = false;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public getSpecs(): ng.IPromise<any> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<ng.IHttpResponse<any>>) {
            if (self.typesInitialized) {
                resolve(self.types);
                return;
            }
            self.$http({
                method: 'GET',
                url: '/api/spec'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.types = response.data.items;
                self.typesInitialized = true;
                resolve(self.types);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    };
    
    public getSpec(id: number): ng.IPromise<any> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            self.getSpecs().then(function(types: any[]) {
                var spec = self.findSpec(types, id);
                if (spec) {
                    resolve(spec);
                } else {
                    reject();
                }
            }, function() {
                reject();
            });
        });
    };
    
    private findSpec(specs: any[], id: number): any {
        var spec = null;
        for (var i=0; i<specs.length; i++) {
            if (specs[i].id == id) {
                spec = specs[i];
                break;
            }
            spec = this.findSpec(specs[i].childs, id);
            if (spec) {
                break;
            }
        }
        return spec;
    }
};

angular.module(Module).service(SERVICE_NAME, SpecService);

