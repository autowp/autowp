import { Component, Injectable, Input, AfterViewInit } from '@angular/core';

interface IMarkdownEditDirectiveScope extends ng.IScope {
  past: boolean;
  date: string;
}

@Component({
  selector: 'app-markdown-edit',
  templateUrl: './markdown-edit.component.html'
})
@Injectable()
export class MarkdownEditComponent {
  @Input() text: string;
  @Input() save: Function;

  // private past: boolean;

  constructor() {}
}
