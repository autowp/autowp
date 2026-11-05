import type {AfterViewInit, ElementRef} from '@angular/core';
import type {FormValueControl} from '@angular/forms/signals';

import {CdkTextareaAutosize} from '@angular/cdk/text-field';
import {
  afterNextRender,
  ChangeDetectionStrategy,
  Component,
  inject,
  Injector,
  input,
  model,
  output,
  signal,
  viewChild,
} from '@angular/core';
import {disabled, form, FormField, maxLength} from '@angular/forms/signals';
import {NgbNav, NgbNavContent, NgbNavItem, NgbNavLink, NgbNavLinkBase, NgbNavOutlet} from '@ng-bootstrap/ng-bootstrap';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-markdown-edit',
  imports: [
    NgbNav,
    NgbNavItem,
    NgbNavLink,
    NgbNavLinkBase,
    NgbNavContent,
    NgbNavOutlet,
    FormField,
    CdkTextareaAutosize,
    RemarkModule,
  ],
  templateUrl: './markdown-edit.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MarkdownEdit implements AfterViewInit, FormValueControl<string> {
  readonly #injector = inject(Injector);
  readonly value = model('');
  readonly disabled = input(false);
  readonly touch = output();
  protected readonly activeTab = signal('edit');
  private readonly textarea = viewChild<ElementRef<HTMLTextAreaElement>>('textarea');

  protected readonly textForm = form(this.value, (path) => {
    maxLength(path, 65536);
    disabled(path, {when: () => this.disabled()});
  });

  private readonly autosize = viewChild.required(CdkTextareaAutosize);

  focus(options?: FocusOptions): void {
    this.activeTab.set('edit');
    afterNextRender(() => this.textarea()?.nativeElement.focus(options), {injector: this.#injector});
  }

  ngAfterViewInit(): void {
    this.autosize().resizeToFitContent(true);
  }
}
