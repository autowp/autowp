import { Component, Injectable } from '@angular/core';
import { PageEnvService } from '../../../services/page-env.service';

@Component({
  selector: 'app-donate-vod-success',
  templateUrl: './success.component.html'
})
@Injectable()
export class DonateVodSuccessComponent {
  constructor(private pageEnv: PageEnvService) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/196/name',
          pageId: 196
        }),
      0
    );
  }
}
