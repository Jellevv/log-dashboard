import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LogDashboard } from './log-dashboard';

describe('LogDashboard', () => {
  let component: LogDashboard;
  let fixture: ComponentFixture<LogDashboard>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LogDashboard],
    }).compileComponents();

    fixture = TestBed.createComponent(LogDashboard);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
