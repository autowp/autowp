import {HttpParams} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {browserWindow} from '@utils/browser-window';

@Component({
  selector: 'app-share',
  standalone: true,
  templateUrl: './share.component.html',
  styleUrl: './share.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ShareComponent {
  readonly #window = browserWindow();

  readonly url = input.required<string>();
  readonly text = input.required<string>();

  buildURL(url: string, params: Record<string, string>): string {
    let p = new HttpParams();
    for (const key of Object.keys(params)) {
      p = p.set(key, params[key]);
    }

    return url + p.toString();
  }

  protected share(href: string) {
    if (this.#window) {
      this.#window.open(
        href,
        undefined,
        'height=600,width=600,resizable=yes,scrollbars=no,status=no,toolbar=no,location=no,directories=no',
      );
    }

    return false;
  }
}
