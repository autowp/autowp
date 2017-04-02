import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'PictureModerVoteService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var templates = null;
        
        this.vote = function(pictureId, vote, reason) {
            return $q(function(resolve, reject) {
                if (templates === null) {
                    $http({
                        method: 'PUT',
                        url: '/api/picture-moder-vote/' + pictureId,
                        data: {
                            vote: vote,
                            reason: reason
                        }
                    }).then(function() {
                        resolve();
                    }, function() {
                        reject();
                    });
                } else {
                    resolve();
                }
            });
        };
    }]);

export default SERVICE_NAME;
