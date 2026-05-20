import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SshSetup } from './ssh-setup';

describe('SshSetup', () => {
  let component: SshSetup;
  let fixture: ComponentFixture<SshSetup>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SshSetup],
    }).compileComponents();

    fixture = TestBed.createComponent(SshSetup);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
