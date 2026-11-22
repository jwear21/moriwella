# Moriwella Android app (Capacitor 8)

Package name: **com.moriwella.app** (permanent). Display name: Moriwella. Copyright (c) JSW Software Designs.

The app is a thin native shell around https://moriwella.com/app/ with a native splash screen, status bar, back button, an offline page, and **Google Play Billing**
(plugin `@capgo/native-purchases`). Inside the app the plan buttons buy through Google Play; the website keeps using Stripe. Both end up as the same household plan.

## Build on your PC (Windows)
1. Install Node.js LTS and Android Studio (it brings the Android SDK and a JDK).
2. In a terminal, in this folder: `npm install` then `npx cap sync android`.
3. `npx cap open android` opens the project in Android Studio. Let Gradle finish syncing.
4. Build > Generate Signed App Bundle / APK > **Android App Bundle**. Create a new upload keystore the first time and keep the file and its passwords somewhere safe and backed up (never in the repo). Google manages the final app signing key.
5. Upload the `.aab` to Play Console (Internal testing first).

Before each release raise `versionCode` (and `versionName`) in `android/app/build.gradle`.

## Test purchases
Add your Google account under Play Console > Setup > License testing, install from the Internal testing link, and buy a plan. Test subscriptions renew quickly and are not charged.

## Fingerprint / face sign-in (3.1.8)
Plugin `@capgo/capacitor-native-biometric`. The website (static/biometric.js) turns it on inside the app only: Account > Fingerprint or face sign-in, plus a one-time offer after signing in.
The phone keeps a random device token behind its biometrics (Android Keystore); unlocking it signs in through `/app/device/login`. Only a hash of the token is stored on the server
(table `device_tokens`); changing or resetting the password revokes all of a person's tokens. After pulling this change run `npm install` then `npx cap sync android` before building.
