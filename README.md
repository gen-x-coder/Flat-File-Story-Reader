# Flat-File Story Reader (PHP + Markdown)

A lightweight, single-file PHP application that reads stories from Markdown files and presents them as a clean, episode-based reading experience in the browser.

This project is intentionally simple:
- No database
- No framework
- No build step
- No authentication
- No server-side dependencies

It is designed for single-author use with trusted content.

---

## Features

- Flat-file content using Markdown files
- Optional front-matter support
- Automatic story listing
- Multi-episode detection using Markdown headers
- Episode-based navigation
- Publication status per story or per episode
- JSON endpoint for fetching individual stories
- Responsive, dark-themed UI
- Everything contained in one PHP file

---

## Project Structure

.
├── index.php
├── content/
│   └── example.md
├── README.md
├── SECURITY.md
├── LICENSE
└── .gitignore

---

## Writing Stories

Stories are written in Markdown and placed in the content directory.

Example:

---
title: My First Story
date: 2025-01-01
published: yes
published_episodes: [1,2]
---

This is the introduction.

# Episode One

The story begins.

# Episode Two

The story continues.

---

## Front-Matter Fields

title  
Optional display title.

date  
Optional publication date. Stories with future dates are hidden.

published  
Set to yes or no.

published_episodes  
Optional list of published episode numbers.

If no front-matter is present, the filename is used as the title.

---

## Episode Detection

Episodes are detected using Markdown level-1 headers.
Content before the first header is treated as an introduction.
Unpublished episodes remain visible but are clearly marked.

---

## Security Model

This project assumes trusted content.

Only the repository owner edits Markdown files.
Markdown is rendered with HTML enabled.
No sanitization is applied by design.

This is safe as long as you are the sole author.

If you plan to accept user-generated content, read SECURITY.md before doing so.

---

## Requirements

- PHP 7.4 or newer
- A web server such as Apache, Nginx, or Caddy
- Internet access for the Markdown rendering library

---

## License

MIT License. See LICENSE for details.

---

## Philosophy

This project avoids frameworks, databases, and unnecessary abstractions.

The goal is to keep the code readable, understandable, and easy to modify.
