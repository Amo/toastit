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
}
