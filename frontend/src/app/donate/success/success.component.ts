import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';

@Component({
  selector: 'app-donate-success',
  imports: [RouterLink, MarkdownComponent],
  templateUrl: './success.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateSuccessComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 196}), 0);
  }
}
