import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';

@Component({
  selector: 'app-telegram',
  imports: [RouterLink, MarkdownComponent],
  templateUrl: './telegram.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TelegramComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 204}), 0);
  }
}
