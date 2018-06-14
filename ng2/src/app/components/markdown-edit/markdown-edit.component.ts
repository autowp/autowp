import { Component, Injectable, Input, AfterViewInit, Output, EventEmitter } from '@angular/core';

@Component({
  selector: 'app-markdown-edit',
  templateUrl: './markdown-edit.component.html',
})
@Injectable()
export class MarkdownEditComponent {
  @Input() text: string;
  @Output() textChange = new EventEmitter();

  constructor() { }

  public onChange() {
    this.textChange.emit(this.text);
  }
}
