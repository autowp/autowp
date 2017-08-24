import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PictureItemService';

angular.module(Module)
    .service(SERVICE_NAME, ['$http', function($http) {
        
        var perspectives = null;
        
        this.setPerspective = function(pictureId, itemId, type, perspectiveId) {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                data: {
                    perspective_id: perspectiveId
                }
            });
        };
        
        this.setArea = function(pictureId, itemId, type, area) {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                data: {
                    area: area
                }
            });
        };
        
        this.create = function(pictureId, itemId, type, data) {
            return $http({
                method: 'POST',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                data: data
            });
        };
        
        this.remove = function(pictureId, itemId, type) {
            return $http({
                method: 'DELETE',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type
            });
        };
        
        this.changeItem = function(pictureId, type, srcItemId, dstItemId) {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + srcItemId + '/' + type,
                data: {
                    item_id: dstItemId
                }
            });
        };
        
        this.get = function(pictureId, itemId, type, params) {
            return $http({
                method: 'GET',
                url: '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
                params: params
            });
        };
    }]);

export default SERVICE_NAME;
