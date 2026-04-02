# Backend Alignment Todo

## Goal

Align the backend codebase with the rules now defined in [AGENTS.md](/Users/amaury/code/toastit/AGENTS.md).

## Todo

1. Rename classes that use forbidden suffixes.
   Start with [src/Security/LoginChallengeManager.php](/Users/amaury/code/toastit/src/Security/LoginChallengeManager.php), [src/Security/ApiRefreshTokenManager.php](/Users/amaury/code/toastit/src/Security/ApiRefreshTokenManager.php), [src/Security/JwtTokenManager.php](/Users/amaury/code/toastit/src/Security/JwtTokenManager.php), [src/Security/PinManager.php](/Users/amaury/code/toastit/src/Security/PinManager.php), [src/Security/PinSessionManager.php](/Users/amaury/code/toastit/src/Security/PinSessionManager.php), [src/Workspace/UserProvisioner.php](/Users/amaury/code/toastit/src/Workspace/UserProvisioner.php).

2. Rework backend naming toward noun-plus-type naming.
   Replace role-oriented or vague technical names with domain nouns followed by a concrete suffix such as `Controller`, `Entity`, `Repository`, `Service`, or `PayloadBuilder`.

3. Clean up the security layer naming first.
   `src/Security` currently contains most of the naming drift and should be treated as the first consistency pass.

4. Re-check controller thinness.
   Review [src/Controller/Api](/Users/amaury/code/toastit/src/Controller/Api) and [src/Controller/App](/Users/amaury/code/toastit/src/Controller/App) to ensure controllers only coordinate request, security, and response.

5. Align product behavior on JSON API only.
   Identify remaining mutation-oriented HTML flows under [src/Controller/App](/Users/amaury/code/toastit/src/Controller/App) and decide which ones should move behind JSON endpoints.

6. Keep payload shaping server-side.
   Verify that derived fields, permissions, labels, and date formatting stay in builders such as [src/Api/WorkspacePayloadBuilder.php](/Users/amaury/code/toastit/src/Api/WorkspacePayloadBuilder.php) and [src/Api/DashboardPayloadBuilder.php](/Users/amaury/code/toastit/src/Api/DashboardPayloadBuilder.php), not in Vue components.

7. Avoid premature interfaces.
   When refactoring services, do not introduce interfaces unless there is an immediate second implementation need.

8. Preserve behavior with tests during refactors.
   Every naming or responsibility refactor must keep integration coverage stable and add targeted unit coverage when new service boundaries appear.
