import { Injectable } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';

export interface APIPictureModerVoteTemplatePostData {
  vote: number;
  name: string;
}

export class APIPictureModerVoteTemplate {
  id: number;
  name: string;
  vote: number;
}

export class APIPictureModerVoteTemplateGetResponse {
  items: APIPictureModerVoteTemplate[];
}

@Injectable()
export class PictureModerVoteTemplateService {
  private templates: APIPictureModerVoteTemplate[];
  private templatesInitialized = false;

  constructor(private http: HttpClient) {}

  public getTemplates(): Promise<APIPictureModerVoteTemplate[]> {
    return new Promise<APIPictureModerVoteTemplate[]>((resolve, reject) => {
      if (this.templatesInitialized) {
        resolve(this.templates);
        return;
      }

      this.http
        .get<APIPictureModerVoteTemplateGetResponse>(
          '/api/picture-moder-vote-template'
        )
        .subscribe(
          response => {
            this.templates = response.items;
            this.templatesInitialized = true;
            resolve(this.templates);
          },
          () => reject()
        );
    });
  }

  public deleteTemplate(id: number): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.http
        .delete<void>('/api/picture-moder-vote-template/' + id)
        .subscribe(
          () => {
            if (this.templates) {
              for (let i = 0; i < this.templates.length; i++) {
                if (this.templates[i].id === id) {
                  this.templates.splice(i, 1);
                  break;
                }
              }
            }
            resolve();
          },
          () => reject()
        );
    });
  }

  public createTemplate(
    template: APIPictureModerVoteTemplatePostData
  ): Promise<APIPictureModerVoteTemplate> {
    return new Promise<APIPictureModerVoteTemplate>((resolve, reject) => {
      this.http
        .post<void>('/api/picture-moder-vote-template', template, {
          observe: 'response'
        })
        .subscribe(
          response => {
            const location = response.headers.get('Location');

            this.http.get<APIPictureModerVoteTemplate>(location).subscribe(
              createdTemplate => {
                this.templates.push(createdTemplate);
                resolve(createdTemplate);
              },
              () => reject()
            );
          },
          () => reject()
        );
    });
  }
}
