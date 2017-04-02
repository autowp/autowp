import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PictureItemService';

angular.module(Module)
    .service(SERVICE_NAME, ['$http', function($http) {
        
        var perspectives = null;
        
        this.setPerspective = function(pictureId, itemId, perspectiveId) {
            return $http({
                method: 'POST',
                url: '/api/picture-item/' + pictureId + '/' + itemId,
                data: {
                    perspective_id: perspectiveId
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
