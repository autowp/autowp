import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../services/acl.service';
import { PageService, APIPage, APIPageLinearized } from '../../services/page';
import { PageEnvService } from '../../services/page-env.service';
import { BehaviorSubject, combineLatest, Subscription } from 'rxjs';
import { switchMapTo } from 'rxjs/operators';

// Acl.inheritsRole('pages-moder', 'unauthorized');

@Component({
  selector: 'app-moder-pages',
  templateUrl: './pages.component.html'
})
@Injectable()
export class ModerPagesComponent implements OnInit, OnDestroy {
  public items: APIPageLinearized[] = [];
  public canManage = false;
  private load$ = new BehaviorSubject<null>(null);
  private sub: Subscription;

  constructor(
    private http: HttpClient,
    private acl: ACLService,
    private pageService: PageService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/68/name',
          pageId: 68
        }),
      0
    );

    this.sub = combineLatest(
      this.acl.isAllowed('hotlinks', 'manage'),
      this.load$.pipe(switchMapTo(this.pageService.getPages()))
    ).subscribe(data => {
      this.canManage = data[0];
      this.items = this.pageService.toPlainArray(data[1].items, 0);
    });
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public move(page: APIPage, direction: any) {
    this.http
      .put<void>('/api/page/' + page.id, {
        position: direction
      })
      .subscribe(response => {
        this.load$.next(null);
      });
  }

  public deletePage(ev: any, page: APIPage) {
    if (window.confirm('Would you like to delete page?')) {
      this.http.delete('/api/page/' + page.id).subscribe(response => {
        this.load$.next(null);
      });
    }
  }
}
