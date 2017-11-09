import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PictureModerVoteService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q: ng.IQService, $http: ng.IHttpService) {
                
        this.vote = function(pictureId: number, vote: number, reason: string): ng.IPromise<void> {
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
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
            });
        };
        
        this.cancel = function(pictureId: number): ng.IPromise<void> {
            return $q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
                $http({
                    method: 'DELETE',
                    url: '/api/picture-moder-vote/' + pictureId
                }).then(function() {
                    resolve();
                }, function() {
                    reject();
                });
            });
        };
    }]);

export default SERVICE_NAME;
