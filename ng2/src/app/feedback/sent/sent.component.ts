import { Component, Injectable } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-feedback-sent',
  templateUrl: './sent.component.html'
})
@Injectable()
export class FeedbackSentComponent {
  constructor(private pageEnv: PageEnvService) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/93/name',
          pageId: 93
        }),
      0
    );
  }
}
