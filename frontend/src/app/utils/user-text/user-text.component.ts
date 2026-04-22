import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {APIUser, Picture, PictureFields, PictureListOptions, PicturesRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {combineLatest, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';
import URLParse from 'url-parse';

import {UserComponent} from '../../user/user/user.component';

interface CommentTextElement {
  picture?: Picture;
  text?: string;
  type: 'a' | 'picture' | 'text' | 'user';
  url?: string;
  user?: APIUser;
}

interface CommentTextLine {
  elements: CommentTextElement[];
}

@Component({
  selector: 'app-user-text',
  imports: [UserComponent, RouterLink, AsyncPipe],
  templateUrl: './user-text.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
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

  protected readonly textPrepared$ = toObservable(this.text).pipe(
    distinctUntilChanged(),
    debounceTime(10),
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

    const hostAllowed = this.#parseUrlHosts.indexOf(uri.host.toLowerCase()) >= 0;

    if (hostAllowed) {
      return this.tryUserLink$(uri).pipe(
        switchMap((element) => {
          if (!element) {
            return this.tryPictureLink$(uri);
          }

          return of(element);
        }),
        map((element) =>
          element
            ? element
            : {
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
