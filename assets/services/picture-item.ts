import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PictureItemService';

export class PictureItemService {
    static $inject = ['$http'];
  
    constructor(
        private $http: ng.IHttpService
    ){}
  
    public setPerspective(pictureId: number, itemId: number, type: number, perspectiveId: number): ng.IPromise<any> {
        return this.$http({
            method: 'PUT',
            url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
            data: {
                perspective_id: perspectiveId
            }
        });
    };
    
    public setArea(pictureId: number, itemId: number, type: number, area: any): ng.IPromise<any> {
        return this.$http({
            method: 'PUT',
            url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
            data: {
                area: area
            }
        });
    };
    
    public create(pictureId: number, itemId: number, type: number, data: any): ng.IPromise<any> {
        return this.$http({
            method: 'POST',
            url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
            data: data
        });
    };
    
    public remove(pictureId: number, itemId: number, type: number): ng.IPromise<any> {
        return this.$http({
            method: 'DELETE',
            url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type
        });
    };
    
    public changeItem(pictureId: number, type: number, srcItemId: number, dstItemId: number): ng.IPromise<any> {
        return this.$http({
            method: 'PUT',
            url: '/api/picture-item/' + pictureId + '/' + srcItemId + '/' + type,
            data: {
                item_id: dstItemId
            }
        });
    };
    
    public get(pictureId: number, itemId: number, type: number, params: any): ng.IPromise<any> {
        return this.$http({
            method: 'GET',
            url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
            params: params
        });
    };
};

angular.module(Module).service(SERVICE_NAME, PictureItemService);

