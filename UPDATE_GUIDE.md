# AcadSyntra: full codebase (2 November 2026)

This folder is your complete codebase: everything from the `acadflow-web_1.zip` you uploaded on 31 October, plus every change made since. `CHANGED_FILES.txt` lists the exact differences.

**Included:**
- the navigation and update-notice fix
- the completed Campus Ambassador programme
- the `/refresh-app` recovery page
- the MySQL migration fix
- automatic referral rewards (₦200 cash + ₦300 study credit) with the reward explanation

**Not included:**
- `.git`, so it can't overwrite your repository history
- `.env`, so it can't overwrite your live settings. Your local `.env` is sent separately as `local.env`.
- `node_modules`. Run `npm install` to recreate it.

`vendor/` and the built front-end (`public/build/`) are included.

---

## Why files went missing

Dragging a folder onto another folder in Finder and choosing **Replace** deletes the old folder completely. Anything that wasn't in the new folder is gone. Use the merge command below instead, which only adds and updates files and never deletes.

The project was also inside iCloud Drive (Desktop/Documents). iCloud can swap files for cloud placeholders, and git then records them as deleted. That is how `routes/api.php` disappeared from your commit. Keep code outside iCloud.

---

## Step 1: get a clean copy of your repository (on your Mac)

```bash
mkdir -p ~/Projects && cd ~/Projects
git clone YOUR_REPOSITORY_URL acadsyntra     # same repository your server deploys from
cd acadsyntra
```

`~/Projects` is not synced by iCloud.

## Step 2: merge this codebase into it (merge, never replace)

Unzip `acadsyntra-full.zip` into Downloads. You'll get `~/Downloads/acadsyntra-full/acadsyntra`. Then run:

```bash
rsync -av --exclude '.git' --exclude '.env' ~/Downloads/acadsyntra-full/acadsyntra/ ~/Projects/acadsyntra/
```

Keep both trailing slashes `/`. `rsync` adds and updates files and deletes nothing.

## Step 3: put your `.env` back

```bash
cp ~/Downloads/local.env ~/Projects/acadsyntra/.env
```

It is your local development `.env` exactly as you uploaded it. Never upload it to the live server. The server keeps its own `.env` with your MySQL details and `APP_ENV=production`.

## Step 4: check before committing

```bash
git status
```

- **`deleted:` lines.** There should be none, apart from the cache files in Step 5. If you see one, stop and ask.
- **Modified files you edited yourself after 31 October.** Run `git diff path/to/file` and copy back any of your own edits that this version doesn't have.

## Step 5 (once): stop committing cache files

Your repository has been tracking compiled views and cache files, including the duplicates `bootstrap/cache/packages 2.php` and `services 2.php`. This version adds the standard Laravel ignore files. To stop tracking those files (they are only removed from git, not from your disk):

```bash
git rm -r --cached -q bootstrap/cache storage/framework/views storage/framework/cache storage/framework/sessions storage/logs
git add bootstrap/cache/.gitignore storage/framework/views/.gitignore storage/framework/cache/.gitignore storage/framework/cache/data/.gitignore storage/framework/sessions/.gitignore storage/framework/testing/.gitignore storage/logs/.gitignore
```

## Step 6: run it locally

```bash
composer install
npm install
php artisan migrate
php artisan optimize:clear
php artisan serve
```

Run `npm run build` too if you change anything under `resources/js` or `resources/css`.

## Step 7: commit and push

```bash
git add -A
git status                      # one last look; no surprise deletions
git commit -m "Bring repository up to date: navigation fix, campus ambassador programme, referral rewards"
git push
```

## Step 8: deploy to the live server

Deploy as usual, then on the server, inside the new release folder, using PHP 8.4:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

**Front-end files:** `.gitignore` excludes `/public/build`. Either your deploy runs `npm ci && npm run build` on the server, or the built files must be uploaded separately. Otherwise the JavaScript fixes (update notice, loader) don't reach the live site. If your server can't run npm, delete the `/public/build` line from `.gitignore` and commit the built files.

**After deploying:**
- If you run Composer or artisan as `root`, run `chown -R YOUR_USER:YOUR_USER` on the app folder afterwards.
- If a browser still shows the old version, open `https://acadsyntra.com/refresh-app` in it.

---

## Applying future updates

- If you receive a small update zip, merge it the same way: `rsync -av UPDATE_FOLDER/ ~/Projects/acadsyntra/`. Or open the zip and copy individual files.
- Never use Finder's "Replace" on folders.
- Run `git status` before every commit.
