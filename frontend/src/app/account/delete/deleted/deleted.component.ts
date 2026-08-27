import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-account-delete-deleted',
  imports: [RemarkModule],
  templateUrl: './deleted.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountDeletedComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FEEDBACK_SENT});
  }
}
