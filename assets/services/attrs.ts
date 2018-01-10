import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'AttrsService';

export class AttrsService {
    static $inject = ['$q', '$http', '$translate'];
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService,
        private $translate: ng.translate.ITranslateService
    ){}

    public getZone(id: number): ng.IPromise<autowp.IAttrZone>
    {
        let self = this;

        return this.$q(function(resolve: ng.IQResolveReject<autowp.IAttrZone>, reject: ng.IQResolveReject<any>) {
        
            self.getZones().then(function(zones: autowp.IAttrZone[]) {
                for (let zone of zones) {
                    if (zone.id == id) {
                        resolve(zone);
                        return;
                    }
                }
                reject();
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    }
    
    public getZones(): ng.IPromise<autowp.IAttrZone[]>
    {
        let self = this;
        return this.$q(function(resolve: ng.IQResolveReject<autowp.IAttrZone[]>, reject: ng.IQResolveReject<any>) {
        
            self.$http({
                method: 'GET',
                url: '/api/attr/zone'
            }).then(function(response: ng.IHttpResponse<autowp.GetZonesResult>) {
                resolve(response.data.items);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    }
    
    public getAttribute(id: number): ng.IPromise<autowp.IAttrAttribute>
    {
        let self = this;
        return this.$q(function(resolve: ng.IQResolveReject<autowp.IAttrAttribute>, reject: ng.IQResolveReject<any>) {
            
            self.$http({
                method: 'GET',
                url: '/api/attr/attribute/' + id
            }).then(function(response: ng.IHttpResponse<autowp.IAttrAttribute>) {
                resolve(response.data);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    }
    
    public getAttributeTypes(): ng.IPromise<autowp.IAttrAttributeType[]>
    {
        let self = this;
        return this.$q(function(resolve: ng.IQResolveReject<autowp.IAttrAttributeType[]>, reject: ng.IQResolveReject<any>) {
        
            self.$http({
                method: 'GET',
                url: '/api/attr/attribute-type'
            }).then(function(response: ng.IHttpResponse<autowp.GetAttributeTypesResult>) {
                resolve(response.data.items);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    }
    
    public getUnits(): ng.IPromise<autowp.IAttrUnit[]>
    {
        let self = this;
        return this.$q(function(resolve: ng.IQResolveReject<autowp.IAttrUnit[]>, reject: ng.IQResolveReject<any>) {
        
            self.$http({
                method: 'GET',
                url: '/api/attr/unit'
            }).then(function(response: ng.IHttpResponse<autowp.GetUnitResult>) {
                resolve(response.data.items);
            }, function(response: ng.IHttpResponse<any>) {
                reject(response);
            });
        });
    }
};

angular.module(Module).service(SERVICE_NAME, AttrsService);

