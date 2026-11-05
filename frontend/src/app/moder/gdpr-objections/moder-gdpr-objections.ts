import type {OnInit, WritableSignal} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {GdprObjection, GdprObjectionCleanupCandidate, Pages} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, Injector, signal} from '@angular/core';
import {disabled, form, FormField, maxLength, pattern, required, submit} from '@angular/forms/signals';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
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
  imports: [FormField, RouterLink, Paginator, AsyncPipe, DatePipe],
  templateUrl: './moder-gdpr-objections.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerGdprObjections implements OnInit {
  readonly #injector = inject(Injector);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #autowpClient = inject(AutowpClient);
  readonly #commentsClient = inject(CommentsClient);
  readonly #toasts = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #window = browserWindow();

  protected readonly CleanupCandidateEntityType = GdprObjectionCleanupCandidateEntityType;

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #hitsOnly = signal(false);
  protected readonly hitsOnly = form(this.#hitsOnly);

  readonly #hitsOnly$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('hits_only') === '1'),
    distinctUntilChanged(),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    map((page) => (page ? page : 0)),
    distinctUntilChanged(),
  );

  readonly #reload$ = new BehaviorSubject<void>(undefined);

  readonly #rowForms = new Map<string, FieldTree<string>>();

  protected readonly data$: Observable<{objections: ObjectionInList[]; paginator?: Pages}> = combineLatest([
    this.#hitsOnly$,
    this.#page$,
    this.#reload$,
  ]).pipe(
    switchMap(([hitsOnly, page]) => {
      this.#hitsOnly.set(hitsOnly);

      return this.#autowpClient.getGdprObjections(new GetGdprObjectionsRequest({hitsOnly, page}));
    }),
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
  );

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
        this.#reload$.next();
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
          this.#reload$.next();
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
        this.#reload$.next();
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
