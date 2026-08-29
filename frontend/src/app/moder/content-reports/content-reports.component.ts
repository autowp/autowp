import type {OnInit, WritableSignal} from '@angular/core';
import type {ContentReport, Pages, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
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
import {timestampToDate} from '@utils/timestamp';
import {
  BehaviorSubject,
  catchError,
  combineLatest,
  distinctUntilChanged,
  EMPTY,
  map,
  of,
  shareReplay,
  switchMap,
} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface ContentReportInList {
  readonly report: ContentReport;
  readonly reporter$: Observable<null | User>;
  readonly resolution: FormControl<string>;
  readonly resolvedBy$: Observable<null | User>;
  readonly submitting: WritableSignal<boolean>;
}

interface CommentLink {
  readonly preview: string;
  readonly route: string[];
}

@Component({
  selector: 'app-moder-content-reports',
  imports: [RouterLink, FormsModule, ReactiveFormsModule, UserComponent, PaginatorComponent, AsyncPipe, DatePipe],
  templateUrl: './content-reports.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerContentReportsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #autowpClient = inject(AutowpClient);
  readonly #commentsClient = inject(CommentsClient);
  readonly #userService = inject(UserService);
  readonly #toasts = inject(ToastsService);

  protected readonly status = new FormControl<ContentReportStatus>(ContentReportStatus.CONTENT_REPORT_STATUS_OPEN, {
    nonNullable: true,
  });
  protected readonly entityType = new FormControl<ContentReportEntityType>(
    ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED,
    {nonNullable: true},
  );

  readonly #status$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('status') ?? '', 10) || ContentReportStatus.CONTENT_REPORT_STATUS_OPEN),
    distinctUntilChanged(),
  );

  readonly #entityType$ = this.#route.queryParamMap.pipe(
    map(
      (params) =>
        parseInt(params.get('entity_type') ?? '', 10) || ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED,
    ),
    distinctUntilChanged(),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
  );

  readonly #reload$ = new BehaviorSubject<void>(undefined);

  protected readonly data$: Observable<{paginator?: Pages; reports: ContentReportInList[]}> = combineLatest([
    this.#status$,
    this.#entityType$,
    this.#page$,
    this.#reload$,
  ]).pipe(
    switchMap(([status, entityType, page]) => {
      this.status.setValue(status, {emitEvent: false});
      this.entityType.setValue(entityType, {emitEvent: false});

      return this.#autowpClient.getContentReports(new ContentReportsRequest({entityType, page, status}));
    }),
    catchError((error: unknown) => {
      this.#toasts.handleError(error);

      return EMPTY;
    }),
    map((response) => ({
      paginator: response.paginator,
      reports: (response.items ?? []).map((report): ContentReportInList => ({
        report,
        reporter$: this.#userService.getUser$(report.reporterId, {authenticated: true}),
        resolution: new FormControl<string>('', {nonNullable: true}),
        resolvedBy$: this.#userService.getUser$(report.resolvedBy, {authenticated: true}),
        submitting: signal(false),
      })),
    })),
  );

  readonly #commentLinkCache = new Map<string, Observable<CommentLink | null>>();

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

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_CONTENT_REPORTS,
    });
  }

  protected setFilter(): void {
    void this.#router.navigate([], {
      queryParams: {
        entity_type: this.entityType.value || null,
        page: null,
        status: this.status.value || null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected resolve(row: ContentReportInList, accepted: boolean): void {
    if (row.submitting()) {
      return;
    }

    row.submitting.set(true);

    this.#autowpClient
      .resolveContentReport(
        new ResolveContentReportRequest({accepted, id: row.report.id, resolution: row.resolution.value.trim()}),
      )
      .subscribe({
        error: (error: unknown) => {
          row.submitting.set(false);
          this.#toasts.handleError(error);
        },
        next: () => {
          this.#reload$.next();
        },
      });
  }
}
