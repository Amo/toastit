export class ToastitApiClient {
  constructor(accessToken, options = {}) {
    this.accessToken = accessToken;
    this.onUnauthorized = options.onUnauthorized ?? null;
  }

  async request(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(this.accessToken ? { Authorization: `Bearer ${this.accessToken}` } : {}),
        ...(options.headers ?? {}),
      },
    });

    if (response.status === 401 && this.onUnauthorized) {
      this.onUnauthorized(response);
    }

    if (response.ok && this.accessToken && url.startsWith('/api/') && !url.startsWith('/api/auth/')) {
      window.dispatchEvent(new CustomEvent('toastit:api-activity'));
    }

    return response;
  }

  async getJson(url) {
    const response = await this.request(url);
    return this.parseJsonResponse(response);
  }

  async postJson(url, payload) {
    const response = await this.request(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    return this.parseJsonResponse(response);
  }

  async putJson(url, payload) {
    const response = await this.request(url, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    return this.parseJsonResponse(response);
  }

  async delete(url) {
    const response = await this.request(url, {
      method: 'DELETE',
    });

    return this.parseJsonResponse(response);
  }

  async postFormData(url, formData) {
    const response = await this.request(url, {
      method: 'POST',
      body: formData,
    });

    return this.parseJsonResponse(response);
  }

  async getBlob(url) {
    const response = await this.request(url);

    return {
      ok: response.ok,
      status: response.status,
      response,
      blob: response.ok ? await response.blob() : null,
    };
  }

  async parseJsonResponse(response) {
    const contentType = response.headers.get('Content-Type') ?? '';
    const hasJsonBody = contentType.includes('application/json');

    return {
      ok: response.ok,
      status: response.status,
      response,
      data: hasJsonBody ? await response.json() : null,
    };
  }
}
