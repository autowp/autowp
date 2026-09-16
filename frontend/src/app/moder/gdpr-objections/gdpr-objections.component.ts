import type {OnInit, WritableSignal} from '@angular/core';
import type {GdprObjection, GdprObjectionCleanupCandidate, Pages} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  AcknowledgeGdprObjectionHitRequest,
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
import {AuthService, Role} from '@services/auth.service';
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
  map,
  of,
  shareReplay,
  switchMap,
} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
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
  imports: [RouterLink, FormsModule, ReactiveFormsModule, PaginatorComponent, AsyncPipe, DatePipe],
  templateUrl: './gdpr-objections.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerGdprObjectionsComponent implements OnInit {
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

  protected readonly hitsOnly = new FormControl<boolean>(false, {nonNullable: true});

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

  protected readonly data$: Observable<{objections: ObjectionInList[]; paginator?: Pages}> = combineLatest([
    this.#hitsOnly$,
    this.#page$,
    this.#reload$,
  ]).pipe(
    switchMap(([hitsOnly, page]) => {
      this.hitsOnly.setValue(hitsOnly, {emitEvent: false});

      return this.#autowpClient.getGdprObjections(new GetGdprObjectionsRequest({hitsOnly, page}));
    }),
    map((response) => ({
      objections: (response.items ?? []).map((objection): ObjectionInList => ({
        acknowledging: signal(false),
        affectedPictureIds: signal(null),
        affectedPicturesLoading: signal(false),
        cleanupCandidates: signal(null),
        cleanupCandidatesLoading: signal(false),
        deleting: signal(false),
        objection,
        sourceText: signal(null),
        sourceTextLoading: signal(false),
      })),
      paginator: response.paginator,
    })),
  );

  protected readonly createForm = new FormGroup({
    contactEmail: new FormControl<string>('', {nonNullable: true, validators: [Validators.maxLength(255)]}),
    names: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    note: new FormControl<string>('', {nonNullable: true, validators: [Validators.maxLength(4000)]}),
    reference: new FormControl<string>('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)],
    }),
    sourceText: new FormControl<string>('', {nonNullable: true}),
  });

  protected readonly creating = signal(false);

  protected readonly timestampToDate = timestampToDate;

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_GDPR_OBJECTIONS,
    });
  }

  protected setFilter(): void {
    void this.#router.navigate([], {
      queryParams: {
        hits_only: this.hitsOnly.value ? '1' : null,
        page: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected create(): void {
    if (this.createForm.invalid || this.creating()) {
      this.createForm.markAllAsTouched();
      return;
    }

    this.creating.set(true);

    const value = this.createForm.getRawValue();
    const names = value.names
      .split('\n')
      .map((name) => name.trim())
      .filter((name) => name !== '');

    this.#autowpClient
      .createGdprObjection(
        new CreateGdprObjectionRequest({
          contactEmail: value.contactEmail.trim(),
          names,
          note: value.note.trim(),
          reference: value.reference.trim(),
          sourceText: value.sourceText.trim(),
        }),
      )
      .subscribe({
        error: (error: unknown) => {
          this.creating.set(false);
          this.#toasts.handleError(error);
        },
        next: () => {
          this.creating.set(false);
          this.createForm.reset({contactEmail: '', names: '', note: '', reference: '', sourceText: ''});
          this.#reload$.next();
        },
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
        },
      });
  }

  // Requesters who ask what was published under their name (GDPR Art. 15) want links they can
  // actually open, not the /moder/pictures ids shown above - those are moderator-only and would
  // 404 (or worse, expose the admin UI) for an outside visitor. window.location.origin matches
  // whichever language domain the moderator is currently on.
  protected copyAffectedPictureLinks(pictureIds: string[]): void {
    const window = this.#window;
    if (!window) {
      return;
    }

    const origin = window.location.origin;
    const links = pictureIds.map((pictureId) => `${origin}/picture/${pictureId}`).join('\n');

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
}
