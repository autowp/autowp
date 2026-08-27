import type {OnInit} from '@angular/core';
import type {Item, Pages, Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {NewboxRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {formatDate, formatGrpcDate, parseGrpcDate, parseStringToGrpcDate} from '@services/utils';
import {catchError, combineLatest, distinctUntilChanged, EMPTY, map, of, shareReplay, switchMap} from 'rxjs';

import {chunkBy} from '../chunk';
import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../toasts/toasts.service';
import {NewListItemComponent} from './list-item/list-item.component';

interface APINewGroupRepacked {
  chunks?: Picture[][];
  item?: Item;
  pictures?: Picture[];
  totalPictures?: number;
  type: string;
}

interface Data {
  current: DayCount;
  groups: (APINewGroupRepacked | null)[];
  next: DayCount;
  paginator: Pages | undefined;
  prev: DayCount;
}

interface DayCount {
  count: number;
  date: Date | null;
}

@Component({
  selector: 'app-new',
  imports: [RouterLink, NewListItemComponent, PaginatorComponent, AsyncPipe, DatePipe, ThumbnailComponent],
  templateUrl: './new.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #page$: Observable<number> = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
  );

  protected readonly date$: Observable<string> = this.#route.paramMap.pipe(
    map((params) => params.get('date') ?? ''),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$: Observable<Data> = combineLatest([this.#page$, this.date$]).pipe(
    switchMap(([page, date]) =>
      this.#picturesClient
        .getNewbox(
          new NewboxRequest({
            date: parseStringToGrpcDate(date),
            language: this.#languageService.language,
            page,
          }),
        )
        .pipe(
          catchError((response: unknown) => {
            this.#toastService.handleError(response);
            return EMPTY;
          }),
          switchMap((response) => {
            const currentDateStr = response.currentDate ? formatGrpcDate(response.currentDate) : '';
            if (date !== currentDateStr) {
              void this.#router.navigate(['/new', currentDateStr]);
              return EMPTY;
            }
            return of(response);
          }),
        ),
    ),
    map((response) => ({
      current: {
        count: response.currentCount,
        date: response.currentDate ? parseGrpcDate(response.currentDate) : null,
      },
      groups: (response.groups ?? [])
        .filter((group) => group.type === 'item' || group.type === 'pictures')
        .map((group) => {
          let repackedGroup: APINewGroupRepacked | null = null;

          switch (group.type) {
            case 'item':
              repackedGroup = group;
              break;
            case 'pictures':
              repackedGroup = {
                chunks: chunkBy(group.pictures ?? [], 6),
                type: group.type,
              };
              break;
          }

          return repackedGroup;
        }),
      next: {
        count: response.nextCount,
        date: response.nextDate ? parseGrpcDate(response.nextDate) : null,
      },
      paginator: response.paginator,
      prev: {
        count: response.prevCount,
        date: response.prevDate ? parseGrpcDate(response.prevDate) : null,
      },
    })),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ITEM_NEW});
  }

  protected readonly formatDate = formatDate;
}
