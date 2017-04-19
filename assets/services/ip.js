import angular from 'angular';
import Module from 'app.module';

const SERVICE_NAME = 'IpService';

angular.module(Module)
    .service(SERVICE_NAME, ['$q', '$http', function($q, $http) {
        
        var hostnames = {};
        
        this.getHostByAddr = function(ip) {
            return $q(function(resolve, reject) {
                if (! hostnames.hasOwnProperty(ip)) {
                    $http({
                        method: 'GET',
                        url: '/api/ip/' + ip,
                        params: {
                            fields: 'hostname'
                        }
                    }).then(function(response) {
                        hostnames[ip] = response.data.hostname;
                        resolve(hostnames[ip]);
                    }, function() {
                        reject(null);
                    });
                } else {
                    resolve(hostnames[ip]);
                }
            });
        };
        
    }]);

export default SERVICE_NAME;
