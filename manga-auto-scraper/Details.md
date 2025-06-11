# Manga Auto Scraper – Features & Implementation Guide

## 1. 📥 Scrape Manga Content from Two Sources

Automatically scrape new manga and chapters from:

- [go-manga.com](https://www.go-manga.com)
- [manga1688.com](https://manga1688.com)

**Initial manga to test with:**

- _I’m Not Kind Talent_ (go-manga)
- _Rettoujin no Maken Tsukai_ (manga1688)
- _Daughter is Final Boss_ (previously mentioned)

**For each manga, extract:**

- **Title**
- **Cover image**
- **Chapter list** (chapter names and links)
- **Chapter images** (all pages per chapter)

---

## 2. 📝 Create WordPress Posts for Each Manga

- Each manga should become a WordPress post (custom post type optional).
- Each chapter should be:
  - A section within the manga post, **or**
  - A separate post for each chapter (choose whichever is more maintainable).
- No need to assign categories or tags.
- Format/layout must follow the **Mangareader** WordPress theme conventions.

---

## 3. 🖼️ Upload Chapter Images to FTP Server

- Upload chapter images via FTP to:
  - **Host:** `89.163.146.144`
  - **User:** `mangapost`
  - **Pass:** `GyLFsBpan5tiehMc`
  - **Remote Folder:** `/server/manga/`

- Images must follow this structure:

  ```text
  /server/manga/{manga-title}/{chapter-number}/{page}.jpg
  ```

---

## 4. ⏰ Global Weekly Schedule

- Plugin should run **automatically once a week**.
- On each run, it must:
  - Pull new manga/chapters from both sources
  - Upload new images to the FTP server
  - Create or update the corresponding WordPress posts
- Use `wp_schedule_event()` to register a custom cron job for this schedule.

---

## 📊 Summary Table

| Feature                   | Requirement                                                                |
|--------------------------|----------------------------------------------------------------------------|
| Scrape Manga Sources     | go-manga.com, manga1688.com                                                |
| Manga Data to Extract    | Title, Cover Image, Chapters, Chapter Images                               |
| WordPress Integration    | Post per manga/chapter, Mangareader theme, no categories/tags              |
| FTP Upload Structure     | `/server/manga/{manga-title}/{chapter-number}/{page}.jpg`                  |
| Automation               | Weekly schedule (`wp_schedule_event` + cron)                               |

**Note:**

- Do not add categories, tags, or extra metadata unless specified.
- Focus on reliability, maintainability, and compatibility with the Mangareader theme.