import 'bootstrap-notify';
import * as $ from 'jquery';
import 'animate.css';
import { HttpResponse } from '@angular/common/http';

class Notify {
  static notify: Notify;

  constructor(options: any, settings: any) {
    if (!settings) {
      settings = {};
    }
    settings.placement = {
      align: 'right',
      from: 'bottom'
    };
    $.notify(options, settings);
  }

  public static custom(options: any, settings: any) {
    this.notify = new Notify(options, settings);
  }

  public static error(message: string) {
    this.notify = new Notify(
      {
        icon: 'fa fa-exclamation-triangle',
        message: message
      },
      {
        type: 'danger'
      }
    );
  }

  public static response(response: HttpResponse<any>) {
    if (response === undefined) {
      Notify.error('undefined');
      return;
    }
    Notify.error(response.status + ': ' + response.statusText);
  }
}

export default Notify;
