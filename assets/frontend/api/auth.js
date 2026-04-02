export class AuthApi {
  constructor(client) {
    this.client = client;
  }

  requestOtp(email) {
    return this.client.postJson('/api/auth/request-otp', { email });
  }

  verifyOtp(email, code) {
    return this.client.postJson('/api/auth/verify-otp', { email, code });
  }

  setupPin(pinSetupToken, pin, pinConfirmation) {
    return this.client.postJson('/api/auth/pin/setup', { pinSetupToken, pin, pinConfirmation });
  }

  refresh(refreshToken, pin) {
    return this.client.postJson('/api/auth/refresh', { refreshToken, pin });
  }
}
