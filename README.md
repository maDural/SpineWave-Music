# SpinWave Music — Stage 3

SpinWave Music is a PHP/MySQL music discovery and personal-library website with a dark, Spotify-inspired responsive interface. Stage 3 builds on Stage 2 and retains YouTube Data API v3 search plus the official YouTube IFrame Player.

## Stage 3 features
- Account registration with server-side validation and `password_hash()`.
- Login with `password_verify()`, session ID regeneration and password-hash rechecking.
- POST-only logout with CSRF protection.
- Profile display name and short bio editing.
- Save YouTube search results to liked songs.
- Create personal playlists, add YouTube tracks, view playlists and remove tracks.
- Server-side playlist ownership checks and prepared PDO statements.
- Shared player UI on home, profile and playlist pages. As with Stage 2, a full-page navigation reload interrupts active YouTube playback; the player is available again on the next page.
- Existing YouTube search endpoint and official embed playback are retained.

## Requirements
- XAMPP (Apache, MySQL and PHP)
- PHP extensions: `pdo_mysql` and `curl`; `mbstring` is optional
- A Google Cloud project with YouTube Data API v3 enabled
- VS Code or another code editor

## Install a fresh Stage 3 database
1. Extract the project folder into `C:\xampp\htdocs\spinwave-music`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Import `database/spinwave.sql`. This creates the database and all tables, including the Stage 3 `bio` field.
5. Check `config/database.php`. The default XAMPP settings are host `localhost`, user `root`, blank password. Adjust if your MySQL configuration differs.
6. Add your YouTube Data API v3 key to `config/services.php` as described below.
7. Visit `http://localhost/spinwave-music/`.

## Upgrade an existing Stage 2 database without losing data
1. Back up your existing `spinwave_music` database in phpMyAdmin: select it, choose **Export**, and save the SQL file.
2. Do NOT import the full `database/spinwave.sql` over your existing database.
3. In phpMyAdmin, select the existing `spinwave_music` database, open the **SQL** tab and run `database/stage3-migration.sql`.
4. The migration adds `users.bio` and creates the library tables only if missing. It is intended to be run once. If you have already added the `bio` column manually, remove/comment out the `ALTER TABLE users ADD COLUMN bio...` line before running it.
5. Copy the Stage 3 project files over your existing project, keeping your current `config/database.php` and `config/services.php` settings if they differ from the XAMPP defaults.
6. Confirm that existing users and tracks are still present, then test account registration and library actions.

The migration does not delete or overwrite existing users, tracks, albums, artists or listening history.

## Configure YouTube Data API v3
1. Open https://console.cloud.google.com/ and select/create a Google Cloud project.
2. Go to **APIs & Services → Library**, find **YouTube Data API v3**, and enable it.
3. Go to **APIs & Services → Credentials → Create credentials → API key**.
4. Restrict the key to YouTube Data API v3. Since PHP makes the request server-side, do not apply a browser-only HTTP referrer restriction to this local PHP request.
5. Paste the key into `config/services.php` under `youtube_api_key`.
6. Ensure PHP cURL is enabled in the active XAMPP `php.ini` and restart Apache.

## Test checklist
1. Open the homepage and search for a song or artist. Check that YouTube results appear.
2. Play a result. Confirm the official YouTube player loads and the player controls work.
3. Register a new account. Confirm the account is created and you land on your profile.
4. Log out, then log in using the new username or email and password.
5. Edit the display name and bio; refresh and confirm they persist.
6. From the homepage, search YouTube while logged in and click the heart button. Open your profile and confirm the track appears under **Liked songs**.
7. Create a playlist from **My playlists**. Return to YouTube search and use the **+ Playlist** selector on a result.
8. Open the playlist and confirm the track appears and can be selected for playback. Remove a track and then delete the playlist.
9. Log out and confirm the library endpoints redirect/deny access where appropriate.

## Security notes
- Passwords are stored using PHP's password hashing API; plaintext passwords are never stored.
- State-changing form actions require a CSRF token. The JSON save endpoint checks the CSRF header.
- SQL operations use PDO prepared statements.
- Users can only view and modify playlists they own.
- Session cookies use HttpOnly, SameSite=Lax and Secure when HTTPS is active; strict session mode and session ID regeneration are enabled.
- `config/` and `database/` are protected from direct web access by `.htaccess` on Apache. Do not rely on `.htaccess` if you deploy on a server that does not honour it.
- Use HTTPS, secure database credentials, rate limiting, backups and privacy/legal notices before public deployment.

## Playback and provider limitations
- YouTube videos play only through YouTube's official embedded player. SpinWave does not extract audio, proxy YouTube streams, remove advertisements or bypass restrictions.
- A search result may still become unavailable or disallow embedding at playback time.
- Spotify, Deezer and Jamendo remain roadmap cards from Stage 2; their catalogue or playback APIs are not implemented by this stage.
- Save only metadata for content you are allowed to use. Only upload or distribute local audio that you own or are licensed to distribute.
- This is a development project, not a production-ready commercial streaming service.
