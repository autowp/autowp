import { Component, Injectable } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-account',
  templateUrl: './account.component.html'
})
@Injectable()
export class AccountComponent {
  constructor(private router: Router) {
    this.router.navigate(['/account/profile']);
  }
}
