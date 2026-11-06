import type {OnInit, WritableSignal} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {GdprObjection, GdprObjectionCleanupCandidate} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, Injector, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {disabled, form, FormField, maxLength, pattern, required, submit} from '@angular/forms/signals';
import {Router, RouterLink} from '@angular/router';
import {
  AcknowledgeGdprObjectionHitRequest,
  AddGdprObjectionCleanupCandidateRequest,
  CommentMessageFields,
  CreateGdprObjectionRequest,
  DeleteGdprObjectionRequest,
  GdprObjectionCleanupCandidateEntityType,
  GetGdprObjectionAffectedPicturesRequest,
  GetGdprObjectionCleanupCandidatesRequest,
  GetGdprObjectionSourceTextRequest,
  GetGdprObjectionsRequest,
  GetMessageRequest,
  ResolveGdprObjectionCleanupCandidateRequest,
} from '@grpc/spec.pb';
import {AutowpClient, CommentsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {browserWindow} from '@utils/browser-window';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {timestampToDate} from '@utils/timestamp';
import {catchError, defer, EMPTY, firstValueFrom, map, of, shareReplay, switchMap} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';
import {ToastsService} from '../../toasts/toasts.service';
interface CommentLink {
  readonly preview: string;
  readonly route: string[];
}

interface CleanupCandidateInList {
  readonly candidate: GdprObjectionCleanupCandidate;
  readonly resolving: WritableSignal<boolean>;
}

interface ObjectionInList {
  readonly acknowledging: WritableSignal<boolean>;
  readonly addCandidatePictureId: FieldTree<string>;
  readonly affectedPictureIdentities: WritableSignal<null | string[]>;
  readonly affectedPictureIds: WritableSignal<null | string[]>;
  readonly affectedPicturesLoading: WritableSignal<boolean>;
  readonly cleanupCandidates: WritableSignal<CleanupCandidateInList[] | null>;
  readonly cleanupCandidatesLoading: WritableSignal<boolean>;
  readonly deleting: WritableSignal<boolean>;
  readonly objection: GdprObjection;
  readonly sourceText: WritableSignal<null | string>;
  readonly sourceTextLoading: WritableSignal<boolean>;
}

@Component({
  selector: 'app-moder-gdpr-objections',
  imports: [ResourceStatus, FormField, RouterLink, Paginator, AsyncPipe, DatePipe],
  templateUrl: './moder-gdpr-objections.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerGdprObjections implements OnInit {
  readonly #injector = inject(Injector);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #autowpClient = inject(AutowpClient);
  readonly #commentsClient = inject(CommentsClient);
  readonly #toasts = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #window = browserWindow();

  protected readonly CleanupCandidateEntityType = GdprObjectionCleanupCandidateEntityType;

  protected readonly isModer = this.#auth.hasRole(Role.MODER);

  readonly #hitsOnly = signal(false);
  protected readonly hitsOnly = form(this.#hitsOnly);

  readonly hitsFilter = input(false, {
    // eslint-disable-next-line @angular-eslint/no-input-rename -- Preserve the public query parameter name.
    alias: 'hits_only',
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) === '1',
  });

  readonly page = input(0, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || 0,
  });

  readonly #rowForms = new Map<string, FieldTree<string>>();

  protected readonly dataResource = rxResource({
    params: () => [this.hitsFilter(), this.page()] as const,
    stream: ({params: [hitsOnly, page]}) =>
      defer(() => {
        this.#hitsOnly.set(hitsOnly);

        return this.#autowpClient.getGdprObjections(new GetGdprObjectionsRequest({hitsOnly, page}));
      }).pipe(
        map((response) => {
          const previousForms = new Map(this.#rowForms);
          this.#rowForms.clear();
          return {
            objections: (response.items ?? []).map((objection): ObjectionInList => {
              const addCandidatePictureId =
                previousForms.get(objection.id) ??
                form(
                  signal(''),
                  (path) => {
                    required(path);
                    pattern(path, /^\s*\d+\s*$/);
                    disabled(path, {when: ({state}) => state.submitting()});
                  },
                  {injector: this.#injector},
                );
              this.#rowForms.set(objection.id, addCandidatePictureId);
              return {
                acknowledging: signal(false),
                addCandidatePictureId,
                affectedPictureIdentities: signal(null),
                affectedPictureIds: signal(null),
                affectedPicturesLoading: signal(false),
                cleanupCandidates: signal(null),
                cleanupCandidatesLoading: signal(false),
                deleting: signal(false),
                objection,
                sourceText: signal(null),
                sourceTextLoading: signal(false),
              };
            }),
            paginator: response.paginator,
          };
        }),
      ),
  });
  protected readonly data = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

  readonly #model = signal({contactEmail: '', names: '', note: '', reference: '', sourceText: ''});
  protected readonly createForm = form(this.#model, (path) => {
    maxLength(path.contactEmail, 255);
    required(path.names);
    maxLength(path.note, 4000);
    required(path.reference);
    maxLength(path.reference, 255);
  });

  protected readonly timestampToDate = timestampToDate;

  readonly #commentLinkCache = new Map<string, Observable<CommentLink | null>>();

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_GDPR_OBJECTIONS,
    });
  }

  protected setFilter(): void {
    void this.#router.navigate([], {
      queryParams: {
        hits_only: this.#hitsOnly() ? '1' : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected create(): void {
    void submit(this.createForm, async () => {
      const value = this.#model();
      const names = value.names
        .split('\n')
        .map((name) => name.trim())
        .filter((name) => name !== '');

      try {
        await firstValueFrom(
          this.#autowpClient.createGdprObjection(
            new CreateGdprObjectionRequest({
              contactEmail: value.contactEmail.trim(),
              names,
              note: value.note.trim(),
              reference: value.reference.trim(),
              sourceText: value.sourceText.trim(),
            }),
          ),
        );

        this.createForm().reset({contactEmail: '', names: '', note: '', reference: '', sourceText: ''});
        this.dataResource.reload();
      } catch (error: unknown) {
        this.#toasts.handleError(error);
      }
    });
  }

  protected acknowledge(row: ObjectionInList): void {
    if (row.acknowledging()) {
      return;
    }

    row.acknowledging.set(true);

    this.#autowpClient
      .acknowledgeGdprObjectionHit(new AcknowledgeGdprObjectionHitRequest({id: row.objection.id}))
      .pipe(
        switchMap(() => {
          this.dataResource.reload();
          return EMPTY;
        }),
      )
      .subscribe({
        error: (error: unknown) => {
          row.acknowledging.set(false);
          this.#toasts.handleError(error);
        },
      });
  }

  protected delete(row: ObjectionInList): void {
    if (row.deleting()) {
      return;
    }

    row.deleting.set(true);

    this.#autowpClient.deleteGdprObjection(new DeleteGdprObjectionRequest({id: row.objection.id})).subscribe({
      error: (error: unknown) => {
        row.deleting.set(false);
        this.#toasts.handleError(error);
      },
      next: () => {
        this.dataResource.reload();
      },
    });
  }

  protected loadSourceText(row: ObjectionInList): void {
    if (row.sourceTextLoading() || row.sourceText() !== null) {
      return;
    }

    row.sourceTextLoading.set(true);

    this.#autowpClient
      .getGdprObjectionSourceText(new GetGdprObjectionSourceTextRequest({id: row.objection.id}))
      .subscribe({
        error: (error: unknown) => {
          row.sourceTextLoading.set(false);
          this.#toasts.handleError(error);
        },
        next: (response) => {
          row.sourceTextLoading.set(false);
          row.sourceText.set(response.text || $localize`(empty)`);
        },
      });
  }

  protected loadAffectedPictures(row: ObjectionInList): void {
    if (row.affectedPicturesLoading() || row.affectedPictureIds() !== null) {
      return;
    }

    row.affectedPicturesLoading.set(true);

    this.#autowpClient
      .getGdprObjectionAffectedPictures(new GetGdprObjectionAffectedPicturesRequest({id: row.objection.id}))
      .subscribe({
        error: (error: unknown) => {
          row.affectedPicturesLoading.set(false);
          this.#toasts.handleError(error);
        },
        next: (response) => {
          row.affectedPicturesLoading.set(false);
          row.affectedPictureIds.set(response.pictureIds);
          row.affectedPictureIdentities.set(response.pictureIdentities);
        },
      });
  }

  // Requesters who ask what was published under their name (GDPR Art. 15) want links they can
  // actually open, not the /moder/pictures ids shown above - those are moderator-only and would
  // 404 (or worse, expose the admin UI) for an outside visitor. The public route is
  // /picture/:identity, not :id - an id-based link 404s, since Picture.Identity isn't the id.
  // window.location.origin matches whichever language domain the moderator is currently on.
  protected copyAffectedPictureLinks(pictureIdentities: string[]): void {
    const window = this.#window;
    if (!window) {
      return;
    }

    const origin = window.location.origin;
    const links = pictureIdentities.map((identity) => `${origin}/picture/${identity}`).join('\n');

    window.navigator.clipboard.writeText(links).then(
      () => {
        this.#toasts.success($localize`Links copied to clipboard.`);
      },
      () => {
        this.#toasts.error($localize`Could not copy to clipboard.`);
      },
    );
  }

  protected loadCleanupCandidates(row: ObjectionInList): void {
    if (row.cleanupCandidatesLoading() || row.cleanupCandidates() !== null) {
      return;
    }

    row.cleanupCandidatesLoading.set(true);

    this.#autowpClient
      .getGdprObjectionCleanupCandidates(new GetGdprObjectionCleanupCandidatesRequest({objectionId: row.objection.id}))
      .subscribe({
        error: (error: unknown) => {
          row.cleanupCandidatesLoading.set(false);
          this.#toasts.handleError(error);
        },
        next: (response) => {
          row.cleanupCandidatesLoading.set(false);
          row.cleanupCandidates.set(
            (response.items ?? []).map((candidate): CleanupCandidateInList => ({candidate, resolving: signal(false)})),
          );
        },
      });
  }

  protected resolveCleanupCandidate(row: ObjectionInList, candidate: CleanupCandidateInList): void {
    if (candidate.resolving() || candidate.candidate.resolveTime) {
      return;
    }

    candidate.resolving.set(true);

    this.#autowpClient
      .resolveGdprObjectionCleanupCandidate(
        new ResolveGdprObjectionCleanupCandidateRequest({id: candidate.candidate.id}),
      )
      .subscribe({
        error: (error: unknown) => {
          candidate.resolving.set(false);
          this.#toasts.handleError(error);
        },
        next: () => {
          row.cleanupCandidates.set(null);
          this.loadCleanupCandidates(row);
        },
      });
  }

  // Covers pictures the SuppressAuthor-time site-wide search missed - a spelling variant, or one
  // found/uploaded afterwards - that a moderator spots by hand (e.g. searching the inbox) and
  // wants tracked against the case alongside the automatically found hits.
  protected addCleanupCandidate(row: ObjectionInList): void {
    void submit(row.addCandidatePictureId, async () => {
      try {
        await firstValueFrom(
          this.#autowpClient.addGdprObjectionCleanupCandidate(
            new AddGdprObjectionCleanupCandidateRequest({
              objectionId: row.objection.id,
              pictureId: row.addCandidatePictureId().value().trim(),
            }),
          ),
        );
        row.addCandidatePictureId().reset('');
        row.cleanupCandidates.set(null);
        this.loadCleanupCandidates(row);
      } catch (error: unknown) {
        this.#toasts.handleError(error);
      }
    });
  }

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
}
