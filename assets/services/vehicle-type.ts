import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'VehicleTypeService';

export class VehicleTypeService {
    static $inject = ['$q', '$http', '$translate'];
    private types: any[] = null;
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService,
        private $translate: any
    ){}
  
    private collectNames(types: any[]): string[] {
        var result: string[] = [];
        this.walkTypes(types, function(type: any) {
            result.push(type.name);
        });
        return result;
    }
    
    private applyTranslations(types: any[], translations: any) {
        this.walkTypes(types, function(type: any) {
            type.nameTranslated = translations[type.name];
        });
    }
    
    private walkTypes(types: any[], callback: (type: any) => void) {
        var self = this;
        angular.forEach(types, function(type: any) {
            callback(type);
            self.walkTypes(type.childs, callback);
        });
    }
    
    public getTypes() {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            if (self.types === null) {
                self.$http({
                    method: 'GET',
                    url: '/api/vehicle-types'
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.types = response.data.items;
                    var names = self.collectNames(self.types);
                    
                    self.$translate(names).then(function (translations: any) {
                        self.applyTranslations(self.types, translations);
                        resolve(self.types);
                    }, function () {
                        reject();
                    });
                }, function() {
                    reject();
                });
            } else {
                resolve(self.types);
            }
        });
    };
    
    public getTypesById(ids: number[]) {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<any>, reject: ng.IQResolveReject<void>) {
            
            if (ids.length <= 0) {
                resolve([]);
                return;
            }
            
            self.getTypes().then(function(types: any[]) {
                var result: any[] = [];
                self.walkTypes(types, function(type) {
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
};

angular.module(Module).service(SERVICE_NAME, VehicleTypeService);

