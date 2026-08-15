import type {ComponentRef} from '@angular/core';
import type {NgbModalRef} from '@ng-bootstrap/ng-bootstrap';

/**
 * Gets the `ComponentRef` for a component opened via `NgbModal.open()`, needed to call
 * `setInput()` on a signal input - `NgbModalRef.componentInstance` only gives the component
 * instance, not a `ComponentRef`. `_contentRef` is ng-bootstrap's own private, undocumented
 * internal property with no public type for it.
 */
export function getModalComponentRef<T>(modalRef: NgbModalRef): ComponentRef<T> {
  // @ts-expect-error: TS2341: Property _contentRef is private and only accessible within class NgbModalRef
  // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
  return modalRef._contentRef.componentRef as ComponentRef<T>;
}
