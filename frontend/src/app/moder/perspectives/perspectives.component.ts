import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PerspectivePage} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {getPerspectiveTranslation} from '@utils/translations';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-moder-perspectives',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './perspectives.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPerspectivesComponent implements OnInit {
  readonly #picturesClient = inject(PicturesClient);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly pages$: Observable<PerspectivePage[]> = this.#picturesClient.getPerspectivePages(new Empty()).pipe(
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => (response.items ? response.items : [])),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 202,
    });
  }

  protected getPerspectiveTranslation(id: string): string {
    return getPerspectiveTranslation(id);
  }
}
