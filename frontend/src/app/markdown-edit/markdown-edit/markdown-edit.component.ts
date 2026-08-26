import type {AfterViewInit} from '@angular/core';

import {CdkTextareaAutosize} from '@angular/cdk/text-field';
import {ChangeDetectionStrategy, Component, computed, input, output, viewChild} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
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
    FormsModule,
    NgbNavOutlet,
    ReactiveFormsModule,
    CdkTextareaAutosize,
    RemarkModule,
  ],
  templateUrl: './markdown-edit.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class MarkdownEditComponent implements AfterViewInit {
  readonly text = input.required<string>();
  readonly textChange = output<string>();

  protected readonly control = computed(() => new FormControl<string>(this.text(), {nonNullable: true}));

  // The @if in the template guards on `control()`, a computed() that always returns a fresh
  // FormControl (never falsy), so the textarea - and this directive - is always present once the
  // view has initialized.
  private readonly autosize = viewChild.required(CdkTextareaAutosize);

  ngAfterViewInit(): void {
    this.autosize().resizeToFitContent(true);
  }

  protected onChange(value: string) {
    this.textChange.emit(value);
  }
}
