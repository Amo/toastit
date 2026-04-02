export class AuthApi {
  constructor(client) {
    this.client = client;
  }

  requestOtp(email) {
    return this.client.postJson('/api/auth/request-otp', { email, purpose: 'login' });
  }

  requestPinReset(email) {
    return this.client.postJson('/api/auth/request-otp', { email, purpose: 'reset_pin' });
  }

  verifyOtp(email, code, purpose = 'login') {
    return this.client.postJson('/api/auth/verify-otp', { email, code, purpose });
  }

  setupPin(pinSetupToken, pin, pinConfirmation) {
    return this.client.postJson('/api/auth/pin/setup', { pinSetupToken, pin, pinConfirmation });
  }

  refresh(refreshToken) {
    return this.client.postJson('/api/auth/refresh', { refreshToken });
  }

  unlockPin({ pin, refreshToken = '', pinUnlockToken = '' }) {
    return this.client.postJson('/api/auth/pin/unlock', { pin, refreshToken, pinUnlockToken });
  }

  consumeMagicLink(selector, token) {
    return this.client.getJson(`/api/auth/magic/${selector}/${token}`);
  }
}
