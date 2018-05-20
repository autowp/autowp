import {
  Component,
  Input,
  Injectable,
  OnChanges,
  SimpleChanges,
  OnInit
} from '@angular/core';
import * as showdown from 'showdown';

@Component({
  selector: 'app-markdown',
  templateUrl: './markdown.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class MarkdownComponent implements OnChanges, OnInit {
  @Input() markdown: string;

  private markdownConverter: showdown.Converter;
  public html = '';

  constructor() {
    this.markdownConverter = new showdown.Converter({});
  }

  ngOnChanges(changes: SimpleChanges) {
    if (changes.markdown) {
      this.markdown = changes.markdown.currentValue;
    }

    this.refresh();
  }

  ngOnInit() {
    this.refresh();
  }

  refresh() {
    this.html = this.markdown
      ? this.markdownConverter.makeHtml(this.markdown)
      : '';
  }
}
