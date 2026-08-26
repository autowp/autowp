import {ChangeDetectionStrategy, Component} from '@angular/core';
import {RouterLink, RouterOutlet} from '@angular/router';

@Component({
  selector: 'app-upload',
  imports: [RouterLink, RouterOutlet],
  templateUrl: './upload.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class -- pure router-outlet shell, all behavior lives in the decorator/template
export class UploadComponent {}
