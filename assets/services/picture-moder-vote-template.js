import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PictureModerVoteTemplateService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var templates = null;
        
        this.getTemplates = function() {
            return $q(function(resolve, reject) {
                if (templates === null) {
                    $http({
                        method: 'GET',
                        url: '/api/picture-moder-vote-template'
                    }).then(function(response) {
                        templates = response.data.items;
                        resolve(templates);
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(templates);
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
