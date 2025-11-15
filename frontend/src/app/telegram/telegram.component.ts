import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-telegram',
  imports: [RouterLink, RemarkModule],
  templateUrl: './telegram.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TelegramComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 204});
  }
}
