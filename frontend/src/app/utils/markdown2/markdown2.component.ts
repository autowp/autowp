import {isPlatformBrowser} from '@angular/common';
import {AfterViewInit, ChangeDetectionStrategy, Component, ElementRef, inject, PLATFORM_ID} from '@angular/core';
import {marked} from 'marked';

@Component({
  selector: 'app-markdown2',
  standalone: true,
  templateUrl: './markdown2.component.html',
  styleUrl: 'markdown2.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Markdown2Component implements AfterViewInit {
  readonly #element = inject(ElementRef);
  readonly #platform = inject(PLATFORM_ID);

  private decodeHtml(html: string): string {
    if (isPlatformBrowser(this.#platform)) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = html;
      return textarea.value;
    }
    return html;
  }

  ngAfterViewInit() {
    const markdown = this.decodeHtml(this.#element.nativeElement.innerHTML);

    this.#element.nativeElement.innerHTML = marked.parse(markdown, {async: false});
  }
}
