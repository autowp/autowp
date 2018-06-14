import { Component, OnInit } from '@angular/core';

import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { AuthService } from './services/auth.service';
import { ACLService } from './services/acl.service';
import Notify from './notify';
import { APIUser } from './services/user';
import { MessageService } from './services/message';
import { Page, PageService } from './services/page';
import { PageEnvService, LayoutParams } from './services/page-env.service';
import { Observable } from 'rxjs';

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
  public user: APIUser;
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
    private pages: PageService,
    private messageService: MessageService,
    private pageEnv: PageEnvService
  ) {
    this.translate.setTranslation('en', require('../languages/en.json'));
    this.translate.setDefaultLang('en');

    this.translate.use('en');

    this.layoutParams$ = this.pageEnv.layoutParams$.asObservable();

    this.auth.loadMe();

    this.auth.getUser().subscribe(user => {
      this.user = user;
    });
  }

  ngOnInit() {
    this.mainMenuItems = this.pages.getMenu(2);
    this.secondaryMenuItems = this.pages.getMenu(87);
    this.mainInSecondaryItems = [];
    for (const page of this.mainMenuItems) {
      if (this.isSecondaryMenuItem(page)) {
        this.mainInSecondaryItems.push(page);
      }
    }

    this.messageService.getNew().subscribe(value => {
      this.newPersonalMessages = value;
    });
  }

  isSecondaryMenuItem(page: Page): boolean {
    return [25, 117, 42].indexOf(page.id) !== -1;
  }

  doLogin() {
    this.auth
      .login(
        this.loginForm.login,
        this.loginForm.password,
        this.loginForm.remember
      )
      .then(
        () => {
          this.router.navigate(['/login/ok']);
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
