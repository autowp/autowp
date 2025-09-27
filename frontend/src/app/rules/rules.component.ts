import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {Markdown2Component} from '@utils/markdown2/markdown2.component';

@Component({
  selector: 'app-rules',
  imports: [RouterLink, Markdown2Component],
  templateUrl: './rules.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RulesComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 106}), 0);
  }
}
