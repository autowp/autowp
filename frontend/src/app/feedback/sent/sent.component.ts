import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-feedback-sent',
  imports: [RouterLink, RemarkModule],
  templateUrl: './sent.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FeedbackSentComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FEEDBACK_SENT});
  }
}
