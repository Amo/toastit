export class WorkspacesApi {
  constructor(client) {
    this.client = client;
  }

  getDashboard(url) {
    return this.client.getJson(url);
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

  saveWorkspaceSettings(workspaceId, payload) {
    return this.client.postJson(`/api/workspaces/${workspaceId}/settings`, payload);
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

  updateToast(itemId, payload) {
    return this.client.putJson(`/api/items/${itemId}`, payload);
  }

  startMeetingMode(workspaceId) {
    return this.client.request(`/api/workspaces/${workspaceId}/meeting/start`, { method: 'POST' });
  }

  stopMeetingMode(workspaceId) {
    return this.client.request(`/api/workspaces/${workspaceId}/meeting/stop`, { method: 'POST' });
  }

  addComment(itemId, content) {
    return this.client.postJson(`/api/items/${itemId}/comments`, { content });
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

  copyToast(itemId, targetWorkspaceId = null) {
    return this.client.postJson(`/api/items/${itemId}/copy`, { targetWorkspaceId });
  }

  transferToast(itemId, targetWorkspaceId) {
    return this.client.postJson(`/api/items/${itemId}/transfer`, { targetWorkspaceId });
  }

  saveDiscussion(itemId, payload) {
    return this.client.postJson(`/api/items/${itemId}/discussion`, payload);
  }
}
