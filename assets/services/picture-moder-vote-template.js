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
        
        this.deleteTemplate = function(id) {
            return $q(function(resolve, reject) {
                $http({
                    method: 'DELETE',
                    url: '/api/picture-moder-vote-template/' + id
                }).then(function(response) {
                    if (templates) {
                        for (var i=0; i<templates.length; i++) {
                            if (templates[i].id == id) {
                                templates.splice(i, 1);
                                break;
                            }
                        }
                    }
                    resolve();
                }, function() {
                    reject();
                });
            });
        };
        
        this.createTemplate = function(template) {
            return $q(function(resolve, reject) {
                $http({
                    method: 'POST',
                    url: '/api/picture-moder-vote-template',
                    data: template
                }).then(function(response) {
                    var location = response.headers('Location');
                    
                    $http({
                        method: 'GET',
                        url: location
                    }).then(function(response) {
                        templates.push(response.data);
                        resolve(response.data);
                    }, function() {
                        reject(null);
                    });
                }, function() {
                    reject();
                });
            });
        };
    }]);

export default SERVICE_NAME;
