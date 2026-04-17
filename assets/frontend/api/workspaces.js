export class WorkspacesApi {
  constructor(client) {
    this.client = client;
  }

  getDashboard(url) {
    return this.client.getJson(url);
  }

  sendWeeklySummary() {
    return this.client.postJson('/api/dashboard/weekly-summary', {});
  }

  createWorkspace(name) {
    return this.client.postJson('/api/workspaces', { name });
  }

  reorderWorkspaceList(workspaceIds) {
    return this.client.postJson('/api/workspaces/reorder', { workspaceIds });
  }

  getWorkspace(url) {
    return this.client.getJson(url);
  }

  getMyActions(url) {
    return this.client.getJson(url);
  }

  saveWorkspaceSettings(workspaceId, payload) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/settings`, payload);
  }

  deleteWorkspace(workspaceId) {
    return this.client.delete(`/api/workspaces/${workspaceId}`);
  }

  restoreWorkspace(workspaceId) {
    return this.client.request(`/api/workspaces/${workspaceId}/restore`, { method: 'POST' })
      .then((response) => this.client.parseJsonResponse(response));
  }

  generateToastCurationDraft(workspaceId) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/curation/draft`, {});
  }

  applyToastCurationDraft(workspaceId, actions) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/curation/apply`, { actions });
  }

  uploadWorkspaceBackground(workspaceId, formData) {
    return this.client.postFormData(`/api/workspaces/${workspaceId}/background`, formData);
  }

  getWorkspaceBackground(url) {
    return this.client.getBlob(url);
  }

  inviteMember(workspaceId, email) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/invite`, { email });
  }

  removeMember(workspaceId, memberId) {
    return this.client.delete(`/api/workspaces/${workspaceId}/members/${memberId}`);
  }

  promoteMember(workspaceId, memberId) {
    return this.client.request(`/api/workspaces/${workspaceId}/members/${memberId}/promote`, { method: 'POST' });
  }

  demoteMember(workspaceId, memberId) {
    return this.client.request(`/api/workspaces/${workspaceId}/members/${memberId}/demote`, { method: 'POST' });
  }

  createToast(workspaceId, payload) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/items`, payload);
  }

  refineToastDraft(workspaceId, payload) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/items/draft/refine`, payload);
  }

  updateToast(itemId, payload) {
    return this.client.putJson(`/api/items/${itemId}`, payload);
  }

  startMeetingMode(workspaceId) {
    return this.client.request(`/api/workspaces/${workspaceId}/meeting/start`, { method: 'POST' });
  }

  stopMeetingMode(workspaceId) {
    return this.client.request(`/api/workspaces/${workspaceId}/meeting/stop`, { method: 'POST' })
      .then((response) => this.client.parseJsonResponse(response));
  }

  generateMeetingSummary(workspaceId) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/meeting/summary`, {});
  }

  generateSessionSummary(workspaceId, sessionId) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/sessions/${sessionId}/summary/generate`, {});
  }

  updateSessionSummary(workspaceId, sessionId, summary) {
    return this.client.putJson(`/api/workspaces/${workspaceId}/sessions/${sessionId}/summary`, { summary });
  }

  sendSessionSummary(workspaceId, sessionId) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/sessions/${sessionId}/summary/send`, {});
  }

  addComment(itemId, content) {
    return this.client.postJson(`/api/items/${itemId}/comments`, { content });
  }

  summarizeComments(itemId) {
    return this.client.postJson(`/api/items/${itemId}/comments/summary`, {});
  }

  toggleVote(itemId) {
    return this.client.request(`/api/items/${itemId}/vote`, { method: 'POST' });
  }

  toggleBoost(itemId) {
    return this.client.request(`/api/items/${itemId}/boost`, { method: 'POST' });
  }

  toggleVeto(itemId) {
    return this.client.request(`/api/items/${itemId}/veto`, { method: 'POST' });
  }

  toastItem(itemId) {
    return this.client.request(`/api/items/${itemId}/toast`, { method: 'POST' });
  }

  setReady(itemId, ready) {
    return this.client.postJson(`/api/items/${itemId}/ready`, { ready });
  }

  copyToast(itemId, targetWorkspaceId = null) {
    return this.client.postJson(`/api/items/${itemId}/copy`, { targetWorkspaceId });
  }

  transferToast(itemId, targetWorkspaceId) {
    return this.client.postJson(`/api/items/${itemId}/transfer`, { targetWorkspaceId });
  }

  saveDiscussion(itemId, payload) {
    return this.client.postJson(`/api/items/${itemId}/discussion`, payload);
  }

  saveDecisionNotes(itemId, payload) {
    return this.client.postJson(`/api/items/${itemId}/decision-notes`, payload);
  }

  generateExecutionPlan(itemId, payload = {}) {
    return this.client.postJson(`/api/items/${itemId}/execution-plan/draft`, payload);
  }

  applyExecutionPlanAction(itemId, action) {
    return this.client.postJson(`/api/items/${itemId}/execution-plan/apply`, { action });
  }
}
