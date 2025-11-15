import {CdkTextareaAutosize} from '@angular/cdk/text-field';
import {AfterViewInit, ChangeDetectionStrategy, Component, computed, input, output, viewChild} from '@angular/core';
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
})
export class MarkdownEditComponent implements AfterViewInit {
  readonly text = input.required<string>();
  readonly textChange = output<string>();

  readonly control = computed(() => new FormControl<string>(this.text(), {nonNullable: true}));

  readonly autosize = viewChild(CdkTextareaAutosize);

  ngAfterViewInit(): void {
    this.autosize()!.resizeToFitContent(true);
  }

  protected onChange(value: string) {
    this.textChange.emit(value);
  }
}
