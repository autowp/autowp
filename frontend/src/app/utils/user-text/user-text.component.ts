import type {Picture, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PictureFields, PictureListOptions, PicturesRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {catchError, combineLatest, distinctUntilChanged, map, of, switchMap} from 'rxjs';
import URLParse from 'url-parse';

import {UserComponent} from '../../user/user/user.component';

interface CommentTextElement {
  picture?: Picture;
  text?: string;
  type: 'a' | 'picture' | 'text' | 'user';
  url?: string;
  user?: User;
}

interface CommentTextLine {
  elements: CommentTextElement[];
}

@Component({
  selector: 'app-user-text',
  imports: [UserComponent, RouterLink, AsyncPipe],
  templateUrl: './user-text.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class UserTextComponent {
  readonly #userService = inject(UserService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly text = input.required<string>();

  readonly #parseUrlHosts = [
    'www.autowp.ru',
    'en.autowp.ru',
    'ru.autowp.ru',
    'autowp.ru',
    'fr.wheelsage.org',
    'en.wheelsage.org',
    'zh.wheelsage.org',
    'be.wheelsage.org',
    'br.wheelsage.org',
    'uk.wheelsage.org',
    'it.wheelsage.org',
    'wheelsage.org',
  ];

  // No debounceTime() before prepareText$(): `text` is a comment/message body that changes only
  // when the message itself does, so there was nothing to coalesce (distinctUntilChanged already
  // drops repeats), and a macrotask delay ahead of the first HTTP call is exactly the shape that
  // stops registering as pending for SSR's whenStable() once the app drops zone-based change
  // detection - see the note on CommentsComponent.dataResource. Every comment body on the site is
  // rendered through here, so it must be in the SSR output.
  protected readonly textPrepared$ = toObservable(this.text).pipe(
    distinctUntilChanged(),
    switchMap((text) => this.prepareText$(text ? text : '')),
  );

  private prepareText$(text: string): Observable<CommentTextLine[]> {
    const lines = text.split(/\r?\n/);
    const result: Observable<CommentTextLine>[] = [];

    lines.forEach((line) => {
      result.push(
        this.prepareLine$(line).pipe(
          map((value) => ({
            elements: value,
          })),
        ),
      );
    });

    return combineLatest(result);
  }

  private prepareLine$(line: string): Observable<CommentTextElement[]> {
    const out: Observable<CommentTextElement>[] = [];

    const re = new RegExp(/(https?:\/\/[\w:.,/?&=~+%#'!|()-]{3,})|(www.[\w.,/?&=~+%#'!|()-]{3,})/i, 'i');

    let res: null | RegExpExecArray;
    let umatch: string;
    let url;

    while (line && (res = re.exec(line))) {
      if (res[1]) {
        umatch = res[1];
        url = umatch;
      } else {
        umatch = res[2];
        url = 'https://' + umatch;
      }

      const linkPos = line.indexOf(umatch);
      const matchLength = umatch.length;
      if (linkPos < 0) {
        throw new Error('Error during parse urls');
      }

      out.push(
        of({
          text: line.substring(0, linkPos),
          type: 'text',
        }),
      );

      out.push(this.processHref$(url));

      line = line.substring(linkPos + matchLength);
    }

    if (line.length > 0) {
      out.push(
        of({
          text: line,
          type: 'text',
        }),
      );
    }

    return out.length ? combineLatest(out) : of([]);
  }

  private processHref$(url: string): Observable<CommentTextElement> {
    const uri = URLParse(url);

    const hostAllowed = this.#parseUrlHosts.includes(uri.host.toLowerCase());

    if (hostAllowed) {
      return this.tryUserLink$(uri).pipe(
        switchMap((element) => {
          if (!element) {
            return this.tryPictureLink$(uri);
          }

          return of(element);
        }),
        map(
          (element) =>
            element ?? {
              type: 'a',
              url,
            },
        ),
      );
    }

    return of({
      type: 'a',
      url,
    });
  }

  private tryUserLink$(uri: URLParse<string>): Observable<CommentTextElement | null> {
    const re = new RegExp(/^\/users\/([^/]+)$/i, 'i');
    const matches = re.exec(uri.pathname);
    if (!matches) {
      return of(null);
    }

    const userIdentity: null | string = matches[1];

    if (userIdentity) {
      return this.#userService.getByIdentity$(userIdentity, undefined).pipe(
        catchError(() => of(null)),
        map((user) =>
          user
            ? {
                type: 'user',
                user,
              }
            : null,
        ),
      );
    }

    return of(null);
  }

  private tryPictureLink$(uri: URLParse<string>): Observable<CommentTextElement | null> {
    const re = new RegExp(/\/pictures?\/([^/]+)$/i, 'i');
    const matches = re.exec(uri.pathname);

    if (!matches) {
      return of(null);
    }

    let pictureId: null | number = null;
    let pictureIdentity: null | string = matches[1];

    const re2 = new RegExp(/^(\d+)$/i, 'i');
    const match = re2.exec(pictureIdentity);
    if (match) {
      pictureIdentity = null;
      pictureId = parseInt(matches[1] || '', 10);
    }

    const fields = new PictureFields({
      commentsCount: true,
      moderVote: true,
      nameText: true,
      thumbMedium: true,
      views: true,
      votes: true,
    });

    if (pictureId) {
      return this.#picturesClient
        .getPicture(
          new PicturesRequest({
            fields,
            language: this.#languageService.language,
            options: new PictureListOptions({id: '' + pictureId}),
          }),
        )
        .pipe(
          catchError(() => of(null)),
          map((picture) =>
            picture
              ? {
                  picture,
                  type: 'picture',
                }
              : null,
          ),
        );
    }

    if (pictureIdentity) {
      return this.#picturesClient
        .getPicture(
          new PicturesRequest({
            fields,
            language: this.#languageService.language,
            options: new PictureListOptions({identity: pictureIdentity}),
          }),
        )
        .pipe(
          catchError(() => of(null)),
          map((picture) =>
            picture
              ? {
                  picture,
                  type: 'picture',
                }
              : null,
          ),
        );
    }

    return of(null);
  }
}
