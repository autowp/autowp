import 'bootstrap-notify';
import $ from 'jquery';
import 'animate.css';

var notify = function(options, settings) {
    settings.placement = {
        align: 'right',
        from: 'bottom'
    };
    $.notify(options, settings);
};

notify.response = function(response) {
    notify({
        icon: 'fa fa-exclamation-triangle',
        message: response.status + ': ' + response.statusText
    }, {
        type: 'danger'
    });
};

export default notify;
