import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'SpecService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
        
        var types: any[] = null;
        
        var service = this;
        
        this.getSpecs = function(): ng.IPromise<any> {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                if (types === null) {
                    $http({
                        method: 'GET',
                        url: '/go-api/spec'
                    }).then(function(response: ng.IHttpResponse<any>) {
                        types = response.data.items;
                        resolve(types);
                    }, function() {
                        reject();
                    });
                } else {
                    resolve(types);
                }
            });
        };
        
        this.getSpec = function(id: number): ng.IPromise<any> {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                service.getSpecs().then(function(types: any[]) {
                    var spec = findSpec(types, id);
                    if (spec) {
                        resolve(spec);
                    } else {
                        reject(null);
                    }
                }, reject);
            });
        };
        
        function findSpec(specs: any[], id: number): any {
            var spec = null;
            for (var i=0; i<specs.length; i++) {
                if (specs[i].id == id) {
                    spec = specs[i];
                    break;
                }
                spec = findSpec(specs[i].childs, id);
                if (spec) {
                    break;
                }
            }
            return spec;
        }
    }]);

export default SERVICE_NAME;
