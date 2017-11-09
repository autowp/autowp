import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'VehicleTypeService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', '$translate', function($q: ng.IQService, $http: ng.IHttpService, $translate: any) {
        
        var types: any[] = null;
        var service = this;
        
        function collectNames(types: any[]): string[] {
            var result: string[] = [];
            walkTypes(types, function(type: any) {
                result.push(type.name);
            });
            return result;
        }
        
        function applyTranslations(types: any[], translations: any) {
            walkTypes(types, function(type: any) {
                type.nameTranslated = translations[type.name];
            });
        }
        
        function walkTypes(types: any[], callback: (type: any) => void) {
            angular.forEach(types, function(type: any) {
                callback(type);
                walkTypes(type.childs, callback);
            });
        }
        
        this.getTypes = function() {
            return $q(function(resolve, reject) {
                if (types === null) {
                    $http({
                        method: 'GET',
                        url: '/api/vehicle-types'
                    }).then(function(response: ng.IHttpResponse<any>) {
                        types = response.data.items;
                        var names = collectNames(types);
                        
                        $translate(names).then(function (translations: any) {
                            applyTranslations(types, translations);
                            resolve(types);
                        }, function () {
                            reject(null);
                        });
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(types);
                }
            });
        };
        
        this.getTypesById = function(ids: number[]) {
            return $q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
                
                if (ids.length <= 0) {
                    resolve([]);
                    return;
                }
                
                service.getTypes().then(function(types: any[]) {
                    var result: any[] = [];
                    walkTypes(types, function(type) {
                        if (ids.includes(type.id)) {
                            result.push(type);
                        }
                    });
                    resolve(result);
                }, function() {
                    reject();
                });
            });
        };
    }]);

export default SERVICE_NAME;
