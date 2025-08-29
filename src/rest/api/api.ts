export * from './articles.service';
import { ArticlesService } from './articles.service';
export * from './autowp.service';
import { AutowpService } from './autowp.service';
export * from './donations.service';
import { DonationsService } from './donations.service';
export const APIS = [ArticlesService, AutowpService, DonationsService];
