import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-donate-vod-success',
  imports: [RouterLink, RemarkModule],
  templateUrl: './donate-vod-success.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateVodSuccess implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.DONATE});
  }
}
