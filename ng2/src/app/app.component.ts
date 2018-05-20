import { Component, OnInit } from '@angular/core';

import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from './services/auth.service';
import { ACLService } from './services/acl.service';
import Notify from './notify';
import { APIUser } from './services/user';
import { MessageService } from './services/message';
import { Page, PageService } from './services/page';
import { PageEnvService, LayoutParams } from './services/page-env.service';
import { Observable } from 'rxjs';

function replaceArgs(str: string, args: {[key: string]: string}): string {
  for (const key in args) {
    if (this.formErrors.hasOwnProperty(key)) {
      str = str.replace(key, args[key]);
    }
  }
  return str;
}

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.less']
})
export class AppComponent implements OnInit {
  public layoutParams$: Observable<LayoutParams>;
  public loginInvalidParams: any;
  public languages;
  public path; // = $location.path();
  public user; // = opt.user;
  public isModer; // = opt.isModer;
  public newPersonalMessages; // = opt.sidebar.newPersonalMessages;
  public mainMenu; // = opt.mainMenu;
  public mainMenuItems: Page[] = [];
  public secondaryMenuItems: Page[] = [];
  public mainInSecondaryItems: Page[] = [];
  public categories = [];
  public moderMenu; // = opt.moderMenu;
  public searchHostname; // = opt.searchHostname;
  public loginForm = {
    login: '',
    password: '',
    remember: false
  };

  constructor(
    public auth: AuthService,
    public acl: ACLService,
    private router: Router,
    private translate: TranslateService,
    private http: HttpClient,
    private pages: PageService,
    private messageService: MessageService,
    private pageEnv: PageEnvService
  ) {
    translate.setTranslation('en', require('../languages/en.json'));
    translate.setDefaultLang('en');

    translate.use('en');

    this.layoutParams$ = this.pageEnv.layoutParams$.asObservable();

    this.auth.loggedIn$.subscribe(() => {
      this.updateRights();
    });
  }

  ngOnInit() {
    // this.updateRights();

    this.mainMenuItems = this.pages.getMenu(2);
    this.secondaryMenuItems = this.pages.getMenu(87);
    this.mainInSecondaryItems = [];
    for (const page of this.mainMenuItems) {
      if (this.isSecondaryMenuItem(page)) {
        this.mainInSecondaryItems.push(page);
      }
    }

    this.messageService.newMessagesCount.subscribe((value) => {
      this.newPersonalMessages = value;
    });
  }

  isSecondaryMenuItem(page: Page): boolean {
    return [25, 117, 42].indexOf(page.id) !== -1;
  }

  doLogin() {
    this.http.post<void>('/api/login', this.loginForm).subscribe(
      () => {
        this.http.get<APIUser>('/api/user/me').subscribe(
          response => {
            this.user = response;
            this.router.navigate(['/login/ok']);
          },
          response => {
            Notify.response(response);
          }
        );
      },
      response => {
        if (response.status === 400) {
          this.loginInvalidParams = response.error.invalid_params;
        } else {
          Notify.response(response);
        }
      }
    );
  }

  updateRights() {}

  signOut(event) {
    event.preventDefault();

    this.auth.signOut().then(
      () => {},
      error => {
        console.log(error);
      }
    );
  }
}
