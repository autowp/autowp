import { Component, Injectable } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-error-403',
  templateUrl: './error-403.component.html'
})
@Injectable()
export class Error403Component {
  constructor(private pageEnv: PageEnvService) {
    this.pageEnv.set({
      layout: {
        isAdminPage: true,
        needRight: true
      },
      title: '403 Forbidden'
    });
  }
}
