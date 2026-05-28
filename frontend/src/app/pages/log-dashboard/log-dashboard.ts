import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, take, takeUntil } from 'rxjs/operators';
import { LogFilesService, LogEntry, Filter } from '../../services/log-files';

@Component({
  selector: 'app-log-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './log-dashboard.html',
  styleUrl: './log-dashboard.css',
})
export class LogDashboard implements OnInit, OnDestroy {

  project = '';
  projectName = '';
  file = '';

  filter: Filter = 'ALL';
  searchQuery = '';

  entries: LogEntry[] = [];

  currentPage = 1;
  readonly PAGE_SIZE = 100;
  hasMore = false;

  isLoading = false;
  isLoadingMore = false;

  selectedLog: LogEntry | null = null;

  newEntryBoundary = 0;

  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private destroy$ = new Subject<void>();
  private requesting = false;
  private requestId = 0;
  private userIsInteracting = false;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private logService: LogFilesService,
    private cdr: ChangeDetectorRef,
  ) { }

  ngOnInit() {
    this.route.queryParams.pipe(take(1), takeUntil(this.destroy$)).subscribe(params => {
      this.project = params['project'] ?? '';
      this.projectName = params['projectName'] ?? '';
      this.file = params['file'] ?? '';

      if (this.project && this.file) {
        this.resetAndLoad();
        this.startAutoRefresh();
      }
    });
  }

  ngOnDestroy() {
    this.stopAutoRefresh();
    this.destroy$.next();
    this.destroy$.complete();
  }

  resetAndLoad() {
    this.currentPage = 1;
    this.entries = [];
    this.hasMore = false;
    this.newEntryBoundary = 0;
    this.fetchPage(false);
  }

  loadMore() {
    if (this.requesting || !this.hasMore) return;

    this.userIsInteracting = true;
    setTimeout(() => this.userIsInteracting = false, 3000);

    this.currentPage++;
    this.fetchPage(true);
  }

  private fetchPage(append: boolean) {
    if (this.requesting) return;

    const myRequest = ++this.requestId;
    this.requesting = true;

    if (!append) {
      this.isLoading = true;
    } else {
      this.isLoadingMore = true;
    }

    this.logService.getLogContent(this.project, this.file, {
      page: this.currentPage,
      limit: this.PAGE_SIZE,
      level: this.filter,
      search: this.searchQuery.trim(),
    }).subscribe({
      next: (page) => {

        if (myRequest !== this.requestId) return;

        if (append) {
          this.entries = [...this.entries, ...page.entries];
        } else {
          this.entries = page.entries;
        }

        this.hasMore =
          page.entries.length === this.PAGE_SIZE &&
          (page.totalFiltered === -1 || this.entries.length < page.totalFiltered);

        this.isLoading = false;
        this.isLoadingMore = false;
        this.requesting = false;

        this.cdr.detectChanges();
      },
      error: (err) => {
        if (myRequest !== this.requestId) return;

        console.error(err);
        this.isLoading = false;
        this.isLoadingMore = false;
        this.requesting = false;

        this.cdr.detectChanges();
      }
    });
  }

  startAutoRefresh() {
    this.stopAutoRefresh();

    this.refreshInterval = setInterval(() => {
      this.refreshLatest();
    }, 5_000);
  }

  stopAutoRefresh() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  refreshLatest() {
    if (this.requesting || this.selectedLog || this.userIsInteracting) return;

    const myRequest = ++this.requestId;

    this.logService.getLogContent(this.project, this.file, {
      page: 1,
      limit: this.PAGE_SIZE,
      level: this.filter,
      search: this.searchQuery.trim(),
    }).subscribe({
      next: (page) => {
        if (myRequest !== this.requestId) return;

        const existing = new Set(
          this.entries.map(e => `${e.timestamp}-${e.message}`)
        );

        const newEntries = page.entries.filter(e =>
          !existing.has(`${e.timestamp}-${e.message}`)
        );

        if (newEntries.length > 0) {
          this.entries = [...newEntries, ...this.entries];
          this.newEntryBoundary = newEntries.length;
        }

        this.cdr.detectChanges();
      },
      error: () => { }
    });
  }

  setFilter(value: Filter) {
    this.filter = value;

    this.userIsInteracting = true;
    setTimeout(() => this.userIsInteracting = false, 3000);

    this.resetAndLoad();
  }

  onSearchEnter() {
    this.resetAndLoad();
  }

  clearSearch() {
    this.searchQuery = '';
    this.resetAndLoad();
  }

  openModal(log: LogEntry) {
    this.selectedLog = log;
  }

  closeModal() {
    this.selectedLog = null;
  }

  onBackdropClick(event: MouseEvent) {
    if ((event.target as HTMLElement).classList.contains('modal-backdrop')) {
      this.closeModal();
    }
  }

  changeFile() {
    this.stopAutoRefresh();

    this.router.navigate(['/logs'], {
      queryParams: {
        project: this.project,
        projectName: this.projectName,
        file: null
      }
    });
  }

  disconnect() {
    this.stopAutoRefresh();
    this.router.navigate(['/ssh']);
  }

  getBadgeClass(level: string): string {
    switch (level) {
      case 'ERROR': return 'bg-red-500 text-gray-800 border border-red-500/40';
      case 'WARNING': return 'bg-yellow-500 text-gray-800 border border-yellow-500/40';
      case 'INFO': return 'bg-cyan-500 text-gray-800';
      default: return 'bg-gray-500/20 text-gray-700 border border-gray-500/40';
    }
  }

  getCardBorderClass(level: string): string {
    const base = 'bg-[#353535] border border-white/20 hover:bg-[#404040] hover:border-white/50';
    switch (level) {
      case 'ERROR': return base + ' border-l-4 border-l-red-500';
      case 'WARNING': return base + ' border-l-4 border-l-yellow-500';
      case 'INFO': return base + ' border-l-4 border-l-cyan-500';
      default: return base + ' border-l-4 border-l-gray-500';
    }
  }

  formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1_048_576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1_048_576).toFixed(1)} MB`;
  }

}
