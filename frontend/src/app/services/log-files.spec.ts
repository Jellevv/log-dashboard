import { TestBed } from '@angular/core/testing';
import { LogFilesService } from './log-files';

describe('LogFilesService', () => {
  let service: LogFilesService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(LogFilesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
