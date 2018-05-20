import { Component, Injectable } from '@angular/core';
import { PageEnvService } from '../../../services/page-env.service';

@Component({
  selector: 'app-restore-password-new-ok',
  templateUrl: './ok.component.html'
})
@Injectable()
export class RestorePasswordNewOkComponent {
  constructor(private pageEnv: PageEnvService) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/135/name',
          pageId: 135
        }),
      0
    );
  }
}
