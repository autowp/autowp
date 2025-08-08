import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {map} from 'rxjs/operators';
import showdown from 'showdown';

@Component({
  selector: 'app-markdown',
  imports: [AsyncPipe],
  templateUrl: './markdown.component.html',
  styleUrl: 'markdown.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MarkdownComponent {
  readonly markdown = input.required<null | string>();

  readonly #markdownConverter = new showdown.Converter({});

  protected readonly html$ = toObservable(this.markdown).pipe(
    map((markdown) => (markdown ? this.#markdownConverter.makeHtml(markdown) : '')),
  );
}
