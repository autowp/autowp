import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {marked} from 'marked';

@Component({
  selector: 'app-markdown',
  templateUrl: './markdown.component.html',
  styleUrl: 'markdown.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MarkdownComponent {
  readonly markdown = input.required<null | string>();

  protected readonly html = computed(() => {
    const markdown = this.markdown();
    return markdown ? marked.parse(markdown, {async: false}) : '';
  });
}
