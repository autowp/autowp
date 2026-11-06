import type {OnInit} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {ContentReport, User} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, Injector, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {disabled, form, FormField, submit} from '@angular/forms/signals';
import {Router, RouterLink} from '@angular/router';
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
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {timestampToDate} from '@utils/timestamp';
import {catchError, defer, firstValueFrom, map, of, shareReplay, switchMap, throwError} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';
import {ToastsService} from '../../toasts/toasts.service';
import {UserLink} from '../../user/user/user-link';
interface ContentReportInList {
  readonly report: ContentReport;
  readonly reporter: null | User;
  readonly resolution: FieldTree<string>;
  readonly resolvedBy: null | User;
}

interface CommentLink {
  readonly preview: string;
  readonly route: string[];
}

@Component({
  selector: 'app-moder-content-reports',
  imports: [ResourceStatus, FormField, RouterLink, UserLink, Paginator, AsyncPipe, DatePipe],
  templateUrl: './moder-content-reports.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerContentReports implements OnInit {
  readonly #injector = inject(Injector);
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

  readonly status = input(ContentReportStatus.CONTENT_REPORT_STATUS_OPEN, {
    transform: (value: RouteParam) =>
      numericEnumValue(
        ContentReportStatus,
        parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || ContentReportStatus.CONTENT_REPORT_STATUS_OPEN,
        ContentReportStatus.CONTENT_REPORT_STATUS_OPEN,
      ),
  });

  readonly entityType = input(ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED, {
    // eslint-disable-next-line @angular-eslint/no-input-rename -- Preserve the public query parameter name.
    alias: 'entity_type',
    transform: (value: RouteParam) =>
      numericEnumValue(
        ContentReportEntityType,
        parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10),
        ContentReportEntityType.CONTENT_REPORT_ENTITY_TYPE_UNSPECIFIED,
      ),
  });

  readonly page = input(0, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || 0,
  });

  readonly #rowForms = new Map<string, FieldTree<string>>();

  protected readonly dataResource = rxResource({
    params: () => [this.status(), this.entityType(), this.page()] as const,
    stream: ({params: [status, entityType, page]}) =>
      defer(() => {
        this.#filterModel.set({status: `${status}`, entityType: `${entityType}`});

        return this.#autowpClient.getContentReports(new ContentReportsRequest({entityType, page, status}));
      }).pipe(
        catchError((error: unknown) => {
          this.#toasts.handleError(error);

          return throwError(() => error);
        }),
        switchMap((response) =>
          this.#userService
            .getUserMap$(
              (response.items ?? []).flatMap((report) => [report.reporterId, report.resolvedBy]),
              {authenticated: true},
            )
            .pipe(map((users) => ({response, users}))),
        ),
        map(({response, users}) => {
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
                reporter: users.get(report.reporterId) ?? null,
                resolution,
                resolvedBy: users.get(report.resolvedBy) ?? null,
              };
            }),
          };
        }),
      ),
  });
  protected readonly data = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

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
        this.dataResource.reload();
      } catch (error: unknown) {
        this.#toasts.handleError(error);
      }
    });
  }
}
