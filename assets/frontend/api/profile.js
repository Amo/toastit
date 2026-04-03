export class ProfileApi {
  constructor(client) {
    this.client = client;
  }

  getProfile(url) {
    return this.client.getJson(url);
  }

  saveProfile(url, payload) {
    return this.client.putJson(url, payload);
  }

  uploadAvatar(url, formData) {
    return this.client.postFormData(url, formData);
  }

  requestDeletionOtp(url) {
    return this.client.postJson(url, {});
  }

  deleteProfile(url, payload) {
    return this.client.request(url, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }).then((response) => this.client.parseJsonResponse(response));
  }
}
