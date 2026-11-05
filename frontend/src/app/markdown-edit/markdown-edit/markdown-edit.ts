import type {AfterViewInit} from '@angular/core';

import {CdkTextareaAutosize} from '@angular/cdk/text-field';
import {ChangeDetectionStrategy, Component, model, viewChild} from '@angular/core';
import {form, FormField, maxLength} from '@angular/forms/signals';
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
export class MarkdownEdit implements AfterViewInit {
  readonly text = model.required<string>();

  protected readonly textForm = form(this.text, (path) => {
    maxLength(path, 65536);
  });

  private readonly autosize = viewChild.required(CdkTextareaAutosize);

  ngAfterViewInit(): void {
    this.autosize().resizeToFitContent(true);
  }
}
