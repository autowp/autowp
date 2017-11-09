import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PictureItemService';

angular.module(Module)
    .service(SERVICE_NAME, ['$http', function($http: ng.IHttpService) {
        
        this.setPerspective = function(pictureId: number, itemId: number, type: number, perspectiveId: number): ng.IPromise<any> {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                data: {
                    perspective_id: perspectiveId
                }
            });
        };
        
        this.setArea = function(pictureId: number, itemId: number, type: number, area: any): ng.IPromise<any> {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                data: {
                    area: area
                }
            });
        };
        
        this.create = function(pictureId: number, itemId: number, type: number, data: any): ng.IPromise<any> {
            return $http({
                method: 'POST',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                data: data
            });
        };
        
        this.remove = function(pictureId: number, itemId: number, type: number): ng.IPromise<any> {
            return $http({
                method: 'DELETE',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type
            });
        };
        
        this.changeItem = function(pictureId: number, type: number, srcItemId: number, dstItemId: number): ng.IPromise<any> {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + srcItemId + '/' + type,
                data: {
                    item_id: dstItemId
                }
            });
        };
        
        this.get = function(pictureId: number, itemId: number, type: number, params: any): ng.IPromise<any> {
            return $http({
                method: 'GET',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                params: params
            });
        };
    }]);

export default SERVICE_NAME;
