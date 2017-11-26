import 'bootstrap-notify';
import * as $ from 'jquery';
import 'animate.css';

class Notify {
    constructor(options: any, settings: any)
    {
        if (! settings) {
            settings = {};
        }
        settings.placement = {
            align: 'right',
            from: 'bottom'
        };
        $.notify(options, settings);
    }
    
    public static error(message: string) {
        new Notify({
            icon: 'fa fa-exclamation-triangle',
            message: message
        }, {
            type: 'danger'
        });
    }
    
    public static response(response: ng.IHttpResponse<any>) {
        Notify.error(response.status + ': ' + response.statusText);
    };
}

export default Notify;
