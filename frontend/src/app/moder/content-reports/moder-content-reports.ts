import type {OnInit} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {ContentReport, Pages, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, Injector, signal} from '@angular/core';
import {disabled, form, FormField, submit} from '@angular/forms/signals';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentMessageFields,
  ContentReportEntityType,
  ContentReportReason,
  ContentReportsRequest,
  ContentReportStatus,
  GetMessageRequest,
  ResolveContentReportRequest,
} from '@grpc/spec.pb';
import {AutowpClient, CommentsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {numericEnumValue} from '@utils/numeric-enum-value';
import {timestampToDate} from '@utils/timestamp';
import {
  BehaviorSubject,
  catchError,
  combineLatest,
  distinctUntilChanged,
  EMPTY,
  firstValueFrom,
  map,
  of,
  shareReplay,
  switchMap,
} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';
import {ToastsService} from '../../toasts/toasts.service';
import {UserLink} from '../../user/user/user-link';

interface ContentReportInList {
  readonly report: ContentReport;
  readonly reporter$: Observable<null | User>;
  readonly resolution: FieldTree<string>;
  readonly resolvedBy$: Observable<null | User>;
}

interface CommentLink {
  readonly preview: string;
  readonly route: string[];
}

@Component({
  selector: 'app-moder-content-reports',
  imports: [FormField, RouterLink, UserLink, Paginator, AsyncPipe, DatePipe],
  templateUrl: './moder-content-reports.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerContentReports implements OnInit {
  readonly #injector = inject(Injector);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #autowpClient = inject(AutowpClient);
  readonly #commentsClient = inject(CommentsClient);
  readonly #userService = inject(UserService);
  readonly #toasts = inject(ToastsService);

  readonly #filterModel = signal({
    status: `${ContentReportStatus.CONTENT_REPORT_STATUS_OPEN}`,
    entityType: `${ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED}`,
  });
  protected readonly filterForm = form(this.#filterModel);

  readonly #status$ = this.#route.queryParamMap.pipe(
    map((params) =>
      numericEnumValue(
        ContentReportStatus,
        parseInt(params.get('status') ?? '', 10) || ContentReportStatus.CONTENT_REPORT_STATUS_OPEN,
        ContentReportStatus.CONTENT_REPORT_STATUS_OPEN,
      ),
    ),
    distinctUntilChanged(),
  );

  readonly #entityType$ = this.#route.queryParamMap.pipe(
    map((params) =>
      numericEnumValue(
        ContentReportEntityType,
        parseInt(params.get('entity_type') ?? '', 10),
        ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED,
      ),
    ),
    distinctUntilChanged(),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
  );

  readonly #reload$ = new BehaviorSubject<void>(undefined);

  readonly #rowForms = new Map<string, FieldTree<string>>();

  protected readonly data$: Observable<{paginator?: Pages; reports: ContentReportInList[]}> = combineLatest([
    this.#status$,
    this.#entityType$,
    this.#page$,
    this.#reload$,
  ]).pipe(
    switchMap(([status, entityType, page]) => {
      this.#filterModel.set({status: `${status}`, entityType: `${entityType}`});

      return this.#autowpClient.getContentReports(new ContentReportsRequest({entityType, page, status}));
    }),
    catchError((error: unknown) => {
      this.#toasts.handleError(error);

      return EMPTY;
    }),
    map((response) => {
      const previousForms = new Map(this.#rowForms);
      this.#rowForms.clear();
      return {
        paginator: response.paginator,
        reports: (response.items ?? []).map((report): ContentReportInList => {
          const resolution =
            previousForms.get(report.id) ??
            form(
              signal(''),
              (path) => {
                disabled(path, {when: ({state}) => state.submitting()});
              },
              {injector: this.#injector},
            );
          this.#rowForms.set(report.id, resolution);
          return {
            report,
            reporter$: this.#userService.getUser$(report.reporterId, {authenticated: true}),
            resolution,
            resolvedBy$: this.#userService.getUser$(report.resolvedBy, {authenticated: true}),
          };
        }),
      };
    }),
  );

  readonly #commentLinkCache = new Map<string, Observable<CommentLink | null>>();

  protected readonly ContentReportEntityType = ContentReportEntityType;
  protected readonly ContentReportReason = ContentReportReason;
  protected readonly ContentReportStatus = ContentReportStatus;

  protected readonly reasons: Record<number, string> = {
    [ContentReportReason.CONTENT_REPORT_REASON_COPYRIGHT]: $localize`Copyright infringement`,
    [ContentReportReason.CONTENT_REPORT_REASON_ILLEGAL]: $localize`Illegal or prohibited content`,
    [ContentReportReason.CONTENT_REPORT_REASON_SPAM]: $localize`Spam or advertising`,
    [ContentReportReason.CONTENT_REPORT_REASON_PRIVACY]: $localize`Disclosure of private information`,
    [ContentReportReason.CONTENT_REPORT_REASON_OTHER]: $localize`Other`,
  };

  protected readonly timestampToDate = timestampToDate;

  protected getCommentLink$(id: string): Observable<CommentLink | null> {
    let o$ = this.#commentLinkCache.get(id);
    if (!o$) {
      o$ = this.#commentsClient
        .getMessage(new GetMessageRequest({fields: new CommentMessageFields({preview: true, route: true}), id}))
        .pipe(
          map((message): CommentLink | null =>
            message.route.length > 0 ? {preview: message.preview, route: message.route} : null,
          ),
          catchError(() => of(null)),
          shareReplay({bufferSize: 1, refCount: false}),
        );
      this.#commentLinkCache.set(id, o$);
    }

    return o$;
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_CONTENT_REPORTS,
    });
  }

  protected setFilter(): void {
    void this.#router.navigate([], {
      queryParams: {
        entity_type: Number(this.#filterModel().entityType) || null,
        page: null,
        status: Number(this.#filterModel().status) || null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected resolve(row: ContentReportInList, accepted: boolean): void {
    void submit(row.resolution, async () => {
      try {
        await firstValueFrom(
          this.#autowpClient.resolveContentReport(
            new ResolveContentReportRequest({
              accepted,
              id: row.report.id,
              resolution: row.resolution().value().trim(),
            }),
          ),
        );
        this.#reload$.next();
      } catch (error: unknown) {
        this.#toasts.handleError(error);
      }
    });
  }
}
