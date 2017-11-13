import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'PictureModerVoteService';

export class PictureModerVoteService {
    static $inject = ['$q', '$http'];
  
    constructor(
        private $q: ng.IQService,
        private $http: ng.IHttpService
    ){}
  
    public vote(pictureId: number, vote: number, reason: string): ng.IPromise<void> {
        var self = this;
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
            self.$http({
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
    
    public cancel(pictureId: number): ng.IPromise<void> {
        var self = this; 
        return this.$q(function(resolve: ng.IQResolveReject<void>, reject: ng.IQResolveReject<void>) {
            self.$http({
                method: 'DELETE',
                url: '/api/picture-moder-vote/' + pictureId
            }).then(function() {
                resolve();
            }, function() {
                reject();
            });
        });
    };
};

angular.module(Module).service(SERVICE_NAME, PictureModerVoteService);

