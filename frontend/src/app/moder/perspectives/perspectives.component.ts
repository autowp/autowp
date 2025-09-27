import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PicturesService} from '@rest/api/pictures.service';
import {GoautowpPerspectivePage} from '@rest/model/goautowpPerspectivePage';
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
  readonly #picturesService = inject(PicturesService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);

  protected readonly pages$: Observable<GoautowpPerspectivePage[]> = this.#picturesService
    .picturesGetPerspectivePages()
    .pipe(
      catchError((response: unknown) => {
        this.#toastService.handleError(response);
        return EMPTY;
      }),
      map((response) => (response.items ? response.items : [])),
    );

  ngOnInit(): void {
    setTimeout(
      () =>
        this.#pageEnv.set({
          layout: {isAdminPage: true},
          pageId: 202,
        }),
      0,
    );
  }

  protected getPerspectiveTranslation(id: string): string {
    return getPerspectiveTranslation(id);
  }
}
