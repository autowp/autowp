import { Component, Injectable } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-restore-password-sent',
  templateUrl: './sent.component.html'
})
@Injectable()
export class RestorePasswordSentComponent {
  constructor(private pageEnv: PageEnvService) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/60/name',
          pageId: 60
        }),
      0
    );
  }
}
