import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PictureItemService';

angular.module(Module)
    .service(SERVICE_NAME, ['$http', function($http) {
        
        var perspectives = null;
        
        this.setPerspective = function(pictureId, itemId, perspectiveId) {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + itemId,
                data: {
                    perspective_id: perspectiveId
                }
            });
        };
        
        this.setArea = function(pictureId, itemId, area) {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + itemId,
                data: {
                    area: area
                }
            });
        };
        
        this.create = function(pictureId, itemId, data) {
            return $http({
                method: 'POST',
                url: '/api/picture-item/' + pictureId + '/' + itemId,
                data: data
            });
        };
        
        this.remove = function(pictureId, itemId) {
            return $http({
                method: 'DELETE',
                url: '/api/picture-item/' + pictureId + '/' + itemId
            });
        };
        
        this.changeItem = function(pictureId, srcItemId, dstItemId) {
            return $http({
                method: 'PUT',
                url: '/api/picture-item/' + pictureId + '/' + srcItemId,
                data: {
                    item_id: dstItemId
                }
            });
        };
        
        this.get = function(pictureId, itemId, params) {
            return $http({
                method: 'GET',
                url: '/api/picture-item/' + pictureId + '/' + itemId,
                params: params
            });
        };
    }]);

export default SERVICE_NAME;
