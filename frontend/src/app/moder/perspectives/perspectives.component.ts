import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {getPerspectiveTranslation} from '@utils/translations';
import {errorMessage} from 'app/grpc';

@Component({
  selector: 'app-moder-perspectives',
  imports: [RouterLink],
  templateUrl: './perspectives.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPerspectivesComponent implements OnInit {
  readonly #picturesClient = inject(PicturesClient);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly pagesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-perspectives',
    stream: () => this.#picturesClient.getPerspectivePages(new Empty()),
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 202,
    });
  }

  protected getPerspectiveTranslation(id: string): string {
    return getPerspectiveTranslation(id);
  }

  protected readonly errorMessage = errorMessage;
}
