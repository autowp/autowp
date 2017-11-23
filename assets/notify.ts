import 'bootstrap-notify';
import * as $ from 'jquery';
import 'animate.css';

class Notify {
    constructor(options: any, settings: any)
    {
        settings.placement = {
            align: 'right',
            from: 'bottom'
        };
        $.notify(options, settings);
    }
    
    public static response(response: ng.IHttpResponse<any>) {
        new Notify({
            icon: 'fa fa-exclamation-triangle',
            message: response.status + ': ' + response.statusText
        }, {
            type: 'danger'
        });
    };
}

export default Notify;
