import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface Project {
  id: string;
  label: string;
}

export interface DynamicProject {
  id: 'dynamic';
  label: string;
  ssh?: {
    host: string;
    user: string;
    password?: string | null;
    path: string;
    authMode: 'password' | 'key';
  };
  local?: {
    path: string;
  };
}

export interface LogFiles {
  name: string;
  size: string;
  modified: string;
}

export interface LogEntry {
  timestamp: string;
  level: string;
  component?: string | null;
  message: string;
  stackTrace?: string | null;
  codeLocation?: string | null;
  requestUrI?: string | null;
  memory?: number | null;
  context?: any | null;
  exception?: string | null;
}

export interface LogPage {
  entries: LogEntry[];
  total: number;
  totalFiltered: number;
  counts: { ERROR: number; WARNING: number; INFO: number };
  page: number;
  limit: number;
}

export interface SavedConnection {
  id: number;
  project_name: string;
  ssh_host: string;
  logs_path: string;
  created_at: string;
}

export type Filter = 'ALL' | 'ERROR' | 'WARNING' | 'INFO';

@Injectable({ providedIn: 'root' })
export class LogFilesService {

  private readonly base = environment.apiUrl + '/api';
  private dynamicProject: DynamicProject | null = null;

  constructor(private http: HttpClient) { }

  connectDynamicProject(payload: {
    sshHost: string;
    logsPath: string;
    projectName: string;
    authMode: 'password' | 'key';
    password?: string | null;
  }): Observable<DynamicProject> {
    return this.http.post<DynamicProject>(`${this.base}/connect`, payload).pipe(
      tap((project) => {
        this.dynamicProject = project;
      })
    );
  }

  connectLocalProject(path: string, projectName: string): void {
    this.dynamicProject = {
      id: 'dynamic',
      label: projectName,
      local: { path },
    };
  }

  getSavedConnections(): Observable<SavedConnection[]> {
    return this.http.get<SavedConnection[]>(`${this.base}/connections`);
  }

  saveConnection(data: {
    project_name: string;
    ssh_host: string;
    logs_path: string;
  }): Observable<SavedConnection> {
    return this.http.post<SavedConnection>(`${this.base}/connections`, data);
  }

  deleteConnection(id: number): Observable<void> {
    return this.http.delete<void>(`${this.base}/connections/${id}`);
  }

  getLogs(projectId: string): Observable<LogFiles[]> {
    if (projectId === 'dynamic' && this.dynamicProject) {
      const payload: any = { project: projectId };

      // TIJDELIJK (live debug)
      if (this.dynamicProject.local) {
        payload.mode = 'local';
        payload.local = this.dynamicProject.local;
      } else {
        payload.ssh = this.dynamicProject.ssh;
      }
      // END TIJDELIJK

      return this.http.post<LogFiles[]>(`${this.base}/logs`, payload);
    }

    return this.http.get<LogFiles[]>(`${this.base}/logs`, {
      params: { project: projectId }
    });
  }

  getLogContent(
    projectId: string,
    fileName: string,
    options: { page?: number; limit?: number; level?: Filter; search?: string } = {}
  ): Observable<LogPage> {
    const payload: any = {
      project: projectId,
      file: fileName,
      page: String(options.page ?? 1),
      limit: String(options.limit ?? 100),
      level: options.level ?? 'ALL',
      search: options.search ?? '',
    };

    if (projectId === 'dynamic' && this.dynamicProject) {
      // TIJDELIJK (live debug)
      if (this.dynamicProject.local) {
        payload.mode = 'local';
        payload.local = this.dynamicProject.local;
      } else {
        payload.ssh = this.dynamicProject.ssh;
      }
      // END TIJDELIJK

      return this.http.post<LogPage>(`${this.base}/log-content`, payload);
    }

    return this.http.get<LogPage>(`${this.base}/log-content`, { params: payload });
  }

  clearDynamicProject(): void {
    this.dynamicProject = null;
  }
}
