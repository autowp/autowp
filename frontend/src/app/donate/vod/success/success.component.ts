import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-donate-vod-success',
  imports: [RouterLink, RemarkModule],
  templateUrl: './success.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateVodSuccessComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 196});
  }
}
