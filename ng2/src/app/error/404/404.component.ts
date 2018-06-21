import { Component, Injectable } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-error-404',
  templateUrl: './404.component.html'
})
@Injectable()
export class Error404Component {
  constructor(private pageEnv: PageEnvService) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: true
          },
          title: '404 Not Found'
        }),
      0
    );
  }
}
